<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\AppVersion;
use Illuminate\Http\Request;

class AppVersionController extends Controller
{
    public function getVersion(Request $request)
    {
        $version = AppVersion::first();
        if (!$version) {
            $version = AppVersion::create(['version_number' => '0.00']);
        }
        if ($request->filled('version_number')) {
            $version->update(['version_number' => $request->version_number]);
        }
        return ResponseHelper::sendResponse($version->version_number, 'Version fetched Successfully');
    }

    public function updateVersion(Request $request)
    {
        $version = AppVersion::first();
        if (!$version) {
            $version = AppVersion::create(['version_number' => '0.00']);
        }
        if ($request->filled('version_number')) {
            $version->update(['version_number' => $request->version_number]);
        }
        return ResponseHelper::sendResponse($version->version_number, 'Version fetched Successfully');
    }
}
