<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\Helpers;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\AdminRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TeamRoleAdminController extends Controller
{
    /* ════════════════════════ ROLES ════════════════════════ */

    public function roles()
    {
        $roles = AdminRole::orderBy('created_at', 'desc')->get();
        $roleIds = $roles->pluck('_id')
            ->map(fn ($x) => (string) $x)
            ->filter(fn ($id) => (bool) preg_match('/^[0-9a-fA-F]{24}$/', $id))
            ->values()
            ->toArray();

        $memberCounts = collect();
        if (!empty($roleIds)) {
            try {
                $memberCounts = User::whereIn('role_id', $roleIds)
                    ->get(['role_id'])
                    ->groupBy(fn ($u) => (string) $u->role_id)
                    ->map->count();
            } catch (\Throwable $e) {
                $memberCounts = collect();
            }
        }

        $result = $roles->map(function ($r) use ($memberCounts) {
            $perms = $this->normalizePermissionList(
                is_array($r->permissions) ? $r->permissions
                    : (is_array($r->permission) ? $r->permission : [])
            );
            $statusVal = $r->status ?? 'active';
            $statusStr = $statusVal === 'inactive' || $statusVal === '0' || $statusVal === 0
                ? 'inactive' : 'active';
            $name = (string) ($r->name ?? '');

            return [
                'id'             => (string) $r->_id,
                'name'           => $name,
                'description'    => $r->description ?? ($name !== '' ? "Role: {$name}" : ''),
                'members'        => (int) ($memberCounts->get((string) $r->_id, 0)),
                'permissions'    => count($perms),
                'status'         => $statusStr,
                'permissionTags' => $this->permissionTags($perms),
                'permissionKeys' => $perms,
                'isLocked'       => $this->isLockedRole($name),
            ];
        })->values();

        return ResponseHelper::sendResponse($result, 'Roles loaded.');
    }

    public function storeRole(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:100',
            'permissions' => 'nullable|array',
            'description' => 'nullable|string|max:500',
            'status'      => 'nullable|in:active,inactive',
        ]);

        $name = trim((string) $request->name);
        if ($this->roleNameExists($name)) {
            return ResponseHelper::sendResponse(null, 'A role with this name already exists.', false, 422);
        }

        $perms = $this->normalizePermissionList($request->input('permissions', []));
        $role = new AdminRole();
        $role->name        = $name;
        $role->description = $request->input('description', "Role: {$name}");
        $role->permissions = $perms;
        $role->permission  = $perms; // compat with Maklad/Spatie legacy readers
        $role->guard_name  = 'web';
        $role->status      = $request->input('status', 'active');
        $role->save();

        return ResponseHelper::sendResponse(['id' => (string) $role->_id], 'Role created.', true, 201);
    }

    public function updateRole(Request $request, string $id)
    {
        $request->validate([
            'name'        => 'required|string|max:100',
            'permissions' => 'nullable|array',
            'description' => 'nullable|string|max:500',
            'status'      => 'nullable|in:active,inactive',
        ]);

        $role = AdminRole::find($id);
        if (!$role) {
            return ResponseHelper::sendResponse(null, 'Role not found', false, 404);
        }

        if ($this->isLockedRole((string) ($role->name ?? ''))) {
            return ResponseHelper::sendResponse(null, 'Super Admin role cannot be modified.', false, 403);
        }

        $name = trim((string) $request->name);
        if ($this->isLockedRole($name)) {
            return ResponseHelper::sendResponse(null, 'Cannot rename a role to Super Admin.', false, 422);
        }
        if ($this->roleNameExists($name, (string) $role->_id)) {
            return ResponseHelper::sendResponse(null, 'A role with this name already exists.', false, 422);
        }

        $role->name = $name;
        if ($request->has('description')) {
            $role->description = $request->description;
        }
        if ($request->has('permissions')) {
            $perms = $this->normalizePermissionList($request->input('permissions', []));
            $role->permissions = $perms;
            $role->permission  = $perms;
        }
        if ($request->has('status')) {
            $role->status = $request->status;
        }
        $role->save();

        return ResponseHelper::sendResponse(['id' => (string) $role->_id], 'Role updated.');
    }

    public function destroyRole(string $id)
    {
        $role = AdminRole::find($id);
        if (!$role) {
            return ResponseHelper::sendResponse(null, 'Role not found', false, 404);
        }

        if ($this->isLockedRole((string) ($role->name ?? ''))) {
            return ResponseHelper::sendResponse(null, 'Super Admin role cannot be deleted.', false, 403);
        }

        $roleId = (string) $role->_id;
        // Unassign both string + ObjectId shaped role_id values.
        User::where('role_id', $roleId)->update(['role_id' => null]);
        try {
            if (preg_match('/^[0-9a-fA-F]{24}$/', $roleId)) {
                User::where('role_id', new \MongoDB\BSON\ObjectId($roleId))->update(['role_id' => null]);
            }
        } catch (\Throwable $e) {
            // ignore — string update already ran
        }

        $role->delete();
        return ResponseHelper::sendResponse(['id' => $id], 'Role deleted.');
    }

    /* ════════════════════════ MEMBERS ════════════════════════ */

    public function members()
    {
        $users = User::where(function ($q) {
                $q->where('is_admin_user', 1)
                  ->orWhere('is_admin_user', '1')
                  ->orWhere('is_admin_user', true)
                  ->orWhere('user_type', 'team_member')
                  ->orWhere('is_superadmin', 1)
                  ->orWhere('is_superadmin', '1')
                  ->orWhere('is_superadmin', true);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $roleIds = $users->pluck('role_id')
            ->filter()
            ->map(fn ($x) => (string) $x)
            ->filter(fn ($id) => (bool) preg_match('/^[0-9a-fA-F]{24}$/', $id))
            ->unique()
            ->values()
            ->toArray();

        $roles = collect();
        if (!empty($roleIds)) {
            try {
                $roles = AdminRole::whereIn('_id', $roleIds)->get()->keyBy(fn ($r) => (string) $r->_id);
            } catch (\Throwable $e) {
                $roles = collect();
            }
        }

        $result = $users->map(function ($u) use ($roles) {
            $isSuper = (int) ($u->is_superadmin ?? 0) === 1;
            $role = $u->role_id ? $roles->get((string) $u->role_id) : null;
            $imagePath = $u->image ?? null;
            $avatarUrl = Helpers::profileImageUrl($imagePath);

            return [
                'id'           => (string) $u->_id,
                'name'         => trim(($u->name ?? '') . ' ' . ($u->last_name ?? '')) ?: ($u->username ?? 'Admin'),
                'email'        => $u->email ?? '',
                'role'         => $isSuper ? 'Super Admin' : ($role->name ?? '—'),
                'roleId'       => $u->role_id ? (string) $u->role_id : null,
                'status'       => ($u->status ?? 1) == 1 || ($u->status ?? 1) === '1' || ($u->status ?? 1) === true
                    ? 'active' : 'inactive',
                'avatar'       => $avatarUrl ?: strtoupper(mb_substr((string) ($u->name ?? 'A'), 0, 2)),
                // Relative path for edit form — never bake CDN URL into the editable value.
                'image'        => $imagePath ? Helpers::cdnRelativePath($imagePath) : null,
                'imageUrl'     => $avatarUrl,
                'isSuperAdmin' => $isSuper,
            ];
        })->values();

        return ResponseHelper::sendResponse($result, 'Team members loaded.');
    }

    public function storeMember(Request $request)
    {
        $email = strtolower(trim((string) $request->input('email', '')));
        $request->merge(['email' => $email]);

        $request->validate([
            'name'     => 'required|string|max:100',
            'email'    => 'required|email|max:150',
            'password' => 'required|string|min:6',
            'role_id'  => 'required|string',
            'image'    => 'nullable|string',
            'status'   => 'nullable|in:0,1',
        ]);

        if ($this->emailTaken($email)) {
            return ResponseHelper::sendResponse(null, 'Email already in use.', false, 422);
        }

        $role = AdminRole::find($request->role_id);
        if (!$role) {
            return ResponseHelper::sendResponse(null, 'Role not found', false, 422);
        }

        [$first, $last] = $this->splitName((string) $request->name);

        $user = new User();
        $user->name          = $first;
        $user->last_name     = $last;
        $user->email         = $email;
        $user->password      = Hash::make($request->password);
        $user->role_id       = (string) $role->_id;
        $user->is_admin_user = 1;
        $user->user_type     = 'team_member';
        $user->status        = (int) $request->input('status', 1);
        if ($request->filled('image')) {
            $user->image = Helpers::cdnRelativePath($request->image);
        }
        $user->save();

        return ResponseHelper::sendResponse(['id' => (string) $user->_id], 'Team member created.', true, 201);
    }

    public function updateMember(Request $request, string $id)
    {
        $user = User::find($id);
        if (!$user) {
            return ResponseHelper::sendResponse(null, 'Member not found', false, 404);
        }

        $isSuper = (int) ($user->is_superadmin ?? 0) === 1;
        $authId  = (string) (optional(Auth::user())->_id ?? '');

        // Non-super callers cannot mutate a superadmin.
        $callerIsSuper = (int) (optional(Auth::user())->is_superadmin ?? 0) === 1;
        if ($isSuper && !$callerIsSuper && $authId !== (string) $user->_id) {
            return ResponseHelper::sendResponse(null, 'Cannot modify Super Admin.', false, 403);
        }

        $email = strtolower(trim((string) $request->input('email', $user->email)));
        $request->merge(['email' => $email]);

        $rules = [
            'name'     => 'required|string|max:100',
            'email'    => 'required|email|max:150',
            'password' => 'nullable|string|min:6',
            'image'    => 'nullable|string',
            'status'   => 'nullable|in:0,1',
        ];
        // Superadmins may have no role_id — keep it optional for them.
        if (!$isSuper) {
            $rules['role_id'] = 'required|string';
        } else {
            $rules['role_id'] = 'nullable|string';
        }

        $request->validate($rules);

        if ($this->emailTaken($email, (string) $user->_id)) {
            return ResponseHelper::sendResponse(null, 'Email already in use.', false, 422);
        }

        [$first, $last] = $this->splitName((string) $request->name);
        $user->name      = $first;
        $user->last_name = $last;
        $user->email     = $email;

        if (!$isSuper) {
            $role = AdminRole::find($request->role_id);
            if (!$role) {
                return ResponseHelper::sendResponse(null, 'Role not found', false, 422);
            }
            $user->role_id = (string) $role->_id;
        } elseif ($request->filled('role_id')) {
            $role = AdminRole::find($request->role_id);
            if ($role) {
                $user->role_id = (string) $role->_id;
            }
        }

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }
        if ($request->has('image')) {
            $user->image = $request->filled('image')
                ? Helpers::cdnRelativePath($request->image)
                : null;
        }
        // Never deactivate / demote superadmin via this endpoint.
        if ($request->has('status') && !$isSuper) {
            $user->status = (int) $request->status;
        }

        $user->save();

        return ResponseHelper::sendResponse(['id' => (string) $user->_id], 'Team member updated.');
    }

    public function destroyMember(string $id)
    {
        $user = User::find($id);
        if (!$user) {
            return ResponseHelper::sendResponse(null, 'Member not found', false, 404);
        }

        if ((int) ($user->is_superadmin ?? 0) === 1) {
            return ResponseHelper::sendResponse(null, 'Cannot delete Super Admin.', false, 403);
        }

        $authId = (string) (optional(Auth::user())->_id ?? '');
        if ($authId !== '' && $authId === (string) $user->_id) {
            return ResponseHelper::sendResponse(null, 'Cannot delete your own account.', false, 403);
        }

        $isTeam = (int) ($user->is_admin_user ?? 0) === 1
            || (string) ($user->user_type ?? '') === 'team_member';
        if (!$isTeam) {
            return ResponseHelper::sendResponse(null, 'Not a team member', false, 400);
        }

        $user->delete();
        return ResponseHelper::sendResponse(['id' => $id], 'Team member deleted.');
    }

    /* ════════════════════════ HELPERS ════════════════════════ */

    private function isLockedRole(string $name): bool
    {
        $n = strtolower(trim($name));
        return $n === 'super admin' || $n === 'superadmin' || $n === 'super-admin';
    }

    private function roleNameExists(string $name, ?string $exceptId = null): bool
    {
        $existing = AdminRole::get(['_id', 'name']);
        foreach ($existing as $r) {
            if ($exceptId && (string) $r->_id === $exceptId) {
                continue;
            }
            if (strcasecmp((string) ($r->name ?? ''), $name) === 0) {
                return true;
            }
        }
        return false;
    }

    private function emailTaken(string $email, ?string $exceptId = null): bool
    {
        $q = User::where('email', $email);
        if ($exceptId) {
            $q->where('_id', '!=', $exceptId);
        }
        return $q->exists();
    }

    /** "John Doe" → ["John", "Doe"] */
    private function splitName(string $full): array
    {
        $full = trim(preg_replace('/\s+/', ' ', $full) ?? '');
        if ($full === '') {
            return ['', ''];
        }
        $parts = explode(' ', $full, 2);
        return [$parts[0], $parts[1] ?? ''];
    }

    /**
     * Canonical keys use dotted Maklad form (`dashboard.read`).
     * Also accept underscore leftovers from the new dashboard UI (`dashboard_read`).
     */
    private function normalizePermissionList($perms): array
    {
        if (!is_array($perms)) {
            return [];
        }
        $out = [];
        foreach ($perms as $p) {
            $key = $this->canonicalPermKey((string) $p);
            if ($key === '' || $key === 'admin_all' || $key === 'admin.all') {
                continue; // "Select All" is UI-only
            }
            $out[$key] = $key;
        }
        return array_values($out);
    }

    private function canonicalPermKey(string $key): string
    {
        $key = trim($key);
        if ($key === '') {
            return '';
        }
        // Already dotted
        if (str_contains($key, '.')) {
            return $key;
        }
        // Legacy UI underscore keys
        $map = [
            'admin_all'         => 'admin_all',
            'dashboard_read'    => 'dashboard.read',
            'user_read'         => 'users.read',
            'user_educated'     => 'users.write',
            'user_uneducated'   => 'users.write',
            'channels_read'     => 'fanpage.read',
            'channels_write'    => 'fanpage.write',
            'feeds_read'        => 'posts.read',
            'feeds_write'       => 'posts.write',
            'music_read'        => 'music.read',
            'music_write'       => 'music.write',
            'surveys_read'      => 'voting.read',
            'surveys_write'     => 'voting.write',
            'settings_read'     => 'policy_terms.read',
            'settings_write'    => 'policy_terms.write',
        ];
        if (isset($map[$key])) {
            return $map[$key];
        }
        // Generic: foo_bar_baz → foo.bar_baz if first underscore splits module.action
        if (preg_match('/^([a-z0-9]+)_(.+)$/i', $key, $m)) {
            return strtolower($m[1]) . '.' . $m[2];
        }
        return $key;
    }

    private function permissionTags(array $perms): array
    {
        $labels = [];
        foreach ($perms as $p) {
            $module = explode('.', (string) $p)[0] ?? (string) $p;
            $label = Str::title(str_replace('_', ' ', $module));
            $labels[$label] = $label;
            if (count($labels) >= 8) {
                break;
            }
        }
        return array_values($labels);
    }
}
