<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Helpers\ResponseHelper;

use App\Models\ZarokMovies;
use App\Models\ZarokSeries;
use App\Models\ZarokStories;
use App\Models\ZarokVideos;
use App\Models\Language;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use DateTime;
use Auth;
use Exception;
use Carbon\Carbon;

class TVController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function zarokStories()
    {
	    $videos = ZarokStories::all();
        return ResponseHelper::sendResponse($videos, 'OK');
    }
	
    public function zarokVideos(){
        $videos = ZarokVideos::all();
        return ResponseHelper::sendResponse($videos, 'OK');
    }
    public function zarokMovies(){
        $videos = ZarokMovies::all();
        return ResponseHelper::sendResponse($videos, 'OK');
    }
    public function zarokSeries(){
        $videos = ZarokSeries::all();
        return ResponseHelper::sendResponse($videos, 'OK');
    }
    public function zarokSeriesSeason($id){
        $video = ZarokSeries::with('seasons')->where('_id', $id)->first();
        return ResponseHelper::sendResponse($video, 'OK');
    }
    public function zarokSeriesEpisodes($id){
        $video = ZarokSeries::with('episodes')->where('_id', $id)->first();
        return ResponseHelper::sendResponse($video, 'OK');
    }
    

    public function zarokStoriesPost(Request $request){
        try {
            
            $vc = new ZarokStories();
            if ($request->video_id) {
                $vc = ZarokStories::find($request->video_id);
            }
            $vc->video_file_name = $request->video_name;
            $vc->video = $request->video_paths;
            $vc->video_file_size = $request->video_sizes;
            $vc->video_file_length = $request->video_durations;
            $cleanedThumbnail = Str::after($request->thumbnail, 'storage/');
            $cleanedThumbnail = Str::before($cleanedThumbnail, '.jpg') . '.jpg';
            $vc->thumbnail = $cleanedThumbnail;
            $vc->save();

            return ResponseHelper::sendResponse(
                200,
                'Video story saved successfully.'
            );
        
        } catch (Exception $e) {
            
            return ResponseHelper::sendResponse(
                500,
                'An error occurred while saving the story.',
                ['error' => $e->getMessage()]
            );
        }

        return ResponseHelper::sendResponse($videos, 'OK');

    }
    
}
