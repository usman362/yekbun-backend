<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UploadVideoCategory;
use Illuminate\Http\Request;

class UploadVideoCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return response()->json(['Video Category' =>UploadVideoCategory::get()] , 200);
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
            'category' => 'required',
           
          ]);

        $video = UploadVideoCategory::create([
               'category' => $request->category,
           ]);
          return response()->json([
           "success" => true,
           "message" => "Vidoe Category successfully created.",
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
        $video = UploadVideoCategory::findorFail($id);
        $video->category = $request->category ?? $video->category;
        if($video->update()){
           return response()->json('Video Category Updated Successfully' , 200);
        }else{
           return response()->json('Failed to updated video category' , 400);
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
        $video = UploadVideoCategory::findorFail($id);
        if($video->delete($video->id)){
          return response()->json('Video Category Deleted Successfully' ,200);
        }else{
           return response()->json('Failed to delete video category' , 400);
        }
    }
}
