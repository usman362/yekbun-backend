<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\Helpers;
use App\Models\Ringtone;
use Illuminate\Http\Request;

class RingtoneController extends Controller
{
    /** GET /api/ringtone — active ringtones with full CDN URLs (mobile list). */
    public function get()
    {
        $data = Ringtone::orderBy('created_at', 'desc')->get()
            ->filter(fn($r) => $r->is_active ?? ($r->ringType == 1))
            ->map(fn($r) => [
                'id'        => (string) $r->_id,
                'name'      => $r->fileName ?? 'Ringtone',
                'url'       => Helpers::mediaUrl($r->filePath) ?? '',
                'duration'  => $r->duration ?? '0:00',
                'size'      => $r->fileSize ?? '',
                'downloads' => (int) ($r->downloads ?? 0),
            ])
            ->values();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /** POST /api/ringtone/{id}/download — increment the download counter, return the file URL. */
    public function download($id)
    {
        $r = Ringtone::find($id);
        if (!$r) return response()->json(['success' => false, 'message' => 'Ringtone not found.'], 404);

        $r->downloads = (int) ($r->downloads ?? 0) + 1;
        $r->save();

        return response()->json([
            'success'   => true,
            'url'       => Helpers::mediaUrl($r->filePath) ?? '',
            'downloads' => (int) $r->downloads,
        ]);
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
