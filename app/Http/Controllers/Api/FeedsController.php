<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helpers;
use App\Helpers\NotificationHelper;
use App\Helpers\PermissionHelper;
use App\Helpers\ResponseHelper;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\CommentsLike;
use App\Models\Event;
use App\Models\Feed;
use App\Models\UserImage;
use App\Models\UserVideo;
use App\Models\FeedComments;
use App\Models\FeedLikes;
use App\Models\FeedViews;
use App\Models\FeedShare;
use App\Models\UserFriends;
use App\Models\History;
use App\Models\Media;
use App\Models\News;
use App\Models\Notifications;
use App\Models\AIVideo;
use App\Models\PopFeeds;
use App\Models\NotificationCenter;
use App\Services\BunnyCDNService;
use Carbon\Carbon;
use Exception;
use MongoDB\BSON\ObjectId;
use Illuminate\Support\Facades\Auth;

class FeedsController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 5);
        $cursor  = $request->get('cursor');

        $userId = Auth::id();
        $user = User::with(['friends', 'family'])->find($userId);
        // People who added me as friend / family — I may see the feeds THEY shared with that
        // circle. `friends()`/`family()` key on friend_id = me, so pluck('user_id') = the owners.
        $friendIds = $user->friends->pluck('user_id')->toArray();
        $familyIds = $user->family->pluck('user_id')->toArray();

        // Privacy: author + open audiences + friends/family circles.
        // Open (public/all/channel) must appear for everyone so "any type" posts still
        // land on the timeline; private circles stay restricted. Newest first via _id.
        $feedsQuery = Feed::with(['user', 'shareUser', 'parentFeed'])
            ->where(function ($q) {
                $q->whereNull('is_deleted')
                    ->orWhere('is_deleted', false)
                    ->orWhere('is_deleted', 0);
            })
            ->where(function ($q) use ($userId, $friendIds, $familyIds) {
                $q->where('user_id', $userId)
                    ->orWhereIn('user_type', [
                        'public', 'Public', 'all', 'All', 'channel', 'Channel',
                    ])
                    ->orWhere(fn($sq) => $sq->whereIn('user_id', $friendIds)->whereIn('user_type', ['friends', 'friends & family']))
                    ->orWhere(fn($sq) => $sq->whereIn('user_id', $familyIds)->whereIn('user_type', ['family', 'friends & family']));
            })
            ->orderBy('_id', 'desc');

        if ($cursor) {
            $feedsQuery->where('_id', '<', new ObjectId($cursor));
        }

        $feeds = $feedsQuery->limit($perPage)->get();
        $nextCursor = optional($feeds->last())->_id;

        return ResponseHelper::sendResponse([
            'feeds' => $feeds,
            'pagination' => [
                'per_page' => $perPage,
                'next_cursor' => $nextCursor,
                'has_more' => $feeds->count() === $perPage,
            ]
        ], 'Feeds fetch successfully');
    }

    /**
     * GET /api/feeds/mixed
     *
     * Single timeline: regular user Feed rows + admin PopFeeds (System / Donation /
     * Surveys / Greetings / Event / SOS), merged and sorted newest-first.
     *
     * Privacy matches GET /feeds for user posts and GET /admin-activity/get-feeds
     * for admin pops. Each row includes `source`: "user" | "admin".
     *
     * Query: per_page (default 10, max 50), cursor (ISO8601 of last item created_at).
     */
    public function mixedFeeds(Request $request)
    {
        $perPage = max(1, min((int) $request->get('per_page', 10), 50));
        $cursor  = $request->get('cursor');
        $userId  = Auth::id();

        $user = User::with(['friends', 'family'])->find($userId);
        if (!$user) {
            return ResponseHelper::sendResponse([], 'User not found.', false, 401);
        }

        $friendIds = $user->friends ? $user->friends->pluck('user_id')->filter()->values()->toArray() : [];
        $familyIds = $user->family ? $user->family->pluck('user_id')->filter()->values()->toArray() : [];

        // Over-fetch each side so merge still fills a full page when one side is sparse.
        $fetchLimit = max($perPage * 3, 30);

        // Same privacy rules as GET /feeds (index).
        $userQuery = Feed::with(['user', 'shareUser', 'parentFeed'])
            ->where(function ($q) {
                $q->whereNull('is_deleted')
                    ->orWhere('is_deleted', false)
                    ->orWhere('is_deleted', 0);
            })
            ->where(function ($q) use ($userId, $friendIds, $familyIds) {
                $q->where('user_id', $userId)
                    ->orWhereIn('user_type', [
                        'public', 'Public', 'all', 'All', 'channel', 'Channel',
                    ]);
                if (!empty($friendIds)) {
                    $q->orWhere(fn ($sq) => $sq->whereIn('user_id', $friendIds)->whereIn('user_type', ['friends', 'friends & family']));
                }
                if (!empty($familyIds)) {
                    $q->orWhere(fn ($sq) => $sq->whereIn('user_id', $familyIds)->whereIn('user_type', ['family', 'friends & family']));
                }
            })
            ->orderBy('created_at', 'desc');

        // Same audience rules as GET /admin-activity/get-feeds (getpopFeeds).
        // Do NOT filter by status here — legacy pop_feeds use mixed status shapes
        // (1 / true / "1" / missing) and getpopFeeds never gated on status.
        $userProvince = $user->province ?? null;
        if ($userProvince === 'Bakûr') {
            $userProvince = 'Bakur';
        }
        if ($userProvince === 'Başûr') {
            $userProvince = 'Basur';
        }

        $shareTargets = ['all-users', 'all_users', 'All Users', 'all'];
        $ut = $user->user_type ?? null;
        if (is_string($ut) && $ut !== '') {
            $shareTargets[] = $ut;
            $shareTargets[] = strtolower($ut);
            $shareTargets[] = ucfirst(strtolower($ut));
        }
        $shareTargets = array_values(array_unique($shareTargets));

        $adminQuery = PopFeeds::with(['user', 'sosPopups'])
            ->whereIn('share_option', $shareTargets)
            ->where(function ($q) use ($userProvince) {
                // Missing / empty province = visible to everyone (legacy rows).
                $q->whereNull('allowed_provinces')
                    ->orWhere('allowed_provinces', '');
                if ($userProvince) {
                    $q->orWhere('allowed_provinces', $userProvince);
                }
            })
            ->orderBy('created_at', 'desc');

        if ($cursor) {
            try {
                $cursorAt = Carbon::parse($cursor);
                $userQuery->where('created_at', '<', $cursorAt);
                $adminQuery->where('created_at', '<', $cursorAt);
            } catch (Exception $e) {
                return ResponseHelper::sendResponse([], 'Invalid cursor', false, 422);
            }
        }

        $userRows = $userQuery->limit($fetchLimit)->get()->map(function ($feed) {
            $row = $feed->toArray();
            $row['source'] = 'user';
            $row['feed_kind'] = 'user_feed';
            $row['id'] = (string) ($feed->getKey() ?? ($row['_id'] ?? $row['id'] ?? ''));
            $row['sort_at'] = optional($feed->created_at)->toISOString()
                ?? (string) ($feed->created_at ?? '');
            return $row;
        });

        $adminRows = $adminQuery->limit($fetchLimit)->get()->map(function ($pop) {
            $row = $pop->toArray();
            $row['source'] = 'admin';
            $row['feed_kind'] = 'admin_pop';
            $row['id'] = (string) ($pop->getKey() ?? ($row['_id'] ?? $row['id'] ?? ''));
            $row['sort_at'] = optional($pop->created_at)->toISOString()
                ?? (string) ($pop->created_at ?? '');
            return $row;
        });

        $merged = $userRows->concat($adminRows)
            ->sortByDesc(function ($row) {
                try {
                    return Carbon::parse($row['sort_at'] ?? null)->timestamp;
                } catch (Exception $e) {
                    return 0;
                }
            })
            ->values();

        $page = $merged->take($perPage)->values();
        $last = $page->last();
        $nextCursor = $last['sort_at'] ?? null;
        $hasMore = $merged->count() > $perPage;

        $feeds = $page->map(function ($row) {
            unset($row['sort_at']);
            return $row;
        })->values();

        return ResponseHelper::sendResponse([
            'feeds' => $feeds,
            'pagination' => [
                'per_page'    => $perPage,
                'next_cursor' => $hasMore ? $nextCursor : null,
                'has_more'    => $hasMore,
            ],
            'meta' => [
                'user_count'  => $userRows->count(),
                'admin_count' => $adminRows->count(),
            ],
        ], 'Mixed feeds fetched successfully');
    }

    public function public_index(Request $request)
    {
        if (!empty($request->user_id)) {
            $feeds = Feed::with('user')->where('user_id', $request->user_id)->orderBy('created_at', 'desc')->paginate(5);
        } else {
            $feeds = Feed::with('user')->orderBy('created_at', 'desc')->paginate(5);
        }
        $data = [
            'feeds' => $feeds->items(),
            'pagination' => [
                'page' => $feeds->currentPage(),
                'count' => $feeds->perPage(),
                'totalItems' => $feeds->total(),
                'totalPages' => $feeds->lastPage(),
            ]
        ];
        return ResponseHelper::sendResponse($data, 'Feeds fetch successfully');
    }

    /**
     * GET /api/user-feeds/{user_id}
     * Returns feeds authored by the given user.
     * Own profile → all non-deleted feeds.
     * Other profile → only feeds the authenticated viewer is allowed to see
     * (friends / family / friends & family / public-style audiences).
     */
    public function userFeeds(Request $request, $user_id = null)
    {
        $targetUserId = $user_id ?: $request->get('user_id');
        if (empty($targetUserId)) {
            return ResponseHelper::sendResponse([], 'User Id is Required', false, 422);
        }

        // Invalid ObjectId would 500 on where('_id') / find — validate first.
        if (!is_string($targetUserId) || !preg_match('/^[0-9a-fA-F]{24}$/', $targetUserId)) {
            return ResponseHelper::sendResponse([], 'Invalid User Id', false, 422);
        }

        $target = User::find($targetUserId);
        if (!$target) {
            return ResponseHelper::sendResponse([], 'User not found', false, 404);
        }

        $authId = Auth::id();
        $perPage = max(1, min((int) $request->get('per_page', 5), 50));
        $cursor = $request->get('cursor');

        $feedsQuery = Feed::with(['user', 'shareUser', 'parentFeed'])
            ->where('user_id', $targetUserId)
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', false);
            });

        // Privacy when viewing someone else's profile.
        if ((string) $authId !== (string) $targetUserId) {
            $viewer = User::with(['friends', 'family'])->find($authId);
            $friendIds = $viewer ? $viewer->friends->pluck('user_id')->map(fn ($id) => (string) $id)->all() : [];
            $familyIds = $viewer ? $viewer->family->pluck('user_id')->map(fn ($id) => (string) $id)->all() : [];
            $isFriend = in_array((string) $targetUserId, $friendIds, true);
            $isFamily = in_array((string) $targetUserId, $familyIds, true);

            $feedsQuery->where(function ($q) use ($isFriend, $isFamily) {
                // Open / channel audiences (case variants seen in legacy data).
                $q->whereIn('user_type', [
                    'public', 'Public', 'all', 'All', 'channel', 'Channel',
                ]);
                if ($isFriend && $isFamily) {
                    $q->orWhereIn('user_type', ['friends', 'family', 'friends & family']);
                } elseif ($isFriend) {
                    $q->orWhereIn('user_type', ['friends', 'friends & family']);
                } elseif ($isFamily) {
                    $q->orWhereIn('user_type', ['family', 'friends & family']);
                }
            });
        }

        $feedsQuery->orderBy('_id', 'desc');

        if ($cursor) {
            try {
                $feedsQuery->where('_id', '<', new ObjectId($cursor));
            } catch (Exception $e) {
                return ResponseHelper::sendResponse([], 'Invalid cursor', false, 422);
            }
        }

        $feeds = $feedsQuery->limit($perPage)->get();
        $nextCursor = optional($feeds->last())->_id;

        return ResponseHelper::sendResponse([
            'feeds' => $feeds,
            'user' => [
                'id' => (string) $target->_id,
                'username' => $target->username ?? $target->name ?? null,
                'name' => $target->name ?? null,
                'image' => Helpers::profileImageUrl($target->image ?? null),
            ],
            'pagination' => [
                'per_page' => $perPage,
                'next_cursor' => $nextCursor,
                'has_more' => $feeds->count() === $perPage,
            ],
        ], 'User feeds fetched successfully');
    }

    public function news()
    {
        $news = News::orderBy('created_at', 'desc')->get();
        return response()->json(['news' => $news, 'success' => true], 200);
    }

    public function events()
    {
        $events = Event::orderBy('created_at', 'desc')->get();
        return response()->json(['events' => $events, 'success' => true], 200);
    }

    public function store_news(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
            'user_type' => 'required',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'image_type' => 'nullable|integer|min:0',
            'start_date' => 'required|date|after:today',
            'end_date' => 'nullable|date|after:start_date',
            'status' => 'required',
        ]);

        $news = new News();
        $news->title = $request->title;
        $news->description = $request->description;
        $news->user_type = $request->user_type;
        $news->image = $request->image;
        $news->image_type = (int)$request->image_type;
        $news->start_date = $request->start_date;
        $news->end_date = $request->end_date;
        $news->comments = (int)$request->comments ?? 0;
        $news->voice_comments = (int)$request->voice_comments ?? 0;
        $news->share = (int)$request->share ?? 0;
        $news->emotion = (int)$request->emotion ?? 0;
        $news->status = $request->status;

        if ($news->save()) {
            return response()->json(['message' => 'News Feed has been created Successfully', 'news' => $news, 'success' => true], 201);
        }
        return response()->json(['message' => 'Something went Wrong!', 'success' => false], 403);
    }

    public function store_event(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
            'start_date' => 'required|date|after:today',
            'start_time' => 'required',
            'end_time' => 'required|after:start_time',
        ]);

        $event = new Event();
        $event->title = $request->title;
        $event->description = $request->description;
        $event->start_date = $request->start_date;
        $event->start_time = $request->start_time;
        $event->end_time = $request->end_time;

        if ($event->save()) {
            return response()->json(['message' => 'Event has been created Successfully', 'event' => $event, 'success' => true], 201);
        }
        return response()->json(['message' => 'Something went Wrong!', 'success' => false], 403);
    }

    public function store(Request $request)
    {
        $allowRequest = PermissionHelper::checkPermission(Auth::user()->level, 'feed_allow_feeds');
        if ($allowRequest !== true) {
            return ResponseHelper::sendResponse([], 'You are not allowed to share feeds.', false, 409);
        }

        $allowVideoRequest = PermissionHelper::checkPermission(Auth::user()->level, 'feed_video_cam');
        if ($request->isCam == 1 && $allowVideoRequest !== true) {
            return ResponseHelper::sendResponse([], 'You are not allowed to share Cam feeds.', false, 409);
        }

        if ($request->hasFile('videos')) {
            $allowVideoRequest = PermissionHelper::checkPermission(Auth::user()->level, 'feed_share_videos');
            if ($allowVideoRequest !== true) {
                return ResponseHelper::sendResponse([], 'You are not Allowed to Share Videos.', false, 409);
            }
        }

        if ($request->hasFile('images')) {
            $allowImageRequest = PermissionHelper::checkPermission(Auth::user()->level, 'feed_share_images');
            if ($allowImageRequest !== true) {
                return ResponseHelper::sendResponse([], 'You are not Allowed to Share Images.', false, 409);
            }
        }

        $request->validate([
            'user_type' => 'required',
            'feed_type' => 'required',
        ]);

        $feeds = new Feed();
        if ($request->feed_type == 'text') {
            $feeds->background_image = $request->background_image;
            $feeds->text_color = $request->text_color;
            $feeds->emoji = $request->emoji;
        }

        $feeds->grid_style = $request->grid_style;
        $feeds->description = $request->description;
        $feeds->text = $request->text;
        $feeds->text_properties = $request->text_properties;
        $feeds->user_type = $request->user_type;
        $feeds->feed_type = $request->feed_type;
        $feeds->user_id = Auth::id();

        $images = [];
        $videos = [];

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $storedImage = Helpers::fileCDNUpload($image, 'images/user_feeds');
                $images[] = [
                    'path' => $storedImage,
                    'name' => $image->getClientOriginalName(),
                    'size' => 0,
                ];
                UserImage::create(['user_id' => Auth::id(), 'image' => $storedImage]);
            }
            $feeds->images = $images;
        }

        if ($request->hasFile('videos')) {
            foreach ($request->file('videos') as $video) {
                $storedVideo = Helpers::fileCDNUpload($video, 'videos/user_feeds');
                $videos[] = [
                    'path' => $storedVideo,
                    'name' => $video->getClientOriginalName(),
                    'size' => 0,
                ];
                UserVideo::create(['user_id' => Auth::id(), 'video' => $storedVideo]);
            }
            $feeds->videos = $videos;
        }

        $feeds->save();
        $feed = Feed::with('user')->find($feeds->id);

        if (!empty($feed->videos)) {
            foreach ($feed->videos as $video) {
                Helpers::userMedia(
                    $feed->_id,
                    $video->path ?? $video['path'],
                    $feed->comments_count,
                    $feed->voice_comments_count,
                    $feed->likes_count,
                    $feed->views_count,
                    $feed->user_id,
                    $feed->description,
                    null,
                    'user_feeds'
                );
            }
        }

        if ($feeds->save()) {
            $notify = AdminNotification::first();
            $actor = Auth::user();
            $actorName = NotificationHelper::actorName($actor);
            $description = $actorName . ' published a new post.';

            // Notify ONLY the circle the feed was shared with — the exact set that can now see
            // it (feed index visibility). Recipients = friend_id rows where user_id = poster.
            $types = match ((string) $request->user_type) {
                'friends'          => ['friends'],
                'family'           => ['family'],
                'friends & family' => ['friends', 'family'],
                default            => [],
            };
            if (!empty($types) && $notify && $notify->feeds == 1) {
                // Notification delivery must NEVER block feed creation.
                try {
                    $recipientIds = UserFriends::where('user_id', Auth::id())
                        ->whereIn('user_type', $types)
                        ->pluck('friend_id')
                        ->map(fn($id) => (string) $id)
                        ->filter(fn($id) => preg_match('/^[0-9a-fA-F]{24}$/', $id))
                        ->unique()
                        ->values()
                        ->toArray();
                    if (!empty($recipientIds)) {
                        $users = User::whereIn('_id', $recipientIds)
                            ->whereIn('info_banner', ['banner', 'alert'])
                            ->get();
                        $feedId = (string) ($feeds->id ?? $feeds->_id);
                        foreach ($users as $user) {
                            NotificationHelper::notifyUser(
                                $user->id,
                                $actorName,
                                $description,
                                'user_feeds',
                                Auth::id(),
                                $actor->image ?? null,
                                'new_feed:' . $feedId . ':' . $user->id,
                                $feedId,
                                'user_feeds'
                            );
                        }
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Feed notification failed: ' . $e->getMessage());
                }
            }
            return response()->json(['message' => 'Feed has been created Successfully', 'feed' => $feed, 'success' => true], 201);
        }
        return response()->json(['message' => 'Something went Wrong!', 'success' => false], 403);
    }

    public function share(Request $request)
    {
        $allowRequest = PermissionHelper::checkPermission(Auth::user()->level, 'feed_allow_feeds');
        if ($allowRequest !== true) {
            return ResponseHelper::sendResponse([], 'You are not allowed to share feeds.', false, 409);
        }

        $feed = Feed::find($request->feed_id);
        $newFeed = $feed->replicate();
        $newFeed->share_by = Auth::id();
        $newFeed->parent_id = $request->feed_id;
        $newFeed->share_text = $request->share_text;
        $newFeed->is_deleted = 0;
        $newFeed->created_at = now();
        $newFeed->updated_at = now();
        $newFeed->save();

        $feed->comments_count = $feed->comments->count();
        $feed->voice_comments_count = $feed->voice_comments->count();
        $feed->likes_count = $feed->likes->count();
        $feed->views_count = $feed->views->count();
        $feed->shares_count = $feed->shares->count();
        $feed->save();

        Helpers::userMedia(
            $feed->_id,
            'exists',
            $feed->comments_count,
            $feed->voice_comments_count,
            $feed->likes_count,
            $feed->views_count,
            $feed->user_id,
            $feed->description,
            $feed->text_properties,
            'user_feeds'
        );

        $sharedFeed = Feed::with(['user', 'shareUser'])->find($newFeed->_id);
        return response()->json(['message' => 'Feed has been shared Successfully', 'feed' => $sharedFeed, 'success' => true], 201);
    }

    public function search_user(Request $request)
    {
        $users = User::whereHas('feeds')->with('country')->where('_id', '!=', Auth::id())
            ->where('status', 1)
            ->where(function ($query) use ($request) {
                $query->where('name', 'LIKE', '%' . $request->search . '%')
                    ->orWhere('last_name', 'LIKE', '%' . $request->search . '%');
            })->get();
        return ResponseHelper::sendResponse($users, 'Users has been Fetched Successfully!');
    }

    public function change_permission(Request $request, $id)
    {
        $feed = Feed::find($id);
        $feed->user_type = $request->user_type;
        $feed->save();
        return ResponseHelper::sendResponse($feed, 'Feed has been Updated Successfully!');
    }

    public function delete($id)
    {
        $feed = Feed::find($id);
        if (!empty($feed->images)) {
            foreach ($feed->images as $image) {
                $bunny = new BunnyCDNService();
                $bunny->delete($image['path']);
            }
        }
        if (!empty($feed->videos)) {
            foreach ($feed->videos as $video) {
                $bunny = new BunnyCDNService();
                $bunny->delete($video['path']);
            }
        }
        $shared = Feed::where('parent_id', $id)->get();
        foreach ($shared as $share) {
            $share->is_deleted = 1;
            $share->save();
        }
        $feed->delete();
        $media = Media::where('media_id', $id)->first();
        if ($media) {
            $media->delete();
        }
        return ResponseHelper::sendResponse([], 'Feed has been Deleted Successfully!');
    }

    public function get_statistics($id)
    {
        $feed = Feed::with(['user', 'views.user', 'likes.user', 'comments.user', 'voice_comments.user', 'shares.user'])->find($id);

        if (!$feed) {
            return ResponseHelper::sendResponse([], 'Feed not found!', false, 404);
        }

        $genderStats = ['male' => 0, 'female' => 0];
        $ageGroups = [
            '18-24' => ['male' => 0, 'female' => 0],
            '25-30' => ['male' => 0, 'female' => 0],
            '31-35' => ['male' => 0, 'female' => 0],
            '36-40' => ['male' => 0, 'female' => 0]
        ];
        $totalUsers = 0;
        $userIds = [];

        $processUser = function ($user) use (&$genderStats, &$ageGroups, &$userIds, &$totalUsers) {
            if ($user && !in_array($user->_id, $userIds)) {
                $userIds[] = $user->_id;
                $totalUsers++;
                if ($user->gender === 'male') $genderStats['male']++;
                elseif ($user->gender === 'female') $genderStats['female']++;

                if (!empty($user->dob)) {
                    $age = Carbon::parse($user->dob)->age;
                    $gender = $user->gender;
                    if ($gender === 'male' || $gender === 'female') {
                        if ($age >= 18 && $age <= 24) $ageGroups['18-24'][$gender]++;
                        elseif ($age >= 25 && $age <= 30) $ageGroups['25-30'][$gender]++;
                        elseif ($age >= 31 && $age <= 35) $ageGroups['31-35'][$gender]++;
                        elseif ($age >= 36 && $age <= 40) $ageGroups['36-40'][$gender]++;
                    }
                }
            }
        };

        $processUser($feed->user);

        $sections = ['likes', 'shares', 'comments', 'voice_comments', 'views'];
        $sectionDetails = [];

        foreach ($sections as $section) {
            $items = $feed->$section;
            $sectionUserImages = [];
            foreach ($items as $item) {
                $user = $item->user ?? null;
                if ($user) {
                    $processUser($user);
                    if (!empty($user->image) && count($sectionUserImages) < 10) {
                        $sectionUserImages[] = [
                            'user_id' => $user->_id,
                            'name' => $user->name ?? '',
                            'image' => Helpers::cdnRelativePath($user->image) ?? '',
                        ];
                    }
                }
            }
            $sectionDetails[$section] = [
                'count' => count($items),
                'users' => $sectionUserImages
            ];
        }

        $formattedAgeGroups = [];
        foreach ($ageGroups as $range => $counts) {
            $formattedAgeGroups[] = [
                'age' => $range,
                'male' => $counts['male'],
                'female' => $counts['female']
            ];
        }

        return ResponseHelper::sendResponse([
            'gender_stats' => $genderStats,
            'age_group' => $formattedAgeGroups,
            'total_stats' => $sectionDetails
        ], 'Statistics has been Fetched Successfully!');
    }

    public function getComments(Request $request, $id)
    {
        try {
            $feedType = $request->feed_type;
            $comments = FeedComments::with(['reports', 'child_comments' => function ($q) {
                $q->with(['reports', 'child_comments' => function ($q) {
                    $q->with(['reports', 'user' => function ($q) {
                        $q->select(['name', 'last_name', 'email', 'dob', 'image', 'username']);
                    }])->with('likes')->with('liked');
                }, 'user' => function ($q) {
                    $q->select(['name', 'last_name', 'email', 'dob', 'image', 'username']);
                }])->with('likes')->with('liked');
            }, 'user' => function ($q) {
                $q->select(['name', 'last_name', 'email', 'dob', 'image', 'username']);
            }])->with('likes')->with('liked')
                ->where('feed_id', $id)->where('feed_type', $feedType)->where('parent_id', null)->get();

            $user = User::select('name', 'last_name', 'email', 'dob', 'image', 'username')->find(Auth::id());

            if ($feedType == 'admin_feeds') {
                $feed = PopFeeds::with(['user' => fn($q) => $q->select(['name', 'last_name', 'email', 'dob', 'image', 'username'])])->find($id);
            } elseif ($feedType == 'history') {
                $feed = History::with(['user' => fn($q) => $q->select(['name', 'last_name', 'email', 'dob', 'image', 'username'])])->find($id);
            } elseif ($feedType == 'ai_videos') {
                $feed = AIVideo::with(['user' => fn($q) => $q->select(['name', 'last_name', 'email', 'dob', 'image', 'username'])])->find($id);
            } else {
                $feed = Feed::with(['user' => fn($q) => $q->select(['name', 'last_name', 'email', 'dob', 'image', 'username'])])->find($id);
            }

            $like = FeedLikes::where('user_id', $user->id)->where('feed_id', $id)->where('feed_type', $feedType)->first();
            $liked = $like ? true : false;
            $likeCount = FeedLikes::where('feed_id', $id)->where('feed_type', $feedType)->count();
            $commentCount = FeedComments::where('feed_id', $id)->where('feed_type', $feedType)->count();

            $data = [
                'comments' => $comments,
                'comments_count' => $commentCount,
                'feed' => $feed,
                'liked' => $liked,
                'like_count' => $likeCount,
                'user' => $user
            ];

            return ResponseHelper::sendResponse($data, 'Comment Fetch successfully');
        } catch (Exception $e) {
            return ResponseHelper::sendResponse([], 'Failed to fetch Comment!', false, 403);
        }
    }

    public function storeComments(Request $request, $id)
    {
        $allowRequest = PermissionHelper::checkPermission(Auth::user()->level, 'feed_text_comments');
        $allowHistoryRequest = PermissionHelper::checkPermission(Auth::user()->level, 'history_text_comments');

        if ($request->feed_type == 'user_feeds' && $allowRequest !== true) {
            return ResponseHelper::sendResponse([], 'You are not Allowed to Text Comments.', false, 409);
        }

        if ($request->feed_type == 'history' && $allowHistoryRequest !== true) {
            return ResponseHelper::sendResponse([], 'You are not Allowed to Text Comments.', false, 409);
        }

        $request->validate([
            'comment' => 'nullable|string',
            'feed_type' => 'required',
            'emoji' => 'nullable|string',
        ]);

        $image = null;
        if ($request->file('image')) {
            $image = Helpers::fileCDNUpload($request->image, 'images/comments/' . $request->feed_type);
        }

        $audio = null;
        // A comment carrying a voice note is ALWAYS an "audio" comment — the Feed model's
        // voice_comments relation filters on comment_type='audio', so we can't rely on the
        // client to send the right type. If audio is present, force it; otherwise keep what
        // the client sent (defaulting to 'normal').
        $commentType = $request->comment_type ?? 'normal';
        if ($request->file('audio')) {
            $allowVoice = PermissionHelper::checkPermission(Auth::user()->level, 'feed_voice_comments');
            $allowHistoryVoice = PermissionHelper::checkPermission(Auth::user()->level, 'history_voice_comments');

            if ($request->feed_type == 'user_feeds' && $allowVoice !== true) {
                return ResponseHelper::sendResponse([], 'You are not Allowed to Voice Comments.', false, 409);
            }
            if ($request->feed_type == 'history' && $allowHistoryVoice !== true) {
                return ResponseHelper::sendResponse([], 'You are not Allowed to Voice Comments.', false, 409);
            }
            $audio = Helpers::fileCDNUpload($request->audio, 'audios/comments/' . $request->feed_type);
            $commentType = 'audio';
        }

        if ($image == null && $audio == null && empty($request->comment) && empty($request->emoji)) {
            return ResponseHelper::sendResponse([], 'Select Content Before Comment!', false, 403);
        }

        $comment = FeedComments::create([
            'user_id' => Auth::id(),
            'feed_id' => $id,
            'feed_type' => $request->feed_type,
            'comment' => $request->comment,
            'comment_type' => $commentType,
            'parent_id' => (empty($request->parent_id) || $request->parent_id === 'null') ? null : $request->parent_id,
            'audio' => $audio,
            'emoji' => $request->emoji,
            'image' => $image,
            'status' => 1
        ]);

        $comments = FeedComments::with(['child_comments' => function ($q) {
            $q->with(['child_comments' => function ($q) {
                $q->with(['user' => fn($q) => $q->select(['name', 'last_name', 'email', 'dob', 'image', 'username'])])->with('likes')->with('liked');
            }, 'user' => fn($q) => $q->select(['name', 'last_name', 'email', 'dob', 'image', 'username'])])->with('likes')->with('liked');
        }, 'user' => fn($q) => $q->select(['name', 'last_name', 'email', 'dob', 'image', 'username'])])
            ->with('likes')->with('liked')
            ->where('feed_id', $id)->where('parent_id', null)->where('feed_type', $request->feed_type)->get();

        $user = User::select('name', 'last_name', 'email', 'dob', 'image', 'username')->find(Auth::id());
        $commentCount = FeedComments::where('feed_id', $id)->where('feed_type', $request->feed_type)->count();
        $like = FeedLikes::where('user_id', $user->id)->where('feed_id', $id)->where('feed_type', $request->feed_type)->first();
        $likeCount = FeedLikes::where('feed_id', $id)->where('feed_type', $request->feed_type)->count();

        $data = [
            'comments' => $comments,
            'comments_count' => $commentCount,
            'liked' => $like ? true : false,
            'like_count' => $likeCount,
            'user' => $user
        ];

        $actor = Auth::user();
        $actorName = NotificationHelper::actorName($actor);
        $commentId = (string) ($comment->id ?? $comment->_id);
        $isReply = !empty($comment->parent_id);
        $isVoice = ($commentType === 'audio');

        if ($isReply) {
            // PDF #8 — reply notifies original comment author (skip self-reply).
            $parentComment = FeedComments::find($request->parent_id);
            if ($parentComment && isset($parentComment->user_id)) {
                NotificationHelper::notifyUser(
                    $parentComment->user_id,
                    $actorName,
                    $actorName . ' replied to your comment.',
                    'feed_comments',
                    Auth::id(),
                    $actor->image ?? null,
                    'comment_reply:' . $commentId,
                    $commentId,
                    'feed_comments'
                );
            }
        } else {
            // PDF #6 / #7 — top-level text or voice comment notifies post owner.
            $ownerFeed = $this->resolveFeedByType((string) $id, (string) $request->feed_type);
            $ownerId = $ownerFeed->user_id ?? null;
            if ($ownerId) {
                $body = $isVoice
                    ? ($actorName . ' left a voice comment on your post.')
                    : ($actorName . ' commented on your post.');
                NotificationHelper::notifyUser(
                    $ownerId,
                    $actorName,
                    $body,
                    $isVoice ? 'feed_voice_comments' : 'feed_comments',
                    Auth::id(),
                    $actor->image ?? null,
                    ($isVoice ? 'voice_comment:' : 'comment:') . $commentId,
                    $commentId,
                    'feed_comments'
                );
            }
        }

        $feed = $this->resolveFeedByType((string) $id, (string) $request->feed_type);

        if ($feed) {
            $counts = $this->syncFeedEngagementCounts($feed, (string) $id, (string) $request->feed_type);
            $feed->comments_count = $counts['comments_count'];
            $feed->voice_comments_count = $counts['voice_comments_count'];
            $feed->likes_count = $counts['likes_count'];
            $feed->views_count = $counts['views_count'];
            $feed->shares_count = $counts['shares_count'] ?? ($feed->shares_count ?? 0);
        }

        if ($feed && $request->feed_type !== 'admin_feeds') {
            Helpers::userMedia(
                $feed->_id,
                'exists',
                $feed->comments_count ?? 0,
                $feed->voice_comments_count ?? 0,
                $feed->likes_count ?? 0,
                $feed->views_count ?? 0,
                $feed->user_id ?? null,
                $feed->description ?? $feed->text ?? $feed->title ?? null,
                null,
                $request->feed_type
            );
        }

        return ResponseHelper::sendResponse($data, 'Comment has been successfully sent');
    }

    public function editComments(Request $request, $id)
    {
        $request->validate([
            'comment' => 'nullable|string',
            'emoji' => 'nullable|string',
        ]);

        $comment = FeedComments::find($id);

        $image = null;
        if ($request->file('image')) {
            $image = Helpers::fileCDNUpload($request->image, 'images/comments/' . ($comment->feed_type ?? 'user_feeds'));
        }

        $audio = null;
        if ($request->file('audio')) {
            $audio = Helpers::fileCDNUpload($request->audio, 'audios/comments/' . ($comment->feed_type ?? 'user_feeds'));
        }

        if ($image == null && $audio == null && empty($request->comment) && empty($request->emoji)) {
            return ResponseHelper::sendResponse([], 'Select Content Before Comment!', false, 403);
        }

        $comment->comment = $request->comment;
        if ($request->file('audio')) { $comment->audio = $audio; $comment->comment_type = 'audio'; }
        if ($request->emoji) $comment->emoji = $request->emoji;
        if ($request->file('image')) $comment->image = $image;
        $comment->save();

        $comments = FeedComments::with(['child_comments' => function ($q) {
            $q->with(['child_comments' => function ($q) {
                $q->with(['user' => fn($q) => $q->select(['name', 'last_name', 'email', 'dob', 'image', 'username'])])->with('likes')->with('liked');
            }, 'user' => fn($q) => $q->select(['name', 'last_name', 'email', 'dob', 'image', 'username'])])->with('likes')->with('liked');
        }, 'user' => fn($q) => $q->select(['name', 'last_name', 'email', 'dob', 'image', 'username'])])
            ->with('likes')->with('liked')
            ->where('feed_id', $comment->feed_id)->where('parent_id', null)->where('feed_type', $comment->feed_type)->get();

        $user = User::select('name', 'last_name', 'email', 'dob', 'image', 'username')->find(Auth::id());
        $commentCount = FeedComments::where('feed_id', $id)->where('feed_type', $comment->feed_type)->count();
        $like = FeedLikes::where('user_id', $user->id)->where('feed_id', $comment->feed_id)->where('feed_type', $comment->feed_type)->first();
        $likeCount = FeedLikes::where('feed_id', $comment->feed_id)->where('feed_type', $comment->feed_type)->count();

        return ResponseHelper::sendResponse([
            'comments' => $comments,
            'comments_count' => $commentCount,
            'liked' => $like ? true : false,
            'like_count' => $likeCount,
            'user' => $user
        ], 'Comment has been successfully sent');
    }

    public function commentLike(Request $request, $id)
    {
        $request->validate(['emoji' => 'nullable|string']);
        $user = Auth::user();
        if (!$user) return ResponseHelper::sendResponse([], 'User not authenticated!', false, 403);

        $like = CommentsLike::where('user_id', $user->id)->where('comment_id', $id)->first();
        $likeRow = null;
        if ($like) {
            $like->delete();
            $liked = false;
        } else {
            $likeRow = CommentsLike::create(['user_id' => $user->id, 'comment_id' => $id, 'emoji' => $request->emoji]);
            $liked = true;
        }

        // Notify comment author after a new like (skip self-like / unlike).
        if ($liked && $likeRow) {
            $comment = FeedComments::find($id);
            if ($comment && isset($comment->user_id)) {
                $actorName = NotificationHelper::actorName($user);
                NotificationHelper::notifyUser(
                    $comment->user_id,
                    $actorName,
                    $actorName . ' liked your comment.',
                    'comment_likes',
                    $user->id,
                    $user->image ?? null,
                    'comment_like:' . (string) ($likeRow->id ?? $likeRow->_id),
                    (string) $id,
                    'feed_comments'
                );
            }
        }

        return ResponseHelper::sendResponse([
            'liked' => $liked,
            'like_count' => CommentsLike::where('comment_id', $id)->count()
        ], 'Like has been successfully Saved');
    }

    public function commentDelete($id)
    {
        try {
            $comment = FeedComments::find($id);
            $childs = FeedComments::where('parent_id', $id)->get();
            if ($childs) {
                foreach ($childs as $child) {
                    if ($child->audio) { $bunny = new BunnyCDNService(); $bunny->delete($child->audio); }
                    if ($child->image) { $bunny = new BunnyCDNService(); $bunny->delete($child->image); }
                    $child->delete();
                }
            }
            if ($comment->audio) { $bunny = new BunnyCDNService(); $bunny->delete($comment->audio); }
            if ($comment->image) { $bunny = new BunnyCDNService(); $bunny->delete($comment->image); }
            $comment->delete();
            return ResponseHelper::sendResponse([], 'Comment has been Deleted!');
        } catch (Exception $e) {
            return ResponseHelper::sendResponse([], 'Failed to Delete Comment', false, 403);
        }
    }

    public function feedLike(Request $request, $id)
    {
        $allowRequest = PermissionHelper::checkPermission(Auth::user()->level, 'feed_like_button');
        $allowHistoryRequest = PermissionHelper::checkPermission(Auth::user()->level, 'history_like_button');

        if ($request->feed_type == 'user_feeds' && $allowRequest !== true) {
            return ResponseHelper::sendResponse([], 'You are not Allowed to Like Feed.', false, 409);
        }
        if ($request->feed_type == 'history' && $allowHistoryRequest !== true) {
            return ResponseHelper::sendResponse([], 'You are not Allowed to Like Feed.', false, 409);
        }

        $request->validate(['feed_type' => 'required']);
        $user = Auth::user();
        if (!$user) return ResponseHelper::sendResponse([], 'User not authenticated!', false, 403);

        $like = FeedLikes::where('user_id', $user->id)->where('feed_id', $id)->where('feed_type', $request->feed_type)->first();
        $likeRow = null;
        if ($like) {
            $like->delete();
            $liked = false;
        } else {
            $likeRow = FeedLikes::create(['user_id' => $user->id, 'feed_id' => $id, 'feed_type' => $request->feed_type]);
            $liked = true;
        }

        $likeCount = FeedLikes::where('feed_id', $id)->where('feed_type', $request->feed_type)->count();

        $feed = $this->resolveFeedByType($id, $request->feed_type);
        $counts = $this->syncFeedEngagementCounts($feed, (string) $id, $request->feed_type);

        // PDF #5 — notify post owner after a new like; never on own content / unlike.
        if ($liked && $feed && $likeRow) {
            $ownerId = $feed->user_id ?? null;
            $actorName = NotificationHelper::actorName($user);
            NotificationHelper::notifyUser(
                $ownerId,
                $actorName,
                $actorName . ' liked your post.',
                'feed_likes',
                $user->id,
                $user->image ?? null,
                'like:' . (string) ($likeRow->id ?? $likeRow->_id),
                (string) $id,
                $request->feed_type
            );
        }

        if ($feed && $request->feed_type !== 'admin_feeds') {
            Helpers::userMedia(
                $feed->_id,
                'exists',
                $counts['comments_count'],
                $counts['voice_comments_count'],
                $counts['likes_count'],
                $counts['views_count'],
                $feed->user_id ?? null,
                $feed->description ?? $feed->title ?? null,
                null,
                $request->feed_type
            );
        }

        return ResponseHelper::sendResponse(array_merge(['liked' => $liked, 'like_count' => $likeCount], $counts), 'Like has been successfully Saved');
    }

    public function getfeedLike(Request $request, $id)
    {
        $allowRequest = PermissionHelper::checkPermission(Auth::user()->level, 'feed_like_button');
        $allowHistoryRequest = PermissionHelper::checkPermission(Auth::user()->level, 'history_like_button');

        if ($request->feed_type == 'user_feeds' && $allowRequest !== true) {
            return ResponseHelper::sendResponse([], 'You are not Allowed to Like Feed.', false, 409);
        }
        if ($request->feed_type == 'history' && $allowHistoryRequest !== true) {
            return ResponseHelper::sendResponse([], 'You are not Allowed to Like Feed.', false, 409);
        }

        $request->validate(['feed_type' => 'required']);
        $user = Auth::user();
        if (!$user) return ResponseHelper::sendResponse([], 'User not authenticated!', false, 403);

        $like = FeedLikes::where('user_id', $user->id)->where('feed_id', $id)->where('feed_type', $request->feed_type)->first();
        if ($like) {
            $like->delete();
            $liked = false;
        } else {
            FeedLikes::create(['user_id' => $user->id, 'feed_id' => $id, 'feed_type' => $request->feed_type]);
            $liked = true;
        }

        $likeCount = FeedLikes::where('feed_id', $id)->where('feed_type', $request->feed_type)->count();

        $feed = $this->resolveFeedByType($id, $request->feed_type);
        $counts = $this->syncFeedEngagementCounts($feed, $id, $request->feed_type);

        if ($feed && $request->feed_type !== 'admin_feeds') {
            Helpers::userMedia(
                $feed->_id,
                'exists',
                $counts['comments_count'],
                $counts['voice_comments_count'],
                $counts['likes_count'],
                $counts['views_count'],
                $feed->user_id ?? null,
                $feed->description ?? $feed->title ?? null,
                null,
                $request->feed_type
            );
        }

        return ResponseHelper::sendResponse(array_merge($counts, [
            'liked' => $liked,
            'like_count' => $likeCount,
        ]), 'Like has been successfully Saved');
    }

    /**
     * Resolve the backing document for a feed id (same rules as getComments / storeComments).
     */
    private function resolveFeedByType(string $id, string $feedType)
    {
        if ($feedType === 'admin_feeds') {
            return PopFeeds::find($id);
        }
        if ($feedType === 'history') {
            return History::find($id);
        }
        if ($feedType === 'ai_videos') {
            return AIVideo::find($id);
        }
        if ($feedType === 'clips') {
            return \App\Models\Clips::find($id);
        }

        return Feed::find($id);
    }

    /**
     * Refresh cached counters on the feed document when present; otherwise derive from engagement tables.
     *
     * @return array{comments_count:int,voice_comments_count:int,likes_count:int,views_count:int,shares_count:int}
     */
    private function syncFeedEngagementCounts($feed, string $feedId, string $feedType): array
    {
        if (!$feed) {
            return $this->engagementCountsFromQueries($feedId, $feedType);
        }

        $feed->comments_count = method_exists($feed, 'comments')
            ? $feed->comments()->count()
            : FeedComments::where('feed_id', $feedId)->where('feed_type', $feedType)->count();

        $feed->voice_comments_count = method_exists($feed, 'voice_comments')
            ? $feed->voice_comments()->count()
            : FeedComments::where('feed_id', $feedId)->where('feed_type', $feedType)->where('comment_type', 'audio')->count();

        $feed->likes_count = method_exists($feed, 'likes')
            ? $feed->likes()->count()
            : FeedLikes::where('feed_id', $feedId)->where('feed_type', $feedType)->count();

        $feed->views_count = method_exists($feed, 'views')
            ? $feed->views()->count()
            : FeedViews::where('feed_id', $feedId)->count();

        $feed->shares_count = method_exists($feed, 'shares')
            ? $feed->shares()->count()
            : FeedShare::where('feed_id', $feedId)->count();

        $feed->save();

        return [
            'comments_count' => (int) $feed->comments_count,
            'voice_comments_count' => (int) $feed->voice_comments_count,
            'likes_count' => (int) $feed->likes_count,
            'views_count' => (int) $feed->views_count,
            'shares_count' => (int) $feed->shares_count,
        ];
    }

    /**
     * @return array{comments_count:int,voice_comments_count:int,likes_count:int,views_count:int,shares_count:int}
     */
    private function engagementCountsFromQueries(string $feedId, string $feedType): array
    {
        return [
            'comments_count' => FeedComments::where('feed_id', $feedId)->where('feed_type', $feedType)->count(),
            'voice_comments_count' => FeedComments::where('feed_id', $feedId)->where('feed_type', $feedType)->where('comment_type', 'audio')->count(),
            'likes_count' => FeedLikes::where('feed_id', $feedId)->where('feed_type', $feedType)->count(),
            'views_count' => FeedViews::where('feed_id', $feedId)->count(),
            'shares_count' => FeedShare::where('feed_id', $feedId)->count(),
        ];
    }
}
