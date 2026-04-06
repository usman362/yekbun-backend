<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ringtone;
use Illuminate\Http\Request;

class RingtoneController extends Controller
{
    public function get()
    {
        $ringtone = Ringtone::get();
        return response()->json(['success' => !$ringtone->isEmpty(), 'data' => $ringtone]);
    }

    public function index()
    {
        return $this->get();
    }

    public function getMessage()
    {
        $ringtones = Ringtone::where('ringType', 1)->get();
        return response()->json(['success' => !$ringtones->isEmpty(), 'data' => $ringtones]);
    }

    public function getCall()
    {
        $ringtones = Ringtone::where('ringType', 2)->get();
        return response()->json(['success' => !$ringtones->isEmpty(), 'data' => $ringtones]);
    }

    public function getNotification()
    {
        $ringtones = Ringtone::where('ringType', 3)->get();
        return response()->json(['success' => !$ringtones->isEmpty(), 'data' => $ringtones]);
    }

    public function store(Request $request)
    {
        $response_msg = $request->ringType == '1' ? 'Message' : ($request->ringType == '3' ? 'Notification' : 'Call');
        if (!empty($request->audio_paths)) {
            foreach ($request->audio_paths as $key => $path) {
                try {
                    Ringtone::updateOrCreate(['_id' => $request->id], [
                        'fileName' => $request->audio_filename[$key],
                        'filePath' => $path,
                        'ringType' => intval($request->ringType),
                        'fileSize' => $request->audio_size[$key]
                    ]);
                } catch (\Throwable $e) {
                    return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
                }
            }
            return response()->json(['success' => true, 'message' => $response_msg . ' ringtone has been updated']);
        }
        return response()->json(['success' => false, 'message' => 'No audio paths provided.'], 400);
    }

    public function destroy($id)
    {
        try {
            $ringtone = Ringtone::find($id);
            if (!$ringtone) return response()->json(['success' => false, 'message' => 'Ringtone not found.'], 404);
            $ringtone->delete();
            return response()->json(['success' => true, 'message' => 'Ringtone has been deleted.'], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete ringtone.', 'error' => $e->getMessage()], 500);
        }
    }
}
