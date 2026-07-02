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

        $feedsQuery = Feed::with(['user', 'shareUser', 'parentFeed'])
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
            $notification = Notifications::first();
            $notify = AdminNotification::first();
            $description = Auth::user()->name . ' ' . Auth::user()->last_name . ' has posted new Feed.';

            $users = UserFriends::where('friend_id', Auth::id())->where('user_type', $request->user_type)->get();
            if ($users && $notify && $notify->feeds == 1) {
                foreach ($users as $user) {
                    NotificationHelper::sendNotification($user->user_id, 'Feeds Notification', $description);
                    NotificationCenter::create([
                        'title' => 'Feeds Notification',
                        'description' => $description,
                        'user_id' => $user->id,
                        'user_image' => $user->image ?? null,
                        'type' => 'user_feeds',
                        'is_read' => 0,
                    ]);
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
                            'image' => $user->image
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

        if (!empty($request->parent_id)) {
            $parentComment = FeedComments::find($request->parent_id);
            if (isset($parentComment->user_id)) {
                NotificationHelper::sendNotification($parentComment->user_id, 'Feeds Comment', Auth::user()->name . ' Replied to your Comment');
                NotificationCenter::create([
                    'title' => 'Feeds Comment',
                    'description' => Auth::user()->name . ' Replied to your Comment',
                    'user_id' => $parentComment->user_id,
                    'user_image' => $parentComment->user->image ?? null,
                    'type' => 'feed_comments',
                    'is_read' => 0,
                ]);
            }
        }

        $feed = Feed::find($id);
        if ($request->feed_type == 'admin_feeds') $feed = PopFeeds::find($id);
        if ($request->feed_type == 'history') $feed = History::find($id);
        if ($request->feed_type == 'ai_videos') $feed = AIVideo::find($id);

        if ($feed) {
            $feed->comments_count = isset($feed->comments) ? $feed->comments->count() : 0;
            $feed->voice_comments_count = isset($feed->voice_comments) ? $feed->voice_comments->count() : 0;
            $feed->likes_count = isset($feed->likes) ? $feed->likes->count() : 0;
            $feed->views_count = isset($feed->views) ? $feed->views->count() : 0;
            $feed->shares_count = isset($feed->shares) ? $feed->shares->count() : 0;
            $feed->save();
        }

        if ($feed && $request->feed_type !== 'admin_feeds') {
            Helpers::userMedia(
                $feed->_id,
                'exists',
                $feed->comments_count,
                $feed->voice_comments_count,
                $feed->likes_count,
                $feed->views_count,
                $feed->user_id,
                $feed->description,
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
        if ($like) {
            $like->delete();
            $liked = false;
        } else {
            CommentsLike::create(['user_id' => $user->id, 'comment_id' => $id, 'emoji' => $request->emoji]);
            $liked = true;
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
        if ($like) {
            $like->delete();
            $liked = false;
        } else {
            FeedLikes::create(['user_id' => $user->id, 'feed_id' => $id, 'feed_type' => $request->feed_type]);
            $liked = true;
        }

        $likeCount = FeedLikes::where('feed_id', $id)->where('feed_type', $request->feed_type)->count();

        $feed = $this->resolveFeedByType($id, $request->feed_type);
        $counts = $this->syncFeedEngagementCounts($feed, (string) $id, $request->feed_type);

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
