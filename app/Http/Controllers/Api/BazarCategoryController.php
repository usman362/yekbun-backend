<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BazarCategory;

class BazarCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $bazar_category = BazarCategory::with('bazarsubcategory')->first();
        return response()->json(['Bazar Category' =>$bazar_category] , 200);

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

        $bazar = BazarCategory::create([
               'name' => $request->name,
           ]);
          return response()->json([
           "success" => true,
           "message" => "Bazar Category successfully created.",
           "data" => $bazar
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
        $bazar = BazarCategory::findorFail($id);
        $bazar->name = $request->name ?? $bazar->name;
        if($bazar->update()){
           return response()->json('Bazar Category Updated Successfully' , 200);
        }else{
           return response()->json('Failed to updated bazar category' , 400);
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
        $bazar = BazarCategory::findorFail($id);
        if($bazar->delete($bazar->id)){
          return response()->json('Baazar Category Deleted Successfully' ,200);
        }else{
           return response()->json('Failed to delete bazar category' , 400);
        }
    }
}
