<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Language;
use Illuminate\Http\Request;

class LanguageController extends Controller
{
    public function index()
    {
        $languages = Language::with('translations')->where('status', '1')->get();
        $response = [];
        foreach ($languages as $language) {
            $translations = [];
            foreach ($language->translations as $translation) {
                $translations[$translation->keyword] = $translation->translated !== '' ? $translation->translated : $translation->keyword;
            }
            $response[$language->code] = ['translation' => $translations];
        }
        return response()->json($response);
    }

    public function languagesList()
    {
        $languages = Language::where('status', '1')->get();
        return response()->json(['languages' => $languages], 200);
    }

    public function store(Request $request)
    {
        $request->validate(['icon' => 'required', 'title' => 'required']);
        $language = new Language();
        $language->title = $request->title;
        $language->status = $request->status;
        if ($request->has('icon')) {
            $image_path = $request->file('icon')->store('/images/language/icon', 'public');
            $language->icon = $image_path;
        }
        if ($language->save()) {
            return response()->json(['language' => $language, 'message' => 'Your language has been created successfully.'], 201);
        }
        return response()->json(['language' => $language, 'message' => 'Your language has not been created successfully.'], 403);
    }

    public function update(Request $request, $id)
    {
        $request->validate(['title' => 'required']);
        $language = Language::find($id);
        if (!$language) return response()->json(['success' => false, 'message' => 'Language not found.'], 404);
        $language->title = $request->title;
        $language->status = $request->status;
        if ($request->hasFile('icon')) {
            $image_path = $request->file('icon')->store('/images/language/icon', 'public');
            $language->icon = $image_path;
        }
        if ($language->save()) {
            return response()->json(['language' => $language, 'message' => 'Your language has been updated successfully.'], 201);
        }
        return response()->json(['language' => $language, 'message' => 'Your language has not been updated.'], 403);
    }

    public function destroy($id)
    {
        $language = Language::find($id);
        if (!$language) return response()->json(['success' => false, 'message' => 'Language not found.'], 404);
        try {
            $language->delete();
            return response()->json(['success' => true, 'message' => 'Your language has been deleted successfully.'], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete language.', 'error' => $e->getMessage()], 500);
        }
    }

    public function keywords($id)
    {
        $keywords = \App\Models\Translation::where('language_id', $id)
            ->get(['section_name', 'keyword', 'translated']);
        return response()->json(['keywords' => $keywords], 200);
    }

    public function keyword($id, $keyword)
    {
        $row = \App\Models\Translation::where('language_id', $id)
            ->where('keyword', $keyword)
            ->first(['section_name', 'keyword', 'translated']);
        if (!empty($row)) {
            return response()->json(['keyword' => $row], 200);
        }
        return response()->json(['message' => 'Not Found!'], 404);
    }
}
