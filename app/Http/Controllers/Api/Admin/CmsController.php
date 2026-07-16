<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\Helpers;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\CmsMedia;
use App\Models\CmsPage;
use App\Models\CmsTranslation;
use Illuminate\Http\Request;

/**
 * Admin-side CRUD for the WebApp CMS — pages, translations, and media slots.
 *
 * The admin dashboard's WebApp CMS page hits these endpoints; the public yekbun.app
 * reads the same data via the matching public endpoints in CmsApiController so
 * changes flow through immediately after the admin saves.
 *
 * All endpoints seed sensible defaults on first read so the admin sees the existing
 * (formerly-localStorage) UI populated even before any actual data has been pushed.
 */
class CmsController extends Controller
{
    /* ───────────── PAGES ───────────── */

    /** GET /admin/cms/pages — full pages list (each with editable field rows). */
    public function pages()
    {
        $this->seedDefaultPagesIfEmpty();
        $pages = CmsPage::orderBy('sort_order')->orderBy('name')->get()->map(function ($p) {
            return [
                'id'     => $p->id ?? (string) $p->_id,
                'name'   => $p->name,
                'fields' => is_array($p->fields) ? $p->fields : [],
            ];
        })->values();

        return ResponseHelper::sendResponse($pages, 'Pages fetched');
    }

    /**
     * PUT /admin/cms/pages/{id}
     * Body: { name?, fields: [{key, label, value}, ...] }
     *
     * Idempotent — wholesale replaces the page's fields array. The admin UI auto-saves
     * the whole edited record on debounce so partial-field PATCH isn't worth the cost.
     */
    public function updatePage(Request $request, string $id)
    {
        $data = $request->validate([
            'name'   => 'sometimes|string|max:255',
            'fields' => 'required|array',
            'fields.*.key'   => 'required|string|max:200',
            'fields.*.label' => 'nullable|string|max:200',
            'fields.*.value' => 'nullable|string',
        ]);

        $page = CmsPage::where('id', $id)->first() ?? new CmsPage();
        $page->id = $id;
        if (isset($data['name'])) $page->name = $data['name'];
        $page->fields = $data['fields'];
        $page->save();

        return ResponseHelper::sendResponse([
            'id'     => $page->id,
            'name'   => $page->name,
            'fields' => $page->fields,
        ], 'Page saved');
    }

    /* ───────────── TRANSLATIONS ───────────── */

    /**
     * GET /admin/cms/translations
     * Returns: { overrides: { "common.cancel": { en: "...", de: "...", ku: "...", ar: "..." }, ... } }
     */
    public function translations()
    {
        $rows = CmsTranslation::all()->map(function ($t) {
            return [
                'key'    => $t->key,
                'values' => is_array($t->values) ? $t->values : [],
            ];
        });

        // Flatten to the `{key: {lng: value}}` shape the admin UI already expects so we
        // don't have to refactor the existing renderer.
        $overrides = [];
        foreach ($rows as $r) {
            $overrides[$r['key']] = $r['values'];
        }

        return ResponseHelper::sendResponse(['overrides' => $overrides], 'Translations fetched');
    }

    /**
     * PUT /admin/cms/translations
     * Body: { overrides: { key: { lng: value, ... }, ... } }
     *
     * Wholesale replacement — easiest to reason about and matches the admin's debounce
     * pattern. We diff against existing rows so we can prune deleted keys cleanly.
     */
    public function updateTranslations(Request $request)
    {
        $data = $request->validate([
            'overrides' => 'required|array',
        ]);

        $overrides = $data['overrides'];

        // Upsert each key.
        foreach ($overrides as $key => $values) {
            if (!is_array($values)) continue;
            $row = CmsTranslation::where('key', $key)->first() ?? new CmsTranslation();
            $row->key    = $key;
            $row->values = $values;
            $row->save();
        }

        // Prune rows that the admin removed entirely (key no longer present).
        $keepKeys = array_keys($overrides);
        CmsTranslation::whereNotIn('key', $keepKeys)->delete();

        return ResponseHelper::sendResponse(['overrides' => $overrides], 'Translations saved');
    }

    /* ───────────── MEDIA ───────────── */

    /** GET /admin/cms/media — list of media slots + current URLs. */
    public function media()
    {
        $this->seedDefaultMediaIfEmpty();
        $items = CmsMedia::orderBy('sort_order')->get()->map(function ($m) {
            return [
                'id'   => $m->slot_id ?? (string) $m->_id,
                'name' => $m->name,
                'slot' => $m->slot,
                'url'  => Helpers::systemAssetUrl($m->url),
            ];
        })->values();

        return ResponseHelper::sendResponse($items, 'Media fetched');
    }

