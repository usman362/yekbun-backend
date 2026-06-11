<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helpers;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\CivilLaw;
use App\Models\Constitution;
use App\Models\Holiday;
use App\Models\Ministry;
use App\Models\PeopleTerritory;

/**
 * Public (mobile-facing) read API for the "Officials" / Kurdistan content the admin manages
 * under /admin/officials. Five sections, each read-only here:
 *
 *   - constitutions   (Constitution of Kurdistan)
 *   - people          (People & Territory)
 *   - ministries      (Structure & Ministries)
 *   - civil-laws      (Civil Law & Daily Life)
 *   - holidays        (Holidays & Memory's)
 *
 * Every row is normalised to a stable shape with media fields resolved to absolute CDN
 * URLs, so the mobile app gets a consistent contract regardless of how each row was saved.
 *
 * Routes (all public):
 *   GET /api/officials                  → overview: counts + all five lists in one call
 *   GET /api/officials/constitutions    → list
 *   GET /api/officials/people           → list
 *   GET /api/officials/ministries       → list
 *   GET /api/officials/civil-laws       → list
 *   GET /api/officials/holidays         → list
 */
class OfficialsApiController extends Controller
{
    /** GET /api/officials — everything the Officials screen needs in a single request. */
    public function index()
    {
        return ResponseHelper::sendResponse([
            'constitutions' => $this->constitutionList(),
            'people'        => $this->peopleList(),
            'ministries'    => $this->ministryList(),
            'civil_laws'    => $this->civilLawList(),
            'holidays'      => $this->holidayList(),
            'counts'        => [
                'constitutions' => Constitution::count(),
                'people'        => PeopleTerritory::count(),
                'ministries'    => Ministry::count(),
                'civil_laws'    => CivilLaw::count(),
                'holidays'      => Holiday::count(),
            ],
        ], 'Officials fetched successfully.');
    }

    public function constitutions()
    {
        return ResponseHelper::sendResponse($this->constitutionList(), 'Constitutions fetched successfully.');
    }

    public function people()
    {
        return ResponseHelper::sendResponse($this->peopleList(), 'People & Territory fetched successfully.');
    }

    public function ministries()
    {
        return ResponseHelper::sendResponse($this->ministryList(), 'Ministries fetched successfully.');
    }

    public function civilLaws()
    {
        return ResponseHelper::sendResponse($this->civilLawList(), 'Civil Laws fetched successfully.');
    }

    public function holidays()
    {
        return ResponseHelper::sendResponse($this->holidayList(), 'Holidays fetched successfully.');
    }

    /* ───────────────────────── normalisers ───────────────────────── */

    private function constitutionList(): array
    {
        return Constitution::orderBy('created_at', 'desc')->get()->map(fn ($c) => [
            '_id'           => (string) $c->_id,
            'title'         => $c->title ?? '',
            'version'       => $c->version ?? '',
            'language_icon' => Helpers::mediaUrl($c->language_icon),
            'audio'         => Helpers::mediaUrl($c->audio),
            'content_text'  => $c->content_text ?? '',
            'created_at'    => $c->created_at,
        ])->values()->all();
    }

    private function peopleList(): array
    {
        return PeopleTerritory::orderBy('created_at', 'desc')->get()->map(fn ($p) => [
            '_id'             => (string) $p->_id,
            'country_name'    => $p->country_name ?? '',
            'country_capital' => $p->country_capital ?? '',
            'mass'            => $p->mass ?? '',
            'language_icon'   => Helpers::mediaUrl($p->language_icon),
            'audio'           => Helpers::mediaUrl($p->audio),
            'about_text'      => $p->about_text ?? '',
            'created_at'      => $p->created_at,
        ])->values()->all();
    }

    private function ministryList(): array
    {
        return Ministry::orderBy('created_at', 'desc')->get()->map(fn ($m) => [
            '_id'           => (string) $m->_id,
            'name'          => $m->name ?? '',
            'description'   => $m->description ?? '',
            'logo'          => Helpers::mediaUrl($m->logo),
            'province'      => $m->province ?? '',
            'city'          => $m->city ?? '',
            'address'       => $m->address ?? '',
            'web'           => $m->web ?? '',
            'mail'          => $m->mail ?? '',
            'phone'         => $m->phone ?? '',
            'appointment'   => $m->appointment ?? '',
            'opening_times' => $m->opening_times ?? null,
            'is_24_7'       => (bool) ($m->is_24_7 ?? false),
            'created_at'    => $m->created_at,
        ])->values()->all();
    }

    private function civilLawList(): array
    {
        return CivilLaw::orderBy('created_at', 'desc')->get()->map(fn ($c) => [
            '_id'           => (string) $c->_id,
            'title'         => $c->title ?? '',
            'version'       => $c->version ?? '',
            'language_icon' => Helpers::mediaUrl($c->language_icon),
            'audio'         => Helpers::mediaUrl($c->audio),
            'content_text'  => $c->content_text ?? '',
            'created_at'    => $c->created_at,
        ])->values()->all();
    }

    private function holidayList(): array
    {
        return Holiday::orderBy('created_at', 'desc')->get()->map(fn ($h) => [
            '_id'             => (string) $h->_id,
            'name'            => $h->name ?? '',
            'date'            => $h->date ?? '',
            'description'     => $h->description ?? '',
            'icon'            => Helpers::mediaUrl($h->icon),
            'banner_image'    => Helpers::mediaUrl($h->banner_image),
            'banner_video'    => Helpers::mediaUrl($h->banner_video),
            'is_national_day' => (bool) ($h->is_national_day ?? false),
            'created_at'      => $h->created_at,
        ])->values()->all();
    }
}
