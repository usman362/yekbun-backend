<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\CivilLaw;
use App\Models\Constitution;
use App\Models\Holiday;
use App\Models\Ministry;
use App\Models\PeopleTerritory;
use App\Services\BunnyCDNService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OfficialsAdminController extends Controller
{
    /* ======================== Constitutions ======================== */

    public function constitutionsIndex()
    {
        $rows = Constitution::orderBy('created_at', 'desc')->get();
        return ResponseHelper::sendResponse($rows, 'Constitutions loaded.');
    }

    public function constitutionsStore(Request $request)
    {
        $request->validate(['title' => 'required|string|max:255']);

        $c = new Constitution();
        $c->title          = $request->title;
        $c->version        = $request->version;
        $c->language_icon  = $request->language_icon;
        $c->audio          = $request->audio;
        $c->content_text   = $request->content_text;
        $c->save();

        return ResponseHelper::sendResponse($c, 'Constitution created.', true, 201);
    }

    public function constitutionsUpdate(Request $request, $id)
    {
        $request->validate(['title' => 'required|string|max:255']);

        $c = Constitution::find($id);
        if (!$c) return ResponseHelper::sendResponse(null, 'Not found', false, 404);

        $c->title          = $request->title;
        $c->version        = $request->version;
        if ($request->has('language_icon')) $c->language_icon = $request->language_icon;
        if ($request->has('audio'))         $c->audio = $request->audio;
        if ($request->has('content_text'))  $c->content_text = $request->content_text;
        $c->save();

        return ResponseHelper::sendResponse($c, 'Constitution updated.');
    }

    public function constitutionsDestroy($id)
    {
        return $this->destroyWithFiles(Constitution::class, $id, ['language_icon', 'audio'], 'Constitution');
    }

    /* ======================== People & Territory ======================== */

    public function peopleIndex()
    {
        $rows = PeopleTerritory::orderBy('created_at', 'desc')->get();
        return ResponseHelper::sendResponse($rows, 'People & Territory loaded.');
    }

    public function peopleStore(Request $request)
    {
        $request->validate(['country_name' => 'required|string|max:255']);

        $p = new PeopleTerritory();
        $p->country_name    = $request->country_name;
        $p->country_capital = $request->country_capital;
        $p->mass            = $request->mass;
        $p->language_icon   = $request->language_icon;
        $p->audio           = $request->audio;
        $p->about_text      = $request->about_text;
        $p->save();

        return ResponseHelper::sendResponse($p, 'Entry created.', true, 201);
    }

    public function peopleUpdate(Request $request, $id)
    {
        $request->validate(['country_name' => 'required|string|max:255']);

        $p = PeopleTerritory::find($id);
        if (!$p) return ResponseHelper::sendResponse(null, 'Not found', false, 404);

        $p->country_name    = $request->country_name;
        $p->country_capital = $request->country_capital;
        $p->mass            = $request->mass;
        if ($request->has('language_icon')) $p->language_icon = $request->language_icon;
        if ($request->has('audio'))         $p->audio = $request->audio;
        if ($request->has('about_text'))    $p->about_text = $request->about_text;
        $p->save();

        return ResponseHelper::sendResponse($p, 'Entry updated.');
    }

    public function peopleDestroy($id)
    {
        return $this->destroyWithFiles(PeopleTerritory::class, $id, ['language_icon', 'audio'], 'Entry');
    }

    /* ======================== Ministries ======================== */

    public function ministriesIndex()
    {
        $rows = Ministry::orderBy('created_at', 'desc')->get();
        return ResponseHelper::sendResponse($rows, 'Ministries loaded.');
    }

    public function ministriesStore(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);

        $m = new Ministry();
        $this->fillMinistry($m, $request);
        $m->save();

        return ResponseHelper::sendResponse($m, 'Ministry created.', true, 201);
    }

    public function ministriesUpdate(Request $request, $id)
    {
        $request->validate(['name' => 'required|string|max:255']);

        $m = Ministry::find($id);
        if (!$m) return ResponseHelper::sendResponse(null, 'Not found', false, 404);

        $this->fillMinistry($m, $request);
        $m->save();

        return ResponseHelper::sendResponse($m, 'Ministry updated.');
    }

    public function ministriesDestroy($id)
    {
        return $this->destroyWithFiles(Ministry::class, $id, ['logo'], 'Ministry');
    }

    private function fillMinistry(Ministry $m, Request $request): void
    {
        $m->name        = $request->name;
        $m->description = $request->description;
        if ($request->has('logo'))         $m->logo = $request->logo;
        if ($request->has('province'))     $m->province = $request->province;
        if ($request->has('city'))         $m->city = $request->city;
        if ($request->has('address'))      $m->address = $request->address;
        if ($request->has('web'))          $m->web = $request->web;
        if ($request->has('mail'))         $m->mail = $request->mail;
        if ($request->has('phone'))        $m->phone = $request->phone;
        if ($request->has('appointment'))  $m->appointment = $request->appointment;
        if ($request->has('opening_times'))$m->opening_times = $request->opening_times;
        if ($request->has('is_24_7'))      $m->is_24_7 = (bool) $request->is_24_7;
    }

    /* ======================== Civil Laws ======================== */

    public function civilLawsIndex()
    {
        $rows = CivilLaw::orderBy('created_at', 'desc')->get();
        return ResponseHelper::sendResponse($rows, 'Civil Laws loaded.');
    }

    public function civilLawsStore(Request $request)
    {
        $request->validate(['title' => 'required|string|max:255']);

        $c = new CivilLaw();
        $c->title          = $request->title;
        $c->version        = $request->version;
        $c->language_icon  = $request->language_icon;
        $c->audio          = $request->audio;
        $c->content_text   = $request->content_text;
        $c->save();

        return ResponseHelper::sendResponse($c, 'Civil law created.', true, 201);
    }

    public function civilLawsUpdate(Request $request, $id)
    {
        $request->validate(['title' => 'required|string|max:255']);

        $c = CivilLaw::find($id);
        if (!$c) return ResponseHelper::sendResponse(null, 'Not found', false, 404);

        $c->title          = $request->title;
        $c->version        = $request->version;
        if ($request->has('language_icon')) $c->language_icon = $request->language_icon;
        if ($request->has('audio'))         $c->audio = $request->audio;
        if ($request->has('content_text'))  $c->content_text = $request->content_text;
        $c->save();

        return ResponseHelper::sendResponse($c, 'Civil law updated.');
    }

    public function civilLawsDestroy($id)
    {
        return $this->destroyWithFiles(CivilLaw::class, $id, ['language_icon', 'audio'], 'Civil law');
    }

    /* ======================== Holidays ======================== */

    public function holidaysIndex()
    {
        $rows = Holiday::orderBy('created_at', 'desc')->get();
        return ResponseHelper::sendResponse($rows, 'Holidays loaded.');
    }

    public function holidaysStore(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);

        $h = new Holiday();
        $h->name            = $request->name;
        $h->date            = $request->date;
        $h->description     = $request->description;
        $h->icon            = $request->icon;
        $h->banner_image    = $request->banner_image;
        $h->banner_video    = $request->banner_video;
        $h->is_national_day = (bool) $request->input('is_national_day', false);
        $h->save();

        return ResponseHelper::sendResponse($h, 'Holiday created.', true, 201);
    }

    public function holidaysUpdate(Request $request, $id)
    {
        $request->validate(['name' => 'required|string|max:255']);

        $h = Holiday::find($id);
        if (!$h) return ResponseHelper::sendResponse(null, 'Not found', false, 404);

        $h->name            = $request->name;
        $h->date            = $request->date;
        $h->description     = $request->description;
        if ($request->has('icon'))            $h->icon = $request->icon;
        if ($request->has('banner_image'))    $h->banner_image = $request->banner_image;
        if ($request->has('banner_video'))    $h->banner_video = $request->banner_video;
        if ($request->has('is_national_day')) $h->is_national_day = (bool) $request->is_national_day;
        $h->save();

        return ResponseHelper::sendResponse($h, 'Holiday updated.');
    }

    public function holidaysDestroy($id)
    {
        return $this->destroyWithFiles(Holiday::class, $id, ['icon', 'banner_image', 'banner_video'], 'Holiday');
    }

    /* ======================== Counts (for KPI cards) ======================== */

    public function counts()
    {
        return ResponseHelper::sendResponse([
            'constitutions' => Constitution::count(),
            'people'        => PeopleTerritory::count(),
            'ministries'    => Ministry::count(),
            'civil_laws'    => CivilLaw::count(),
            'holidays'      => Holiday::count(),
        ], 'Officials counts loaded.');
    }

    /* ======================== Helpers ======================== */

    private function destroyWithFiles(string $modelClass, string $id, array $fileFields, string $label)
    {
        $row = $modelClass::find($id);
        if (!$row) {
            return ResponseHelper::sendResponse(null, $label . ' not found', false, 404);
        }

        $bunny = new BunnyCDNService();
        $cdnBase = rtrim((string) env('BUNNY_CDN_URL'), '/');

        foreach ($fileFields as $field) {
            $value = $row->{$field} ?? null;
            if (!empty($value)) {
                $bunny->delete($this->cdnPath((string) $value, $cdnBase));
            }
        }

        $row->delete();
        return ResponseHelper::sendResponse(['id' => $id], $label . ' deleted.');
    }

    private function cdnPath(string $fullUrl, string $cdnBase): string
    {
        if ($cdnBase !== '' && Str::startsWith($fullUrl, $cdnBase . '/')) {
            return Str::after($fullUrl, $cdnBase . '/');
        }
        return ltrim($fullUrl, '/');
    }
}
