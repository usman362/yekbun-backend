<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\AutoLogout;
use Illuminate\Http\Request;

class AutoLogoutAdminController extends Controller
{
    /** GET /admin/auto-logout — current config (seeds defaults on first load). */
    public function index()
    {
        return ResponseHelper::sendResponse($this->configOrSeed(), 'Auto-logout config fetched.');
    }

    /** PUT /admin/auto-logout — save the config. */
    public function save(Request $request)
    {
        $c = $this->configOrSeed();

        if ($request->has('enabled'))         $c->enabled         = $request->boolean('enabled');
        if ($request->has('minutes'))         $c->minutes         = max(1, min(120, (int) $request->minutes));
        if ($request->has('warn_before'))     $c->warn_before     = $request->boolean('warn_before');
        if ($request->has('warn_seconds'))    $c->warn_seconds    = max(5, min(300, (int) $request->warn_seconds));
        if ($request->has('logout_on_close')) $c->logout_on_close = $request->boolean('logout_on_close');
        if ($request->has('exclude_admins'))  $c->exclude_admins  = $request->boolean('exclude_admins');
        $c->save();

        return ResponseHelper::sendResponse($this->present($c), 'Auto-logout config saved.');
    }

    /** GET /api/app/auto-logout — PUBLIC: mobile reads the inactivity policy. */
    public function current()
    {
        $c = AutoLogout::first();
        return response()->json($this->present($c ?: $this->defaults()));
    }

    // ── helpers ──

    private function configOrSeed(): AutoLogout
    {
        $c = AutoLogout::first();
        if ($c) return $c;

        $c = new AutoLogout();
        foreach ($this->defaults() as $k => $v) $c->{$k} = $v;
        $c->save();
        return $c;
    }

    private function present($c): array
    {
        $d = $this->defaults();
        return [
            'enabled'         => (bool) ($c->enabled ?? $d['enabled']),
            'minutes'         => (int) ($c->minutes ?? $d['minutes']),
            'warn_before'     => (bool) ($c->warn_before ?? $d['warn_before']),
            'warn_seconds'    => (int) ($c->warn_seconds ?? $d['warn_seconds']),
            'logout_on_close' => (bool) ($c->logout_on_close ?? $d['logout_on_close']),
            'exclude_admins'  => (bool) ($c->exclude_admins ?? $d['exclude_admins']),
        ];
    }

    private function defaults(): array
    {
        return [
            'enabled'         => true,
            'minutes'         => 15,
            'warn_before'     => true,
            'warn_seconds'    => 30,
            'logout_on_close' => false,
            'exclude_admins'  => true,
        ];
    }
}
