<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Ringtone;
use Carbon\Carbon;
use Illuminate\Http\Request;

class RingtoneController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->get('filter', 'all');

        $query = Ringtone::query();

        if ($filter === 'active') {
            $query->where('ringType', 1);
        } elseif ($filter === 'inactive') {
            $query->where(function ($q) {
                $q->where('ringType', '!=', 1)->orWhereNull('ringType');
            });
        }

        $ringtones = $query->orderBy('created_at', 'desc')->get();

        $result = $ringtones->map(function ($r) {
            $isActive = $r->ringType == 1;
            return [
                'id'        => $r->_id,
                'name'      => $r->fileName ?? 'Untitled',
                'duration'  => '0:00',
                'size'      => $r->fileSize ?? '0 KB',
                'format'    => $this->getFormat($r->fileName ?? ''),
                'status'    => $isActive ? 'active' : 'inactive',
                'downloads' => 0,
                'createdAt' => $r->created_at ? Carbon::parse($r->created_at)->format('Y-m-d') : '',
            ];
        })->values();

        return ResponseHelper::sendResponse($result, 'Ringtones fetched.');
    }

    public function stats()
    {
        $total    = Ringtone::count();
        $active   = Ringtone::where('ringType', 1)->count();
        $inactive = $total - $active;

        return ResponseHelper::sendResponse([
            'total'    => $total,
            'active'   => $active,
            'inactive' => $inactive,
        ], 'Ringtone stats fetched.');
    }

    private function getFormat(string $fileName): string
    {
        $ext = strtoupper(pathinfo($fileName, PATHINFO_EXTENSION));
        return $ext ?: 'M4A';
    }
}
