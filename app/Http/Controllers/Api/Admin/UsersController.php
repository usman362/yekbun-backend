<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class UsersController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);
        $tab     = $request->get('tab', 'male');
        $search  = $request->get('search', '');
        $level   = $request->get('level');

        $query = User::query();

        if ($tab === 'male') {
            $query->where('gender', 'male')->where('status', 1);
        } elseif ($tab === 'female') {
            $query->where('gender', 'female')->where('status', 1);
        } elseif ($tab === 'closed') {
            $query->where('status', 0);
        }

        if ($level && $level !== 'all') {
            $levelMap = ['cultivated' => 1, 'educated' => 2, 'academic' => 3, 'flagged' => 4];
            if (isset($levelMap[$level])) {
                $query->where('level', $levelMap[$level]);
            }
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $paginated = $query->orderBy('created_at', 'desc')
            ->paginate($perPage, [
                'name', 'email', 'username', 'user_id', 'image',
                'gender', 'status', 'device_type', 'device_imei',
                'device_model', 'device_serial', 'created_at',
            ]);

        $users = collect($paginated->items())->map(function ($u) {
            return [
                'id'           => $u->_id,
                'name'         => $u->name ?? '',
                'email'        => $u->email ?? '',
                'username'     => $u->username ?? '',
                'userId'       => $u->user_id ?? '',
                'avatar'       => $u->image ?? '',
                'gender'       => $u->gender ?? 'male',
                'status'       => $u->status == 1 ? 'active' : 'closed',
                'deviceType'   => $u->device_type ?? 'android',
                'deviceImei'   => $u->device_imei ?? '',
                'deviceModel'  => $u->device_model ?? '',
                'serialNumber' => $u->device_serial ?? 'unknown',
                'joinDate'     => $u->created_at ? Carbon::parse($u->created_at)->format('d/m/Y') : '',
            ];
        });

        return ResponseHelper::sendResponse([
            'users'       => $users,
            'total'       => $paginated->total(),
            'page'        => $paginated->currentPage(),
            'last_page'   => $paginated->lastPage(),
            'per_page'    => $paginated->perPage(),
        ], 'Users fetched.');
    }

    public function stats()
    {
        $total  = User::count();
        $male   = User::where('gender', 'male')->where('status', 1)->count();
        $female = User::where('gender', 'female')->where('status', 1)->count();
        $closed = User::where('status', 0)->count();

        return ResponseHelper::sendResponse([
            'total'  => $total,
            'male'   => $male,
            'female' => $female,
            'closed' => $closed,
        ], 'User stats fetched.');
    }
}
