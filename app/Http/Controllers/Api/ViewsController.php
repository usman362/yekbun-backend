<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Helpers\Helpers;
use App\Http\Controllers\Controller;
use App\Models\AIVideo;
use App\Models\Clips;
use App\Models\ClipsViews;
use App\Models\Feed;
use App\Models\FeedViews;
use App\Models\History;
use App\Models\Media;
use App\Models\MultimediaViews;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use MongoDB\BSON\ObjectId;

class ViewsController extends Controller
{
    public function get_feeds_views(Request $request)
    {
        $feeds = FeedViews::with('user')->where('feed_type', $request->feed_type)->where('feed_id', $request->feed_id)->get();
        return ResponseHelper::sendResponse($feeds, 'Feeds View Fetched');
    }

    public function store_feeds_views(Request $request)
    {
        $feedType = (string) ($request->feed_type ?? '');
        // Clips: accept feed_id / clip_id / id / media_id (mobile grid often sends Media._id).
        $rawId = $request->feed_id
            ?? $request->clip_id
            ?? $request->id
            ?? $request->media_id;

        if (!$rawId) {
            return ResponseHelper::sendResponse([], 'Feed Id is Required', false, 401);
        }

        // ── Clips (feed_type=clips) — replaces legacy POST view-clips ──
        if ($feedType === 'clips') {
            return $this->storeClipView((string) $rawId);
        }

        $feeds = FeedViews::where('user_id', Auth::id())
            ->where('feed_id', $rawId)
            ->when($feedType !== '', fn ($q) => $q->where('feed_type', $feedType))
            ->first();
        if (!$feeds) {
            $feeds = FeedViews::create([
                'user_id' => Auth::id(),
                'feed_id' => $rawId,
                'feed_type' => $feedType,
            ]);
        }

        $feed = null;
        if ($feedType === 'history') {
            $feed = History::find($rawId);
        } elseif ($feedType === 'ai_videos') {
            $feed = AIVideo::find($rawId);
        } else {
            $feed = Feed::find($rawId);
        }

        if ($feed) {
            $feed->comments_count = method_exists($feed, 'comments') ? $feed->comments()->count() : (int) ($feed->comments_count ?? 0);
            $feed->voice_comments_count = method_exists($feed, 'voice_comments') ? $feed->voice_comments()->count() : (int) ($feed->voice_comments_count ?? 0);
            $feed->likes_count = method_exists($feed, 'likes') ? $feed->likes()->count() : (int) ($feed->likes_count ?? 0);
            $feed->views_count = method_exists($feed, 'views') ? $feed->views()->count() : FeedViews::where('feed_id', $rawId)->count();
            $feed->shares_count = method_exists($feed, 'shares') ? $feed->shares()->count() : (int) ($feed->shares_count ?? 0);
            $feed->save();

            // Keep mobile `media` feed counts in sync.
            if (in_array($feedType, ['history', 'ai_videos', 'user_feeds', 'feeds'], true)) {
                Helpers::userMedia(
                    $feed->_id,
                    'exists',
                    (int) $feed->comments_count,
                    (int) $feed->voice_comments_count,
                    (int) $feed->likes_count,
                    (int) $feed->views_count,
                    $feed->user_id ?? null,
                    $feed->source ?? $feed->description ?? null,
                    null,
                    $feedType === 'feeds' ? 'user_feeds' : $feedType
                );
            }
        }

        return ResponseHelper::sendResponse($feeds, 'Feeds View Succeed');
    }

    /**
     * Record a clip view via store-feeds-views (feed_type=clips).
     * Primary store: feed_views. Legacy clips_views kept in sync for old rows.
     */
    private function storeClipView(string $requestId): \Illuminate\Http\JsonResponse
    {
        $clip = $this->resolveClipFromId($requestId);
        if (!$clip) {
            return ResponseHelper::sendResponse([], 'Clip Not Found', false, 404);
        }

        $clipId = (string) $clip->_id;

        $feedView = FeedViews::where('user_id', Auth::id())
            ->where('feed_id', $clipId)
            ->where('feed_type', 'clips')
            ->first();
        if (!$feedView) {
            $feedView = FeedViews::create([
                'user_id' => Auth::id(),
                'feed_id' => $clipId,
                'feed_type' => 'clips',
            ]);
        }

        // Legacy clips_views — only if this user has no row yet (avoid double-count on max()).
        $existingClipView = ClipsViews::where('user_id', Auth::id())
            ->where(function ($q) use ($clipId, $requestId) {
                $q->where('clip_id', $clipId);
                if ($requestId !== $clipId) {
                    $q->orWhere('clip_id', $requestId);
                }
            })
            ->first();
        if (!$existingClipView) {
            ClipsViews::create(['user_id' => Auth::id(), 'clip_id' => $clipId]);
        }

        $viewsCount = $this->clipViewsCount($clip, $clipId);
        $clip->views_count = $viewsCount;
        $clip->save();

        Helpers::userMedia(
            $clip->_id,
            $clip->clip ?? null,
            (int) ($clip->comments_count ?? 0),
            (int) ($clip->voice_comments_count ?? 0),
            (int) ($clip->likes_count ?? 0),
            $viewsCount,
            $clip->user_id ?? null,
            $clip->text ?? null,
            $clip->text_properties ?? null,
            'clips'
        );

        return ResponseHelper::sendResponse($feedView, 'Feeds View Succeed');
    }

    /** Prefer feed_views (clips); fall back to legacy clips_views if higher. */
    private function clipViewsCount(Clips $clip, string $clipId): int
    {
        $fromRelation = (int) $clip->views()->count();
        $legacy = (int) ClipsViews::where('clip_id', $clipId)->count();

        return max($fromRelation, $legacy);
    }

    /**
     * Resolve clips._id — mobile may send clips._id or Media._id from allMediaRecord.
     */
    private function resolveClipFromId(string $id): ?Clips
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }

        $clip = Clips::find($id);
        if ($clip) {
            return $clip;
        }

        $media = Media::find($id);
        if (!$media && strlen($id) === 24 && ctype_xdigit($id)) {
            try {
                $media = Media::where('_id', new ObjectId($id))->first();
            } catch (\Throwable) {
                $media = null;
            }
        }
        if ($media && ($media->type ?? '') === 'clips' && !empty($media->media_id)) {
            $clip = Clips::find((string) $media->media_id);
            if (!$clip && strlen((string) $media->media_id) === 24) {
                try {
                    $clip = Clips::where('_id', new ObjectId((string) $media->media_id))->first();
                } catch (\Throwable) {
                    $clip = null;
                }
            }
            if ($clip) {
                return $clip;
            }
        }

        if (strlen($id) === 24 && ctype_xdigit($id)) {
            try {
                return Clips::where('_id', new ObjectId($id))->first();
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
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
