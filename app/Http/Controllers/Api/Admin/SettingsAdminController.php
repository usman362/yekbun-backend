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

    /* ───────── Tier-based user permissions (cultivated / educated / academic) ───────── */

    private function tierName(string $tier): ?string
    {
        $allowed = ['cultivated', 'educated', 'academic'];
        return in_array($tier, $allowed, true) ? $tier : null;
    }

    public function tierShow(string $tier)
    {
        $name = $this->tierName($tier);
        if (!$name) {
            return ResponseHelper::sendResponse(null, 'Invalid tier', false, 400);
        }

        $row = Setting::where('name', $name)->first();
        $value = $row ? ($row->value ?? new \stdClass()) : new \stdClass();

        return ResponseHelper::sendResponse([
            'tier'  => $name,
            'value' => $value,
        ], 'Tier settings loaded.');
    }

    public function tierUpdate(Request $request, string $tier)
    {
        $name = $this->tierName($tier);
        if (!$name) {
            return ResponseHelper::sendResponse(null, 'Invalid tier', false, 400);
        }

        $patch = $request->input('value', []);
        if (!is_array($patch)) {
            return ResponseHelper::sendResponse(null, 'value must be an object', false, 422);
        }

        $row = Setting::firstOrNew(['name' => $name]);
        $current = is_array($row->value) ? $row->value : [];
        $row->value = array_merge($current, $patch);
        $row->save();

        return ResponseHelper::sendResponse([
            'tier'  => $name,
            'value' => $row->value,
        ], 'Tier settings saved.');
    }
}
