<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

use Maklad\Permission\Models\Permission;

class UserRolesController extends Controller
{
    public function educated()
    {
        $userLevel = 'educated';
        $modules = $this->getModules($userLevel);
        $permissions = Setting::where('name', $userLevel)->first();
        return response()->json(['permissions' => $permissions['value'] ?? []], 200);
    }

    public function cultivated()
    {
        $userLevel = 'cultivated';
        $modules = $this->getModules($userLevel);
        $permissions = Setting::where('name', $userLevel)->first();
        return response()->json(['permissions' => $permissions['value'] ?? []], 200);
    }

    public function academic()
    {
        $userLevel = 'academic';
        $modules = $this->getModules($userLevel);
        $permissions = Setting::where('name', $userLevel)->first();
        return response()->json(['permissions' => $permissions['value'] ?? []], 200);
    }

    public function prices($userLevel)
    {
        $permissions = Setting::where('name', $userLevel)->first();
        $monthly_price = $permissions['value']['monthly_price'] ?? 0;
        $yearly_price = $permissions['value']['yearly_price'] ?? 0;
        $music_playlist_price_amount = $permissions['value']['music_playlist_price_amount'] ?? 0;
        $video_playlist_price_amount = $permissions['value']['video_playlist_price_amount'] ?? 0;
        $data = ['monthly_price' => $monthly_price,'yearly_price' => $yearly_price,
        'music_playlist_price' => $music_playlist_price_amount, 'video_playlist_price' => $video_playlist_price_amount];
        return response()->json($data, 200);
    }

    protected function getModules($userLevel = 'standard')
    {
        $modules = json_decode(file_get_contents(base_path('resources/data/modules.json')));

        // Get permission names from Settings
        $settingNamesChunks = array_map(function ($module) use ($userLevel) {
            $settingName = $userLevel . "_" . $module->name;
            return array_map(function ($permission) use ($settingName) {
                $settingName = $settingName . "_" . $permission->name;
                return $settingName;
            }, $module->userPermissions);
        }, $modules);

        $settingNames = [];
        foreach ($settingNamesChunks as $chunk) {
            $settingNames = array_merge($settingNames, $chunk);
        }

        // Get settings from database
        $settings = Setting::whereIn('name', $settingNames)->get();

        // Attach values from settings to their respective permissions
        $modules = array_map(function ($module) use ($userLevel, $settings) {
            $settingName = $userLevel . "_" . $module->name;
            $module->userPermissions = array_map(function ($permission) use ($settingName, $settings) {
                $settingName = $settingName . "_" . $permission->name;
                $setting = $settings->search(function ($item) use ($settingName) {
                    return $item->name === $settingName;
                });
                if ($setting === false) { // then create one
                    $setting = Setting::create([
                        'name' => $settingName,
                        'value' => $permission->defaultValue?? null
                    ]);
                } else {
                    $setting = $settings->get($setting);
                }

                $permission->value = $setting->value;
                return $permission;
            }, $module->userPermissions);
            return $module;
        }, $modules);

        return $modules;
    }
}
