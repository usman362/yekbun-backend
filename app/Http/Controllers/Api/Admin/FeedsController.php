<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Helpers\Helpers;
use App\Http\Controllers\Controller;
use App\Models\CommentsLike;
use App\Models\Emoji;
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
use Illuminate\Support\Facades\Auth;
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
        try {
            $reports = ReportComments::orderBy('created_at', 'desc')->get();

            // Only query `_id` with strings that are real 24-char hex ObjectIds. A single bad
            // value (empty, legacy numeric id, malformed) makes the Mongo driver throw while
            // casting → the whole endpoint 500s. Filtering keeps it resilient to dirty data.
            $commentIds = $this->onlyObjectIds($reports->pluck('comment_id'));
            $comments = FeedComments::whereIn('_id', $commentIds)->get()->keyBy('_id');

            $userIds = $this->onlyObjectIds(
                $reports->pluck('user_id')->merge($comments->pluck('user_id'))
            );
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
                        'avatar'   => Helpers::profileImageUrl($commentUser->image) ?? '',
                    ] : null,
                    'reporter'         => $reporter ? [
                        'id'       => $reporter->_id,
                        'username' => $reporter->username ?? $reporter->name ?? 'User',
                        'avatar'   => Helpers::profileImageUrl($reporter->image) ?? '',
                    ] : null,
                    'reason'           => $r->reason ?? '',
                    'reported_at'      => $r->created_at ? Carbon::parse($r->created_at)->diffForHumans() : '',
                ];
            })->values()->toArray();

            return ResponseHelper::sendResponse($rows, 'Reported comments fetched.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('reportedComments failed: ' . $e->getMessage());
            return ResponseHelper::sendResponse([], 'Could not load reported comments.', false, 500);
        }
    }

    /** Keep only values that are valid 24-char hex ObjectId strings (safe for `_id` queries). */
    private function onlyObjectIds($collection): array
    {
        return collect($collection)
            ->filter(fn($id) => is_string($id) && preg_match('/^[0-9a-fA-F]{24}$/', $id))
            ->unique()
            ->values()
            ->toArray();
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
                'audio'        => Helpers::mediaUrl($c->audio ?? null),
                'image'        => Helpers::mediaUrl($c->image ?? null),
                'emoji'        => $c->emoji ?? null,
                'username'     => $u->username ?? $u->name ?? 'User',
                'avatar'       => Helpers::profileImageUrl($u->image ?? null) ?? '',
                'timestamp'    => Carbon::parse($c->created_at)->diffForHumans(),
            ];
        })->values()->toArray();

        return ResponseHelper::sendResponse($rows, 'Comments fetched.');
    }

    public function addComment(Request $request, $id)
    {
        $request->validate([
            'comment'   => 'required|string|max:2000',
            'feed_type' => 'nullable|string',
        ]);

        $feed = Feed::find($id);
        if (!$feed) {
            return ResponseHelper::sendResponse(null, 'Feed not found', false, 404);
        }

        $comment = new FeedComments();
        $comment->user_id      = optional(Auth::user())->id;
        $comment->feed_id      = $id;
        $comment->feed_type    = $request->input('feed_type', 'user_feeds');
        $comment->comment_type = 'normal';
        $comment->comment      = $request->comment;
        $comment->status       = 1;
        $comment->save();

        $user = Auth::user();
        return ResponseHelper::sendResponse([
            'id'           => $comment->_id,
            'feed_id'      => $comment->feed_id,
            'parent_id'    => null,
            'comment_type' => 'normal',
            'text'         => $comment->comment,
            'audio'        => null,
            'image'        => null,
            'emoji'        => null,
            'username'     => $user->username ?? $user->name ?? 'Admin',
            'avatar'       => Helpers::profileImageUrl($user->image ?? null) ?? '',
            'timestamp'    => 'just now',
        ], 'Comment posted.', true, 201);
    }

    public function likeComment($id)
    {
        $comment = FeedComments::find($id);
        if (!$comment) {
            return ResponseHelper::sendResponse(null, 'Comment not found', false, 404);
        }

        $userId = optional(Auth::user())->id;
        if (!$userId) {
            return ResponseHelper::sendResponse(null, 'Unauthorized', false, 401);
        }

        $existing = CommentsLike::where('comment_id', $comment->_id)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            $existing->delete();
            $liked = false;
        } else {
            CommentsLike::create([
                'comment_id' => $comment->_id,
                'user_id'    => $userId,
            ]);
            $liked = true;
        }

        $count = CommentsLike::where('comment_id', $comment->_id)->count();
        return ResponseHelper::sendResponse([
            'comment_id' => $comment->_id,
            'liked'      => $liked,
            'count'      => $count,
        ], $liked ? 'Liked' : 'Unliked');
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

    private function formatUserType($type): string
    {
        if (empty($type)) return 'All Users';
        $map = [
            'friends & family' => 'Friends & Family',
            'friends_family'   => 'Friends & Family',
            'channel'          => 'Channel',
            'all'              => 'All Users',
            'public'           => 'Public',
        ];
        $key = strtolower((string) $type);
        return $map[$key] ?? ucwords(str_replace('_', ' ', (string) $type));
    }

    private function cdnPath(string $fullUrl, string $cdnBase): string
    {
        if ($cdnBase !== '' && Str::startsWith($fullUrl, $cdnBase . '/')) {
            return Str::after($fullUrl, $cdnBase . '/');
        }
        return ltrim($fullUrl, '/');
    }

    public function transformFeeds($feeds): array
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

        // Resolve custom pack emoji names → image URLs in one query (mobile stores name in `emoji`).
        $emojiNames = $feeds->pluck('emoji')
            ->map(fn ($e) => is_string($e) ? trim($e) : '')
            ->filter(fn ($e) => $e !== '' && strtolower($e) !== 'null')
            ->unique()
            ->values()
            ->all();
        $emojiByName = empty($emojiNames)
            ? collect()
            : Emoji::whereIn('name', $emojiNames)->get()->keyBy('name');

        return $feeds->map(function ($feed) use ($users, $commentsByFeed, $reportCounts, $emojiByName) {
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
                    'avatar'    => Helpers::profileImageUrl($cu->image) ?? '',
                    'text'      => $c->comment ?? '',
                    'emoji'     => $this->nonEmptyString($c->emoji ?? null) ?: null,
                    'timestamp' => Carbon::parse($c->created_at)->diffForHumans(),
                ];
            })->values()->toArray();

            $description = $this->nonEmptyString($feed->description ?? null);
            $text        = $this->nonEmptyString($feed->text ?? null);
            $emoji       = $this->nonEmptyString($feed->emoji ?? null) ?: null;
            $emojiUrl    = $this->resolveEmojiUrl($emoji, $emojiByName);

            // Uploaded images are often a mobile snapshot that already burns in the caption
            // text. Client-side text overlay is only needed when there is no snapshot media
            // (background-only / text posts) or when text_properties are present without images.
            $hasUploadedMedia = !empty($media) && !collect($media)->contains(fn ($m) => ($m['id'] ?? '') === 'bg');

            return [
                'id'              => $feed->_id,
                'username'        => $user->username ?? $user->name ?? 'Unknown',
                'avatar'          => Helpers::profileImageUrl($user->image) ?? '',
                'timestamp'       => Carbon::parse($feed->created_at)->diffForHumans(),
                'image'           => $firstImage,
                // Always send the media array (even for a single item) so the card knows the
                // type. Previously single-media feeds sent [] and the frontend fell back to
                // treating `image` as an <img> — a single VIDEO feed then rendered its video
                // URL inside an <img> → broken image icon.
                'media'           => $media,
                'views'           => (int) ($feed->views_count ?? 0),
                'shares'          => (int) ($feed->shares_count ?? 0),
                'edits'           => 0,
                'reports'         => (int) ($reportCounts->get($feed->_id, 0)),
                'reactions'       => (int) ($feed->likes_count ?? 0),
                'flags'           => (int) ($reportCounts->get($feed->_id, 0)),
                'maxFlags'        => 5,
                'location'        => $feed->location ?? null,
                'comments'        => $comments,
                // Empty-string description must not block fallback to `text` (?? only skips null).
                'description'     => $description !== '' ? $description : $text,
                'text'            => $text,
                'emoji'           => $emoji,
                'emoji_url'       => $emojiUrl,
                'text_color'      => $this->nonEmptyString($feed->text_color ?? null) ?: null,
                'text_properties' => $this->parseTextProperties($feed->text_properties ?? null),
                'feed_type'       => $feed->feed_type ?? null,
                'overlay_text'    => !$hasUploadedMedia && $text !== '',
                'targetAudience'  => $this->formatUserType($feed->user_type),
            ];
        })->values()->toArray();
    }

    public function buildMedia(Feed $feed): array
    {
        $media = [];

        if (!empty($feed->images) && is_array($feed->images)) {
            foreach ($feed->images as $img) {
                $path = $img['path'] ?? '';
                if ($path === '' || $path === null) {
                    continue;
                }
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
                if ($path === '' || $path === null) {
                    continue;
                }
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

        // Text posts often only have a background template (no uploaded snapshot).
        if (empty($media)) {
            $bg = $feed->background_image ?: ($feed->feed_background_image ?? null);
            if ($this->nonEmptyString($bg)) {
                $media[] = [
                    'id'   => 'bg',
                    'type' => 'image',
                    'url'  => Helpers::mediaUrl($bg) ?? '',
                ];
            }
        }

        return $media;
    }

    private function nonEmptyString($value): string
    {
        if ($value === null) {
            return '';
        }
        $s = trim((string) $value);
        if ($s === '' || strtolower($s) === 'null') {
            return '';
        }
        return $s;
    }

    private function parseTextProperties($raw): ?array
    {
        if ($raw === null || $raw === '' || $raw === 'null') {
            return null;
        }
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : null;
        }
        return null;
    }

    /**
     * Custom pack emoji names resolve to an image URL; unicode / unknown names return null
     * (frontend still renders the raw `emoji` string).
     */
    private function resolveEmojiUrl(?string $emoji, $emojiByName): ?string
    {
        if ($emoji === null || $emoji === '') {
            return null;
        }
        if (Str::startsWith($emoji, ['http://', 'https://'])) {
            return $emoji;
        }
        // Path-like values (legacy)
        if (Str::contains($emoji, '/') || preg_match('/\.(gif|png|webp|jpe?g)$/i', $emoji)) {
            return Helpers::storageUrl($emoji) ?? Helpers::mediaUrl($emoji);
        }
        $row = $emojiByName->get($emoji);
        if ($row && !empty($row->image)) {
            return Helpers::storageUrl($row->image) ?? Helpers::mediaUrl($row->image);
        }
        return null;
    }
}
