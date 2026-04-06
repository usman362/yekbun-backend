<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsAdminController extends Controller
{
    public function show()
    {
        $s = Setting::first();

        return ResponseHelper::sendResponse($s ? $s->toArray() : new \stdClass, 'Settings loaded.');
    }

    public function update(Request $request)
    {
        $s = Setting::first() ?? new Setting;
        $s->fill($request->all());
        $s->save();

        return ResponseHelper::sendResponse($s, 'Settings saved.');
    }
}
