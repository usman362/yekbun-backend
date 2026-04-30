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
        $request->validate(['video' => 'required|file']);

        $clip = new Clips();
        $clip->template_id = $request->template_id;
        $clip->thumbnail = $request->hasFile('thumbnail') ? Helpers::fileCDNUpload($request->thumbnail, 'images/thumbnails/clips') : '';
        $clip->emoji = $request->emoji;
        $clip->share_with = $request->share_with;
        $clip->user_id = Auth::id();
        $clip->text = $request->text;
        $clip->text_properties = $request->text_properties;

        $videoPath = $request->video;
        $audioPath = public_path('audios/empty.mp3');
        if ($request->hasFile('audio')) {
            $audioPath = $request->audio;
        } elseif (!empty($request->audio) && Storage::exists('public/' . $request->audio)) {
            $audioPath = storage_path('app/public/' . $request->audio);
        }

        $outputPath = storage_path('app/public/videos/clip_' . $videoPath->getClientOriginalName());
        $videoVolume = $request->video_volume ?? 0.8;
        $audioVolume = $request->audio_volume ?? 0.5;

        $command = "ffmpeg -i {$videoPath} -i {$audioPath} -filter_complex " .
            "\"[1:a]volume={$audioVolume}[a1];[0:a]volume={$videoVolume}[a2];" .
            "[a1][a2]amix=inputs=2:duration=first[a]\" " .
            "-map 0:v -map \"[a]\" -shortest {$outputPath}";

        exec($command, $output, $return_var);

        if ($return_var === 0) {
            $clip->clip = Helpers::fileCDNUpload2(new \Illuminate\Http\File($outputPath), 'clips');
            if (file_exists($outputPath)) unlink($outputPath);
        }

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
