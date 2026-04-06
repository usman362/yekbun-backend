<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\AIVideo;

class AIVideoController extends Controller
{
    public function index()
    {
        $ai_videos = AIVideo::where('status', '1')->orderBy('created_at', 'desc')->get();
        $ai_videos->transform(function ($ai_video) {
            $ai_video->comments_count = $ai_video->comments->count();
            $ai_video->voice_comments_count = $ai_video->voice_comments->count();
            $ai_video->likes_count = $ai_video->likes->count();
            $ai_video->views_count = $ai_video->views->count();
            $ai_video->shares_count = $ai_video->shares->count();
            return $ai_video;
        });
        return ResponseHelper::sendResponse($ai_videos, 'AI Videos has been Fetch Successfully!');
    }
}
