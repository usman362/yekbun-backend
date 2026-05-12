<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Helpers\Helpers;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Services\BunnyCDNService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::orderBy('created_at', 'desc')->get();

        $result = $departments
            ->filter(fn($d) => empty($d->parent_id) || $d->parent_id === '0' || $d->parent_id === 0)
            ->map(function ($d) {
                $subCount = Department::where('parent_id', (string) $d->_id)->count();
                return $this->present($d, $subCount);
            })->values();

        return ResponseHelper::sendResponse($result, 'Departments fetched.');
    }

    public function subDepartments(string $id)
    {
        $subs = Department::where('parent_id', $id)->orderBy('created_at', 'desc')->get();
        $result = $subs->map(fn($d) => $this->present($d, 0));
        return ResponseHelper::sendResponse($result, 'Sub-departments fetched.');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'           => 'required|string|max:255',
            'thumbnail_path' => 'nullable|string',
            'parent_id'      => 'nullable|string',
            'status'         => 'nullable|in:active,inactive',
        ]);

        $d = new Department();
        $d->name           = $request->name;
        $d->thumbnail_path = $request->thumbnail_path;
        $d->parent_id      = $request->parent_id ?? '0';
        $d->status         = $request->input('status', 'active');
        $d->save();

        $subCount = Department::where('parent_id', (string) $d->_id)->count();
        return ResponseHelper::sendResponse($this->present($d, $subCount), 'Department created.', true, 201);
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'name'   => 'required|string|max:255',
            'status' => 'nullable|in:active,inactive',
        ]);

        $d = Department::find($id);
        if (!$d) {
            return ResponseHelper::sendResponse(null, 'Department not found', false, 404);
        }

        $d->name = $request->name;
        if ($request->has('thumbnail_path')) $d->thumbnail_path = $request->thumbnail_path;
        if ($request->has('parent_id'))      $d->parent_id      = $request->parent_id ?? '0';
        if ($request->has('status'))         $d->status         = $request->status;
        $d->save();

        $subCount = Department::where('parent_id', (string) $d->_id)->count();
        return ResponseHelper::sendResponse($this->present($d, $subCount), 'Department updated.');
    }

    public function destroy(string $id)
    {
        $d = Department::find($id);
        if (!$d) {
            return ResponseHelper::sendResponse(null, 'Department not found', false, 404);
        }

        $bunny = new BunnyCDNService();
        $cdnBase = rtrim((string) env('BUNNY_CDN_URL'), '/');

        // Cascade: collect this + all descendants (1-level deep — could recurse if needed)
        $subs = Department::where('parent_id', (string) $d->_id)->get();
        foreach ($subs as $sub) {
            if (!empty($sub->thumbnail_path)) {
                $bunny->delete($this->cdnPath((string) $sub->thumbnail_path, $cdnBase));
            }
            $sub->delete();
        }

        if (!empty($d->thumbnail_path)) {
            $bunny->delete($this->cdnPath((string) $d->thumbnail_path, $cdnBase));
        }
        $d->delete();

        return ResponseHelper::sendResponse(['id' => $id], 'Department deleted.');
    }

    private function present(Department $d, int $subCount): array
    {
        $thumb = $d->thumbnail_path;
        $statusStr = $d->status ?? 'active';
        return [
            'id'          => (string) $d->_id,
            'name'        => $d->name ?? 'Untitled',
            'thumbnail'   => $thumb ? (Helpers::mediaUrl($thumb) ?? $thumb) : '',
            'totalSub'    => $subCount,
            'status'      => $statusStr === 'inactive' ? 'inactive' : 'active',
            'createdDate' => $d->created_at ? Carbon::parse($d->created_at)->format('d/m/Y') : '',
            'parentId'    => $d->parent_id && $d->parent_id !== '0' ? (string) $d->parent_id : null,
        ];
    }

    private function cdnPath(string $fullUrl, string $cdnBase): string
    {
        if ($cdnBase !== '' && Str::startsWith($fullUrl, $cdnBase . '/')) {
            return Str::after($fullUrl, $cdnBase . '/');
        }
        return ltrim($fullUrl, '/');
    }
}
