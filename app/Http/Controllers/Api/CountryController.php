<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\User;
use Illuminate\Http\Request;

class CountryController extends Controller
{
    public function index()
    {
        $countries = Country::select('name', 'flag_path')->orderBy('name', 'ASC')->get();
        return response()->json(['countries' => $countries], 200);
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string']);
        $country = Country::create($request->all());
        return response()->json(['message' => 'Country successfully added.', 'country' => $country], 201);
    }

    public function update(Request $request, $id)
    {
        $request->validate(['name' => 'required|string']);
        $country = Country::find($id);
        if (!$country) return response()->json(['message' => 'Country not found.'], 404);
        $country->fill($request->all());
        $country->save();
        return response()->json(['message' => 'Country successfully updated.', 'country' => $country], 201);
    }

    public function destroy($id)
    {
        $country = Country::find($id);
        if (!$country) return response()->json(['message' => 'Country not found.'], 404);
        $country->delete();
        return response()->json(['message' => 'Country successfully deleted.'], 201);
    }

    public function kurdishPeoples(Request $request)
    {
        $users = User::where('origin', 'kurdish')->select('username', 'image');
        if (!empty($request->province)) $users->where('province', $request->province);
        if (!empty($request->city)) $users->where('city', $request->city);
        return ResponseHelper::sendResponse($users->get(), 'Kurdish Peoples Successfully Fetch!');
    }

    public function allCountries()
    {
        $countries = Country::select('name', 'flag_path')->orderBy('name', 'ASC')->get();
        return ResponseHelper::sendResponse($countries, 'Countries fetched successfully');
    }

    public function search_location(Request $request)
    {
        $searchval = $request->search;
        $results = \App\Models\City::where('name', 'like', '%' . $searchval . '%')->orderBy('name', 'asc')->get();
        $array = [];
        foreach ($results as $row) {
            $array[] = optional($row->country)->name . ' ' . optional($row->region)->name . ' ' . $row->name;
        }
        return response()->json(['message' => 'Ok', 'locations' => $array], 201);
    }
}
