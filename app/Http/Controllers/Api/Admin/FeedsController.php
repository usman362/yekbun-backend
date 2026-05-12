<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Helpers\Helpers;
use App\Http\Controllers\Controller;
use App\Models\Feed;
use App\Models\FeedComments;
use App\Models\FlaggedUser;
use App\Models\NotificationCenter;
use App\Models\Notifications;
use App\Models\ReportFeeds;
use App\Models\Report;
use App\Models\ReportComments;
use App\Models\User;
use App\Services\BunnyCDNService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FeedsController extends Controller
{
    public function latest(Request $request)
    {
        $perPage = (int) $request->get('per_page', 8);
        $page    = (int) $request->get('page', 1);
        $filter  = $request->get('filter', 'all');

        $query = Feed::whereNull('is_deleted')
            ->orWhere('is_deleted', false);

        if ($filter === 'user') {
            $query->where('user_type', 'friends & family');
        } elseif ($filter === 'channel') {
            $query->where('user_type', 'channel');
        }

        $paginated = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $feeds = $this->transformFeeds(collect($paginated->items()));

        return ResponseHelper::sendResponse([
            'feeds'     => $feeds,
            'total'     => $paginated->total(),
            'page'      => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
        ], 'Latest feeds fetched.');
    }

    public function onHold(Request $request)
    {
        $filter = $request->get('filter', 'all');

        $reportedFeedIds = ReportFeeds::pluck('feed_id')->unique()->toArray();

        $query = Feed::whereIn('_id', $reportedFeedIds);

        if ($filter === 'user') {
            $query->where('user_type', 'friends & family');
        } elseif ($filter === 'channel') {
            $query->where('user_type', 'channel');
        }

        $feeds = $this->transformFeeds($query->orderBy('created_at', 'desc')->get());

        return ResponseHelper::sendResponse($feeds, 'On-hold feeds fetched.');
    }

    public function stats()
    {
        $reportedFeeds    = ReportFeeds::distinct('feed_id')->count();
        $reportedComments = ReportComments::count();

        return ResponseHelper::sendResponse([
            'reported_feeds'    => $reportedFeeds,
            'reported_comments' => $reportedComments,
        ], 'Feed stats fetched.');
    }

    public function reportedFeeds()
    {
        $reportFeedIds = ReportFeeds::pluck('feed_id')->unique()->toArray();
        $feeds = Feed::whereIn('_id', $reportFeedIds)->orderBy('created_at', 'desc')->get();
        return ResponseHelper::sendResponse($this->transformFeeds($feeds), 'Reported feeds fetched.');
    }

    public function reportedComments()
    {
        $reports = ReportComments::orderBy('created_at', 'desc')->get();

        $commentIds = $reports->pluck('comment_id')->unique()->filter()->toArray();
        $comments = FeedComments::whereIn('_id', $commentIds)->get()->keyBy('_id');

        $userIds = $reports->pluck('user_id')
            ->merge($comments->pluck('user_id'))
            ->unique()->filter()->toArray();
        $users = User::whereIn('_id', $userIds)->get()->keyBy('_id');

        $rows = $reports->map(function ($r) use ($comments, $users) {
            $c = $comments->get($r->comment_id);
            $commentUser = $c ? $users->get($c->user_id) : null;
            $reporter = $users->get($r->user_id);
            return [
                'id'               => $r->_id,
                'comment_id'       => $r->comment_id,
                'feed_id'          => $c->feed_id ?? null,
                'comment_text'     => $c->comment ?? '',
                'comment_type'     => $c->comment_type ?? 'normal',
                'comment_user'     => $commentUser ? [
                    'id'       => $commentUser->_id,
                    'username' => $commentUser->username ?? $commentUser->name ?? 'User',
                    'avatar'   => Helpers::mediaUrl($commentUser->image) ?? '',
                ] : null,
                'reporter'         => $reporter ? [
                    'id'       => $reporter->_id,
                    'username' => $reporter->username ?? $reporter->name ?? 'User',
                    'avatar'   => Helpers::mediaUrl($reporter->image) ?? '',
                ] : null,
                'reason'           => $r->reason ?? '',
                'reported_at'      => Carbon::parse($r->created_at)->diffForHumans(),
            ];
        })->values()->toArray();

        return ResponseHelper::sendResponse($rows, 'Reported comments fetched.');
    }

    public function feedComments(Request $request, $id)
    {
        $feed = Feed::find($id);
        if (!$feed) {
            return ResponseHelper::sendResponse(null, 'Feed not found', false, 404);
        }

        $comments = FeedComments::where('feed_id', $id)
            ->whereNull('parent_id')
            ->orderBy('created_at', 'desc')
            ->get();

        $userIds = $comments->pluck('user_id')->unique()->filter()->toArray();
        $users = User::whereIn('_id', $userIds)->get()->keyBy('_id');

        $rows = $comments->map(function ($c) use ($users) {
            $u = $users->get($c->user_id);
            return [
                'id'           => $c->_id,
                'feed_id'      => $c->feed_id,
                'parent_id'    => $c->parent_id,
                'comment_type' => $c->comment_type ?? 'normal',
                'text'         => $c->comment ?? '',
                'audio'        => $c->audio ?? null,
                'image'        => $c->image ?? null,
                'emoji'        => $c->emoji ?? null,
                'username'     => $u->username ?? $u->name ?? 'User',
                'avatar'       => Helpers::mediaUrl($u->image ?? null) ?? '',
                'timestamp'    => Carbon::parse($c->created_at)->diffForHumans(),
            ];
        })->values()->toArray();

        return ResponseHelper::sendResponse($rows, 'Comments fetched.');
    }

    public function deleteComment($id)
    {
        $comment = FeedComments::find($id);
        if (!$comment) {
            return ResponseHelper::sendResponse(null, 'Comment not found', false, 404);
        }

        // Delete audio attachment from BunnyCDN if present
        if (!empty($comment->audio)) {
            $bunny = new BunnyCDNService();
            $cdnBase = rtrim((string) env('BUNNY_CDN_URL'), '/');
            $bunny->delete($this->cdnPath($comment->audio, $cdnBase));
        }

        // Cascade: delete reports + child comments + likes
        ReportComments::where('comment_id', $comment->_id)->delete();
        FeedComments::where('parent_id', $comment->_id)->delete();

        $comment->delete();

        return ResponseHelper::sendResponse(['id' => $id], 'Comment deleted.');
    }

    public function actionFeed(Request $request)
    {
        $request->validate([
            'feed_id'      => 'required|string',
            'action_level' => 'required|in:0,1,2,3',
        ]);

        $feed = Feed::find($request->feed_id);
        if (!$feed) {
            return ResponseHelper::sendResponse(null, 'Feed not found', false, 404);
        }

        $user = User::find($feed->user_id);
        if ($user) {
            $user->is_flagged = 0;
        }

        $level = (string) $request->action_level;
        $notifyMsg = '';

        if ($level !== '0') {
            // Delete media (images, videos) from BunnyCDN
            $bunny = new BunnyCDNService();
            $cdnBase = rtrim((string) env('BUNNY_CDN_URL'), '/');

            if (is_array($feed->images)) {
                foreach ($feed->images as $image) {
                    if (!empty($image['path'])) {
                        $bunny->delete($this->cdnPath($image['path'], $cdnBase));
                    }
                }
            }
            if (is_array($feed->videos)) {
                foreach ($feed->videos as $video) {
                    if (!empty($video['path'])) {
                        $bunny->delete($this->cdnPath($video['path'], $cdnBase));
                    }
                }
            }

            // Delete feed comments + audio attachments
            $comments = FeedComments::where('feed_id', $feed->_id)->get();
            foreach ($comments as $comment) {
                if ($comment->comment_type === 'audio' && !empty($comment->audio)) {
                    $bunny->delete($this->cdnPath($comment->audio, $cdnBase));
                }
                $comment->delete();
            }

            // Clear report records for this feed
            ReportFeeds::where('feed_id', $feed->_id)->delete();

            // Delete the feed
            $feed->delete();

            if ($user) {
                if ($level === '1') {
                    $user->is_flagged = 1;
                    FlaggedUser::create([
                        'user_id'      => $user->_id,
                        'reason'       => $request->input('reason', 'Posted Feed'),
                        'status'       => 0,
                        'action_taken' => 1,
                    ]);
                    $notifyMsg = "Your feed has been deleted and you've been flagged.";
                } elseif ($level === '2') {
                    $user->old_level     = $user->level;
                    $user->old_user_type = $user->user_type;
                    $user->level         = 0;
                    $user->user_type     = 'cultivated';
                    $user->action_type   = 'downgrade';
                    $user->action_duration = $this->resolveDuration($request->input('duration'));
                    $notifyMsg = "Your feed has been deleted and you've been downgraded to Cultivated.";
                } elseif ($level === '3') {
                    $user->status        = 0;
                    $user->action_type   = 'suspend';
                    $user->action_duration = $this->resolveDuration($request->input('duration'));
                    $notifyMsg = "Your feed has been deleted and you've been suspended.";
                }

                // Notification center entry
                if ($notifyMsg) {
                    NotificationCenter::create([
                        'title'       => 'Feed Deleted',
                        'description' => $notifyMsg,
                        'user_id'     => $user->_id,
                        'user_image'  => $user->image ?? null,
                        'type'        => 'feeds',
                        'is_read'     => 0,
                    ]);
                }
            }
        }

        if ($user) {
            $user->save();
        }

        return ResponseHelper::sendResponse([
            'feed_id'      => $request->feed_id,
            'action_level' => $level,
        ], 'Action applied.');
    }

    private function resolveDuration($duration): Carbon
    {
        $d = (string) $duration;
        if ($d === '24h') return Carbon::now()->addDay();
        if ($d === '7d')  return Carbon::now()->addDays(7);
        if ($d === '30d') return Carbon::now()->addDays(30);
        if ($d === 'permanent' || $d === 'perm') return Carbon::now()->addYears(100);
        // Numeric months from old project: 1, 2, 3
        if ($d === '1') return Carbon::now()->addMonth();
        if ($d === '2') return Carbon::now()->addMonths(2);
        if ($d === '3') return Carbon::now()->addMonths(3);
        return Carbon::now()->addDays(15);
    }

    private function cdnPath(string $fullUrl, string $cdnBase): string
    {
        if ($cdnBase !== '' && Str::startsWith($fullUrl, $cdnBase . '/')) {
            return Str::after($fullUrl, $cdnBase . '/');
        }
        return ltrim($fullUrl, '/');
    }

    private function transformFeeds($feeds): array
    {
        $userIds = $feeds->pluck('user_id')->unique()->filter()->toArray();
        $users   = User::whereIn('_id', $userIds)->get()->keyBy('_id');

        $feedIds = $feeds->pluck('_id')->toArray();
        $commentsByFeed = FeedComments::whereIn('feed_id', $feedIds)
            ->where('comment_type', 'normal')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('feed_id');

        $reportCounts = ReportFeeds::whereIn('feed_id', $feedIds)
            ->get()
            ->groupBy('feed_id')
            ->map(fn($g) => $g->count());

        return $feeds->map(function ($feed) use ($users, $commentsByFeed, $reportCounts) {
            $user = $users->get($feed->user_id);

            $media = $this->buildMedia($feed);
            $firstImage = $media[0]['url'] ?? '';

            $feedComments = $commentsByFeed->get($feed->_id, collect());
            $commentUsers = User::whereIn('_id', $feedComments->pluck('user_id')->unique()->toArray())->get()->keyBy('_id');

            $comments = $feedComments->take(3)->map(function ($c) use ($commentUsers) {
                $cu = $commentUsers->get($c->user_id);
                return [
                    'id'        => $c->_id,
                    'username'  => $cu->username ?? $cu->name ?? 'User',
                    'avatar'    => Helpers::mediaUrl($cu->image) ?? '',
                    'text'      => $c->comment ?? '',
                    'timestamp' => Carbon::parse($c->created_at)->diffForHumans(),
                ];
            })->values()->toArray();

            return [
                'id'        => $feed->_id,
                'username'  => $user->username ?? $user->name ?? 'Unknown',
                'avatar'    => Helpers::mediaUrl($user->image) ?? '',
                'timestamp' => Carbon::parse($feed->created_at)->diffForHumans(),
                'image'     => $firstImage,
                'media'     => count($media) > 1 ? $media : [],
                'views'     => (int) ($feed->views_count ?? 0),
                'shares'    => (int) ($feed->shares_count ?? 0),
                'edits'     => 0,
                'reports'   => (int) ($reportCounts->get($feed->_id, 0)),
                'reactions' => (int) ($feed->likes_count ?? 0),
                'flags'     => (int) ($reportCounts->get($feed->_id, 0)),
                'maxFlags'  => 5,
                'location'  => $feed->location ?? null,
                'comments'  => $comments,
            ];
        })->values()->toArray();
    }

    private function buildMedia(Feed $feed): array
    {
        $media = [];

        if (!empty($feed->images) && is_array($feed->images)) {
            foreach ($feed->images as $img) {
                $path = $img['path'] ?? '';
                $media[] = [
                    'id'   => md5($path),
                    'type' => 'image',
                    'url'  => Helpers::mediaUrl($path) ?? '',
                ];
            }
        }

        if (!empty($feed->videos) && is_array($feed->videos)) {
            foreach ($feed->videos as $vid) {
                $path = $vid['path'] ?? '';
                $media[] = [
                    'id'   => md5($path),
                    'type' => 'video',
                    'url'  => Helpers::mediaUrl($path) ?? '',
                ];
            }
        }

        if (empty($media) && $feed->image) {
            $media[] = [
                'id'   => 'main',
                'type' => 'image',
                'url'  => Helpers::mediaUrl($feed->image),
            ];
        }

        return $media;
    }
}
