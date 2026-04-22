<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Region;
use App\Models\Country;
use Illuminate\Http\Request;

class CityController extends Controller
{
    public function index()
    {
        $cities = City::orderBy('name', 'ASC')->get();
        $regions = Region::orderBy('name', 'ASC')->get();
        $countries = Country::orderBy('name', 'ASC')->get();
        return response()->json(['regions' => $regions, 'countries' => $countries, 'cities' => $cities]);
    }

    public function getCities(Request $request)
    {
        $query = City::orderBy('name', 'ASC');
        if (!empty($request->region_id)) $query->where('region_id', $request->region_id);
        if (!empty($request->search)) $query->where('name', 'LIKE', $request->search . '%');

        if (!empty($request->limit)) {
            if ($request->limit !== 'all') {
                return ResponseHelper::sendResponse($query->limit((int) $request->limit)->get(), 'Cities fetched successfully!');
            }
            return ResponseHelper::sendResponse($query->get(), 'Cities fetched successfully!');
        }

        $cities = $query->paginate(15);
        $data = [
            'cities' => $cities->items(),
            'pagination' => [
                'page' => $cities->currentPage(),
                'count' => $cities->perPage(),
                'totalItems' => $cities->total(),
                'totalPages' => $cities->lastPage(),
            ]
        ];
        return ResponseHelper::sendResponse($data, 'Cities fetched successfully!');
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string', 'region_id' => 'required', 'country_id' => 'required']);
        if ($request->has('cities') && is_array($request->cities)) {
            foreach ($request->cities as $city) {
                City::create(array_merge($request->except('cities'), ['zipcode' => $city['zipcode'] ?? null, 'name' => $city['name']]));
            }
        } else {
            City::create($request->all());
        }
        return response()->json(['success' => 'true', 'message' => 'Cities created successfully']);
    }

    public function update(Request $request, $id)
    {
        $request->validate(['name' => 'required|string']);
        $city = City::find($id);
        if (!$city) return response()->json(['message' => 'City not found.'], 404);
        $city->fill($request->all());
        $city->save();
        return response()->json(['success' => 'true', 'message' => 'City Updated successfully']);
    }

    public function destroy($id)
    {
        $city = City::find($id);
        if (!$city) return response()->json(['message' => 'City not found.'], 404);
        $city->delete();
        return response()->json(['success' => 'true', 'message' => 'City Deleted successfully']);
    }

    public function allCities(Request $request)
    {
        $country = Country::where('name', $request->country_name)->first();
        if (!$country) return ResponseHelper::sendResponse([], 'Invalid Country', false, 422);
        $cities = City::where('country_id', $country->conid)->select('name', 'country_id')->get();
        return ResponseHelper::sendResponse($cities, 'Cities have been fetched successfully');
    }
}
