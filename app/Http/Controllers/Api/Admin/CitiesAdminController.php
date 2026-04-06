<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use Illuminate\Http\Request;

class CitiesAdminController extends Controller
{
    public function index(Request $request)
    {
        $q = City::query()->with(['region', 'country']);

        if ($request->filled('search')) {
            $s = $request->search;
            $q->where(function ($qq) use ($s) {
                $qq->where('name', 'like', '%' . $s . '%')
                    ->orWhere('zipcode', 'like', '%' . $s . '%');
            });
        }
        if ($request->filled('country_id')) {
            $q->where('country_id', $request->country_id);
        }
        if ($request->filled('region_id')) {
            $q->where('region_id', $request->region_id);
        }

        $perPage = min((int) $request->get('per_page', 20), 100);
        $paginator = $q->orderBy('name')->paginate($perPage);

        $items = collect($paginator->items())->map(function ($city) {
            return [
                'id' => (string) $city->_id,
                'name' => $city->name,
                'zipCode' => $city->zipcode ?? '',
                'countryId' => $city->country_id ? (string) $city->country_id : '',
                'regionId' => $city->region_id ? (string) $city->region_id : '',
                'countryName' => optional($city->country)->name ?? '',
                'regionName' => optional($city->region)->name ?? '',
                'active' => ($city->status ?? 1) == 1 || ($city->status ?? '1') === '1',
            ];
        });

        return ResponseHelper::sendResponse([
            'cities' => $items,
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ], 'Cities loaded.');
    }

    public function meta()
    {
        return ResponseHelper::sendResponse([
            'countries' => Country::orderBy('name')->get()->map(fn ($c) => [
                'id' => (string) $c->_id,
                'name' => $c->name,
            ]),
            'regions' => Region::orderBy('name')->get()->map(fn ($r) => [
                'id' => (string) $r->_id,
                'name' => $r->name,
                'country_id' => (string) $r->country_id,
            ]),
        ], 'Meta loaded.');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'region_id' => 'required|string',
            'country_id' => 'required|string',
        ]);
        $city = City::create([
            'name' => $request->name,
            'region_id' => $request->region_id,
            'country_id' => $request->country_id,
            'zipcode' => $request->input('zipCode', ''),
            'status' => $request->boolean('active', true) ? 1 : 0,
        ]);

        return ResponseHelper::sendResponse(['id' => (string) $city->_id], 'City created.');
    }

    public function update(Request $request, string $id)
    {
        $city = City::find($id);
        if (!$city) {
            return ResponseHelper::sendResponse([], 'City not found.', false, 404);
        }
        if ($request->has('name')) {
            $city->name = $request->name;
        }
        if ($request->has('zipCode')) {
            $city->zipcode = $request->zipCode;
        }
        if ($request->has('region_id')) {
            $city->region_id = $request->region_id;
        }
        if ($request->has('country_id')) {
            $city->country_id = $request->country_id;
        }
        if ($request->has('active')) {
            $city->status = $request->boolean('active') ? 1 : 0;
        }
        $city->save();

        return ResponseHelper::sendResponse($city, 'City updated.');
    }

    public function destroy(string $id)
    {
        $city = City::find($id);
        if (!$city) {
            return ResponseHelper::sendResponse([], 'City not found.', false, 404);
        }
        $city->delete();

        return ResponseHelper::sendResponse([], 'City deleted.');
    }
}
