<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UplaodVideo;
use Illuminate\Http\Request;


class UploadVideoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

  
     
    public function index()
    {
        return response()->json(['Upload Video' =>UplaodVideo::get()] , 200);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'thumbnail' => 'required',
            'title' => 'required',
            'video' => 'required',
          ]); 

          $imagePath  = $request->video;
          if($request->hasFile('video')){
                    $path = $request->file('video')->store('/images/video' , 'public');
                    $imagePath = $path;
             }
             
             $video = UplaodVideo::create([
               'title' => $request->title,
               'thumbnail' => $request->thumbnail,
               'description' => $request->description,
             'category_id' => $request->category_id,
               'video' => $imagePath
            ]);

            return response()->json([
                "success" => true,
                "message" => "Video successfully created.",
                "data" => $video
            ], 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $video = UplaodVideo::find($id);
        $video->thumbnail = $request->thumbnail ?? $video->thumbnail;
        $video->title = $request->title ?? $video->title;
        $video->description = $request->description ?? $video->description;
        $video->category_id = $request->category_id ?? $video->category_id;

        if($request->hasFile('video')){
            if(isset($video->video)){
                $video_path  = public_path('/storage/'.$video->video);
                if(file_exists($video_path)){
                    unlink($video_path);
                }
                $path  = $request->file('video')->storeAs('/images/video/' , 'public');
                $video->video = $path;
             }
        }

        if($video->update()){
            return response()->json('Video Updated Successfully' , 200);

           }else{
            return response()->json('Failed to updated video' , 400);

    
           }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $video = UplaodVideo::findorFail($id);
        if($video->video){
           $image_path = public_path('storage/'.$video->video);
           if(file_exists($image_path)){
               unlink($image_path);
           }
        }
        if($video->delete($video->id)){
            return response()->json('Video Deleted Successfully' , 200);

           }else{
            return response()->json('Failed to delete video' , 400);
           }
    }
}
