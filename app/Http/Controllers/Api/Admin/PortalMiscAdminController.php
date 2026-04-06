<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\AppInfo;
use App\Models\NotificationCenter;
use Illuminate\Http\Request;

class PortalMiscAdminController extends Controller
{
    public function notifications(Request $request)
    {
        $perPage = min((int) $request->get('per_page', 20), 100);
        $q = NotificationCenter::with(['user', 'send_by'])->orderBy('created_at', 'desc');
        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(function ($qq) use ($s) {
                $qq->where('title', 'like', '%' . $s . '%')
                    ->orWhere('description', 'like', '%' . $s . '%');
            });
        }
        $paginator = $q->paginate($perPage);
        $items = collect($paginator->items())->map(function ($n) {
            return [
                'id' => (string) $n->_id,
                'title' => $n->title,
                'description' => $n->description ?? '',
                'type' => $n->type ?? '',
                'is_read' => (bool) $n->is_read,
                'created_at' => $n->created_at?->toIso8601String(),
                'user' => optional($n->user)->username ?? optional($n->user)->name,
            ];
        });

        return ResponseHelper::sendResponse([
            'notifications' => $items,
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ], 'Notifications loaded.');
    }

    public function appInfo()
    {
        $info = AppInfo::latest()->first();

        return ResponseHelper::sendResponse($info ? $info->toArray() : null, 'App info loaded.');
    }

    public function updateAppInfo(Request $request)
    {
        $info = AppInfo::latest()->first() ?? new AppInfo;
        $info->fill($request->only([
            'city_zipcode', 'zipcode', 'company_name', 'address', 'house_number', 'description',
        ]));
        $info->save();

        return ResponseHelper::sendResponse($info, 'App info saved.');
    }
}
