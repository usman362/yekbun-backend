<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Helpers\Helpers;
use App\Http\Controllers\Controller;
use App\Models\Ringtone;
use Carbon\Carbon;
use Illuminate\Http\Request;

class RingtoneController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->get('filter', 'all');

        $result = Ringtone::orderBy('created_at', 'desc')->get()
            ->map(fn($r) => $this->present($r))
            ->filter(function ($r) use ($filter) {
                if ($filter === 'active')   return $r['status'] === 'active';
                if ($filter === 'inactive') return $r['status'] === 'inactive';
                return true;
            })
            ->values();

        return ResponseHelper::sendResponse($result, 'Ringtones fetched.');
    }

    public function stats()
    {
        $all = Ringtone::all();
        $active = $all->filter(fn($r) => $this->isActive($r))->count();

        return ResponseHelper::sendResponse([
            'total'     => $all->count(),
            'active'    => $active,
            'inactive'  => $all->count() - $active,
            'downloads' => (int) $all->sum('downloads'),
        ], 'Ringtone stats fetched.');
    }

    /** POST /admin/ringtones — upload an M4A ringtone to API public disk (device-cache asset). */
    public function store(Request $request)
    {
        $request->validate([
            'name'  => 'required|string|max:255',
            'audio' => 'required|file|max:2048', // 2MB
        ]);

        $file = $request->file('audio');
        $sizeMb   = round($file->getSize() / 1024 / 1024, 2);
        $duration = $this->extractDuration($file->getRealPath());
        // System / cacheable assets → server disk (not Bunny CDN).
        $path = Helpers::fileUpload($file, 'ringtones');

        $r = new Ringtone();
        $r->fileName  = $request->name;
        $r->filePath  = $path;
        $r->fileSize  = $sizeMb . ' MB';
        $r->duration  = $duration ?? '0:00';
        $r->is_active = $request->input('status', 'active') === 'active';
        $r->downloads = 0;
        $r->ringType  = (int) $request->input('ringType', 1);
        $r->save();

        return ResponseHelper::sendResponse($this->present($r), 'Ringtone uploaded.', true, 201);
    }

    /** POST /admin/ringtones/{id}/toggle — flip active/inactive. */
    public function toggle($id)
    {
        $r = Ringtone::find($id);
        if (!$r) return ResponseHelper::sendResponse(null, 'Ringtone not found.', false, 404);

        $r->is_active = !$this->isActive($r);
        $r->save();

        return ResponseHelper::sendResponse($this->present($r), 'Status updated.');
    }

    /** DELETE /admin/ringtones/{id} — delete row + audio file (disk and/or legacy CDN). */
    public function destroy($id)
    {
        $r = Ringtone::find($id);
        if (!$r) return ResponseHelper::sendResponse(null, 'Ringtone not found.', false, 404);

        if (!empty($r->filePath)) {
            Helpers::systemAssetDelete((string) $r->filePath);
        }
        $r->delete();

        return ResponseHelper::sendResponse(null, 'Ringtone deleted.');
    }

    // ── helpers ──

    private function isActive($r): bool
    {
        // Explicit is_active wins; fall back to legacy ringType==1 for old rows.
        return $r->is_active ?? ($r->ringType == 1);
    }

    private function present($r): array
    {
        $active = $this->isActive($r);
        return [
            'id'        => (string) $r->_id,
            'name'      => $r->fileName ?? 'Untitled',
            'duration'  => $r->duration ?? '0:00',
            'size'      => $r->fileSize ?? '0 KB',
            'format'    => $this->getFormat($r->filePath ?? $r->fileName ?? ''),
            'status'    => $active ? 'active' : 'inactive',
            'downloads' => (int) ($r->downloads ?? 0),
            'url'       => Helpers::systemAssetUrl($r->filePath) ?? '',
            'createdAt' => $r->created_at ? Carbon::parse($r->created_at)->format('Y-m-d') : '',
        ];
    }

    private function getFormat(string $name): string
    {
        $ext = strtoupper(pathinfo($name, PATHINFO_EXTENSION));
        return $ext ?: 'M4A';
    }

    private function extractDuration(?string $path): ?string
    {
        if (!$path || !file_exists($path)) return null;
        $ffprobe = trim((string) @shell_exec('which ffprobe 2>/dev/null'));
        if (!$ffprobe) return null;
        $out = @shell_exec(escapeshellcmd($ffprobe) . ' -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 ' . escapeshellarg($path) . ' 2>/dev/null');
        $secs = (float) trim((string) $out);
        if ($secs <= 0) return null;
        $m = floor($secs / 60);
        $s = (int) round($secs - ($m * 60));
        if ($s === 60) { $m++; $s = 0; }
        return sprintf('%d:%02d', $m, $s);
    }
}
