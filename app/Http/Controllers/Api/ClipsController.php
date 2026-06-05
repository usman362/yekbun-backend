<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helpers;
use App\Helpers\NotificationHelper;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Clips;
use App\Models\ClipsViews;
use App\Models\ClipsLikes;
use App\Models\ClipTemplates;
use App\Models\Media;
use App\Models\NotificationCenter;
use App\Models\User;
use App\Models\UserVideo;
use App\Services\BunnyCDNService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ClipsController extends Controller
{
    public function index(Request $request)
    {
        $userId = Auth::id();
        $user = User::with(['friends', 'family'])->find($userId);
        $friendIds = $user->friends->pluck('user_id')->toArray();
        $familyIds = $user->family->pluck('user_id')->toArray();

        $query = Clips::with(['template', 'user', 'likes' => fn($q) => $q->with('user'), 'views' => fn($q) => $q->with('user')])
            ->where(function ($query) use ($userId, $friendIds, $familyIds) {
                $query->where('user_id', $userId)
                    ->orWhere(fn($q) => $q->whereIn('user_id', $friendIds)->whereIn('share_with', ['friends', 'friends & family']))
                    ->orWhere(fn($q) => $q->whereIn('user_id', $familyIds)->whereIn('share_with', ['family', 'friends & family']));
            })->orderBy('created_at', 'desc');

        $videos = !empty($request->clip_id) ? $query->find($request->clip_id) : $query->get();
        return ResponseHelper::sendResponse($videos, 'Clips has been Fetch Successfully!');
    }

    public function getMyClips(Request $request)
    {
        $query = Clips::with(['template', 'user', 'likes' => fn($q) => $q->with('user'), 'views' => fn($q) => $q->with('user')])
            ->where('user_id', Auth::id())->orderBy('created_at', 'desc');

        $videos = !empty($request->clip_id) ? $query->find($request->clip_id) : $query->get();
        return ResponseHelper::sendResponse($videos, 'Clips has been Fetch Successfully!');
    }

    public function get_templates()
    {
        return ResponseHelper::sendResponse(ClipTemplates::all(), 'Clips Templates has been Fetch Successfully!');
    }

    public function store_clips(Request $request)
    {
        // Loose validation by design — `mimetypes` is detected from file CONTENT, not the
        // Content-Type header, and React Native's multipart uploads frequently mis-report
        // (.m4a coming through as audio/mp4, .mp4 sometimes as application/octet-stream, etc.).
        // We just check the field is a real uploaded file and within our size budget. Hard
        // mime/extension restrictions live in the ffmpeg step — if the bytes aren't actually a
        // playable container, ffmpeg will reject them with a clear error we surface as 500.
        //
        // Size cap: 200MB (must stay ≤ post_max_size on the server — see deploy notes).
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'video'        => 'required|file|max:204800',
            'audio'        => 'nullable|file|max:51200',
            'thumbnail'    => 'nullable|file|max:10240',
            'template_id'  => 'nullable|string',
            'share_with'   => 'nullable|string',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();

            // Log the failing payload so we can debug "why did the mobile get a 422" without
            // asking the user to reproduce it. Only the FIRST error is included in `message`
            // so any mobile UI that only renders `response.message` still shows something useful
            // (e.g. "The video field is required." instead of a generic "Validation error.").
            \Illuminate\Support\Facades\Log::warning('store_clips validation failed', [
                'user_id'      => Auth::id(),
                'errors'       => $errors->toArray(),
                'has_video'    => $request->hasFile('video'),
                'has_audio'    => $request->hasFile('audio'),
                'has_thumb'    => $request->hasFile('thumbnail'),
                'video_size'   => $request->hasFile('video') ? $request->file('video')->getSize() : null,
                'video_err'    => $request->hasFile('video') ? $request->file('video')->getError() : null,
                'audio_size'   => $request->hasFile('audio') ? $request->file('audio')->getSize() : null,
                'all_keys'     => array_keys($request->all()),
            ]);

            $firstError = $errors->first() ?: 'Validation failed.';
            return ResponseHelper::sendResponse(
                ['errors' => $errors->toArray()],
                $firstError,
                false,
                422
            );
        }

        // Locate ffmpeg up front — if it's missing we want to fail loudly NOW, not after a
        // silent exec() that swallows the failure and saves a clip with no video URL.
        $ffmpegBin = trim((string) @shell_exec('which ffmpeg'));
        if ($ffmpegBin === '') {
            return ResponseHelper::sendResponse(null, 'Server is missing ffmpeg — please contact support.', false, 500);
        }

        // Resolve the audio source. Three legal inputs:
        //   1. Uploaded file under `audio` (preferred)
        //   2. Path string under `audio` that lives in `storage/app/public/...`
        //   3. Nothing → fall back to the silent placeholder
        $audioPath = public_path('audios/empty.mp3');
        if ($request->hasFile('audio')) {
            $audioPath = $request->file('audio')->getPathname();
        } elseif (!empty($request->audio) && is_string($request->audio) && Storage::exists('public/' . $request->audio)) {
            $audioPath = storage_path('app/public/' . $request->audio);
        }

        $videoFile  = $request->file('video');
        $videoLocal = $videoFile->getPathname();

        // Unique output filename — without this, two users uploading clips named `video.mp4`
        // race on the same path and one overwrites the other mid-encode → broken clips.
        $tmpDir = storage_path('app/public/videos');
        if (!is_dir($tmpDir)) @mkdir($tmpDir, 0775, true);
        $outputPath = $tmpDir . '/clip_' . uniqid('', true) . '.mp4';

        $videoVolume = max(0.0, min(2.0, (float) ($request->video_volume ?? 0.8)));
        $audioVolume = max(0.0, min(2.0, (float) ($request->audio_volume ?? 0.5)));

        // Escape every interpolated path/value so spaces, quotes, or shell metacharacters in
        // uploaded filenames can't break the command (or worse, be exploited).
        $command = sprintf(
            '%s -y -i %s -i %s -filter_complex %s -map 0:v -map "[a]" -shortest %s 2>&1',
            escapeshellcmd($ffmpegBin),
            escapeshellarg($videoLocal),
            escapeshellarg($audioPath),
            escapeshellarg(sprintf(
                '[1:a]volume=%.3f[a1];[0:a]volume=%.3f[a2];[a1][a2]amix=inputs=2:duration=first[a]',
                $audioVolume,
                $videoVolume
            )),
            escapeshellarg($outputPath)
        );

        exec($command, $output, $return_var);

        // Hard fail if ffmpeg didn't produce a usable file. Previously the controller carried
        // on, saved a clip row with empty `clip` URL, and pushed a "new clip!" notification —
        // users got pings for clips that played nothing.
        if ($return_var !== 0 || !file_exists($outputPath) || filesize($outputPath) === 0) {
            if (file_exists($outputPath)) @unlink($outputPath);
            \Illuminate\Support\Facades\Log::error('store_clips ffmpeg failure', [
                'user_id'  => Auth::id(),
                'return'   => $return_var,
                'tail'     => array_slice($output, -10), // last 10 lines of ffmpeg stderr
            ]);
            return ResponseHelper::sendResponse(
                null,
                'Video processing failed. Please try again with a different clip.',
                false,
                500
            );
        }

        // Now that we have a real output file, upload to CDN. Wrap so a transient CDN error
        // doesn't leak the temp file or leave a half-saved Clips row.
        try {
            $clipUrl = Helpers::fileCDNUpload2(new \Illuminate\Http\File($outputPath), 'clips');
        } catch (\Throwable $e) {
            @unlink($outputPath);
            \Illuminate\Support\Facades\Log::error('store_clips CDN upload failed', [
                'user_id' => Auth::id(),
                'error'   => $e->getMessage(),
            ]);
            return ResponseHelper::sendResponse(null, 'Could not upload the clip. Please try again.', false, 502);
        } finally {
            if (file_exists($outputPath)) @unlink($outputPath);
        }

        // All side-effects above succeeded — now create the DB row + post-create work.
        $clip = new Clips();
        $clip->template_id = $request->template_id;
        $clip->thumbnail = $request->hasFile('thumbnail') ? Helpers::fileCDNUpload($request->thumbnail, 'images/thumbnails/clips') : '';
        $clip->emoji = $request->emoji;
        $clip->share_with = $request->share_with;
        $clip->user_id = Auth::id();
        $clip->text = $request->text;
        $clip->text_properties = $request->text_properties;
        $clip->clip = $clipUrl;
        $clip->likes_count = 0;
        $clip->views_count = 0;
        $clip->comments_count = 0;
        $clip->voice_comments_count = 0;
        $clip->save();

        Helpers::userMedia(
            $clip->_id,
            $clip->clip,
            $clip->comments_count,
            $clip->voice_comments_count,
            $clip->likes_count,
            $clip->views_count,
            $clip->user_id,
            $clip->text,
            $request->text_properties,
            'clips'
        );

        $description = Auth::user()->name . ' ' . Auth::user()->last_name . ' has posted new Clip.';
        $users = User::where('_id', '!=', Auth::id())->whereNotNull('fcm_token')->whereIn('info_banner', ['banner', 'alert'])->get();
        foreach ($users as $user) {
            NotificationHelper::sendNotification($user->id, 'Clips Notification', $description);
            NotificationCenter::create([
                'title' => 'Clips Notification', 'description' => $description,
                'user_id' => $user->id, 'user_image' => $user->image ?? null, 'type' => 'clips', 'is_read' => 0,
            ]);
        }
        return ResponseHelper::sendResponse($clip, 'Clip has been Created Successfully!');
    }

    public function store_templates(Request $request)
    {
        $clip = new ClipTemplates();
        $clip->title = $request->title;
        $clip->json_paths = $request->json_paths[0] ?? '';
        $clip->json_sizes = $request->json_sizes[0] ?? '';
        $clip->json_name = $request->json_name[0] ?? '';
        $clip->save();
        if ($request->hasFile('json_file')) $clip->json_file = Helpers::fileCDNUpload($request->json_file, 'files/clip_template_json');
        if ($request->hasFile('video')) $clip->video = Helpers::fileCDNUpload($request->video, 'videos/clip_template');
        return ResponseHelper::sendResponse($clip, 'Template has been Created Successfully!');
    }

    public function destroy($id)
    {
        $clip = Clips::find($id);
        if (!$clip) return ResponseHelper::sendResponse([], 'Clip Not Found', false, 401);
        if (isset($clip->thumbnail)) { $bunny = new BunnyCDNService(); $bunny->delete($clip->thumbnail); }
        if (isset($clip->clip)) { $bunny = new BunnyCDNService(); $bunny->delete($clip->clip); }
        if ($clip->delete()) {
            $media = Media::where('media_id', $id)->first();
            if ($media) $media->delete();
            return ResponseHelper::sendResponse([], 'Clip has been Deleted Successfully');
        }
        return ResponseHelper::sendResponse([], 'Failed to Delete Clip', false, 401);
    }

    public function view_clips(Request $request)
    {
        if (!$request->clip_id) return ResponseHelper::sendResponse([], 'Clip Id is Required', false, 401);

        $existingView = ClipsViews::where('user_id', Auth::id())->where('clip_id', $request->clip_id)->first();
        if (!$existingView) {
            ClipsViews::create(['user_id' => Auth::id(), 'clip_id' => $request->clip_id]);
        }
        $clip = Clips::find($request->clip_id);
        $clip->views_count = $clip->views->count();
        $clip->save();
        Helpers::userMedia(
            $clip->_id,
            $clip->clip,
            $clip->comments_count,
            $clip->voice_comments_count,
            $clip->likes_count,
            $clip->views_count,
            $clip->user_id,
            $clip->text,
            $clip->text_properties,
            'clips'
        );
        return ResponseHelper::sendResponse([], 'Clip Viewed Successfully');
    }

    public function like_clips(Request $request)
    {
        if (!$request->clip_id) return ResponseHelper::sendResponse([], 'Clip Id is Required', false, 401);

        $existingLike = ClipsLikes::where('user_id', Auth::id())->where('clip_id', $request->clip_id)->first();

        if (!$existingLike) {
            ClipsLikes::create(['user_id' => Auth::id(), 'clip_id' => $request->clip_id, 'emoji' => $request->emoji ?? null]);
            $clip = Clips::find($request->clip_id);
            $clip->likes_count = $clip->likes->count();
            $clip->save();
            return ResponseHelper::sendResponse($clip, 'Clip Liked Successfully');
        }

        if ($request->filled('emoji')) {
            $existingLike->emoji = $request->emoji;
            $existingLike->save();
            $clip = Clips::find($request->clip_id);
            $clip->likes_count = $clip->likes->count();
            $clip->save();
            return ResponseHelper::sendResponse($clip, 'Like Updated Successfully');
        }

        $existingLike->delete();
        $clip = Clips::find($request->clip_id);
        $clip->likes_count = $clip->likes->count();
        $clip->save();
        Helpers::userMedia(
            $clip->_id,
            $clip->clip,
            $clip->comments_count,
            $clip->voice_comments_count,
            $clip->likes_count,
            $clip->views_count,
            $clip->user_id,
            $clip->text,
            $clip->text_properties,
            'clips'
        );
        return ResponseHelper::sendResponse([], 'Clip Unliked Successfully');
    }
}
