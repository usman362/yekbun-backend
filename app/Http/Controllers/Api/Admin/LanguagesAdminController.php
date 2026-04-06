<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\Translation;
use Illuminate\Http\Request;

class LanguagesAdminController extends Controller
{
    public function index()
    {
        $languages = Language::orderBy('title')->get()->map(function ($lang) {
            return [
                'id' => (string) $lang->_id,
                'name' => $lang->title ?? $lang->name ?? '',
                'code' => $lang->code ?? '',
                'icon' => $lang->icon ?? null,
                'active' => ($lang->status ?? '1') === '1' || ($lang->status ?? 1) == 1,
            ];
        });

        return ResponseHelper::sendResponse($languages, 'Languages loaded.');
    }

    public function keywords(string $id)
    {
        $language = Language::with('translations')->find($id);
        if (!$language) {
            return ResponseHelper::sendResponse([], 'Language not found.', false, 404);
        }
        $rows = $language->translations->map(function ($t) {
            return [
                'id' => (string) $t->_id,
                'keyword' => $t->keyword ?? $t->text_id ?? '',
                'translated' => $t->translated ?? $t->translation ?? '',
            ];
        });

        return ResponseHelper::sendResponse($rows, 'Keywords loaded.');
    }

    public function updateKeyword(Request $request, string $languageId, string $translationId)
    {
        $request->validate(['translated' => 'required|string']);
        $language = Language::find($languageId);
        if (!$language) {
            return ResponseHelper::sendResponse([], 'Language not found.', false, 404);
        }
        $tr = Translation::find($translationId);
        if (!$tr || (string) $tr->language_id !== (string) $language->_id) {
            return ResponseHelper::sendResponse([], 'Translation not found.', false, 404);
        }
        $tr->translated = $request->translated;
        $tr->translation = $request->translated;
        $tr->save();

        return ResponseHelper::sendResponse($tr, 'Translation saved.');
    }
}
