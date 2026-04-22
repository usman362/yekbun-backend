<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\News;
use App\Models\NewsCategory;

class NewsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return response()->json(['news' => News::get()], 200);
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
            'description' => 'required',
            'category_id' => 'required',
        ]);

        $imagePath  = $request->image;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('/images/news/', 'public');
            $imagePath = $path;
        }
        $news = News::create([
            'title' => $request->title,
            'description' => $request->description,
            'category_id' => $request->category_id,
            'image' => $imagePath
        ]);
        return response()->json([
            "success" => true,
            "message" => "News successfully created.",
            "data" => $news
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

        $news = News::findorFail($id);
        $news->title = $request->title ?? $news->title;
        $news->description = $request->description ?? $news->description;
        $news->category_id = $request->category_id ?? $news->category_id;

        if ($request->hasFile('image')) {
            if (isset($news->image)) {
                $image_path  = public_path('storage/' . $news->image);
                if (file_exists($image_path)) {
                    unlink($image_path);
                }
                $path = $request->file('image')->store('/images/news', 'public');
                $news->image = $path;
            }
        }

        if ($news->update()) {
            return response()->json('News Updated Successfully', 200);
        } else {
            return response()->json('Failed to updated news', 400);
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
        $news = News::findorFail($id);
        if ($news->image) {
            $image_path = public_path('storage/' . $news->image);
            if (file_exists($image_path)) {
                unlink($image_path);
            }
        }

        if ($news->delete($news->id)) {
            return response()->json('News Deleted Successfully', 200);
        } else {
            return response()->json('Failed to delete news', 400);
        }
    }

    public function category_news($id)
    {
        $news = News::select('id','title','description','category_id','created_at')->with('gallery')->where('category_id', $id)->inRandomOrder()->limit(8)->get();

   
        if($news->isNotEmpty()){
            $news = $news->map(function($new){
               $formatDate = $new->created_at->format('M d Y');
                $new->setAttribute('formatted_created_at',$formatDate);
                return $new;
            });

            return response()->json(['success' => true, 'data' => $news]);
        }
        return response()->json(['success' => false , 'message' => 'No news found.']);
    }

    public function cover_news()
    {
        $news = News::select('id','title','description','category_id','created_at')->with('gallery')->limit(3)->get();
        if($news->isNotEmpty()){
            $news = $news->map(function($new){
                $formatDate = $new->created_at->format('M d Y');
                $new->setAttribute('formatted_created_at',$formatDate);
                return $new;
            });
            return response()->json(['success' => true, 'data' => $news]);
        }
        return response()->json(['success' => false , 'message' => 'No cover news found.']);
    }

    public function categories()
    {
        $categories = NewsCategory::all();
        if($categories->isNotEmpty()){
            return response()->json(['success' => true, 'data' => $categories]);
        }
        return response()->json(['success' => false , 'message' => 'No news category found.']);
    }

    public function detail($id)
    {
        $news = News::select('id','title','description','category_id' , 'created_at')->with(['news_category','gallery'])->find($id);
        if($news != []){
            $new = $news->created_at->format('M d Y');
            $news = $news->setAttribute('formatted_created_at',$new);
            return response()->json(['success' => true, 'data' => $news]);
        }
        return response()->json(['success' => false ,'message' => 'No news found.']);
    }

    public function search(Request $request)
    {
        $news = News::select('id','title','description','category_id')->with('gallery')->where('title', 'like', '%' . $request->search . '%')->get();
        if($news->isNotEmpty()){
            return response()->json(['success' => true, 'data' => $news]);
        }
        return response()->json(['success' => false , 'message' => 'No news found.']);
    }
}
