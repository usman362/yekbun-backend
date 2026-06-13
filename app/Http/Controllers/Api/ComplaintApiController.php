<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helpers;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\FeedComments;
use App\Models\FeedLikes;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * Kurdistan Complaints — citizen grievance system.
 *
 * Flow: a user submits a complaint (publicly or anonymously) with a category, title,
 * description, photos and a location. It starts as `pending` and only appears in the
 * public feed once the moderation team approves it. Complaints are shown like feeds and
 * REUSE the existing feed like/comment endpoints:
 *
 *   - Like   :  POST /api/feeds/{complaint_id}/likes     body { "feed_type": "complaint" }
 *   - Comment:  POST /api/feeds/{complaint_id}/comments  body { "feed_type": "complaint", ... }
 *   - Get comments: GET /api/feeds/{complaint_id}/comments?feed_type=complaint
 *
 * so the mobile app does not need any new like/comment code.
 *
 * Public routes:   GET  /complaint-categories,  GET /complaints,  GET /complaints/{id}
 * Auth routes:     POST /complaints,  GET /my-complaints,  POST /complaints/upload-image
 */
class ComplaintApiController extends Controller
{
    /** feed_type tag used in the shared FeedLikes / FeedComments tables for complaints. */
    public const FEED_TYPE = 'complaint';

    /** Shown to the user on the "submitted" screen. */
    private const ESTIMATED_REVIEW = '24 - 48 Hours';

    /* ───────────────────────── Categories ───────────────────────── */

    /** GET /api/complaint-categories — the admin-managed category list. */
    public function categories()
    {
        $this->seedDefaultCategoriesIfEmpty();

        $cats = ComplaintCategory::where('status', 'active')
            ->orderBy('sort_order')->orderBy('name')->get()
            ->map(fn ($c) => [
                '_id'        => (string) $c->_id,
                'name'       => $c->name ?? '',
                'slug'       => $c->slug ?? '',
                'icon'       => Helpers::mediaUrl($c->icon),
                'sort_order' => (int) ($c->sort_order ?? 0),
            ])->values();

        return ResponseHelper::sendResponse($cats, 'Complaint categories fetched.');
    }

    /* ───────────────────────── Public feed ───────────────────────── */

    /** GET /api/complaints — approved complaints, newest first (the public feed). */
    public function index(Request $request)
    {
        $perPage = min((int) $request->get('per_page', 15), 50);

        $query = Complaint::where('status', 'approved');
        if ($request->filled('category')) {
            $query->where('category_slug', $request->category);
        }

        $paginator = $query->orderBy('created_at', 'desc')->paginate($perPage);
        $viewerId = Auth::id();

        $items = collect($paginator->items())
            ->map(fn ($c) => $this->transform($c, $viewerId))
            ->values();

        return ResponseHelper::sendResponse([
            'items'        => $items,
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'total'        => $paginator->total(),
        ], 'Complaints fetched.');
    }

    /** GET /api/complaints/{id} — a single approved complaint. */
    public function show($id)
    {
        $c = Complaint::find($id);
        if (!$c || ($c->status ?? '') !== 'approved') {
            return ResponseHelper::sendResponse(null, 'Complaint not found.', false, 404);
        }
        return ResponseHelper::sendResponse($this->transform($c, Auth::id()), 'Complaint fetched.');
    }

    /* ───────────────────────── Submit + own list ───────────────────────── */

