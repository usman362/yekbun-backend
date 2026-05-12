<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ResponseHelper;
use App\Models\History;
use App\Services\BunnyCDNService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ContentHistoryAdminController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'title'      => 'required|string|max:255',
            'status'     => 'required|in:0,1',
            'video_path' => 'required|string',
            'thumbnail'  => 'required|string',
        ]);

        $history = new History();
        $history->title             = $request->title;
        $history->source            = $request->source;
        $history->status            = (string) $request->status;
        $history->is_comments       = (int) $request->input('is_comments', 0);
        $history->is_voice_comments = (int) $request->input('is_voice_comments', 0);
        $history->is_emoji          = (int) $request->input('is_emoji', 0);
        $history->user_id           = optional(auth()->user())->id;
        $history->views_count       = 0;
        $history->likes_count       = 0;
        $history->comments_count    = 0;
        $history->shares_count      = 0;

        $history->thumbnail = $request->thumbnail;
        $history->video = [[
            'path'     => $request->video_path,
            'name'     => $request->input('video_name', ''),
            'duration' => $request->input('video_duration', 0),
            'size'     => $request->input('video_size', ''),
        ]];

        $history->save();
        return ResponseHelper::sendResponse($history, 'History created', true, 201);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'title'  => 'required|string|max:255',
            'status' => 'required|in:0,1',
        ]);

        $history = History::find($id);
        if (!$history) {
            return ResponseHelper::sendResponse(null, 'History not found', false, 404);
        }

        $history->title             = $request->title;
        $history->source            = $request->source;
        $history->status            = (string) $request->status;
        $history->is_comments       = (int) $request->input('is_comments', 0);
        $history->is_voice_comments = (int) $request->input('is_voice_comments', 0);
        $history->is_emoji          = (int) $request->input('is_emoji', 0);

        if ($request->filled('thumbnail')) {
            $history->thumbnail = $request->thumbnail;
        }
        if ($request->filled('video_path')) {
            $history->video = [[
                'path'     => $request->video_path,
                'name'     => $request->input('video_name', ''),
                'duration' => $request->input('video_duration', 0),
                'size'     => $request->input('video_size', ''),
            ]];
        }

        $history->save();
        return ResponseHelper::sendResponse($history, 'History updated');
    }

    public function destroy($id)
    {
        $history = History::find($id);
        if (!$history) {
            return ResponseHelper::sendResponse(null, 'History not found', false, 404);
        }

        $bunny = new BunnyCDNService();
        $cdnBase = rtrim((string) env('BUNNY_CDN_URL'), '/');

        if (!empty($history->thumbnail)) {
            $bunny->delete($this->cdnPath($history->thumbnail, $cdnBase));
        }
        if (is_array($history->video)) {
            foreach ($history->video as $v) {
                if (!empty($v['path'])) {
                    $bunny->delete($this->cdnPath($v['path'], $cdnBase));
                }
            }
        }

        $history->delete();
        return ResponseHelper::sendResponse(null, 'History deleted');
    }

    public function generateThumbnails(Request $request)
    {
        $request->validate([
            'video_path' => 'required|string',
            'duration'   => 'required|numeric|min:1',
        ]);

        $videoUrl = $request->video_path;
        $duration = (int) $request->duration;

        $ffmpeg = trim((string) @shell_exec('which ffmpeg'));
        if ($ffmpeg === '') {
            return ResponseHelper::sendResponse(null, 'ffmpeg not installed on server', false, 500);
        }

        $timestamps = [
            max(1, (int) round($duration * 0.25)),
            max(1, (int) round($duration * 0.40)),
            max(1, (int) round($duration * 0.75)),
        ];

        $bunny = new BunnyCDNService();
        $baseName = pathinfo(parse_url($videoUrl, PHP_URL_PATH) ?: $videoUrl, PATHINFO_FILENAME);
        $tmpDir = storage_path('app/thumb_tmp');
        if (!is_dir($tmpDir)) @mkdir($tmpDir, 0775, true);

        $thumbnails = [];
        foreach ($timestamps as $i => $sec) {
            $localTmp = $tmpDir . '/' . Str::random(12) . '.jpg';

            $cmd = sprintf(
                '%s -y -ss %d -i %s -frames:v 1 -q:v 2 %s 2>&1',
                escapeshellcmd($ffmpeg),
                $sec,
                escapeshellarg($videoUrl),
                escapeshellarg($localTmp)
            );
            @shell_exec($cmd);

            if (!file_exists($localTmp) || filesize($localTmp) === 0) {
                @unlink($localTmp);
                continue;
            }

            $cdnUrl = $bunny->upload(
                'images/history/thumbnails',
                $baseName . "_thumb_{$i}_" . Str::random(6) . '.jpg',
                file_get_contents($localTmp),
                'image/jpeg'
            );
            @unlink($localTmp);
            $thumbnails[] = $cdnUrl;
        }

        if (count($thumbnails) === 0) {
            return ResponseHelper::sendResponse(null, 'Failed to generate thumbnails', false, 500);
        }

        return ResponseHelper::sendResponse(['thumbnails' => $thumbnails], 'Thumbnails generated');
    }

    private function cdnPath(string $fullUrl, string $cdnBase): string
    {
        if ($cdnBase !== '' && Str::startsWith($fullUrl, $cdnBase . '/')) {
            return Str::after($fullUrl, $cdnBase . '/');
        }
        return ltrim($fullUrl, '/');
    }
}
