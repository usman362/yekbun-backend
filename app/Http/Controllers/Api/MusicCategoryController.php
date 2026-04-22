<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MusicCategory;

class MusicCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return response()->json(['Music Category' =>MusicCategory::get()] , 200);
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
            'name' => 'required',
           
          ]);

        $musiccat = MusicCategory::create([
               'name' => $request->name,
           ]);
          return response()->json([
           "success" => true,
           "message" => "Music Category successfully created.",
           "data" => $musiccat
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
        $music = MusicCategory::findorFail($id);
        $music->name = $request->name ?? $music->name;
        if($music->update()){
           return response()->json('Music Category Updated Successfully' , 200);
        }else{
           return response()->json('Failed to updated music category' , 400);
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
        $musiccat = MusicCategory::findorFail($id);
         if($musiccat->delete($musiccat->id)){
           return response()->json('Music Category Deleted Successfully' ,200);
         }else{
            return response()->json('Failed to delete music category' , 400);
         }
    }
}
