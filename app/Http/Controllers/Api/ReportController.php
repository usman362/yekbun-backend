<?php

namespace App\Http\Controllers\Api;

use App\Models\Report;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\StoreReportRequest;
use App\Http\Requests\UpdateReportRequest;

class ReportController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (request()->limit) {
            return Report::orderBy("updated_at", "DESC")
                        ->paginate(request()->limit)
                        ->appends(["limit" => request()->limit]);
        }

        $reports = Report::orderBy("updated_at", "DESC")->get();

        return [
            "data" => $reports
        ];
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreReportRequest $request)
    {
        $validated = $request->validated();

        $report = Report::create($validated);

        return [
            "message" => "Report successfully created.",
            "data" => $report
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
            "data" => Report::find($id)
        ];
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateReportRequest $request, $id)
    {
        $validated = $request->validated();

        $report = Report::find($id);
        $report->fill($validated);
        $report->save();

        return [
            "message" => "Report successfully updated.",
            "data" => $report
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
        $report = Report::find($id);
        $report->delete();

        return [
            "message" => "Report successfully deleted."
        ];
    }
}
