<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FanPage;

class ManageFanPageController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return response()->json(['Mange Fan Page' =>FanPage::where('status' , 0)->orWhere('status' , 2)->get()] , 200);
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
            'user_name' =>'required',
            'fanpage_name' =>'required',
        ]);
        
        $page = FanPage::create([
            'user_name' => $request->user_name,
            'fanpage_name' => $request->fanpage_name
        ]);
        return response()->json([
            "success" => true,
            "message" => "Fan Page  successfully created.",
            "data" => $page
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
        
        $page = FanPage::findorFail($id);
         $page->user_name = $request->user_name;
         $page->fanpage_name = $request->fanpage_name;
         if($page->update()){
            return response()->json('Fan Pgae Updated Successfully' , 200);
         }else{
            return response()->json('Failed to updated fan page' , 400);
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
        $page = FanPage::findorFail($id);
        if($page->delete($page->id)){
            return response()->json('Fan page Deleted Successfully' ,200);
          }else{
             return response()->json('Failed to delete fan page' , 400);
          }
    }
}
