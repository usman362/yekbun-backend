<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ResponseHelper;
use App\Helpers\NotificationHelper;
use App\Helpers\CommentPresenter;
use App\Models\AIVideo;
use App\Services\BunnyCDNService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ContentAiVideoAdminController extends Controller
{
    /** GET /admin/content/ai-videos/{id}/comments — real comments for the detail modal. */
    public function comments($id)
    {
        return ResponseHelper::sendResponse(
            CommentPresenter::forFeed($id, 'ai_videos'),
            'AI video comments fetched.'
        );
    }

    /**
     * Notify opted-in users that a new AI video is live. Config-driven: uses the admin's
     * Portal Notifications settings (new_ai_videos toggle + title/description). Failure-safe
     * so a bad token never aborts the upload.
     */
    private function broadcastNewAiVideo(AIVideo $row): void
    {
        NotificationHelper::sendConfiguredBroadcast(
            'new_ai_videos',
            ['[name]' => (string) $row->title],
            'ai_videos'
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'      => 'required|string|max:255',
            'status'     => 'required|in:0,1',
            'video_path' => 'required|string',
            'thumbnail'  => 'required|string',
        ]);

        $row = new AIVideo();
        $row->title             = $request->title;
        $row->source            = $request->source;
        $row->status            = (string) $request->status;
        $row->is_comments       = (int) $request->input('is_comments', 0);
        $row->is_voice_comments = (int) $request->input('is_voice_comments', 0);
        $row->is_emoji          = (int) $request->input('is_emoji', 0);
        $row->user_id           = optional(auth()->user())->id;
        $row->views_count       = 0;
        $row->likes_count       = 0;
        $row->comments_count    = 0;
        $row->shares_count      = 0;

        $row->thumbnail = $request->thumbnail;
        $row->video = [[
            'path'     => $request->video_path,
            'name'     => $request->input('video_name', ''),
            'duration' => $request->input('video_duration', 0),
            'size'     => $request->input('video_size', ''),
        ]];

        $row->save();

        // Only push when the video is actually published (status 1), not saved as draft.
        if ((string) $row->status === '1') {
            $this->broadcastNewAiVideo($row);
        }

        return ResponseHelper::sendResponse($row, 'AI Video created', true, 201);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'title'  => 'required|string|max:255',
            'status' => 'required|in:0,1',
        ]);

        $row = AIVideo::find($id);
        if (!$row) {
            return ResponseHelper::sendResponse(null, 'AI Video not found', false, 404);
        }

        // Remember prior state so we only push when a draft first becomes published.
        $wasPublished = (string) $row->status === '1';

        $row->title             = $request->title;
        $row->source            = $request->source;
        $row->status            = (string) $request->status;
        $row->is_comments       = (int) $request->input('is_comments', 0);
        $row->is_voice_comments = (int) $request->input('is_voice_comments', 0);
        $row->is_emoji          = (int) $request->input('is_emoji', 0);

        if ($request->filled('thumbnail')) {
            $row->thumbnail = $request->thumbnail;
        }
        if ($request->filled('video_path')) {
            $row->video = [[
                'path'     => $request->video_path,
                'name'     => $request->input('video_name', ''),
                'duration' => $request->input('video_duration', 0),
                'size'     => $request->input('video_size', ''),
            ]];
        }

        $row->save();

        // Fire the push when a draft is published for the first time (0 → 1 transition).
        if (!$wasPublished && (string) $row->status === '1') {
            $this->broadcastNewAiVideo($row);
        }

        return ResponseHelper::sendResponse($row, 'AI Video updated');
    }

    public function destroy($id)
    {
        $row = AIVideo::find($id);
        if (!$row) {
            return ResponseHelper::sendResponse(null, 'AI Video not found', false, 404);
        }

        $bunny = new BunnyCDNService();
        $cdnBase = rtrim((string) env('BUNNY_CDN_URL'), '/');

        if (!empty($row->thumbnail)) {
            $bunny->delete($this->cdnPath($row->thumbnail, $cdnBase));
        }
        if (is_array($row->video)) {
            foreach ($row->video as $v) {
                if (!empty($v['path'])) {
                    $bunny->delete($this->cdnPath($v['path'], $cdnBase));
                }
            }
        }

        $row->delete();
        return ResponseHelper::sendResponse(null, 'AI Video deleted');
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
                'images/ai-videos/thumbnails',
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
