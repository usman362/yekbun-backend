<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Helpers\Helpers;
use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\LanguageDetail;
use Illuminate\Http\Request;

class LanguagesAdminController extends Controller
{
    /** Language flags live on API public disk (not CDN) — same as old admin Languages. */
    private function iconUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }
        $path = trim($path);
        if ($path === '') {
            return null;
        }
        // Legacy base64 previews accidentally stored in Mongo — pass through so UI still renders.
        if (str_starts_with($path, 'data:') || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Helpers::storageUrl($path);
    }

    public function index()
    {
        $languages = Language::orderBy('title')->get()->map(function ($lang) {
            return [
                'id'        => (string) $lang->_id,
                'name'      => $lang->title ?? $lang->name ?? '',
                'code'      => $lang->code ?? '',
                'icon'      => $this->iconUrl($lang->icon ?? null),
                'direction' => $lang->direction ?? 'ltr',
                'active'    => ($lang->status ?? '1') === '1' || ($lang->status ?? 1) == 1,
            ];
        });

        return ResponseHelper::sendResponse($languages, 'Languages loaded.');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'      => 'required|string|max:255',
            'code'      => 'required|string|max:10',
            'direction' => 'nullable|in:ltr,rtl',
            'status'    => 'nullable|in:0,1',
            'icon'      => 'nullable|image|max:2048',
        ]);

        $lang = new Language();
        $lang->title     = $request->name;
        $lang->name      = $request->name;
        $lang->code      = $request->code;
        $lang->direction = $request->input('direction', 'ltr');
        $lang->status    = (string) $request->input('status', '0');

        if ($request->hasFile('icon')) {
            $lang->icon = Helpers::fileUpload($request->file('icon'), 'images/languages/icon');
        }

        $lang->save();

        return ResponseHelper::sendResponse([
            'id'        => (string) $lang->_id,
            'name'      => $lang->title,
            'code'      => $lang->code,
            'direction' => $lang->direction,
            'icon'      => $this->iconUrl($lang->icon ?? null),
            'active'    => $lang->status === '1',
        ], 'Language created.', true, 201);
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'name'       => 'required|string|max:255',
            'code'       => 'required|string|max:10',
            'direction'  => 'nullable|in:ltr,rtl',
            'status'     => 'nullable|in:0,1',
            'icon'       => 'nullable|image|max:2048',
            'clear_icon' => 'nullable|in:0,1,true,false',
        ]);

        $lang = Language::find($id);
        if (!$lang) {
            return ResponseHelper::sendResponse(null, 'Language not found.', false, 404);
        }

        $lang->title     = $request->name;
        $lang->name      = $request->name;
        $lang->code      = $request->code;
        $lang->direction = $request->input('direction', $lang->direction ?? 'ltr');
        $lang->status    = (string) $request->input('status', $lang->status ?? '0');

        $clear = filter_var($request->input('clear_icon'), FILTER_VALIDATE_BOOLEAN);
        if ($clear) {
            $lang->icon = null;
        } elseif ($request->hasFile('icon')) {
            $lang->icon = Helpers::fileUpload($request->file('icon'), 'images/languages/icon');
        }

        $lang->save();

        return ResponseHelper::sendResponse([
            'id'        => (string) $lang->_id,
            'name'      => $lang->title,
            'code'      => $lang->code,
            'direction' => $lang->direction,
            'icon'      => $this->iconUrl($lang->icon ?? null),
            'active'    => $lang->status === '1',
        ], 'Language updated.');
    }

    public function destroy(string $id)
    {
        $lang = Language::find($id);
        if (!$lang) {
            return ResponseHelper::sendResponse(null, 'Language not found.', false, 404);
        }

        LanguageDetail::where('language_id', (string) $lang->_id)->delete();
        $lang->delete();

        return ResponseHelper::sendResponse(['id' => $id], 'Language deleted.');
    }

    public function exportKeywords(string $id)
    {
        $language = Language::find($id);
        if (!$language) {
            return ResponseHelper::sendResponse([], 'Language not found.', false, 404);
        }

        $rows = LanguageDetail::where('language_id', (string) $language->_id)
            ->get()
            ->map(function ($t) {
                return [
                    'keyword'    => (string) ($t->keyword ?? ''),
                    'translated' => (string) ($t->translated ?? ''),
                ];
            })->values()->toArray();

        $safe = preg_replace('/[^A-Za-z0-9_-]+/', '_', $language->title ?? $language->code ?? 'language');
        $filename = $safe . '-translations.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['keyword', 'translated']);
            foreach ($rows as $r) {
                fputcsv($out, [$r['keyword'], $r['translated']]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function importKeywords(Request $request, string $id)
    {
        $request->validate(['file' => 'required|file']);

        $language = Language::find($id);
        if (!$language) {
            return ResponseHelper::sendResponse(null, 'Language not found.', false, 404);
        }

        $path = $request->file('file')->getRealPath();
        $handle = fopen($path, 'r');
        if (!$handle) {
            return ResponseHelper::sendResponse(null, 'Could not read CSV.', false, 422);
        }

        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return ResponseHelper::sendResponse(null, 'CSV is empty.', false, 422);
        }
        $headers = array_map('strtolower', array_map('trim', $headers));
        $keyIdx  = array_search('keyword', $headers, true);
        $valIdx  = array_search('translated', $headers, true);

        if ($keyIdx === false || $valIdx === false) {
            fclose($handle);
            return ResponseHelper::sendResponse(null, 'CSV must have keyword and translated columns.', false, 422);
        }

        $existing = LanguageDetail::where('language_id', (string) $language->_id)->get()->keyBy(function ($t) {
            return (string) ($t->keyword ?? '');
        });

        $updated = 0;
        $created = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $keyword = trim((string) ($row[$keyIdx] ?? ''));
            $value   = (string) ($row[$valIdx] ?? '');
            if ($keyword === '') continue;

            if (isset($existing[$keyword])) {
                $tr = $existing[$keyword];
                $tr->translated = $value;
                $tr->save();
                $updated++;
            } else {
                LanguageDetail::create([
                    'language_id' => (string) $language->_id,
                    'keyword'     => $keyword,
                    'translated'  => $value,
                ]);
                $created++;
            }
        }
        fclose($handle);

        return ResponseHelper::sendResponse([
            'updated' => $updated,
            'created' => $created,
        ], "Import complete: {$created} created, {$updated} updated.");
    }

    public function keywords(string $id)
    {
        $language = Language::find($id);
        if (!$language) {
            return ResponseHelper::sendResponse([], 'Language not found.', false, 404);
        }
        $rows = LanguageDetail::where('language_id', (string) $language->_id)
            ->orderBy('main_section')
            ->orderBy('section_name')
            ->get()
            ->map(function ($t) {
                return [
                    'id'         => (string) $t->_id,
                    'keyword'    => $t->keyword ?? '',
                    'translated' => $t->translated ?? '',
                    // main_section = top-level SECTION (sidebar); section_name = MODULE (tabs).
                    'section'    => $t->main_section ?? '',
                    'module'     => $t->section_name ?? '',
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
        $tr = LanguageDetail::find($translationId);
        if (!$tr || (string) $tr->language_id !== (string) $language->_id) {
            return ResponseHelper::sendResponse([], 'Translation not found.', false, 404);
        }
        $tr->translated = $request->translated;
        $tr->save();

        return ResponseHelper::sendResponse($tr, 'Translation saved.');
    }
}
