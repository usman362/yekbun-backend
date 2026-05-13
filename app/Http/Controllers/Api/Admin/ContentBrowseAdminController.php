<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\AdminActivityController;
use App\Http\Controllers\Api\Admin\FeedsController as AdminFeedsCtrl;
use App\Models\AIVideo;
use App\Models\Event;
use App\Models\Feed;
use App\Models\History;
use App\Models\PopFeeds;
use App\Models\ReportFeeds;
use App\Models\Voting;
use Illuminate\Http\Request;

class ContentBrowseAdminController extends Controller
{
    public function history()
    {
        $rows = History::where('status', '1')->orderBy('created_at', 'desc')->limit(100)->get();

        return ResponseHelper::sendResponse($rows, 'History loaded.');
    }

    public function aiVideos()
    {
        $rows = AIVideo::where('status', '1')->orderBy('created_at', 'desc')->limit(100)->get();

        return ResponseHelper::sendResponse($rows, 'AI videos loaded.');
    }

    public function events()
    {
        $rows = Event::orderBy('created_at', 'desc')->limit(100)->get();

        return ResponseHelper::sendResponse($rows, 'Events loaded.');
    }

    public function votings()
    {
        $rows = Voting::where('status', '1')->with('reactions')->orderBy('created_at', 'desc')->get();

        return ResponseHelper::sendResponse($rows, 'Votings loaded.');
    }

    public function complaints(Request $request)
    {
        $perPage = min((int) $request->get('per_page', 15), 100);

        // Paginate distinct reported feed IDs by most-recent report
        $reportsPaginator = ReportFeeds::orderBy('created_at', 'desc')
            ->paginate($perPage);

        $feedIds = collect($reportsPaginator->items())
            ->pluck('feed_id')
            ->unique()
            ->filter()
            ->values()
            ->toArray();

        $feeds = empty($feedIds)
            ? collect()
            : Feed::whereIn('_id', $feedIds)->get();

        // Preserve report-order: sort feeds in the same order as $feedIds
        $orderMap = array_flip($feedIds);
        $feeds = $feeds->sortBy(fn($f) => $orderMap[$f->_id] ?? PHP_INT_MAX)->values();

        $items = app(AdminFeedsCtrl::class)->transformFeeds($feeds);

        return ResponseHelper::sendResponse([
            'items'     => $items,
            'total'     => $reportsPaginator->total(),
            'page'      => $reportsPaginator->currentPage(),
            'last_page' => $reportsPaginator->lastPage(),
        ], 'Reported feeds loaded.');
    }

    public function postsPreview()
    {
        $feeds = Feed::with('user')->orderBy('created_at', 'desc')->limit(40)->get();

        return ResponseHelper::sendResponse($feeds, 'Feeds preview loaded.');
    }

    public function adminActivity(string $type)
    {
        $ctrl = app(AdminActivityController::class);

        return match ($type) {
            'system' => $ctrl->getSystemInfo(),
            'donation' => $ctrl->getDonations(),
            'surveys' => $ctrl->getSurveys(),
            'greetings' => $ctrl->getGreetings(),
            'user-sos' => ResponseHelper::sendResponse(
                PopFeeds::where('type', 'SOS')->orderBy('created_at', 'desc')->get(),
                'SOS Feeds'
            ),
            'go-live' => ResponseHelper::sendResponse(
                PopFeeds::where('type', 'Event')->orderBy('created_at', 'desc')->get(),
                'Event Feeds'
            ),
            'agent-feeds' => ResponseHelper::sendResponse(
                PopFeeds::where('type', 'AgentFeed')->orderBy('created_at', 'desc')->get(),
                'Agent Feeds'
            ),
            'public-feed' => ResponseHelper::sendResponse(
                PopFeeds::with('user')->where('share_option', 'all-users')->orderBy('created_at', 'desc')->limit(150)->get(),
                'Public admin activity feeds loaded.'
            ),
            default => ResponseHelper::sendResponse([], 'Unknown type.', false, 404),
        };
    }

    public function adminActivityStore(\Illuminate\Http\Request $request, string $type)
    {
        $ctrl = app(AdminActivityController::class);

        return match ($type) {
            'system' => $ctrl->store_systemInfo($request),
            'donation' => $ctrl->store_donation($request),
            'surveys' => $ctrl->store_surveys($request),
            'greetings' => $ctrl->store_greetings($request),
            'user-sos' => $ctrl->store_userSos($request),
            'go-live' => $ctrl->store_goLive($request),
            'agent-feeds' => $ctrl->store_agentFeed($request),
            default => ResponseHelper::sendResponse([], 'Unknown type.', false, 404),
        };
    }

    public function adminActivityDestroy(string $id)
    {
        return app(AdminActivityController::class)->destroyById($id);
    }
}
