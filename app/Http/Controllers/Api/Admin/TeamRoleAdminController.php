<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\Helpers;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\AdminRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class TeamRoleAdminController extends Controller
{
    /* ════════════════════════ ROLES ════════════════════════ */

    public function roles()
    {
        $roles = AdminRole::orderBy('created_at', 'desc')->get();
        $roleIds = $roles->pluck('_id')->map(fn($x) => (string) $x)->toArray();

        $memberCounts = User::whereIn('role_id', $roleIds)
            ->get(['role_id'])
            ->groupBy(fn($u) => (string) $u->role_id)
            ->map->count();

        $result = $roles->map(function ($r) use ($memberCounts) {
            // Existing data may use 'permission' (singular) per Spatie/Maklad; align both
            $perms = is_array($r->permissions) ? $r->permissions
                : (is_array($r->permission) ? $r->permission : []);
            $statusVal = $r->status ?? 'active';
            $statusStr = $statusVal === 'inactive' || $statusVal === '0' || $statusVal === 0
                ? 'inactive' : 'active';
            return [
                'id'             => (string) $r->_id,
                'name'           => $r->name ?? '',
                'description'    => $r->description ?? "Role: {$r->name}",
                'members'        => (int) ($memberCounts->get((string) $r->_id, 0)),
                'permissions'    => count($perms),
                'status'         => $statusStr,
                'permissionTags' => array_slice($perms, 0, 12),
                'permissionKeys' => $perms,
            ];
        })->values();

        return ResponseHelper::sendResponse($result, 'Roles loaded.');
    }

    public function storeRole(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:100',
            'permissions' => 'nullable|array',
        ]);

        $perms = array_values($request->input('permissions', []));
        $role = new AdminRole();
        $role->name        = $request->name;
        $role->description = $request->input('description', "Role: {$request->name}");
        $role->permissions = $perms;
        $role->permission  = $perms; // compat with Spatie/Maklad existing readers
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
        ]);

        $role = AdminRole::find($id);
        if (!$role) {
            return ResponseHelper::sendResponse(null, 'Role not found', false, 404);
        }

        $role->name        = $request->name;
        if ($request->has('description')) $role->description = $request->description;
        if ($request->has('permissions')) {
            $perms = array_values($request->input('permissions', []));
            $role->permissions = $perms;
            $role->permission  = $perms;
        }
        if ($request->has('status'))      $role->status      = $request->status;
        $role->save();

        return ResponseHelper::sendResponse(['id' => (string) $role->_id], 'Role updated.');
    }

    public function destroyRole(string $id)
    {
        $role = AdminRole::find($id);
        if (!$role) {
            return ResponseHelper::sendResponse(null, 'Role not found', false, 404);
        }

        // Unset role_id on any users currently assigned
        User::where('role_id', (string) $role->_id)->update(['role_id' => null]);

        $role->delete();
        return ResponseHelper::sendResponse(['id' => $id], 'Role deleted.');
    }

    /* ════════════════════════ MEMBERS (admin users) ════════════════════════ */

    public function members()
    {
        $users = User::where(function ($q) {
                $q->where('is_admin_user', 1)
                  ->orWhere('user_type', 'team_member')
                  ->orWhere('is_superadmin', 1);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $roleIds = $users->pluck('role_id')->filter()->unique()->toArray();
        $roles = AdminRole::whereIn('_id', $roleIds)->get()->keyBy(fn($r) => (string) $r->_id);

        $result = $users->map(function ($u) use ($roles) {
            $isSuper = (int) ($u->is_superadmin ?? 0) === 1;
            $role = $u->role_id ? $roles->get((string) $u->role_id) : null;
            return [
                'id'      => (string) $u->_id,
                'name'    => trim(($u->name ?? '') . ' ' . ($u->last_name ?? '')) ?: ($u->username ?? 'Admin'),
                'email'   => $u->email ?? '',
                'role'    => $isSuper ? 'Super Admin' : ($role->name ?? '—'),
                'roleId'  => $u->role_id ? (string) $u->role_id : null,
                'status'  => ($u->status ?? 1) == 1 ? 'active' : 'inactive',
                'avatar'  => Helpers::mediaUrl($u->image) ?? strtoupper(mb_substr((string) ($u->name ?? 'A'), 0, 2)),
                'image'   => Helpers::mediaUrl($u->image) ?? null,
                'isSuperAdmin' => $isSuper,
            ];
        })->values();

        return ResponseHelper::sendResponse($result, 'Team members loaded.');
    }

    public function storeMember(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:100',
            'email'    => 'required|email|max:150|unique:users,email',
            'password' => 'required|string|min:6',
            'role_id'  => 'required|string',
            'image'    => 'nullable|string',
            'status'   => 'nullable|in:0,1',
        ]);

        $role = AdminRole::find($request->role_id);
        if (!$role) {
            return ResponseHelper::sendResponse(null, 'Role not found', false, 422);
        }

        $user = new User();
        $user->name          = $request->name;
        $user->email         = $request->email;
        $user->password      = Hash::make($request->password);
        $user->role_id       = (string) $role->_id;
        $user->is_admin_user = 1;
        $user->user_type     = 'team_member';
        $user->status        = (int) $request->input('status', 1);
        if ($request->filled('image')) $user->image = Helpers::cdnRelativePath($request->image);
        $user->save();

        return ResponseHelper::sendResponse(['id' => (string) $user->_id], 'Team member created.', true, 201);
    }

    public function updateMember(Request $request, string $id)
    {
        $user = User::find($id);
        if (!$user) {
            return ResponseHelper::sendResponse(null, 'Member not found', false, 404);
        }

        $request->validate([
            'name'     => 'required|string|max:100',
            'email'    => 'required|email|max:150|unique:users,email,' . $id . ',_id',
            'role_id'  => 'required|string',
            'password' => 'nullable|string|min:6',
            'image'    => 'nullable|string',
            'status'   => 'nullable|in:0,1',
        ]);

        $role = AdminRole::find($request->role_id);
        if (!$role) {
            return ResponseHelper::sendResponse(null, 'Role not found', false, 422);
        }

        $user->name    = $request->name;
        $user->email   = $request->email;
        $user->role_id = (string) $role->_id;
        if ($request->filled('password')) $user->password = Hash::make($request->password);
        if ($request->has('image'))       $user->image    = Helpers::cdnRelativePath($request->image);
        if ($request->has('status'))      $user->status   = (int) $request->status;
        $user->save();

        return ResponseHelper::sendResponse(['id' => (string) $user->_id], 'Team member updated.');
    }

    public function destroyMember(string $id)
    {
        $user = User::find($id);
        if (!$user) {
            return ResponseHelper::sendResponse(null, 'Member not found', false, 404);
        }
        if ((int) ($user->is_admin_user ?? 0) !== 1) {
            return ResponseHelper::sendResponse(null, 'Not a team member', false, 400);
        }
        $user->delete();
        return ResponseHelper::sendResponse(['id' => $id], 'Team member deleted.');
    }
}
