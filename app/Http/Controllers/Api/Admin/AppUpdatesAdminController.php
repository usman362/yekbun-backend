<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\AppUpdate;
use Illuminate\Http\Request;

class AppUpdatesAdminController extends Controller
{
    /** GET /admin/app-updates — full page payload: releases + summary stats. */
    public function index()
    {
        $updates = AppUpdate::orderBy('version_code', 'desc')->get();

        $current   = $updates->firstWhere('is_current', true) ?? $updates->first();
        $published = $updates->where('status', 'published')->sortByDesc('version_code')->first();

        $rows = $updates->map(fn($u) => $this->present($u))->values();

        // "Users by version" distribution — each release's adoption %, biggest first.
        $distribution = $updates
            ->sortByDesc('adoption')
            ->map(fn($u) => ['version' => (string) $u->version, 'percent' => (int) ($u->adoption ?? 0)])
            ->values();

        $stats = [
            'current_version'   => $current->version ?? '—',
            'latest_published'  => $published->version ?? '—',
            'latest_published_at' => $published && $published->release_date ? $published->release_date : null,
            'total_updates'     => $updates->count(),
            'force_update'      => (bool) ($current->force_update ?? false),
            'on_latest_percent' => (int) ($current->adoption ?? 0),
            'downloads_total'   => (int) $updates->sum('downloads'),
            'adoption_percent'  => (int) ($current->adoption ?? 0),
            'forced_count'      => $updates->where('force_update', true)->count(),
        ];

        return ResponseHelper::sendResponse([
            'updates'      => $rows,
            'stats'        => $stats,
            'distribution' => $distribution,
            'current'      => $current ? $this->present($current) : null,
        ], 'App updates fetched.');
    }

    /** POST /admin/app-updates — create a release. */
    public function store(Request $request)
    {
        $request->validate([
            'version'      => 'required|string|max:20',
            'version_code' => 'required|integer',
            'title'        => 'required|string|max:255',
        ]);

        $update = new AppUpdate();
        $this->fill($update, $request);
        // A newly-created release with status published + is_current becomes THE live one.
        if ($request->boolean('is_current')) {
            AppUpdate::where('is_current', true)->update(['is_current' => false]);
            $update->is_current = true;
        }
        $update->published_by = $update->published_by ?: (optional(auth()->user())->name ?? 'Admin');
        $update->save();

        return ResponseHelper::sendResponse($this->present($update), 'Update created.', true, 201);
    }

    /** PUT /admin/app-updates/{id} */
    public function update(Request $request, $id)
    {
        $update = AppUpdate::find($id);
        if (!$update) return ResponseHelper::sendResponse(null, 'Update not found.', false, 404);

        $this->fill($update, $request);
        if ($request->boolean('is_current')) {
            AppUpdate::where('_id', '!=', $id)->where('is_current', true)->update(['is_current' => false]);
            $update->is_current = true;
        }
        $update->save();

        return ResponseHelper::sendResponse($this->present($update), 'Update saved.');
    }

    /** POST /admin/app-updates/{id}/set-current — make this the live release. */
    public function setCurrent($id)
    {
        $update = AppUpdate::find($id);
        if (!$update) return ResponseHelper::sendResponse(null, 'Update not found.', false, 404);

        AppUpdate::where('is_current', true)->update(['is_current' => false]);
        $update->is_current = true;
        $update->status = 'published';
        $update->save();

        return ResponseHelper::sendResponse($this->present($update), 'Set as current release.');
    }

    /** POST /admin/app-updates/{id}/toggle-force */
    public function toggleForce($id)
    {
        $update = AppUpdate::find($id);
        if (!$update) return ResponseHelper::sendResponse(null, 'Update not found.', false, 404);

        $update->force_update = !$update->force_update;
        $update->save();

        return ResponseHelper::sendResponse($this->present($update), 'Force update toggled.');
    }

    /** DELETE /admin/app-updates/{id} */
    public function destroy($id)
    {
        $update = AppUpdate::find($id);
        if (!$update) return ResponseHelper::sendResponse(null, 'Update not found.', false, 404);
        $update->delete();
        return ResponseHelper::sendResponse(null, 'Update deleted.');
    }

    /**
     * GET /api/app/update — PUBLIC endpoint the mobile app polls for the live release.
     * Returns exactly the shape shown in the admin "API Endpoint" preview.
     */
    public function current()
    {
        $u = AppUpdate::where('is_current', true)->first()
            ?? AppUpdate::where('status', 'published')->orderBy('version_code', 'desc')->first();

        if (!$u) {
            return response()->json(['success' => false, 'message' => 'No release configured.'], 404);
        }

        return response()->json([
            'current_version'    => (string) $u->version,
            'version_code'       => (int) $u->version_code,
            'force_update'       => (bool) $u->force_update,
            'title'              => (string) $u->title,
            'description'        => (string) $u->description,
            'release_date'       => (string) ($u->release_date ?? ''),
            'google_play_url'    => (string) ($u->google_play_url ?? ''),
            'closed_testing_url' => (string) ($u->closed_testing_url ?? ''),
        ]);
    }

    // ── helpers ──

    private function fill(AppUpdate $u, Request $request): void
    {
        $u->version            = $request->input('version', $u->version);
        $u->version_code       = (int) $request->input('version_code', $u->version_code);
        $u->title              = $request->input('title', $u->title);
        $u->description        = $request->input('description', $u->description ?? '');
        $u->force_update       = $request->boolean('force_update', (bool) $u->force_update);
        $u->status             = $request->input('status', $u->status ?? 'published');
        $u->release_date       = $request->input('release_date', $u->release_date ?? date('Y-m-d'));
        $u->google_play_url    = $request->input('google_play_url', $u->google_play_url ?? '');
        $u->closed_testing_url = $request->input('closed_testing_url', $u->closed_testing_url ?? '');
        if ($request->filled('published_by')) $u->published_by = $request->input('published_by');
        if ($request->filled('downloads')) $u->downloads = (int) $request->input('downloads');
        if ($request->filled('adoption'))  $u->adoption  = (int) $request->input('adoption');
    }

    private function present(AppUpdate $u): array
    {
        return [
            'id'                 => (string) $u->getKey(),
            'version'            => (string) $u->version,
            'version_code'       => (int) $u->version_code,
            'title'              => (string) $u->title,
            'description'        => (string) ($u->description ?? ''),
            'force_update'       => (bool) $u->force_update,
            'status'             => (string) ($u->status ?? 'published'),
            'is_current'         => (bool) $u->is_current,
            'release_date'       => (string) ($u->release_date ?? ''),
            'published_by'       => (string) ($u->published_by ?? 'Admin'),
            'google_play_url'    => (string) ($u->google_play_url ?? ''),
            'closed_testing_url' => (string) ($u->closed_testing_url ?? ''),
            'downloads'          => (int) ($u->downloads ?? 0),
            'adoption'           => (int) ($u->adoption ?? 0),
        ];
    }
}
