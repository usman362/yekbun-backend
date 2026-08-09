<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Cities;
use App\Models\Region;
use App\Models\Country;
use App\Models\Countries;
use Illuminate\Http\Request;
use MongoDB\BSON\ObjectId;

class CityController extends Controller
{
    public function index()
    {
        $cities = City::orderBy('name', 'ASC')->get();
        $regions = Region::orderBy('sort_order', 'ASC')->orderBy('name', 'ASC')->get();
        $countries = Country::orderBy('name', 'ASC')->get();
        return response()->json(['regions' => $regions, 'countries' => $countries, 'cities' => $cities]);
    }

    public function getCities(Request $request)
    {
        $query = City::query();
        if (!empty($request->region_id)) {
            $query->where('region_id', $request->region_id);
        }
        if (!empty($request->country_id)) {
            $query->where('country_id', $request->country_id);
        }
        if (!empty($request->search)) {
            $query->where('name', 'LIKE', $request->search . '%');
        }

        if ($request->limit === 'all') {
            return ResponseHelper::sendResponse($query->orderBy('name', 'ASC')->get(), 'Cities fetched successfully!');
        }

        if (!empty($request->limit) && !$request->has('cursor') && !$request->has('per_page')) {
            return ResponseHelper::sendResponse(
                $query->orderBy('name', 'ASC')->limit((int) $request->limit)->get(),
                'Cities fetched successfully!'
            );
        }

        // Legacy offset pagination (page=)
        if ($request->has('page') && !$request->has('cursor')) {
            $perPage = max(1, min((int) $request->get('per_page', 15), 100));
            $cities = $query->orderBy('name', 'ASC')->paginate($perPage);
            return ResponseHelper::sendResponse([
                'cities' => $cities->items(),
                'pagination' => [
                    'page' => $cities->currentPage(),
                    'count' => $cities->perPage(),
                    'totalItems' => $cities->total(),
                    'totalPages' => $cities->lastPage(),
                ],
            ], 'Cities fetched successfully!');
        }

        // Cursor pagination — same envelope as feeds/media
        $perPage = max(1, min((int) $request->get('per_page', 15), 100));
        $query->orderBy('name', 'ASC')->orderBy('_id', 'ASC');
        $this->applyCityCursor($query, $request->get('cursor'));

        $cities = $query->limit($perPage + 1)->get();
        $hasMore = $cities->count() > $perPage;
        if ($hasMore) {
            $cities = $cities->take($perPage)->values();
        } else {
            $cities = $cities->values();
        }
        $last = $cities->last();

        return ResponseHelper::sendResponse([
            'cities' => $cities,
            'pagination' => [
                'per_page' => $perPage,
                'next_cursor' => $last ? $this->encodeCityCursor((string) $last->name, (string) $last->_id) : null,
                'has_more' => $hasMore,
            ],
        ], 'Cities fetched successfully!');
    }

    private function encodeCityCursor(string $name, string $id): string
    {
        return rtrim(strtr(base64_encode(json_encode(['n' => $name, 'id' => $id])), '+/', '-_'), '=');
    }

    private function decodeCityCursor(?string $cursor): ?array
    {
        if (!$cursor) {
            return null;
        }
        $pad = strlen($cursor) % 4;
        if ($pad) {
            $cursor .= str_repeat('=', 4 - $pad);
        }
        $raw = base64_decode(strtr($cursor, '-_', '+/'), true);
        if ($raw === false) {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['id'])) {
            return null;
        }
        return [
            'name' => (string) ($data['n'] ?? ''),
            'id' => (string) $data['id'],
        ];
    }

    private function applyCityCursor($query, $cursor): void
    {
        if (!$cursor) {
            return;
        }
        $decoded = $this->decodeCityCursor((string) $cursor);
        if ($decoded) {
            $name = $decoded['name'];
            try {
                $oid = new ObjectId($decoded['id']);
            } catch (\Throwable $e) {
                return;
            }
            $query->where(function ($q) use ($name, $oid) {
                $q->where('name', '>', $name)
                    ->orWhere(function ($q2) use ($name, $oid) {
                        $q2->where('name', $name)->where('_id', '>', $oid);
                    });
            });
            return;
        }
        try {
            $query->where('_id', '>', new ObjectId((string) $cursor));
        } catch (\Throwable $e) {
            // invalid cursor → first page
        }
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
        $country = Countries::where('name', $request->country_name)->first();
        if (!$country) return ResponseHelper::sendResponse([], 'Invalid Country', false, 422);
        $cities = Cities::select(['name', 'country_id'])
            ->where('country_id', $country->conid)
            ->get();
        return ResponseHelper::sendResponse($cities, 'Cities have been fetched successfully');
    }
}
