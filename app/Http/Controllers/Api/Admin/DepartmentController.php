<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Department;
use Carbon\Carbon;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::orderBy('created_at', 'desc')->get();

        $result = $departments->map(function ($d) {
            $subCount = Department::where('parent_id', $d->_id)->count();
            return [
                'id'          => $d->_id,
                'name'        => $d->name ?? 'Untitled',
                'thumbnail'   => $d->thumbnail_path ?? '📁',
                'totalSub'    => $subCount,
                'status'      => 'active',
                'createdDate' => $d->created_at ? Carbon::parse($d->created_at)->format('d/m/Y') : '',
            ];
        })->values();

        return ResponseHelper::sendResponse($result, 'Departments fetched.');
    }
}
