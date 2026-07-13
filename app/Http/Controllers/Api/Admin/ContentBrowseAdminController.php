<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Helpers\Helpers;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\AdminActivityController;
use App\Http\Controllers\Api\Admin\FeedsController as AdminFeedsCtrl;
use App\Models\AIVideo;
use App\Models\CommentsLike;
use App\Models\Event;
use App\Models\Feed;
use App\Models\FeedComments;
use App\Models\FeedLikes;
use App\Models\History;
use App\Models\PopFeeds;
use App\Models\ReportFeeds;
use App\Models\User;
use App\Models\Voting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContentBrowseAdminController extends Controller
{
    public function history()
    {
        $rows = History::with('user')->orderBy('created_at', 'desc')->limit(100)->get()
            ->map(fn($h) => $this->presentVideoContent($h))
            ->values();

        return ResponseHelper::sendResponse($rows, 'History loaded.');
    }

    public function aiVideos()
    {
        $rows = AIVideo::with('user')->orderBy('created_at', 'desc')->limit(100)->get()
            ->map(fn($h) => $this->presentVideoContent($h))
            ->values();

        return ResponseHelper::sendResponse($rows, 'AI videos loaded.');
    }

    public function events()
    {
        $rows = Event::orderBy('created_at', 'desc')->limit(100)->get();

        return ResponseHelper::sendResponse($rows, 'Events loaded.');
    }

    public function votings()
    {
        $rows = Voting::where('status', '1')->with('reactions')->orderBy('created_at', 'desc')->get()
            ->map(function ($v) {
                $arr = $v->toArray();
                foreach (['banner', 'image', 'view_banner', 'audio'] as $field) {
                    if (!empty($arr[$field])) {
                        $arr[$field] = Helpers::mediaUrl($arr[$field]);
                    }
                }
                if (is_array($arr['options'] ?? null)) {
                    $arr['options'] = array_map(function ($o) {
                        if (is_array($o) && !empty($o['image'])) {
                            $o['image'] = Helpers::mediaUrl($o['image']);
                        }
                        return $o;
                    }, $arr['options']);
                }
                return $arr;
            })
            ->values();

        return ResponseHelper::sendResponse($rows, 'Votings loaded.');
    }

    /**
     * History / AI Video rows store CDN-relative thumbnail + video[].path —
     * resolve to full URLs so the dashboard can render without a client-side base.
     * Also attach live engagement counts (stored denormalized fields are often stale/0).
     */
    private function presentVideoContent($row): array
    {
        $arr = $row->toArray();
        if (!empty($arr['thumbnail'])) {
            $arr['thumbnail'] = Helpers::mediaUrl($arr['thumbnail']);
        }
        if (is_array($arr['video'] ?? null)) {
            $arr['video'] = array_map(function ($v) {
                if (is_array($v) && !empty($v['path'])) {
                    $v['path'] = Helpers::mediaUrl($v['path']);
                }
                return $v;
            }, $arr['video']);
        }
        if (is_array($arr['gallery'] ?? null)) {
            $arr['gallery'] = array_map(function ($g) {
                if (is_array($g) && !empty($g['path'])) {
                    $g['path'] = Helpers::mediaUrl($g['path']);
                }
                return $g;
            }, $arr['gallery']);
        }

        // Live counts — text comments = comment_type normal; views from FeedViews.
        $arr['comments_count']       = (int) $row->comments()->count();
        $arr['voice_comments_count'] = (int) $row->voice_comments()->count();
        $arr['likes_count']          = (int) $row->likes()->count();
        $arr['views_count']          = (int) $row->views()->count();
        $arr['shares_count']         = (int) $row->shares()->count();

        // Author profile — API disk (images/user), not Bunny CDN.
        $author = $row->relationLoaded('user') ? $row->user : null;
        if (!$author && !empty($row->user_id)) {
            $author = \App\Models\User::find($row->user_id);
        }
        $arr['avatar'] = Helpers::profileImageUrl($author->image ?? null) ?? '';
        $arr['creator'] = trim((string) (($author->name ?? '') . ' ' . ($author->last_name ?? '')))
            ?: (string) ($author->username ?? $arr['title'] ?? 'Admin');
        $arr['user'] = $author ? [
            'id'       => (string) $author->_id,
            'name'     => $author->name ?? '',
            'username' => $author->username ?? '',
            'image'    => Helpers::profileImageUrl($author->image ?? null) ?? '',
        ] : null;

        return $arr;
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
        $feeds = Feed::with('user')->orderBy('created_at', 'desc')->limit(40)->get()
            ->map(function ($f) {
                $arr = $f->toArray();
                if (!empty($arr['image'])) {
                    $arr['image'] = Helpers::mediaUrl($arr['image']);
                }
                if (is_array($arr['images'] ?? null)) {
                    $arr['images'] = array_map(function ($img) {
                        if (is_array($img) && !empty($img['path'])) {
                            $img['path'] = Helpers::mediaUrl($img['path']);
                        } elseif (is_string($img)) {
                            return Helpers::mediaUrl($img);
                        }
                        return $img;
                    }, $arr['images']);
                }
                if (is_array($arr['videos'] ?? null)) {
                    $arr['videos'] = array_map(function ($vid) {
                        if (is_array($vid) && !empty($vid['path'])) {
                            $vid['path'] = Helpers::mediaUrl($vid['path']);
                        }
                        return $vid;
                    }, $arr['videos']);
                }
                if (!empty($arr['user']['image'])) {
                    $arr['user']['image'] = Helpers::profileImageUrl($arr['user']['image']);
                }
                return $arr;
            })
            ->values();

        return ResponseHelper::sendResponse($feeds, 'Feeds preview loaded.');
    }

    public function adminActivity(string $type)
    {
        $typeMap = [
            'system'      => 'System',
            'donation'    => 'Donation',
            'surveys'     => 'Surveys',
            'greetings'   => 'Greetings',
            'user-sos'    => 'SOS',
            'go-live'     => 'Event',
            'agent-feeds' => 'AgentFeed',
        ];

        if ($type === 'public-feed') {
            $rows = PopFeeds::with('user')
                ->where('share_option', 'all-users')
                ->orderBy('created_at', 'desc')
                ->limit(150)
                ->get();
            return ResponseHelper::sendResponse(
                $this->transformFeeds($rows),
                'Public admin activity feeds loaded.'
            );
        }

        if (!isset($typeMap[$type])) {
            return ResponseHelper::sendResponse([], 'Unknown type.', false, 404);
        }

        $rows = PopFeeds::with('user')
            ->where('type', $typeMap[$type])
            ->orderBy('created_at', 'desc')
            ->get();

        return ResponseHelper::sendResponse($this->transformFeeds($rows), ucfirst($type) . ' feeds');
    }

    /**
     * Map raw PopFeeds records and resolve media paths to absolute URLs.
     * Admin Activity uploads go to the API public disk (storeAs … 'public'),
     * NOT Bunny CDN — same as the old Blade admin (`asset('storage/…')`).
     */
    private function transformFeeds($rows): \Illuminate\Support\Collection
    {
        $ids = $rows->pluck('_id')->map(fn($id) => (string) $id)->toArray();

        $likeCounts = FeedLikes::whereIn('feed_id', $ids)
            ->where('feed_type', 'admin_feeds')
            ->get()->groupBy('feed_id')->map(fn($g) => $g->count());

        $commentCounts = FeedComments::whereIn('feed_id', $ids)
            ->where('feed_type', 'admin_feeds')
            ->get()->groupBy('feed_id')->map(fn($g) => $g->count());

        return $rows->map(function ($f) use ($likeCounts, $commentCounts) {
            $arr = $f->toArray();
            foreach (['image', 'audio', 'video', 'icon1', 'icon2', 'icon3'] as $field) {
                if (!empty($arr[$field])) {
                    $arr[$field] = Helpers::storageUrl($arr[$field]) ?? $arr[$field];
                }
            }
            $arr['likes_count']    = (int) $likeCounts->get((string) $f->_id, 0);
            $arr['comments_count'] = (int) $commentCounts->get((string) $f->_id, 0);
            return $arr;
        });
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

    // ─── Admin-activity comments + likes ─────────────────────────────────────
    // Mobile uses the same FeedComments / FeedLikes tables with feed_type='admin_feeds'.
    // These admin-prefixed endpoints expose the same data to the dashboard view modal.

    public function adminActivityComments(string $id)
    {
        $feed = PopFeeds::find($id);
        if (!$feed) {
            return ResponseHelper::sendResponse(null, 'Activity not found', false, 404);
        }

        $comments = FeedComments::where('feed_id', $id)
            ->where('feed_type', 'admin_feeds')
            ->whereNull('parent_id')
            ->orderBy('created_at', 'desc')
            ->get();

        $userIds = $comments->pluck('user_id')->unique()->filter()->toArray();
        $users = User::whereIn('_id', $userIds)->get()->keyBy('_id');

        $commentIds = $comments->pluck('_id')->map(fn($i) => (string) $i)->toArray();
        $likeCounts = CommentsLike::whereIn('comment_id', $commentIds)
            ->get()->groupBy('comment_id')->map(fn($g) => $g->count());

        $authId = optional(Auth::user())->_id;
        $likedByMe = $authId
            ? CommentsLike::whereIn('comment_id', $commentIds)->where('user_id', $authId)->pluck('comment_id')->toArray()
            : [];

        $rows = $comments->map(function ($c) use ($users, $likeCounts, $likedByMe) {
            $u = $users->get($c->user_id);
            $emoji = is_string($c->emoji ?? null) ? trim((string) $c->emoji) : null;
            if ($emoji === '' || strtolower((string) $emoji) === 'null') {
                $emoji = null;
            }
            return [
                'id'         => (string) $c->_id,
                'username'   => $u->username ?? $u->name ?? 'User',
                'avatar'     => Helpers::profileImageUrl($u->image ?? null) ?? '',
                'text'       => $c->comment ?? '',
                'audio'      => Helpers::mediaUrl($c->audio ?? null),
                'image'      => Helpers::mediaUrl($c->image ?? null),
                'emoji'      => $emoji,
                'emoji_url'  => Helpers::emojiUrl($emoji),
                'comment_type' => $c->comment_type ?? 'normal',
                'likes'      => (int) $likeCounts->get((string) $c->_id, 0),
                'liked'      => in_array((string) $c->_id, $likedByMe, true),
                'created_at' => Carbon::parse($c->created_at)->diffForHumans(),
                'replies'    => [],
            ];
        })->values();

        $feedLikeCount = FeedLikes::where('feed_id', $id)->where('feed_type', 'admin_feeds')->count();
        $feedLiked = $authId
            ? FeedLikes::where('feed_id', $id)->where('feed_type', 'admin_feeds')->where('user_id', $authId)->exists()
            : false;

        return ResponseHelper::sendResponse([
            'comments'       => $rows,
            'comments_count' => $rows->count(),
            'likes_count'    => (int) $feedLikeCount,
            'liked'          => $feedLiked,
        ], 'Comments fetched.');
    }

    public function adminActivityAddComment(Request $request, string $id)
    {
        $request->validate(['comment' => 'required|string|max:2000']);

        $feed = PopFeeds::find($id);
        if (!$feed) {
            return ResponseHelper::sendResponse(null, 'Activity not found', false, 404);
        }

        $auth = Auth::user();
        $comment = FeedComments::create([
            'user_id'      => optional($auth)->_id,
            'feed_id'      => $id,
            'feed_type'    => 'admin_feeds',
            'comment_type' => 'normal',
            'comment'      => $request->comment,
            'status'       => 1,
        ]);

        return ResponseHelper::sendResponse([
            'id'         => (string) $comment->_id,
            'username'   => $auth->username ?? $auth->name ?? 'Admin',
            'avatar'     => Helpers::profileImageUrl($auth->image ?? null) ?? '',
            'text'       => $comment->comment,
            'audio'      => null,
            'image'      => null,
            'emoji'      => null,
            'emoji_url'  => null,
            'likes'      => 0,
            'liked'      => false,
            'created_at' => 'just now',
            'replies'    => [],
        ], 'Comment posted.', true, 201);
    }

    public function adminActivityDeleteComment(string $id)
    {
        $comment = FeedComments::find($id);
        if (!$comment) {
            return ResponseHelper::sendResponse(null, 'Comment not found', false, 404);
        }
        CommentsLike::where('comment_id', $id)->delete();
        $comment->delete();
        return ResponseHelper::sendResponse(['id' => $id], 'Comment deleted.');
    }

    public function adminActivityCommentLike(string $id)
    {
        $comment = FeedComments::find($id);
        if (!$comment) {
            return ResponseHelper::sendResponse(null, 'Comment not found', false, 404);
        }

        $userId = optional(Auth::user())->_id;
        if (!$userId) {
            return ResponseHelper::sendResponse(null, 'Unauthorized', false, 401);
        }

        $existing = CommentsLike::where('comment_id', $id)->where('user_id', $userId)->first();
        if ($existing) {
            $existing->delete();
            $liked = false;
        } else {
            CommentsLike::create(['comment_id' => $id, 'user_id' => $userId]);
            $liked = true;
        }

        return ResponseHelper::sendResponse([
            'comment_id' => $id,
            'liked'      => $liked,
            'count'      => CommentsLike::where('comment_id', $id)->count(),
        ], $liked ? 'Liked' : 'Unliked');
    }

    public function adminActivityFeedLike(string $id)
    {
        $feed = PopFeeds::find($id);
        if (!$feed) {
            return ResponseHelper::sendResponse(null, 'Activity not found', false, 404);
        }

        $userId = optional(Auth::user())->_id;
        if (!$userId) {
            return ResponseHelper::sendResponse(null, 'Unauthorized', false, 401);
        }

        $existing = FeedLikes::where('feed_id', $id)
            ->where('feed_type', 'admin_feeds')
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            $existing->delete();
            $liked = false;
        } else {
            FeedLikes::create([
                'feed_id'   => $id,
                'feed_type' => 'admin_feeds',
                'user_id'   => $userId,
            ]);
            $liked = true;
        }

        return ResponseHelper::sendResponse([
            'feed_id' => $id,
            'liked'   => $liked,
            'count'   => FeedLikes::where('feed_id', $id)->where('feed_type', 'admin_feeds')->count(),
        ], $liked ? 'Liked' : 'Unliked');
    }
}