    /**
     * POST /admin/cms/media/upload
     * Body (multipart): { slot: string, image: file }
     *
     * Stores the image on API public disk (device-cache asset), updates the slot URL.
     */
    public function uploadMedia(Request $request)
    {
        $request->validate([
            'slot'  => 'required|string|max:200',
            'image' => 'required|file|image|max:10240', // 10MB — covers banner-size assets
        ]);

        $path = Helpers::fileUpload($request->file('image'), 'images/cms');

        $row = CmsMedia::where('slot', $request->slot)->first() ?? new CmsMedia();
        // If the slot didn't exist yet (admin added it ad-hoc), keep a friendly name.
        $row->slot = $request->slot;
        if (empty($row->name)) $row->name = $request->input('name', $request->slot);
        // Store the relative public-disk path. Convert to full URL only when responding.
        $row->url = $path;
        $row->save();

        return ResponseHelper::sendResponse([
            'id'   => $row->slot_id ?? (string) $row->_id,
            'name' => $row->name,
            'slot' => $row->slot,
            'url'  => Helpers::systemAssetUrl($row->url),
        ], 'Media updated');
    }

    /** DELETE /admin/cms/media/{slot} — clears the URL but keeps the slot record. */
    public function deleteMedia(string $slot)
    {
        $row = CmsMedia::where('slot', $slot)->first();
        if (!$row) {
            return ResponseHelper::sendResponse(null, 'Slot not found', false, 404);
        }
        $row->url = null;
        $row->save();

        return ResponseHelper::sendResponse(['slot' => $slot], 'Media cleared');
    }

    /* ───────────── Seeders ───────────── *
     * These mirror the seed arrays from the localStorage-era admin UI so the first
     * page load is populated with sensible defaults the admin can edit in place.
     */

    private function seedDefaultPagesIfEmpty(): void
    {
        if (CmsPage::count() > 0) return;

        $defaults = [
            ['id' => 'landing', 'name' => 'Landing Page', 'sort_order' => 1, 'fields' => [
                ['key' => 'hero.title',    'label' => 'Hero Title',    'value' => 'Discover Kurdistan'],
                ['key' => 'hero.subtitle', 'label' => 'Hero Subtitle', 'value' => 'A modern community for everyone'],
                ['key' => 'hero.cta',      'label' => 'Hero CTA',      'value' => 'Get started'],
            ]],
            ['id' => 'login', 'name' => 'Login / OTP', 'sort_order' => 2, 'fields' => [
                ['key' => 'login.title',    'label' => 'Login Title',    'value' => 'Welcome back'],
                ['key' => 'login.subtitle', 'label' => 'Login Subtitle', 'value' => 'Sign in to your account'],
                ['key' => 'login.cta',      'label' => 'Login Button',   'value' => 'Continue'],
                ['key' => 'otp.title',      'label' => 'OTP Title',      'value' => 'Enter verification code'],
                ['key' => 'otp.subtitle',   'label' => 'OTP Subtitle',   'value' => 'We sent a 6-digit code to your phone'],
            ]],
            ['id' => 'dashboard', 'name' => 'Dashboard', 'sort_order' => 3, 'fields' => [
                ['key' => 'dashboard.title',    'label' => 'Dashboard Title',    'value' => 'Dashboard'],
                ['key' => 'dashboard.subtitle', 'label' => 'Dashboard Subtitle', 'value' => 'Overview & Analytics'],
            ]],
            ['id' => 'marketplace', 'name' => 'Marketplace', 'sort_order' => 4, 'fields' => [
                ['key' => 'marketplace.title',    'label' => 'Marketplace Title',    'value' => 'YekBûn Marketplace'],
                ['key' => 'marketplace.subtitle', 'label' => 'Marketplace Subtitle', 'value' => 'Buy & sell within the community'],
            ]],
            ['id' => 'footer', 'name' => 'Footer', 'sort_order' => 5, 'fields' => [
                ['key' => 'footer.tagline',   'label' => 'Tagline',   'value' => 'YekBûn — One nation, one heart'],
                ['key' => 'footer.copyright', 'label' => 'Copyright', 'value' => '© 2026 YekBûn. All rights reserved.'],
            ]],
        ];

        foreach ($defaults as $d) {
            CmsPage::create($d);
        }
    }

    private function seedDefaultMediaIfEmpty(): void
    {
        if (CmsMedia::count() > 0) return;

        $defaults = [
            ['slot_id' => 'm1', 'name' => 'Hero Banner',       'slot' => 'landing.hero',      'url' => null, 'sort_order' => 1],
            ['slot_id' => 'm2', 'name' => 'Login Background',  'slot' => 'login.bg',          'url' => null, 'sort_order' => 2],
            ['slot_id' => 'm3', 'name' => 'Marketplace Cover', 'slot' => 'marketplace.cover', 'url' => null, 'sort_order' => 3],
            ['slot_id' => 'm4', 'name' => 'App Logo',          'slot' => 'global.logo',       'url' => null, 'sort_order' => 4],
            ['slot_id' => 'm5', 'name' => 'Promo Card 1',      'slot' => 'landing.promo1',    'url' => null, 'sort_order' => 5],
            ['slot_id' => 'm6', 'name' => 'Promo Card 2',      'slot' => 'landing.promo2',    'url' => null, 'sort_order' => 6],
        ];

        foreach ($defaults as $d) {
            CmsMedia::create($d);
        }
    }
}
