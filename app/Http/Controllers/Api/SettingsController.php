<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppInfo;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function app_info(Request $request)
    {
        $app_info = AppInfo::latest()->first();
        return response()->json(['app_info' => $app_info], 200);
    }
}
