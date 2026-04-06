<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\PolicyAndTerm;
use Illuminate\Http\Request;

class PolicyAdminController extends Controller
{
    public function index()
    {
        $policies = PolicyAndTerm::orderBy('name')->get()->map(fn ($p) => [
            'id' => (string) $p->_id,
            'name' => $p->name,
            'privacy_policy' => $p->privacy_policy ?? '',
            'disclaimer' => $p->disclaimer ?? '',
        ]);

        return ResponseHelper::sendResponse($policies, 'Policies loaded.');
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);
        $policy = PolicyAndTerm::create(['name' => $request->name]);

        return ResponseHelper::sendResponse(['id' => (string) $policy->_id], 'Policy section created.');
    }

    public function update(Request $request, string $id)
    {
        $policy = PolicyAndTerm::find($id);
        if (!$policy) {
            return ResponseHelper::sendResponse([], 'Not found.', false, 404);
        }
        if ($request->has('name')) {
            $policy->name = $request->name;
        }
        if ($request->has('privacy_policy')) {
            $policy->privacy_policy = $request->privacy_policy;
        }
        if ($request->has('disclaimer')) {
            $policy->disclaimer = $request->disclaimer;
        }
        $policy->save();

        return ResponseHelper::sendResponse($policy, 'Updated.');
    }

    public function destroy(string $id)
    {
        $policy = PolicyAndTerm::find($id);
        if (!$policy) {
            return ResponseHelper::sendResponse([], 'Not found.', false, 404);
        }
        $policy->delete();

        return ResponseHelper::sendResponse([], 'Deleted.');
    }
}
