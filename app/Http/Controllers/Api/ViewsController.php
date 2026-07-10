<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Helpers\Helpers;
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

        $feedType = (string) $request->feed_type;
        $feed = null;
        if ($feedType === 'history') {
            $feed = \App\Models\History::find($request->feed_id);
        } elseif ($feedType === 'ai_videos') {
            $feed = \App\Models\AIVideo::find($request->feed_id);
        } else {
            $feed = Feed::find($request->feed_id);
        }

        if ($feed) {
            $feed->comments_count = method_exists($feed, 'comments') ? $feed->comments()->count() : (int) ($feed->comments_count ?? 0);
            $feed->voice_comments_count = method_exists($feed, 'voice_comments') ? $feed->voice_comments()->count() : (int) ($feed->voice_comments_count ?? 0);
            $feed->likes_count = method_exists($feed, 'likes') ? $feed->likes()->count() : (int) ($feed->likes_count ?? 0);
            $feed->views_count = method_exists($feed, 'views') ? $feed->views()->count() : FeedViews::where('feed_id', $request->feed_id)->count();
            $feed->shares_count = method_exists($feed, 'shares') ? $feed->shares()->count() : (int) ($feed->shares_count ?? 0);
            $feed->save();

            // Keep mobile videos feed (`media` collection) counts in sync for history / AI.
            if (in_array($feedType, ['history', 'ai_videos'], true)) {
                Helpers::userMedia(
                    $feed->_id,
                    'exists',
                    (int) $feed->comments_count,
                    (int) $feed->voice_comments_count,
                    (int) $feed->likes_count,
                    (int) $feed->views_count,
                    $feed->user_id ?? null,
                    $feed->source ?? null,
                    null,
                    $feedType
                );
            }
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
