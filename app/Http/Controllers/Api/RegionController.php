<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Region;
use App\Models\Country;
use Illuminate\Http\Request;

class RegionController extends Controller
{
    public function index()
    {
        $regions = Region::orderBy('name', 'ASC')->with('cities')->get();
        $countries = Country::orderBy('name', 'ASC')->get();
        return response()->json(['regions' => $regions, 'countries' => $countries]);
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string', 'country_id' => 'required']);
        Region::create($request->all());
        return response()->json(['success' => 'true', 'message' => 'Region created successfully']);
    }

    public function update(Request $request, $id)
    {
        $request->validate(['name' => 'required|string']);
        $region = Region::find($id);
        if (!$region) return response()->json(['message' => 'Region not found.'], 404);
        $region->fill($request->all());
        $region->save();
        return response()->json(['success' => 'true', 'message' => 'Region updated successfully']);
    }

    public function destroy($id)
    {
        $region = Region::find($id);
        if (!$region) return response()->json(['message' => 'Region not found.'], 404);
        $region->delete();
        return response()->json(['success' => 'true', 'message' => 'Region Deleted successfully']);
    }
}
