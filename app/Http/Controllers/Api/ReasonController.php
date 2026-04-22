<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\FeedReason;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReasonController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $reasons = FeedReason::all();
            return response()->json(['status' => true, 'data' => $reasons], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching reasons: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to fetch reasons'], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'title' => 'required|string|max:255',
            'reason' => 'required|string',
        ]);

        $status = empty($request->reason_id) ? 'created' : 'updated';

        try {
            $reason = FeedReason::updateOrCreate(
                ['_id' => $request->reason_id],
                $validatedData
            );
            return response()->json(['status' => true, 'message' => 'Feed Reason has been ' . $status], 200);
        } catch (\Exception $e) {
            Log::error('Error ' . $status . ' Feed Reason: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to ' . $status . ' Feed Reason'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $reason = FeedReason::findOrFail($id);
            $reason->delete();
            return response()->json(['status' => true, 'message' => 'Feed Reason deleted successfully'], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting Feed Reason: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to delete Feed Reason'], 500);
        }
    }
}
