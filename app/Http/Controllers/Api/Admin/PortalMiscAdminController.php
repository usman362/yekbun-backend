<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\AppInfo;
use App\Models\NotificationCenter;
use App\Models\Notifications;
use Illuminate\Http\Request;

class PortalMiscAdminController extends Controller
{
    /**
     * Portal Notification config types: [key => [group, label]]. `key` maps to the
     * `Notifications` config columns `{key}` (on/off), `{key}_title`, `{key}_description`
     * and to the per-user opt-in field of the same name.
     */
    private const NOTIF_TYPES = [
        'admin_system_info' => ['Admin Activity', 'System Update'],
        'admin_donation'    => ['Admin Activity', 'Portal Donation'],
        'admin_surveys'     => ['Admin Activity', 'Portal Surveys'],
        'admin_greetings'   => ['Admin Activity', 'Portal Greetings'],
        'admin_events'      => ['Admin Activity', 'Portal Event'],
        'admin_sos'         => ['Admin Activity', 'SOS'],
        'admin_live_stream' => ['Admin Activity', 'Portal Live Stream'],
        'new_music'         => ['Content', 'Songs'],
        'new_artist'        => ['Content', 'Artist'],
        'new_video_clips'   => ['Content', 'Video Clips'],
        'new_donation'      => ['Content', 'Donation'],
        'new_events'        => ['Content', 'Events'],
        'new_history'       => ['Content', 'History'],
        'new_news'          => ['Content', 'News'],
        'new_ai_videos'     => ['Content', 'AI-Videos'],
        'new_votes'         => ['Content', 'Survey'],
    ];

    /** GET /admin/portal/notification-config — the per-type notification templates + toggles. */
    public function notificationConfig()
    {
        $c = Notifications::first();
        $items = [];
        foreach (self::NOTIF_TYPES as $key => [$group, $label]) {
            $items[] = [
                'key'         => $key,
                'group'       => $group,
                'label'       => $label,
                'enabled'     => (string) ($c->{$key} ?? 'false') === 'true',
                'title'       => (string) ($c->{$key . '_title'} ?? ''),
                'description' => (string) ($c->{$key . '_description'} ?? ''),
            ];
        }
        return ResponseHelper::sendResponse(['items' => $items], 'Notification config fetched.');
    }

    /** PUT /admin/portal/notification-config — replace the templates/toggles. */
    public function updateNotificationConfig(Request $request)
    {
        $items = $request->input('items', []);
        if (!is_array($items)) {
            return ResponseHelper::sendResponse(null, 'items must be an array', false, 422);
        }

        // Index incoming rows by key for a quick lookup.
        $byKey = [];
        foreach ($items as $row) {
            if (is_array($row) && !empty($row['key'])) {
                $byKey[$row['key']] = $row;
            }
        }

        $c = Notifications::first() ?: new Notifications();
        foreach (self::NOTIF_TYPES as $key => $_) {
            if (!isset($byKey[$key])) continue;
            $row = $byKey[$key];
            $c->{$key}                 = !empty($row['enabled']) ? 'true' : 'false';
            $c->{$key . '_title'}       = (string) ($row['title'] ?? '');
            $c->{$key . '_description'} = (string) ($row['description'] ?? '');
        }
        $c->save();

        return ResponseHelper::sendResponse(null, 'Notification config saved.');
    }

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
