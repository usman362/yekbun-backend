<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsCategory;
use Illuminate\Http\Request;

class NewsCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return response()->json(['news Category' =>NewsCategory::get()] , 200);
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

        $newscat = NewsCategory::create([
               'name' => $request->name,
           ]);
          return response()->json([
           "success" => true,
           "message" => "News Category successfully created.",
           "data" => $newscat
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
        
        $news = NewsCategory::findorFail($id);
        $news->name = $request->name ?? $news->name;
        if($news->update()){
           return response()->json('News Category Updated Successfully' , 200);
        }else{
           return response()->json('Failed to updated news category' , 400);
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
        $newscat = NewsCategory::findorFail($id);
         if($newscat->delete($newscat->id)){
           return response()->json('News Category Deleted Successfully' ,200);
         }else{
            return response()->json('Failed to delete news category' , 400);
         }
    }
}
