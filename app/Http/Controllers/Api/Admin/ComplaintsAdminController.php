<?php

namespace App\Http\Controllers\Api\Admin;

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
use Illuminate\Support\Str;

/**
 * Admin side of the Kurdistan Complaints system: manage the categories the mobile app
 * offers, and moderate (approve / reject) incoming complaints. A complaint only shows up
 * in the public mobile feed once it is `approved` here.
 */
class ComplaintsAdminController extends Controller
{
    /* ───────────────── Categories ───────────────── */

    public function categoriesIndex()
    {
        $cats = ComplaintCategory::orderBy('sort_order')->orderBy('name')->get()
            ->map(fn ($c) => [
                'id'         => (string) $c->_id,
                'name'       => $c->name ?? '',
                'slug'       => $c->slug ?? '',
                'icon'       => Helpers::mediaUrl($c->icon),
                'sort_order' => (int) ($c->sort_order ?? 0),
                'status'     => $c->status ?? 'active',
            ])->values();
        return ResponseHelper::sendResponse($cats, 'Categories loaded.');
    }

    public function categoriesStore(Request $request)
    {
        $v = $request->validate([
            'name'       => 'required|string|max:120',
            'icon'       => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0',
            'status'     => 'nullable|string|max:20',
        ]);

        $c = ComplaintCategory::create([
            'name'       => $v['name'],
            'slug'       => Str::slug($v['name']),
            'icon'       => $v['icon'] ?? null,
            'sort_order' => $v['sort_order'] ?? 0,
            'status'     => $v['status'] ?? 'active',
        ]);
        return ResponseHelper::sendResponse(['id' => (string) $c->_id], 'Category created.', true, 201);
    }

    public function categoriesUpdate(Request $request, $id)
    {
        $c = ComplaintCategory::find($id);
        if (!$c) return ResponseHelper::sendResponse(null, 'Category not found.', false, 404);

        $v = $request->validate([
            'name'       => 'sometimes|string|max:120',
            'icon'       => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0',
            'status'     => 'nullable|string|max:20',
        ]);
        if (isset($v['name'])) {
            $c->name = $v['name'];
            $c->slug = Str::slug($v['name']);
        }
        if ($request->has('icon'))       $c->icon = $v['icon'];
        if (isset($v['sort_order']))     $c->sort_order = $v['sort_order'];
        if (isset($v['status']))         $c->status = $v['status'];
        $c->save();

        return ResponseHelper::sendResponse(['id' => (string) $c->_id], 'Category updated.');
    }

    public function categoriesDestroy($id)
    {
        $c = ComplaintCategory::find($id);
        if (!$c) return ResponseHelper::sendResponse(null, 'Category not found.', false, 404);
        $c->delete();
        return ResponseHelper::sendResponse(['id' => $id], 'Category deleted.');
    }

    /* ───────────────── Complaints moderation ───────────────── */

    /** GET /admin/complaints?status=pending|approved|rejected (default: all). */
    public function index(Request $request)
    {
        $perPage = min((int) $request->get('per_page', 15), 100);
        $query = Complaint::query();
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        $paginator = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $items = collect($paginator->items())->map(fn ($c) => $this->adminTransform($c))->values();

        return ResponseHelper::sendResponse([
            'items'     => $items,
            'page'      => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total'     => $paginator->total(),
            'counts'    => [
                'pending'  => Complaint::where('status', 'pending')->count(),
                'approved' => Complaint::where('status', 'approved')->count(),
                'rejected' => Complaint::where('status', 'rejected')->count(),
            ],
        ], 'Complaints loaded.');
    }

    /** POST /admin/complaints/{id}/status  body { status: approved|rejected, note? }. */
    public function updateStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required|in:pending,approved,rejected']);

        $c = Complaint::find($id);
        if (!$c) return ResponseHelper::sendResponse(null, 'Complaint not found.', false, 404);

        $c->status        = $request->status;
        $c->review_note   = $request->input('note');
        $c->reviewed_at   = Carbon::now();
        $c->save();

        return ResponseHelper::sendResponse(['id' => (string) $c->_id, 'status' => $c->status], 'Complaint ' . $c->status . '.');
    }

    private function adminTransform(Complaint $c): array
    {
        $u = $c->user_id ? User::find($c->user_id) : null;
        return [
            'id'            => (string) $c->_id,
            'reference_id'  => $c->reference_id ?? '',
            'visibility'    => $c->visibility ?? 'public',
            'category'      => $c->category_name ?? '',
            'title'         => $c->title ?? '',
            'description'   => $c->description ?? '',
            'images'        => array_map(fn ($i) => Helpers::mediaUrl($i), (array) ($c->images ?? [])),
            'location'      => $c->location ?? null,
            'status'        => $c->status ?? 'pending',
            // The reporter is always visible to admins, even for anonymous public complaints.
            'reporter'      => $u ? ['id' => (string) $u->_id, 'name' => $u->name ?? ($u->username ?? 'User')] : null,
            'likes_count'   => FeedLikes::where('feed_id', (string) $c->_id)->where('feed_type', 'complaint')->count(),
            'comments_count'=> FeedComments::where('feed_id', (string) $c->_id)->where('feed_type', 'complaint')->count(),
            'created_at'    => $c->created_at,
            'reviewed_at'   => $c->reviewed_at ?? null,
        ];
    }
}