    /** POST /api/complaints — submit a new complaint (starts as pending). */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'visibility'       => 'required|in:public,anonymous',
            'category'         => 'required|string|max:120',   // category slug or _id
            'title'            => 'required|string|max:200',
            'description'      => 'required|string|max:5000',
            'images'           => 'nullable|array|max:6',
            'images.*'         => 'string|max:1000',
            'location'         => 'nullable',
            'location.address' => 'nullable|string|max:300',
            'location.lat'     => 'nullable|numeric',
            'location.lng'     => 'nullable|numeric',
        ]);
        if ($validator->fails()) {
            return ResponseHelper::sendResponse(
                ['errors' => $validator->errors()->toArray()],
                $validator->errors()->first() ?: 'Validation failed.',
                false,
                422
            );
        }

        $category = $this->resolveCategory($request->category);
        if (!$category) {
            return ResponseHelper::sendResponse(null, 'Invalid category.', false, 422);
        }

        $location = $request->input('location');
        if (is_string($location)) {
            $location = ['address' => $location];
        }

        $c = new Complaint();
        $c->user_id        = (string) Auth::id();
        $c->reference_id   = Complaint::generateReference();
        $c->visibility     = $request->visibility;
        $c->category_id    = (string) $category->_id;
        $c->category_slug  = $category->slug;
        $c->category_name  = $category->name;
        $c->title          = $request->title;
        $c->description    = $request->description;
        $c->images         = array_values((array) $request->input('images', []));
        $c->location       = $location ?: null;
        $c->status         = 'pending';
        $c->created_at     = Carbon::now();
        $c->save();

        return ResponseHelper::sendResponse([
            'complaint'             => $this->transform($c, Auth::id(), true),
            'estimated_review_time' => self::ESTIMATED_REVIEW,
        ], 'Report sent for review.', true, 201);
    }

    /** GET /api/my-complaints — the caller's own complaints (every status). */
    public function mine(Request $request)
    {
        $perPage = min((int) $request->get('per_page', 15), 50);
        $paginator = Complaint::where('user_id', (string) Auth::id())
            ->orderBy('created_at', 'desc')->paginate($perPage);

        $items = collect($paginator->items())
            ->map(fn ($c) => $this->transform($c, Auth::id(), true))
            ->values();

        return ResponseHelper::sendResponse([
            'items'        => $items,
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'total'        => $paginator->total(),
        ], 'My complaints fetched.');
    }

    /** POST /api/complaints/upload-image — push one photo to the CDN, return its URL. */
    public function uploadImage(Request $request)
    {
        $request->validate(['image' => 'required|file|image|max:8192']);
        $url = Helpers::fileCDNUpload($request->file('image'), 'images/complaints');
        return ResponseHelper::sendResponse(['url' => $url], 'Image uploaded.');
    }

    /* ───────────────────────── helpers ───────────────────────── */

    /** Accept either a category slug or a category _id. */
    private function resolveCategory(string $value): ?ComplaintCategory
    {
        return ComplaintCategory::where('slug', $value)->first()
            ?? ComplaintCategory::where('_id', $value)->first();
    }

    /**
     * Normalise a complaint for the client. `$full` includes status/reference for the owner;
     * the public feed hides the author when the complaint was filed anonymously.
     */
    private function transform(Complaint $c, $viewerId, bool $full = false): array
    {
        $likeCount = FeedLikes::where('feed_id', (string) $c->_id)
            ->where('feed_type', self::FEED_TYPE)->count();
        $commentCount = FeedComments::where('feed_id', (string) $c->_id)
            ->where('feed_type', self::FEED_TYPE)->count();
        $hasLiked = $viewerId
            ? FeedLikes::where('feed_id', (string) $c->_id)
                ->where('feed_type', self::FEED_TYPE)
                ->where('user_id', (string) $viewerId)->exists()
            : false;

        $data = [
            '_id'           => (string) $c->_id,
            'reference_id'  => $c->reference_id ?? '',
            'feed_type'     => self::FEED_TYPE,          // pass this to the like/comment endpoints
            'visibility'    => $c->visibility ?? 'public',
            'category'      => $c->category_slug ?? '',
            'category_name' => $c->category_name ?? '',
            'title'         => $c->title ?? '',
            'description'   => $c->description ?? '',
            'images'        => array_map(fn ($i) => Helpers::mediaUrl($i), (array) ($c->images ?? [])),
            'location'      => $c->location ?? null,
            'author'        => $this->author($c),
            'likes_count'   => $likeCount,
            'comments_count'=> $commentCount,
            'has_liked'     => $hasLiked,
            'created_at'    => $c->created_at,
        ];

        // Owner-only fields (submit response + my-complaints): show the moderation state.
        if ($full) {
            $data['status']      = $c->status ?? 'pending';
            $data['reviewed_at'] = $c->reviewed_at ?? null;
        }

        return $data;
    }

    /** Public author block — hidden for anonymous complaints. */
    private function author(Complaint $c): array
    {
        if (($c->visibility ?? 'public') === 'anonymous') {
            return ['id' => null, 'name' => 'Anonymous', 'avatar' => null];
        }
        $u = User::find($c->user_id);
        return [
            'id'     => $u ? (string) $u->_id : null,
            'name'   => $u->name ?? ($u->username ?? 'User'),
            'avatar' => $u ? Helpers::mediaUrl($u->avatar ?? $u->image ?? null) : null,
        ];
    }

    /** Mirror the categories shown in the mobile design on first use. */
    private function seedDefaultCategoriesIfEmpty(): void
    {
        if (ComplaintCategory::count() > 0) return;

        $defaults = [
            'Roads', 'Advertising', 'Water', 'Healthcare', 'Education',
            'Environment', 'Safety', 'Government', 'Community',
        ];
        foreach ($defaults as $i => $name) {
            ComplaintCategory::create([
                'name'       => $name,
                'slug'       => \Illuminate\Support\Str::slug($name),
                'icon'       => null,
                'sort_order' => $i + 1,
                'status'     => 'active',
            ]);
        }
    }
}
