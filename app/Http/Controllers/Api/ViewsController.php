<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\FeedViews;
use App\Models\Feed;
use App\Models\MultimediaViews;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ViewsController extends Controller
{
    public function get_feeds_views(Request $request)
    {
        $feeds = FeedViews::with('user')->where('feed_type', $request->feed_type)->where('feed_id', $request->feed_id)->get();
        return ResponseHelper::sendResponse($feeds, 'Feeds View Fetched');
    }

    public function store_feeds_views(Request $request)
    {
        if (!$request->feed_id) return ResponseHelper::sendResponse([], 'Feed Id is Required', false, 401);

        $feeds = FeedViews::where('user_id', Auth::id())->where('feed_id', $request->feed_id)->first();
        if (!$feeds) {
            $feeds = FeedViews::create([
                'user_id' => Auth::id(),
                'feed_id' => $request->feed_id,
                'feed_type' => $request->feed_type,
            ]);
        }

        $feed = Feed::find($request->feed_id);
        if ($feed) {
            $feed->comments_count = $feed->comments->count();
            $feed->voice_comments_count = $feed->voice_comments->count();
            $feed->likes_count = $feed->likes->count();
            $feed->views_count = $feed->views->count();
            $feed->shares_count = $feed->shares->count();
            $feed->save();
        }

        return ResponseHelper::sendResponse($feeds, 'Feeds View Succeed');
    }

    public function get_multimedia_views(Request $request)
    {
        $multimedia = FeedViews::with('user')->where('media_type', $request->media_type)->where('media_id', $request->media_id)->get();
        return ResponseHelper::sendResponse($multimedia, 'Multimedia View Fetched');
    }

    public function store_multimedia_views(Request $request)
    {
        $multimedia = MultimediaViews::where('user_id', Auth::id())->where('media_id', $request->media_id)->first();
        if (!$multimedia) {
            $multimedia = MultimediaViews::create([
                'user_id' => Auth::id(),
                'media_id' => $request->media_id,
                'media_type' => $request->media_type,
            ]);
        }
        return ResponseHelper::sendResponse($multimedia, 'Multimedia View Succeed');
    }
}
