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
use MongoDB\BSON\ObjectId;
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
        // Pre-validation: check the raw PHP upload error first so we can give a precise
        // message instead of the generic "The video failed to upload." that Laravel's
        // `file` rule emits whenever UploadedFile::isValid() is false. Without this,
        // a video that exceeded `upload_max_filesize` looks identical to a missing file
        // or a half-transferred upload — undebuggable from the client side.
        foreach (['video', 'audio', 'thumbnail'] as $field) {
            if (!$request->hasFile($field)) continue;
            $file = $request->file($field);
            $err  = $file->getError();
            if ($err === UPLOAD_ERR_OK) continue;

            $reason = match ($err) {
                UPLOAD_ERR_INI_SIZE   => "File too large for the server (exceeds upload_max_filesize). Raise PHP limits or use a smaller file.",
                UPLOAD_ERR_FORM_SIZE  => "File too large (exceeds form MAX_FILE_SIZE).",
                UPLOAD_ERR_PARTIAL    => "Upload was interrupted — only part of the file arrived. Retry on a stable connection.",
                UPLOAD_ERR_NO_FILE    => "No file was uploaded for `{$field}`.",
                UPLOAD_ERR_NO_TMP_DIR => "Server temp folder missing — contact backend.",
                UPLOAD_ERR_CANT_WRITE => "Server couldn't write the uploaded file to disk.",
                UPLOAD_ERR_EXTENSION  => "Upload blocked by a PHP extension.",
                default               => "Unknown upload error (code {$err}).",
            };

            \Illuminate\Support\Facades\Log::warning('store_clips upload error', [
                'user_id' => Auth::id(),
                'field'   => $field,
                'php_err' => $err,
                'size'    => $file->getSize(),
                'limits'  => [
                    'upload_max_filesize' => ini_get('upload_max_filesize'),
                    'post_max_size'       => ini_get('post_max_size'),
                ],
            ]);

            return ResponseHelper::sendResponse(
                ['field' => $field, 'php_error' => $err],
                "Could not upload `{$field}`: {$reason}",
                false,
                422
            );
        }

        // Loose validation — `mimetypes` is detected from file CONTENT, not the Content-Type
        // header, and React Native multipart uploads frequently mis-report (.m4a coming as
        // audio/mp4, .mp4 sometimes as application/octet-stream, etc.). We just check the
        // field is a real uploaded file and within our size budget. Hard mime restrictions
        // live in the ffmpeg step — bad bytes there get a clear 500 with stderr in the log.
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
        $ffprobeBin = trim((string) @shell_exec('which ffprobe'));

        // Detect whether the user actually picked a separate audio track. Without this check
        // we were forcing every video through a 2-input ffmpeg `amix` even when there was no
        // user audio, which (combined with `-shortest` + `duration=first`) silently truncated
        // the output to ~2 seconds (the length of empty.mp3) AND replaced the original audio.
        $hasUserAudio = $request->hasFile('audio')
            || (!empty($request->audio) && is_string($request->audio) && Storage::exists('public/' . $request->audio));

        $audioPath = null;
        if ($request->hasFile('audio')) {
            $audioPath = $request->file('audio')->getPathname();
        } elseif (!empty($request->audio) && is_string($request->audio) && Storage::exists('public/' . $request->audio)) {
            $audioPath = storage_path('app/public/' . $request->audio);
        }

        $videoFile  = $request->file('video');
        $videoLocal = $videoFile->getPathname();

        // Diagnostic + guard: probe the SOURCE for a real video stream. Broken/empty
        // recordings (a known issue with some emulators) arrive with an audio track but no
        // usable video — ffmpeg then turns them into a video-less stub that plays nothing.
        if ($ffprobeBin !== '') {
            $srcVideo = trim((string) @shell_exec(sprintf(
                '%s -v error -select_streams v:0 -show_entries stream=codec_name,duration -of csv=p=0 %s 2>&1',
                escapeshellcmd($ffprobeBin),
                escapeshellarg($videoLocal)
            )));
            \Illuminate\Support\Facades\Log::info('store_clips source probe', [
                'user_id'    => Auth::id(),
                'input_size' => @filesize($videoLocal),
                'video'      => $srcVideo !== '' ? $srcVideo : 'NO VIDEO STREAM',
            ]);
            if ($srcVideo === '') {
                return ResponseHelper::sendResponse(
                    null,
                    'The uploaded file has no video track. Please record or pick a real video and try again.',
                    false,
                    422
                );
            }
        }

        // Unique output filename — without this, two users uploading clips named `video.mp4`
        // race on the same path and one overwrites the other mid-encode → broken clips.
        $tmpDir = storage_path('app/public/videos');
        if (!is_dir($tmpDir)) @mkdir($tmpDir, 0775, true);
        $outputPath = $tmpDir . '/clip_' . uniqid('', true) . '.mp4';

        $videoVolume = max(0.0, min(2.0, (float) ($request->video_volume ?? 0.8)));
        $audioVolume = max(0.0, min(2.0, (float) ($request->audio_volume ?? 0.5)));

        if ($hasUserAudio) {
            // Mix the user's audio over the video's audio track.
            // `duration=longest` (NOT `first`) + dropping `-shortest` so the output keeps the
            // full length of whichever is longer — never truncates to the shorter input.
            $command = sprintf(
                '%s -y -i %s -i %s -filter_complex %s -map 0:v -map "[a]" %s 2>&1',
                escapeshellcmd($ffmpegBin),
                escapeshellarg($videoLocal),
                escapeshellarg($audioPath),
                escapeshellarg(sprintf(
                    '[1:a]volume=%.3f[a1];[0:a]volume=%.3f[a2];[a1][a2]amix=inputs=2:duration=longest[a]',
                    $audioVolume,
                    $videoVolume
                )),
                escapeshellarg($outputPath)
            );
        } else {
            // No user audio — just remux the video as-is. Stream copy means no re-encode
            // (fast, lossless) and preserves the video's original audio track. If the video
            // has no audio track at all, `-c copy` happily produces a silent-but-valid mp4.
            $command = sprintf(
                '%s -y -i %s -c copy %s 2>&1',
                escapeshellcmd($ffmpegBin),
                escapeshellarg($videoLocal),
                escapeshellarg($outputPath)
            );
        }

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

        // A non-zero, non-empty file can STILL be a video-less stub (audio-only) if the
        // source's video track was unusable. Verify a real video stream survived — otherwise
        // we'd publish a clip that plays nothing (871-byte, 0-frame files seen in the wild).
        if ($ffprobeBin !== '') {
            $outVideo = trim((string) @shell_exec(sprintf(
                '%s -v error -select_streams v:0 -show_entries stream=codec_name -of csv=p=0 %s 2>&1',
                escapeshellcmd($ffprobeBin),
                escapeshellarg($outputPath)
            )));
            if ($outVideo === '') {
                \Illuminate\Support\Facades\Log::error('store_clips output has no video stream', [
                    'user_id'    => Auth::id(),
                    'input_size' => @filesize($videoLocal),
                    'out_size'   => @filesize($outputPath),
                    'ffmpeg_tail' => array_slice($output, -12),
                ]);
                @unlink($outputPath);
                return ResponseHelper::sendResponse(
                    null,
                    'Clip processing produced no video (the source recording may be broken). Please try a different video.',
                    false,
                    422
                );
            }
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
        $requestId = $this->requestClipIdentifier($request);
        if (!$requestId) {
            return ResponseHelper::sendResponse([], 'Clip Id is Required', false, 401);
        }

        $clip = $this->resolveClipFromId($requestId);
        if (!$clip) {
            return ResponseHelper::sendResponse([], 'Clip Not Found', false, 404);
        }

        $clipId = (string) $clip->_id;

        $existingView = ClipsViews::where('user_id', Auth::id())
            ->where(function ($q) use ($clipId, $requestId) {
                $q->where('clip_id', $clipId);
                if ($requestId !== $clipId) {
                    $q->orWhere('clip_id', $requestId);
                }
            })
            ->first();
        if (!$existingView) {
            ClipsViews::create(['user_id' => Auth::id(), 'clip_id' => $clipId]);
        }

        $viewsCount = $this->syncClipViewsCount($clip, $clipId);
        Helpers::userMedia(
            $clip->_id,
            $clip->clip ?? null,
            $clip->comments_count ?? 0,
            $clip->voice_comments_count ?? 0,
            $clip->likes_count ?? 0,
            $viewsCount,
            $clip->user_id ?? null,
            $clip->text ?? null,
            $clip->text_properties ?? null,
            'clips'
        );
        return ResponseHelper::sendResponse([], 'Clip Viewed Successfully');
    }

    public function like_clips(Request $request)
    {
        $requestId = $this->requestClipIdentifier($request);
        if (!$requestId) {
            return ResponseHelper::sendResponse([], 'Clip Id is Required', false, 401);
        }

        $clip = $this->resolveClipFromId($requestId);
        if (!$clip) {
            return ResponseHelper::sendResponse([], 'Clip Not Found', false, 404);
        }

        $clipId = (string) $clip->_id;
        $userId = Auth::id();
        $existingLike = ClipsLikes::where('user_id', $userId)
            ->where(function ($q) use ($clipId, $requestId) {
                $q->where('clip_id', $clipId);
                if ($requestId !== $clipId) {
                    $q->orWhere('clip_id', $requestId);
                }
            })
            ->first();

        if ($existingLike && (string) $existingLike->clip_id !== $clipId) {
            $existingLike->clip_id = $clipId;
            $existingLike->save();
        }

        if (!$existingLike) {
            ClipsLikes::create(['user_id' => $userId, 'clip_id' => $clipId, 'emoji' => $request->emoji ?? null]);
            $this->syncClipLikesCount($clip, $clipId);
            return ResponseHelper::sendResponse($clip->fresh(), 'Clip Liked Successfully');
        }

        if ($request->filled('emoji')) {
            $existingLike->emoji = $request->emoji;
            $existingLike->save();
            $this->syncClipLikesCount($clip, $clipId);
            return ResponseHelper::sendResponse($clip->fresh(), 'Like Updated Successfully');
        }

        $existingLike->delete();
        $likesCount = $this->syncClipLikesCount($clip, $clipId);
        Helpers::userMedia(
            $clip->_id,
            $clip->clip ?? null,
            $clip->comments_count ?? 0,
            $clip->voice_comments_count ?? 0,
            $likesCount,
            $clip->views_count ?? 0,
            $clip->user_id ?? null,
            $clip->text ?? null,
            $clip->text_properties ?? null,
            'clips'
        );
        return ResponseHelper::sendResponse([], 'Clip Unliked Successfully');
    }

    private function syncClipLikesCount(Clips $clip, string $clipId): int
    {
        $count = ClipsLikes::where('clip_id', $clipId)->count();
        $clip->likes_count = $count;
        $clip->save();

        return $count;
    }

    private function syncClipViewsCount(Clips $clip, string $clipId): int
    {
        $count = ClipsViews::where('clip_id', $clipId)->count();
        $clip->views_count = $count;
        $clip->save();

        return $count;
    }

    /** Accept clip_id (legacy) or id (mobile clipLike). */
    private function requestClipIdentifier(Request $request): ?string
    {
        $id = $request->input('clip_id') ?? $request->input('id') ?? $request->input('media_id');
        if ($id === null || $id === '') {
            return null;
        }

        return (string) $id;
    }

    /**
     * Resolve clips._id from what the client sent.
     * Mobile video grid often sends Media._id (allMediaRecord) instead of clips._id.
     */
    private function resolveClipFromId(string $id): ?Clips
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }

        $clip = Clips::find($id);
        if ($clip) {
            return $clip;
        }

        $media = Media::find($id);
        if (!$media && strlen($id) === 24 && ctype_xdigit($id)) {
            try {
                $media = Media::where('_id', new ObjectId($id))->first();
            } catch (\Throwable) {
                $media = null;
            }
        }
        if ($media && ($media->type ?? '') === 'clips' && !empty($media->media_id)) {
            $clip = Clips::find((string) $media->media_id);
            if (!$clip && strlen((string) $media->media_id) === 24) {
                try {
                    $clip = Clips::where('_id', new ObjectId((string) $media->media_id))->first();
                } catch (\Throwable) {
                    $clip = null;
                }
            }
            if ($clip) {
                return $clip;
            }
        }

        if (strlen($id) === 24 && ctype_xdigit($id)) {
            try {
                return Clips::where('_id', new ObjectId($id))->first();
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
