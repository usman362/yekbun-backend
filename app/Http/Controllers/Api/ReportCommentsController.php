<?php

namespace App\Http\Controllers\Api;

use App\Helpers\NotificationHelper;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Feed;
use App\Models\FeedComments;
use App\Models\NotificationCenter;
use App\Models\ReportComments;
use App\Models\ReportFeeds;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class ReportCommentsController extends Controller
{
    public function index(Request $request, $id)
    {
        $reports = ReportComments::where('comment_id', $id)->get();
        return ResponseHelper::sendResponse($reports, 'Report Comments');
    }

    public function getUserReportedComments()
    {
        $userId = Auth::id();

        $reportComments = ReportComments::with(['comments.feed', 'user'])
            ->whereHas('comments', fn($q) => $q->where('user_id', $userId))
            ->where('status', 1)->get()
            ->map(fn($item) => ['type' => 'comment', 'data' => $item, 'created_at' => $item->created_at]);

        $reportFeeds = ReportFeeds::with(['feed', 'user'])
            ->whereHas('feed', fn($q) => $q->where('user_id', $userId))
            ->where('status', 1)->get()
            ->map(fn($item) => ['type' => 'feed', 'data' => $item, 'created_at' => $item->created_at]);

        $mergedReports = collect()->merge($reportComments)->merge($reportFeeds)->sortByDesc('created_at')->values();

        return ResponseHelper::sendResponse(['reported_items' => $mergedReports], 'Reported items fetched successfully');
    }

    public function store(Request $request, $id)
    {
        $request->validate(['report_type' => 'required|string|max:255']);
        $userId = Auth::id();

        $exists = ReportComments::where('user_id', $userId)->where('comment_id', $id)->first();
        if ($exists) {
            if ($exists->status == 1) return ResponseHelper::sendResponse([], 'You have already reported this comment.', false, 400);
            $exists->delete();
        }

        $report = ReportComments::create([
            'comment_id' => $id, 'report_type' => Str::slug($request->report_type),
            'user_id' => $userId, 'status' => 1,
        ]);

        $comment = FeedComments::find($id);
        if ($comment) {
            $owner = User::where('_id', $comment->user_id)->whereIn('info_banner', ['banner', 'alert'])->first();
            if ($owner) {
                NotificationHelper::sendNotification($owner->_id, 'Feed Comment Reported', 'Your comment has been reported');
                NotificationCenter::create([
                    'title' => 'Feed Comment Reported', 'description' => 'Your comment has been reported',
                    'user_id' => $owner->_id, 'user_image' => $owner->image ?? null, 'type' => 'feed_comments', 'is_read' => 0,
                ]);
            }
        }

        return ResponseHelper::sendResponse($report, 'Comment reported successfully');
    }

    public function reportfeedstore(Request $request, $id)
    {
        $request->validate(['report_type' => 'required|string|max:255']);
        $userId = Auth::id();

        $exists = ReportFeeds::where('feed_id', $id)->where('user_id', $userId)->first();
        if ($exists) {
            if ($exists->status == 1) return ResponseHelper::sendResponse([], 'You have already reported this feed.', false, 400);
            $exists->delete();
        }

        $report = ReportFeeds::create([
            'feed_id' => $id, 'report_type' => Str::slug($request->report_type),
            'user_id' => $userId, 'status' => 1
        ]);

        $feed = Feed::find($id);
        if ($feed) {
            $owner = User::where('_id', $feed->user_id)->whereIn('info_banner', ['banner', 'alert'])->first();
            if ($owner) {
                NotificationHelper::sendNotification($owner->_id, 'Feed Reported', 'Your feed has been reported');
                NotificationCenter::create([
                    'title' => 'Feed Reported', 'description' => 'Your feed has been reported',
                    'user_id' => $owner->_id, 'user_image' => $owner->image ?? null, 'type' => 'feeds', 'is_read' => 0,
                ]);
            }
        }

        return ResponseHelper::sendResponse($report, 'Feed reported successfully');
    }

    public function resolveReportViolation(Request $request)
    {
        if (!$request->report_id) return ResponseHelper::sendResponse([], 'Report Id is required!', false, 404);

        $reportFeed = ReportFeeds::find($request->report_id);
        $reportComments = ReportComments::find($request->report_id);

        if (!$reportFeed && !$reportComments) return ResponseHelper::sendResponse([], 'Reported Item Not Found!', false, 404);

        if ($reportFeed) {
            $reportFeed->status = 0;
            $reportFeed->resolved_reason = $request->resolved_reason;
            $reportFeed->save();
            return ResponseHelper::sendResponse($reportFeed, 'Violation Resolved Successfully!');
        }

        $reportComments->status = 0;
        $reportComments->resolved_reason = $request->resolved_reason;
        $reportComments->save();
        return ResponseHelper::sendResponse($reportComments, 'Violation Resolved Successfully!');
    }
}
