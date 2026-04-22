<?php

namespace App\Http\Controllers\Api;

use App\Models\Event;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;

class EventController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (request()->limit) {
            return Event::orderBy("updated_at", "DESC")
                        ->paginate(request()->limit)
                        ->appends(["limit" => request()->limit]);
        }

        $events = Event::orderBy("updated_at", "DESC")->get();

        return [
            "data" => $events
        ];
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreEventRequest $request)
    {
        $validated = $request->validated();

        $event = Event::create($validated);

        return [
            "message" => "Event successfully created.",
            "data" => $event
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
            "data" => Event::find($id)
        ];
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateEventRequest $request, $id)
    {
        $validated = $request->validated();

        $event = Event::find($id);
        $event->fill($validated);
        $event->save();

        return [
            "message" => "Event successfully updated.",
            "data" => $event
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
        $event = Event::find($id);
        $event->delete();

        return [
            "message" => "Event successfully deleted."
        ];
    }
}
