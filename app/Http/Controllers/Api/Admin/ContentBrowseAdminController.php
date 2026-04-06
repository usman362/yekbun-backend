<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\AdminActivityController;
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
        $paginator = ReportFeeds::with(['feed', 'user'])->orderBy('created_at', 'desc')->paginate($perPage);

        return ResponseHelper::sendResponse([
            'items' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
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
            'public-feed' => ResponseHelper::sendResponse(
                PopFeeds::with('user')->where('share_option', 'all-users')->orderBy('created_at', 'desc')->limit(150)->get(),
                'Public admin activity feeds loaded.'
            ),
            default => ResponseHelper::sendResponse([], 'Unknown type.', false, 404),
        };
    }
}
