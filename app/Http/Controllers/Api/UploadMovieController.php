<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UploadMovie;
use Illuminate\Http\Request;

class UploadMovieController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return response()->json(['Upload Movie' =>UploadMovie::get()] , 200);
        
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

          $imagePath  = $request->movie;
          if($request->hasFile('movie')){
                    $path = $request->file('movie')->store('/images/movie' , 'public');
                    $imagePath = $path;
             }
             
             $movie = UploadMovie::create([
               'title' => $request->title,
               'thumbnail' => $request->thumbnail,
               'description' => $request->description,
             'category_id' => $request->category_id,
               'movie' => $imagePath
            ]);

            return response()->json([
                "success" => true,
                "message" => "Movie successfully created.",
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
        $movie = UploadMovie::find($id);
        $movie->thumbnail = $request->thumbnail ?? $movie->thumbnail;
        $movie->title = $request->title ?? $movie->title;
        $movie->description = $request->description ?? $movie->description;
        $movie->category_id = $request->category_id ?? $movie->category_id;

        if($request->hasFile('movie')){
            if(isset($movie->movie)){
                $movie_path  = public_path('/storage/'.$movie->movie);
                if(file_exists($movie_path)){
                    unlink($movie_path);
                }
                $path  = $request->file('movie')->storeAs('/images/movie/' , 'public');
                $movie->movie = $path;
             }
        }

        if($movie->update()){
            return response()->json('Movie Updated Successfully' , 200);

           }else{
            return response()->json('Failed to updated Movie' , 400);

    
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
        $movie = UploadMovie::findorFail($id);
        if($movie->movie){
           $image_path = public_path('storage/'.$movie->movie);
           if(file_exists($image_path)){
               unlink($image_path);
           }
        }
        if($movie->delete($movie->id)){
            return response()->json('Movie Deleted Successfully' , 200);

           }else{
            return response()->json('Failed to delete movie' , 400);
           }
    }
}
