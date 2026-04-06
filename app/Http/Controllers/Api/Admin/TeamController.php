<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TeamController extends Controller
{
    public function roles()
    {
        $rolesCol = DB::connection('mongodb')->getDatabase()->selectCollection('roles');
        $roles = iterator_to_array($rolesCol->find());

        $result = collect($roles)->map(function ($r) {
            $id = (string) $r['_id'];
            $perms = $r['permission'] ?? [];
            $memberCount = User::where('role_id', $id)->count();

            $permTags = collect($perms)->map(function ($p) {
                return ucfirst(explode('.', $p)[0]);
            })->unique()->values()->toArray();

            return [
                'id'             => $id,
                'name'           => $r['name'] ?? 'Untitled',
                'description'    => 'Role: ' . ($r['name'] ?? ''),
                'members'        => $memberCount,
                'permissions'    => count($perms),
                'status'         => 'active',
                'permissionTags' => $permTags,
            ];
        })->values();

        return ResponseHelper::sendResponse($result, 'Roles fetched.');
    }

    public function members()
    {
        $teamMembers = User::where('user_type', 'team_member')->get();

        $rolesCol = DB::connection('mongodb')->getDatabase()->selectCollection('roles');
        $roles = collect(iterator_to_array($rolesCol->find()))->keyBy(fn($r) => (string) $r['_id']);

        $superAdmin = User::where('is_superadmin', 1)->first();

        $members = collect();

        if ($superAdmin) {
            $members->push([
                'id'     => $superAdmin->_id,
                'name'   => trim(($superAdmin->name ?? '') . ' ' . ($superAdmin->last_name ?? '')),
                'email'  => $superAdmin->email ?? '',
                'role'   => 'Super Admin',
                'status' => $superAdmin->status == 1 ? 'active' : 'inactive',
                'avatar' => strtoupper(substr($superAdmin->name ?? 'SA', 0, 2)),
            ]);
        }

        foreach ($teamMembers as $m) {
            $role = $roles->get($m->role_id);
            $members->push([
                'id'     => $m->_id,
                'name'   => $m->name ?? '',
                'email'  => $m->email ?? '',
                'role'   => $role ? ($role['name'] ?? 'Unknown') : 'Unknown',
                'status' => $m->status == 1 ? 'active' : 'inactive',
                'avatar' => strtoupper(substr($m->name ?? 'XX', 0, 2)),
            ]);
        }

        return ResponseHelper::sendResponse($members->values(), 'Members fetched.');
    }
}
