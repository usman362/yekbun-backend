<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\Avatars;
use App\Models\Avatars_sources;
use App\Models\Avatars_Feed;
use Illuminate\Http\Request;
use App\Models\Language;
use DateTime;
use Auth;
use Carbon\Carbon;

class AvatarsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    
	
	

	public function getfeeds(){

		$avatars =  Avatars::orderBy('created_at', 'desc')
				->get();
        
        $aray = array();

		foreach($avatars as $av){

			//$feed = Avatars_Feed::where('avatar_Id', $av->id)->where('created_at', date("Y-m-d", time()))->get();	
			
			//$feed = Avatars_Feed::where('avatar_Id', $av->av_Id)->where('created_at', Carbon::today())->first();	
			
			//$feed = Avatars_Feed::where('avatar_Id', $av->av_Id)->where('created_at', '>=', Carbon::today())->first();	
			
			
			//if($feed != null){
				
			//	continue;
			//}

			

			$nary = array();
			$nary["id"] = $av->_id;
			$nary["avatar"] = $av->av_Id;
			$nary["task"] =  $av->task;
			$nary["online"] = 0;

			$sorucaray = array();

			$sources = Avatars_sources::where('avatar_Id', $av->id)->get();	

			foreach($sources as $csource){
				$sorucaray[] = $csource->source_link;
			}

            $nary["sources"] = $sorucaray; 

            $aray[] = $nary;
		}

        return response()->json(['message' => 'Ok','data' => $aray],201);
    }
	
	

   
    /**
     * Store a newly created resource in storage.
     */
    public function postfeed(Request $request)
    {
		
		$av_id = $request['avatar'];
		$title = $request['title'];
		$image = $request['image_path'];
		$content = $request['content'];
		
		$feed = new Avatars_Feed();
		$feed->avatar_Id = $av_id;
		$feed->title = $title;
		$feed->image = $image;
		$feed->content = $content;
		$feed->forwards = 0;
		$feed->online = 0;
		
		$feed->comments = 0;
		$feed->likes = 0;
		$feed->save();
		
		return response()->json(['message' => 'Ok'],201);
		
        //
    }




    
    
}
