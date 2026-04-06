<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use Illuminate\Http\Request;

class LocationAdminController extends Controller
{
    public function tree()
    {
        $countries = Country::orderBy('name')->get();
        $data = $countries->map(function ($c) {
            $regions = Region::where('country_id', $c->_id)->orderBy('name')->get();
            return [
                'id' => (string) $c->_id,
                'name' => $c->name,
                'code' => $c->code ?? '',
                'flag' => $c->icon_code ?? $c->flag_path ?? '🏴',
                'active' => ($c->status ?? 1) == 1 || ($c->status ?? '1') === '1',
                'totalPeople' => 0,
                'provinces' => $regions->map(function ($r) {
                    $cityCount = City::where('region_id', $r->_id)->count();
                    return [
                        'id' => (string) $r->_id,
                        'name' => $r->name,
                        'shortCode' => $r->shortcode ?? '',
                        'totalPeople' => 0,
                        'totalCities' => $cityCount,
                        'active' => ($r->status ?? 1) == 1 || ($r->status ?? '1') === '1',
                    ];
                })->values(),
            ];
        });

        return ResponseHelper::sendResponse($data, 'Locations loaded.');
    }

    public function storeCountry(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);
        $country = Country::create([
            'name' => $request->name,
            'code' => $request->input('code', ''),
            'icon_code' => $request->input('flag', '🏴'),
            'flag_path' => $request->input('flag_path'),
            'status' => $request->boolean('active', true) ? 1 : 0,
        ]);

        return ResponseHelper::sendResponse(['id' => (string) $country->_id, 'country' => $country], 'Country created.');
    }

    public function updateCountry(Request $request, string $id)
    {
        $country = Country::find($id);
        if (!$country) {
            return ResponseHelper::sendResponse([], 'Country not found.', false, 404);
        }
        $country->fill(array_filter([
            'name' => $request->input('name'),
            'code' => $request->input('code'),
            'icon_code' => $request->input('flag'),
            'flag_path' => $request->input('flag_path'),
            'status' => $request->has('active') ? ($request->boolean('active') ? 1 : 0) : null,
        ], fn ($v) => $v !== null));
        $country->save();

        return ResponseHelper::sendResponse($country, 'Country updated.');
    }

    public function destroyCountry(string $id)
    {
        $country = Country::find($id);
        if (!$country) {
            return ResponseHelper::sendResponse([], 'Country not found.', false, 404);
        }
        foreach (Region::where('country_id', $country->_id)->get() as $r) {
            City::where('region_id', $r->_id)->delete();
            $r->delete();
        }
        $country->delete();

        return ResponseHelper::sendResponse([], 'Country deleted.');
    }

    public function storeRegion(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'country_id' => 'required|string',
        ]);
        $region = Region::create([
            'name' => $request->name,
            'country_id' => $request->country_id,
            'shortcode' => $request->input('shortCode', ''),
            'status' => $request->boolean('active', true) ? 1 : 0,
        ]);

        return ResponseHelper::sendResponse(['id' => (string) $region->_id, 'region' => $region], 'Region created.');
    }

    public function updateRegion(Request $request, string $id)
    {
        $region = Region::find($id);
        if (!$region) {
            return ResponseHelper::sendResponse([], 'Region not found.', false, 404);
        }
        if ($request->has('name')) {
            $region->name = $request->name;
        }
        if ($request->has('shortCode')) {
            $region->shortcode = $request->shortCode;
        }
        if ($request->has('active')) {
            $region->status = $request->boolean('active') ? 1 : 0;
        }
        $region->save();

        return ResponseHelper::sendResponse($region, 'Region updated.');
    }

    public function destroyRegion(string $id)
    {
        $region = Region::find($id);
        if (!$region) {
            return ResponseHelper::sendResponse([], 'Region not found.', false, 404);
        }
        City::where('region_id', $region->_id)->delete();
        $region->delete();

        return ResponseHelper::sendResponse([], 'Region deleted.');
    }
}
