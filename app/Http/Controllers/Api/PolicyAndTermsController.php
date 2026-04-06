<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PolicyAndTerm;
use Illuminate\Http\Request;

class PolicyAndTermsController extends Controller
{
    public function index()
    {
        $data = PolicyAndTerm::all();
        return response()->json(['policy' => $data], 200);
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required']);
        $policy = PolicyAndTerm::create(['name' => $request->name]);
        return response()->json(['message' => 'New privacy policy and terms section has been created.', 'policy' => $policy], 201);
    }

    public function show($id)
    {
        $policy = PolicyAndTerm::find($id);
        if (!$policy) return response()->json(['message' => 'Not found.'], 404);
        return response()->json(['policy' => $policy], 200);
    }

    public function update(Request $request, $id)
    {
        $policy = PolicyAndTerm::find($id);
        if (!$policy) return response()->json(['message' => 'Not found.'], 404);
        $policy->fill($request->all());
        $policy->save();
        return response()->json(['message' => 'Updated successfully.', 'policy' => $policy], 200);
    }

    public function destroy($id)
    {
        $policy = PolicyAndTerm::find($id);
        if (!$policy) return response()->json(['message' => 'Not found.'], 404);
        $policy->delete();
        return response()->json(['message' => 'Privacy policy and terms section has been deleted.', 'policy' => $policy], 201);
    }

    public function saveFileds(Request $request)
    {
        $request->validate(['privacy_policy' => 'required']);
        $check = PolicyAndTerm::find($request->id);
        if (!$check) return response()->json(['message' => 'Not found.'], 404);
        $check->privacy_policy = $request->privacy_policy;
        if ($check->save()) {
            return response()->json(['message' => 'Privacy Policy and term updated successfully.', 'policy' => $check], 201);
        }
        return response()->json(['message' => 'Failed to update Privacy Policy and term.'], 403);
    }
}
