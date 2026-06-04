<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\CmsMedia;
use App\Models\CmsPage;
use App\Models\CmsTranslation;

/**
 * Public read-only access to the admin-managed CMS content. Mirrors the admin
 * controller's shapes so the public yekbun.app can swap in / out without an extra
 * adapter layer. Cacheable with `Cache::remember()` if traffic warrants it.
 */
class CmsApiController extends Controller
{
    /**
     * GET /api/cms/pages
     * Returns: { pages: { landing: {hero.title: "...", hero.subtitle: "..."}, login: {...}, ... } }
     *
     * Shape is page → flat key:value map so the public app can do `pages.landing["hero.title"]`
     * without iterating a fields array on every render.
     */
    public function pages()
    {
        $rows = CmsPage::orderBy('sort_order')->orderBy('name')->get();

        $out = [];
        foreach ($rows as $p) {
            $map = [];
            foreach ((array) ($p->fields ?? []) as $f) {
                if (!isset($f['key'])) continue;
                $map[$f['key']] = $f['value'] ?? '';
            }
            $pageId = $p->id ?? (string) $p->_id;
            $out[$pageId] = $map;
        }

        return ResponseHelper::sendResponse(['pages' => $out], 'CMS pages fetched');
    }

    /**
     * GET /api/cms/translations
     * Returns: { overrides: { "common.cancel": { en: "...", de: "...", ku: "...", ar: "..." } } }
     *
     * Public app merges these on top of its bundled i18n catalogue (which provides
     * fallbacks for keys the admin never customized).
     */
    public function translations()
    {
        $overrides = [];
        foreach (CmsTranslation::all() as $t) {
            $overrides[$t->key] = is_array($t->values) ? $t->values : [];
        }

        return ResponseHelper::sendResponse(['overrides' => $overrides], 'Translations fetched');
    }

    /**
     * GET /api/cms/media
     * Returns: { slots: { "landing.hero": "https://cdn...", "global.logo": "...", ... } }
     *
     * Empty-URL slots are skipped so the public app falls back to its bundled default
     * (no "broken image" placeholder unless the admin explicitly cleared a slot).
     */
    public function media()
    {
        $slots = [];
        foreach (CmsMedia::orderBy('sort_order')->get() as $m) {
            if (!empty($m->url)) {
                $slots[$m->slot] = $m->url;
            }
        }

        return ResponseHelper::sendResponse(['slots' => $slots], 'CMS media fetched');
    }
}
