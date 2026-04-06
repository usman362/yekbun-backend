<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\Translation;
use Illuminate\Http\Request;

class TranslationController extends Controller
{
    public function fetch_languages()
    {
        $languages = Language::all()->map(function ($item) {
            return [
                'id' => $item->_id,
                'title' => $item->title,
                'icon' => $item->icon,
            ];
        });
        return response()->json(['success' => true, 'data' => $languages]);
    }

    public function translate($id)
    {
        $language = Language::find($id);
        if (!$language) return response()->json(['success' => false, 'message' => 'Language not found.'], 404);

        $translations = Translation::where('language_id', $id)->get();
        $data = [];
        foreach ($translations as $translation) {
            $data[$translation->keyword] = $translation->translated !== '' ? $translation->translated : $translation->keyword;
        }
        return response()->json(['success' => true, 'data' => $data]);
    }
}
