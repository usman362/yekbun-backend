<?php

namespace App\Http\Controllers\Api;

use App\Models\Ticket;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;

class TicketController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (request()->limit) {
            return Ticket::orderBy("updated_at", "DESC")
                        ->paginate(request()->limit)
                        ->appends(["limit" => request()->limit]);
        }

        $tickets = Ticket::orderBy("updated_at", "DESC")->get();

        return [
            "data" => $tickets
        ];
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreTicketRequest $request)
    {
        $validated = $request->validated();

        $ticket = Ticket::create($validated);

        return [
            "message" => "Ticket successfully created.",
            "data" => $ticket
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
            "data" => Ticket::find($id)
        ];
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateTicketRequest $request, $id)
    {
        $validated = $request->validated();

        $ticket = Ticket::find($id);
        $ticket->fill($validated);
        $ticket->save();

        return [
            "message" => "Ticket successfully updated.",
            "data" => $ticket
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
        $ticket = Ticket::find($id);
        $ticket->delete();

        return [
            "message" => "Ticket successfully deleted."
        ];
    }
}
