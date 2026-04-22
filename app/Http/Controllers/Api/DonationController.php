<?php

namespace App\Http\Controllers\Api;

use App\Models\Donation;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDonationRequest;
use App\Http\Requests\UpdateDonationRequest;

class DonationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (request()->limit) {
            return Donation::orderBy("updated_at", "DESC")
                        ->paginate(request()->limit)
                        ->appends(["limit" => request()->limit]);
        }

        $donations = Donation::orderBy("updated_at", "DESC")->get();

        return [
            "data" => $donations
        ];
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreDonationRequest $request)
    {
        $validated = $request->validated();

        $validated['start_date'] .= ' 00:00:00';
        $validated['end_date'] .= ' 23:59:59'; 

        $donation = Donation::create($validated);

        return [
            "message" => "Donation successfully created.",
            "data" => $donation
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
            "data" => Donation::find($id)
        ];
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateDonationRequest $request, $id)
    {
        $validated = $request->validated();

        $validated['start_date'] .= ' 00:00:00';
        $validated['end_date'] .= ' 23:59:59'; 

        $donation = Donation::find($id);
        $donation->fill($validated);
        $donation->save();

        return [
            "message" => "Donation successfully updated.",
            "data" => $donation
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
        $donation = Donation::find($id);
        $donation->delete();

        return [
            "message" => "Donation successfully deleted."
        ];
    }
}
