<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Helpers\Helpers;
use App\Http\Controllers\Controller;
use App\Models\ClipTemplates;
use App\Models\Clips;
use App\Models\ClipsViews;
use App\Models\ClipsLikes;
use App\Models\Report;
use App\Models\User;
use App\Services\BunnyCDNService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserClipsController extends Controller
{
    public function templates()
    {
        $templates = ClipTemplates::orderBy('created_at', 'desc')->get();

        $result = $templates->map(fn($t) => $this->presentTemplate($t))->values();

        return ResponseHelper::sendResponse($result, 'Templates fetched.');
    }

    public function storeTemplate(Request $request)
    {
        $request->validate([
            'title'      => 'required|string|max:255',
            'video_path' => 'required|string',
            'thumbnail'  => 'nullable|string',
        ]);

        $t = new ClipTemplates();
        $this->fillTemplate($t, $request);
        $t->save();

        return ResponseHelper::sendResponse($this->presentTemplate($t), 'Template created.', true, 201);
    }

    public function updateTemplate(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $t = ClipTemplates::find($id);
        if (!$t) {
            return ResponseHelper::sendResponse(null, 'Template not found', false, 404);
        }

        $this->fillTemplate($t, $request, false);
        $t->save();

        return ResponseHelper::sendResponse($this->presentTemplate($t), 'Template updated.');
    }

    public function destroyTemplate($id)
    {
        $t = ClipTemplates::find($id);
        if (!$t) {
            return ResponseHelper::sendResponse(null, 'Template not found', false, 404);
        }

        $bunny = new BunnyCDNService();
        $cdnBase = rtrim((string) env('BUNNY_CDN_URL'), '/');

        foreach (['json_paths', 'video_paths', 'thumbnail'] as $field) {
            if (!empty($t->{$field})) {
                $bunny->delete($this->cdnPath((string) $t->{$field}, $cdnBase));
            }
        }

        // Cascade delete child clips
        Clips::where('template_id', $t->_id)->delete();

        $t->delete();

        return ResponseHelper::sendResponse(['id' => $id], 'Template deleted.');
    }

    public function generateTemplateThumbnails(Request $request)
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
                'images/clip-templates/thumbnails',
                $baseName . "_thumb_{$i}_" . Str::random(6) . '.jpg',
                file_get_contents($localTmp),
                'image/jpeg'
            );
            @unlink($localTmp);
            $thumbnails[] = Helpers::mediaUrl($cdnUrl) ?? $cdnUrl;
        }

        if (count($thumbnails) === 0) {
            return ResponseHelper::sendResponse(null, 'Failed to generate thumbnails', false, 500);
        }

        return ResponseHelper::sendResponse(['thumbnails' => $thumbnails], 'Thumbnails generated.');
    }

    private function fillTemplate(ClipTemplates $t, Request $request, bool $isCreate = true): void
    {
        $t->title = $request->input('title');
        if ($request->has('json_path'))   $t->json_paths = $request->input('json_path');
        if ($request->has('json_name'))   $t->json_name = $request->input('json_name');
        if ($request->has('json_size'))   $t->json_sizes = $request->input('json_size');
        if ($request->has('video_path'))  $t->video_paths = $request->input('video_path');
        if ($request->has('video_name'))  $t->video_name = $request->input('video_name');
        if ($request->has('video_size'))  $t->video_sizes = $request->input('video_size');
        if ($request->filled('thumbnail')) $t->thumbnail = $request->input('thumbnail');
        if ($request->has('educated_price'))   $t->educated_price = $request->input('educated_price');
        if ($request->has('cultivated_price')) $t->cultivated_price = $request->input('cultivated_price');
        if ($request->has('text'))           $t->text = $request->input('text');
        if ($request->has('text_color'))     $t->text_color = $request->input('text_color');
        if ($request->has('text_position'))  $t->text_position = $request->input('text_position');
        if ($request->has('text_font_size')) $t->text_font_size = $request->input('text_font_size');
        if ($request->has('variant'))        $t->variant = $request->input('variant');
    }

    private function presentTemplate(ClipTemplates $t): array
    {
        return [
            'id'              => $t->_id,
            'title'           => $t->title ?? 'Untitled',
            'date'            => $t->created_at ? Carbon::parse($t->created_at)->format('d/m/Y') : '',
            'image'           => $t->thumbnail
                ? (Str::startsWith($t->thumbnail, 'http') ? $t->thumbnail : (Helpers::mediaUrl($t->thumbnail) ?? ''))
                : '',
            'variant'         => $t->variant ?? 'portrait',
            'educatedPrice'   => $t->educated_price ?? 'Free',
            'cultivatedPrice' => $t->cultivated_price ?? 'Free',
            'jsonPath'        => Helpers::mediaUrl($t->json_paths) ?? ($t->json_paths ?? ''),
            'jsonName'        => $t->json_name ?? '',
            'jsonSize'        => $t->json_sizes ?? '',
            'videoPath'       => Helpers::mediaUrl($t->video_paths) ?? ($t->video_paths ?? ''),
            'videoName'       => $t->video_name ?? '',
            'videoSize'       => $t->video_sizes ?? '',
            'text'            => $t->text ?? '',
            'textColor'       => $t->text_color ?? '',
            'textPosition'    => $t->text_position ?? '',
            'textFontSize'    => $t->text_font_size ?? '',
        ];
    }

    private function cdnPath(string $fullUrl, string $cdnBase): string
    {
        if ($cdnBase !== '' && Str::startsWith($fullUrl, $cdnBase . '/')) {
            return Str::after($fullUrl, $cdnBase . '/');
        }
        return ltrim($fullUrl, '/');
    }

    public function clips()
    {
        $clips = Clips::orderBy('created_at', 'desc')->get();

        $userIds = $clips->pluck('user_id')->unique()->filter()->toArray();
        $users = User::whereIn('_id', $userIds)->get()->keyBy('_id');

        $result = $clips->map(function ($c) use ($users) {
            $user = $users->get($c->user_id);
            $thumbnailUrl = Helpers::mediaUrl($c->thumbnail) ?? '';
            $videoUrl     = Helpers::mediaUrl($c->clip) ?? '';

            return [
                'id'        => $c->_id,
                'username'  => $user->username ?? $user->name ?? 'Unknown',
                'avatar'    => Helpers::mediaUrl($user->image) ?? '',
                'timestamp' => Carbon::parse($c->created_at)->diffForHumans(),
                'image'     => $thumbnailUrl,
                // Expose the actual video so the dashboard's gallery viewer can play it.
                // Without this, the clip card only renders the thumbnail and click-to-play breaks.
                'media'     => $videoUrl ? [[
                    'id'        => (string) $c->_id,
                    'type'      => 'video',
                    'url'       => $videoUrl,
                    'thumbnail' => $thumbnailUrl,
                ]] : [],
                'views'     => (int) ClipsViews::where('clip_id', $c->_id)->count(),
                'shares'    => 0,
                'edits'     => 0,
                'reports'   => 0,
                'reactions' => (int) ClipsLikes::where('clip_id', $c->_id)->count(),
                'flags'     => 0,
                'maxFlags'  => 5,
                'location'  => null,
                'comments'  => [],
            ];
        })->values();

        return ResponseHelper::sendResponse($result, 'User clips fetched.');
    }

    public function reported()
    {
        $reportedClipIds = Report::where('report_type', 'clip')
            ->orWhere('reported_type', 'clip')
            ->pluck('reported_post_id')
            ->unique()
            ->filter()
            ->toArray();

        if (empty($reportedClipIds)) {
            return ResponseHelper::sendResponse([], 'No reported clips.');
        }

        $clips = Clips::whereIn('_id', $reportedClipIds)->get();
        $userIds = $clips->pluck('user_id')->unique()->filter()->toArray();
        $users = User::whereIn('_id', $userIds)->get()->keyBy('_id');

        $reportCounts = Report::whereIn('reported_post_id', $reportedClipIds)
            ->get()
            ->groupBy('reported_post_id')
            ->map(fn($g) => $g->count());

        $result = $clips->map(function ($c) use ($users, $reportCounts) {
            $user = $users->get($c->user_id);
            $reports = $reportCounts->get($c->_id, 0);
            $thumbnailUrl = Helpers::mediaUrl($c->thumbnail) ?? '';
            $videoUrl     = Helpers::mediaUrl($c->clip) ?? '';

            return [
                'id'        => $c->_id,
                'username'  => $user->username ?? $user->name ?? 'Unknown',
                'avatar'    => Helpers::mediaUrl($user->image) ?? '',
                'timestamp' => Carbon::parse($c->created_at)->diffForHumans(),
                'image'     => $thumbnailUrl,
                'media'     => $videoUrl ? [[
                    'id'        => (string) $c->_id,
                    'type'      => 'video',
                    'url'       => $videoUrl,
                    'thumbnail' => $thumbnailUrl,
                ]] : [],
                'views'     => (int) ClipsViews::where('clip_id', $c->_id)->count(),
                'shares'    => 0,
                'edits'     => 0,
                'reports'   => (int) $reports,
                'reactions' => (int) ClipsLikes::where('clip_id', $c->_id)->count(),
                'flags'     => (int) $reports,
                'maxFlags'  => 5,
                'location'  => null,
                'comments'  => [],
            ];
        })->values();

        return ResponseHelper::sendResponse($result, 'Reported clips fetched.');
    }
}
