<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\EventCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventCategoryRequest;
use App\Http\Requests\UpdateEventCategoryRequest;

class EventCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (request()->limit) {
            return EventCategory::orderBy("updated_at", "DESC")
                        ->paginate(request()->limit)
                        ->appends(["limit" => request()->limit]);
        }

        $categories = EventCategory::orderBy("updated_at", "DESC")->get();

        return [
            "data" => $categories
        ];
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreEventCategoryRequest $request)
    {
        $validated = $request->validated();

        $category = EventCategory::create($validated);

        return [
            "message" => "Category successfully created.",
            "data" => $category
        ];
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        return [
            "data" => EventCategory::find($id)
        ];
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateEventCategoryRequest $request, $id)
    {
        $validated = $request->validated();

        $category = EventCategory::find($id);
        $category->fill($validated);
        $category->save();

        return [
            "message" => "Category successfully updated.",
            "data" => $category
        ];
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $category = EventCategory::find($id);
        $category->delete();

        return [
            "message" => "Category successfully deleted."
        ];
    }
}
