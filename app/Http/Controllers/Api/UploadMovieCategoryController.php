<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UploadMovieCategory;

class UploadMovieCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return response()->json(['Movie Category' =>UploadMovieCategory::get()] , 200);
        
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

        $movie = UploadMovieCategory::create([
               'category' => $request->category,
           ]);
          return response()->json([
           "success" => true,
           "message" => "Vidoe Category successfully created.",
           "data" => $movie
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
        $movie = UploadMovieCategory::findorFail($id);
        $movie->category = $request->category ?? $movie->category;
        if($movie->update()){
           return response()->json('Movie Category Updated Successfully' , 200);
        }else{
           return response()->json('Failed to updated movie category' , 400);
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
        $movie = UploadMovieCategory::findorFail($id);
        if($movie->delete($movie->id)){
          return response()->json('Movie Category Deleted Successfully' ,200);
        }else{
           return response()->json('Failed to delete movie category' , 400);
        }
    }
}
