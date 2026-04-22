<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\VotingCategory;

class VotingCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return response()->json(['Voting Category' =>VotingCategory::get()] , 200);
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

        $voting = VotingCategory::create([
               'name' => $request->name,
           ]);
          return response()->json([
           "success" => true,
           "message" => "Voting Category successfully created.",
           "data" => $voting
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
        
        $vote = VotingCategory::findorFail($id);
        $vote->name = $request->name ?? $vote->name;
        if($vote->update()){
           return response()->json('Voting Category Updated Successfully' , 200);
        }else{
           return response()->json('Failed to updated voting category' , 400);
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
        $vote = VotingCategory::findorFail($id);
         if($vote->delete($vote->id)){
           return response()->json('Voting Category Deleted Successfully' ,200);
         }else{
            return response()->json('Failed to delete voting category' , 400);
         }
    }
}
