<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Bazar;
use Illuminate\Support\Facades\Auth;

class BazarController extends Controller
{
  /**
   * Display a listing of the resource.
   *
   * @return \Illuminate\Http\Response
   */
  public function index()
  {
    return response()->json(['Bazar' => Bazar::with('bazar_category')->get()], 200);
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
    //   'image' => 'required|array|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
      'image.*' => 'required|image|max:2048'
    ]);

    // try {
      $images = [];
      foreach ($request->file('image') as $value) {
        $path = $value->store('/images/bazar/img','public');
        $images[] = $path;
      }

      $bazar = Bazar::create([
        'category_id' => $request->category_id,
        'title' => $request->title,
        'image' => json_encode($images),
        'user_id' => Auth::id() ?? null,
        'price' => $request->price,
        'status' => $request->status,
        'warranty' => $request->warranty,
      ]);
      return response()->json(
        [
          'success' => true,
          'message' => 'Bazar  successfully created.',
          'data' => $bazar,
        ],
        200
      );
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
  public function update($id, Request $request)
  {

    $bazar = Bazar::findOrFail($id);
    $bazar->title = $request->title ?? $bazar->title;
    $bazar->category_id = $request->category_id ?? $bazar->category_id;
    $bazar->warranty = $request->warranty ?? $bazar->warranty;
    $bazar->status = $request->status ?? $bazar->status;
    $images = collect([]);
    if($request->hasFile('image')){
        foreach($request->file('image') as $value){
            if(isset($bazar->image)){
                $image_path =public_path('storage/'.$bazar->image);
                if(file_exists($image_path)){
                    unlink($image_path);
                }
                $path = $value->store('/images/bazar/img','public');
                $images->push($path);
            }
        }
        $bazar->image = $images;
    }else{
        $bazar->image = $bazar->image ?? '';
    }

    if ($bazar->update()) {
      return response()->json('Bazar  Updated Successfully', 200);
    } else {
      return response()->json('Failed to updated bazar', 400);
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
    $bazar = Bazar::findorFail($id);
    if ($bazar->images) {
      $image_path = public_path('storage/' . $bazar->images);
      if (isset($image_path)) {
        unlink($image_path);
      }
    }
    if ($bazar->delete($bazar->id)) {
      return response()->json('Bazar  Deleted Successfully', 200);
    } else {
      return response()->json('Failed to delete bazar', 400);
    }
  }
}
