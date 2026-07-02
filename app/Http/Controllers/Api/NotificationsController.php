<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helpers;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\NotificationCenter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Notification Center.
 *
 * `notifications_center` is the SHARED, app-wide notification store — friend requests, feed
 * comments, clip/feed activity, reports, etc. all land here. The general `index()` keeps its
 * original shape so the social app is unaffected.
 *
 * The Zercash WALLET app shows a separate two-tab notification screen, so it has its own
 * scoped reader (`walletIndex` / `walletReadAll`) that only surfaces wallet-related rows:
 *   - "System Info"  → order / payment / account / wallet events
 *   - "Wallet News"  → merchant / news cards with a banner image
 */
class NotificationsController extends Controller
{
    /** Wallet "System Info" tab. */
    private const WALLET_SYSTEM_TYPES = ['wallet', 'order', 'payment', 'account', 'transaction', 'cashback', 'deposit'];

    /** Wallet "Wallet News" tab. */
    private const WALLET_NEWS_TYPES = ['wallet_news', 'merchant', 'merchant_news'];

    /* ───────────── General (social) — unchanged contract ───────────── */

    public function index()
    {
        $notifications = NotificationCenter::with(['user', 'send_by'])
            ->where('is_read', 0)
            ->where('user_id', Auth::id())
            ->get();
        return ResponseHelper::sendResponse($notifications, 'Notifications has been Fetch Successfully!');
    }

    public function store(Request $request)
    {
        $userImage = '';
        if ($request->hasFile('user_image')) {
            $userImage = Helpers::fileUpload($request->user_image, 'notification-users');
        }

        $notification = NotificationCenter::create([
            'title'       => $request->title,
            'description' => $request->description,
            'user_id'     => $request->user_id,
            'send_by_id'  => Auth::id(),
            'user_image'  => $userImage,
            'type'        => $request->type,
            'category'    => $request->category,   // e.g. order_success, order_cancelled, card_connected
            'image'       => $request->image,      // banner image for wallet-news cards
            'link'        => $request->link,
            'is_read'     => 0,
        ]);

        return ResponseHelper::sendResponse($notification, 'Notification has been Sent!');
    }

    public function read($id)
    {
        $notification = NotificationCenter::find($id);
        if (!$notification) {
            return ResponseHelper::sendResponse(null, 'Notification not found.', false, 404);
        }
        // Defense-in-depth: an authenticated caller may only touch their own notifications.
        if (Auth::check() && (string) $notification->user_id !== (string) Auth::id()) {
            return ResponseHelper::sendResponse(null, 'Not allowed.', false, 403);
        }
        $notification->is_read = 1;
        $notification->read_at = Carbon::now();
        $notification->save();
        return ResponseHelper::sendResponse($notification, 'Notification has been Read Successfully!');
    }

    public function delete($id)
    {
        $notification = NotificationCenter::find($id);
        if (!$notification) {
            return ResponseHelper::sendResponse(null, 'Notification not found.', false, 404);
        }
        if (Auth::check() && (string) $notification->user_id !== (string) Auth::id()) {
            return ResponseHelper::sendResponse(null, 'Not allowed.', false, 403);
        }
        $notification->delete();
        return ResponseHelper::sendResponse([], 'Notification has been Deleted Successfully!');
    }

    public function getSystemSettings()
    {
        $notify = AdminNotification::select(['screenshots', 'recording'])->first();
        return ResponseHelper::sendResponse($notify, 'System Settings has fetch Successfully!');
    }

    /* ───────────── Wallet-scoped notification screen ───────────── */

    /**
     * GET /api/wallet/notifications?tab=system|news&per_page=15
     * Two-tab wallet notifications (read + unread, newest first) + unread counts for the badge.
     */
    public function walletIndex(Request $request)
    {
        $userId   = Auth::id();
        $perPage  = min((int) $request->get('per_page', 15), 50);
        $tab      = $request->query('tab');
        $allTypes = array_merge(self::WALLET_SYSTEM_TYPES, self::WALLET_NEWS_TYPES);

        $query = NotificationCenter::where('user_id', $userId)->whereIn('type', $allTypes);
        if ($tab === 'news') {
            $query->whereIn('type', self::WALLET_NEWS_TYPES);
        } elseif ($tab === 'system') {
            $query->whereIn('type', self::WALLET_SYSTEM_TYPES);
        }

        $paginator = $query->orderBy('created_at', 'desc')->paginate($perPage);
        $items = collect($paginator->items())->map(fn ($n) => $this->transform($n))->values();

        $unreadIn = fn (array $types) => NotificationCenter::where('user_id', $userId)
            ->whereIn('type', $types)->where('is_read', 0)->count();

        return ResponseHelper::sendResponse([
            'items'        => $items,
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'total'        => $paginator->total(),
            'unread'       => [
                'system' => $unreadIn(self::WALLET_SYSTEM_TYPES),
                'news'   => $unreadIn(self::WALLET_NEWS_TYPES),
                'total'  => $unreadIn($allTypes),
            ],
        ], 'Wallet notifications fetched.');
    }

    /** POST /api/wallet/notifications/read-all?tab=system|news — mark wallet notifications read. */
    public function walletReadAll(Request $request)
    {
        $userId   = Auth::id();
        $allTypes = array_merge(self::WALLET_SYSTEM_TYPES, self::WALLET_NEWS_TYPES);

        $types = match ($request->query('tab')) {
            'news'   => self::WALLET_NEWS_TYPES,
            'system' => self::WALLET_SYSTEM_TYPES,
            default  => $allTypes,
        };

        NotificationCenter::where('user_id', $userId)
            ->whereIn('type', $types)
            ->where('is_read', 0)
            ->update(['is_read' => 1, 'read_at' => Carbon::now()]);

        return ResponseHelper::sendResponse(null, 'Wallet notifications marked as read.');
    }

    /** Normalise a wallet notification row for the mobile screen. */
    private function transform(NotificationCenter $n): array
    {
        $type = $n->type ?? 'wallet';
        $tab  = in_array($type, self::WALLET_NEWS_TYPES, true) ? 'news' : 'system';
        $when = $n->created_at ? Carbon::parse($n->created_at) : null;

        return [
            '_id'         => (string) $n->_id,
            'tab'         => $tab,                 // "system" | "news"
            'type'        => $type,
            'category'    => $n->category ?? null, // drives the System-Info icon (see docs)
            'title'       => $n->title ?? '',
            'description' => $n->description ?? '',
            'image'       => Helpers::mediaUrl($n->image),       // wallet-news banner
            'user_image'  => Helpers::mediaUrl($n->user_image),
            'link'        => $n->link ?? null,
            'is_read'     => (bool) ($n->is_read ?? false),
            'date'        => $when ? $when->format('d M Y') : null, // "19 Dec 2022"
            'time'        => $when ? $when->format('h:i A') : null, // "08:50 PM"
            'created_at'  => $n->created_at,
        ];
    }
}
