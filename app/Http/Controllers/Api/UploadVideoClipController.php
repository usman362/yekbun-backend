<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UplaodVideoClip;

class UploadVideoClipController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return response()->json(['Upload Clip' =>UplaodVideoClip::get()] , 200);
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
            'title' => 'required',
            'video' => 'required'
          ]); 

          $imagePath  = $request->video;
          if($request->hasFile('video')){
                    $path = $request->file('video')->store('/images/video/' , 'public');
                    $imagePath = $path;
             }
             $video = UplaodVideoClip::create([
              'title' => $request->title,
              'category_id' => $request->artist_id,
              'video' => $imagePath
              ]);
              return response()->json([
                "success" => true,
                "message" => "Uplaod Video Clip successfully created.",
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
        $video = UplaodVideoClip::findorFail($id);
        $video->title = $request->title ?? $video->title;
        $video->category_id = $request->artist_id ?? $video->category_id;
        
        if($request->hasFile('video')){
           if(isset($news->video)){
               $image_path  = public_path('storage/'.$video->image);
               if(file_exists($image_path)){
                   unlink($image_path);
               }
               $path = $request->file('image')->store('/images/video' , 'public');
               $video->video = $path;
           }
        }
        if($video->update()){
            return response()->json('Video Updated Successfully' , 200);
         }else{
            return response()->json('Failed to updated artist' , 400);
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
        $video = UplaodVideoClip::findorFail($id);
         if($video->video){
            $image_path = public_path('storage/'.$video->video);
            if(file_exists($image_path)){
                unlink($image_path);
            }
         }
         if($video->delete($video->id)){
            return response()->json('Video Deleted Successfully' ,200);
          }else{
             return response()->json('Failed to delete video' , 400);
          }

    }
}
