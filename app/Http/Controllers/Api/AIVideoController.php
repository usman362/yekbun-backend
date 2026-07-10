<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helpers;
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
            $this->presentMedia($ai_video);
            return $ai_video;
        });
        return ResponseHelper::sendResponse($ai_videos, 'AI Videos has been Fetch Successfully!');
    }

    /** Resolve stored relative CDN paths to full URLs for mobile clients. */
    private function presentMedia(AIVideo $row): void
    {
        if (!empty($row->thumbnail)) {
            $row->thumbnail = Helpers::mediaUrl($row->thumbnail);
        }
        if (is_array($row->video)) {
            $row->video = array_map(function ($v) {
                if (is_array($v) && !empty($v['path'])) {
                    $v['path'] = Helpers::mediaUrl($v['path']);
                }
                return $v;
            }, $row->video);
        }
    }
}
