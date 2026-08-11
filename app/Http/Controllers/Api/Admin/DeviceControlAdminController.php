<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\CacheProfile;
use App\Models\DeviceProfile;
use App\Models\DeviceTelemetry;
use App\Models\ProblemDevice;
use App\Models\RuntimeProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

/**
 * Device Control Center — Phase 1 foundation APIs.
 * Collections: device_profiles, runtime_profiles, cache_profiles,
 * device_telemetry, problem_devices.
 */
class DeviceControlAdminController extends Controller
{
    /** Request-scoped live telemetry counts keyed by profile_key. */
    private ?array $telemetryCountCache = null;

    /* ═══════════════════════════════════════════════════════════
     * Overview
     * ═══════════════════════════════════════════════════════════ */

    /** GET /admin/device-control/overview */
    public function overview()
    {
        $profiles = DeviceProfile::orderBy('priority')->get();
        $counts = $this->telemetryCountsByProfileKey();
        $totalDevices = (int) array_sum($counts);

        // Critical = devices reporting critical health / crashes (live telemetry).
        $critical = (int) DeviceTelemetry::where(function ($q) {
            $q->whereIn('status', ['critical', 'Critical', 'unhealthy', 'Unhealthy'])
                ->orWhere('crash_count', '>', 0);
        })->count();
        $openProblems = ProblemDevice::whereIn('status', ['Open', 'Under Review'])->count();

        $crashDevices = (int) DeviceTelemetry::where('crash_count', '>', 0)->count();
        $crashFree = $totalDevices > 0
            ? round((($totalDevices - $crashDevices) / $totalDevices) * 100, 1) . '%'
            : '—';

        return ResponseHelper::sendResponse([
            'stats' => [
                'total_devices'        => $totalDevices,
                'active_profiles'      => $profiles->where('status', 'published')->count(),
                'stable_devices'       => max(0, $totalDevices - $critical),
                'critical_devices'     => $critical,
                'crash_free_sessions'  => $crashFree,
                'pending_profiles'     => $profiles->where('status', 'draft')->count(),
                'open_problem_groups'  => $openProblems,
            ],
            'distribution' => $profiles->map(fn ($p) => [
                'key'              => $p->key,
                'name'             => $p->name,
                'color'            => $p->color,
                'assigned_devices' => (int) ($counts[(string) $p->key] ?? 0),
                'status'           => $p->status,
            ])->values(),
            'profiles' => $profiles->map(fn ($p) => $this->presentDeviceProfile($p))->values(),
        ], 'Device control overview fetched.');
    }

    /**
     * POST /admin/device-control/seed-defaults
     * Upserts Entry→Ultra device / runtime / cache profiles (safe; no wipe unless force=1).
     */
    public function seedDefaults(Request $request)
    {
        $force = $request->boolean('force');
        $params = $force ? ['--force' => true] : [];

        try {
            Artisan::call('device-control:seed-defaults', $params);
        } catch (\Throwable $e) {
            return ResponseHelper::sendResponse(
                ['error' => $e->getMessage()],
                'Seed failed: ' . $e->getMessage(),
                false,
                500
            );
        }

        $required = ['entry', 'low', 'balanced', 'high', 'ultra'];
        $cacheKeys = CacheProfile::pluck('key')->map(fn ($k) => (string) $k)->all();
        $runtimeKeys = RuntimeProfile::pluck('key')->map(fn ($k) => (string) $k)->all();
        $deviceKeys = DeviceProfile::pluck('key')->map(fn ($k) => (string) $k)->all();

        return ResponseHelper::sendResponse([
            'forced'           => $force,
            'device_profiles'  => DeviceProfile::count(),
            'runtime_profiles' => RuntimeProfile::count(),
            'cache_profiles'   => CacheProfile::count(),
            'tiers'            => [
                'device'  => collect($required)->mapWithKeys(fn ($k) => [$k => in_array($k, $deviceKeys, true)]),
                'runtime' => collect($required)->mapWithKeys(fn ($k) => [$k => in_array($k, $runtimeKeys, true)]),
                'cache'   => collect($required)->mapWithKeys(fn ($k) => [$k => in_array($k, $cacheKeys, true)]),
            ],
            'output'           => trim(Artisan::output()),
        ], 'Entry→Ultra Device / Cache / Runtime defaults are ready.');
    }

    /* ═══════════════════════════════════════════════════════════
     * Device Profiles
     * ═══════════════════════════════════════════════════════════ */

    /** GET /admin/device-control/profiles */
    public function profilesIndex()
    {
        $rows = DeviceProfile::orderBy('priority')->get()
            ->map(fn ($p) => $this->presentDeviceProfile($p))
            ->values();

        return ResponseHelper::sendResponse($rows, 'Device profiles fetched.');
    }

    /** GET /admin/device-control/profiles/{id} */
    public function profilesShow($id)
    {
        $p = $this->findByIdOrKey(DeviceProfile::class, $id);
        if (!$p) {
            return ResponseHelper::sendResponse(null, 'Device profile not found.', false, 404);
        }

        return ResponseHelper::sendResponse($this->presentDeviceProfile($p), 'Device profile fetched.');
    }

    /** POST /admin/device-control/profiles */
    public function profilesStore(Request $request)
    {
        $request->validate([
            'key'  => 'required|string|max:64',
            'name' => 'required|string|max:120',
        ]);

        $key = Str::slug($request->input('key'), '_');
        if (DeviceProfile::where('key', $key)->exists()) {
            return ResponseHelper::sendResponse(null, 'Profile key already exists.', false, 422);
        }

        $p = new DeviceProfile();
        $this->fillDeviceProfile($p, $request, $key);
        $p->history = $this->appendHistory([], 'Created device profile');
        $p->save();

        return ResponseHelper::sendResponse($this->presentDeviceProfile($p), 'Device profile created.', true, 201);
    }

    /** PUT /admin/device-control/profiles/{id} */
    public function profilesUpdate(Request $request, $id)
    {
        $p = $this->findByIdOrKey(DeviceProfile::class, $id);
        if (!$p) {
            return ResponseHelper::sendResponse(null, 'Device profile not found.', false, 404);
        }

        $this->fillDeviceProfile($p, $request, $p->key);
        $p->history = $this->appendHistory($p->history ?? [], 'Updated device profile', $p->version);
        $p->save();

        return ResponseHelper::sendResponse($this->presentDeviceProfile($p), 'Device profile saved.');
    }

    /** DELETE /admin/device-control/profiles/{id} */
    public function profilesDestroy($id)
    {
        $p = $this->findByIdOrKey(DeviceProfile::class, $id);
        if (!$p) {
            return ResponseHelper::sendResponse(null, 'Device profile not found.', false, 404);
        }
        $p->delete();

        return ResponseHelper::sendResponse(null, 'Device profile deleted.');
    }

    /** POST /admin/device-control/profiles/{id}/publish */
    public function profilesPublish($id)
    {
        $p = $this->findByIdOrKey(DeviceProfile::class, $id);
        if (!$p) {
            return ResponseHelper::sendResponse(null, 'Device profile not found.', false, 404);
        }

        $p->status = 'published';
        $p->published_at = now();
        $p->published_by = $this->adminName();
        $p->history = $this->appendHistory($p->history ?? [], 'Published device profile', $p->version);
        $p->save();

        return ResponseHelper::sendResponse($this->presentDeviceProfile($p), 'Device profile published.');
    }

    /** POST /admin/device-control/profiles/{id}/rollback — unpublish to draft */
    public function profilesRollback($id)
    {
        $p = $this->findByIdOrKey(DeviceProfile::class, $id);
        if (!$p) {
            return ResponseHelper::sendResponse(null, 'Device profile not found.', false, 404);
        }

        $p->status = 'draft';
        $p->history = $this->appendHistory($p->history ?? [], 'Rolled back to draft', $p->version);
        $p->save();

        return ResponseHelper::sendResponse($this->presentDeviceProfile($p), 'Device profile rolled back to draft.');
    }

    /** POST /admin/device-control/profiles/{id}/duplicate */
    public function profilesDuplicate(Request $request, $id)
    {
        $src = $this->findByIdOrKey(DeviceProfile::class, $id);
        if (!$src) {
            return ResponseHelper::sendResponse(null, 'Device profile not found.', false, 404);
        }

        $baseKey = $request->input('key') ?: ($src->key . '_copy');
        $key = Str::slug($baseKey, '_');
        $n = 1;
        $candidate = $key;
        while (DeviceProfile::where('key', $candidate)->exists()) {
            $candidate = $key . '_' . $n++;
        }

        $attrs = collect($src->getAttributes())
            ->except(['_id', 'id', 'created_at', 'updated_at'])
            ->all();
        $attrs['key'] = $candidate;
        $attrs['name'] = $request->input('name') ?: ($src->name . ' Copy');
        $attrs['status'] = 'draft';
        $attrs['published_at'] = null;
        $attrs['published_by'] = null;
        $attrs['assigned_devices'] = 0;
        $attrs['history'] = [[
            'at'      => now()->toIso8601String(),
            'by'      => $this->adminName(),
            'version' => $src->version,
            'note'    => 'Duplicated from ' . $src->key,
        ]];

        $p = DeviceProfile::create($attrs);

        return ResponseHelper::sendResponse($this->presentDeviceProfile($p), 'Device profile duplicated.', true, 201);
    }

    /* ═══════════════════════════════════════════════════════════
     * Runtime Profiles
     * ═══════════════════════════════════════════════════════════ */

    public function runtimeIndex()
    {
        $rows = RuntimeProfile::orderBy('key')->get()
            ->map(fn ($p) => $this->presentRuntimeProfile($p))
            ->values();

        return ResponseHelper::sendResponse($rows, 'Runtime profiles fetched.');
    }

    public function runtimeShow($id)
    {
        $p = $this->findByIdOrKey(RuntimeProfile::class, $id);
        if (!$p) {
            return ResponseHelper::sendResponse(null, 'Runtime profile not found.', false, 404);
        }

        return ResponseHelper::sendResponse($this->presentRuntimeProfile($p), 'Runtime profile fetched.');
    }

    public function runtimeStore(Request $request)
    {
        $request->validate([
            'key'  => 'required|string|max:64',
            'name' => 'required|string|max:120',
        ]);

        $key = Str::slug($request->input('key'), '_');
        if (RuntimeProfile::where('key', $key)->exists()) {
            return ResponseHelper::sendResponse(null, 'Runtime key already exists.', false, 422);
        }

        $p = new RuntimeProfile();
        $this->fillRuntimeProfile($p, $request, $key);
        $p->history = $this->appendHistory([], 'Created runtime profile');
        $p->save();

        return ResponseHelper::sendResponse($this->presentRuntimeProfile($p), 'Runtime profile created.', true, 201);
    }

    public function runtimeUpdate(Request $request, $id)
    {
        $p = $this->findByIdOrKey(RuntimeProfile::class, $id);
        if (!$p) {
            return ResponseHelper::sendResponse(null, 'Runtime profile not found.', false, 404);
        }

        $this->fillRuntimeProfile($p, $request, $p->key);
        $p->history = $this->appendHistory($p->history ?? [], 'Updated runtime profile', $p->version);
        $p->save();

        return ResponseHelper::sendResponse($this->presentRuntimeProfile($p), 'Runtime profile saved.');
    }

    public function runtimeDestroy($id)
    {
        $p = $this->findByIdOrKey(RuntimeProfile::class, $id);
        if (!$p) {
            return ResponseHelper::sendResponse(null, 'Runtime profile not found.', false, 404);
        }
        $p->delete();

        return ResponseHelper::sendResponse(null, 'Runtime profile deleted.');
    }

    /** POST /admin/device-control/runtime-profiles/{id}/publish */
    public function runtimePublish($id)
    {
        $p = $this->findByIdOrKey(RuntimeProfile::class, $id);
        if (!$p) {
            return ResponseHelper::sendResponse(null, 'Runtime profile not found.', false, 404);
        }

        $p->status = 'published';
        $p->published_at = now();
        $p->history = $this->appendHistory($p->history ?? [], 'Published runtime profile', $p->version);
        $p->save();

        return ResponseHelper::sendResponse($this->presentRuntimeProfile($p), 'Runtime profile published.');
    }

    /** POST /admin/device-control/runtime-profiles/{id}/rollback */
    public function runtimeRollback($id)
    {
        $p = $this->findByIdOrKey(RuntimeProfile::class, $id);
        if (!$p) {
            return ResponseHelper::sendResponse(null, 'Runtime profile not found.', false, 404);
        }

        $p->status = 'draft';
        $p->history = $this->appendHistory($p->history ?? [], 'Rolled back to draft', $p->version);
        $p->save();

        return ResponseHelper::sendResponse($this->presentRuntimeProfile($p), 'Runtime profile rolled back to draft.');
    }

    /** POST /admin/device-control/runtime-profiles/{id}/duplicate */
    public function runtimeDuplicate(Request $request, $id)
    {
        $src = $this->findByIdOrKey(RuntimeProfile::class, $id);
        if (!$src) {
            return ResponseHelper::sendResponse(null, 'Runtime profile not found.', false, 404);
        }

        $baseKey = $request->input('key') ?: ($src->key . '_copy');
        $key = Str::slug($baseKey, '_');
        $n = 1;
        $candidate = $key;
        while (RuntimeProfile::where('key', $candidate)->exists()) {
            $candidate = $key . '_' . $n++;
        }

        $attrs = collect($src->getAttributes())
            ->except(['_id', 'id', 'created_at', 'updated_at'])
            ->all();
        $attrs['key'] = $candidate;
        $attrs['name'] = $request->input('name') ?: ($src->name . ' Copy');
        $attrs['status'] = 'draft';
        $attrs['published_at'] = null;
        $attrs['affected_devices'] = 0;
        $attrs['history'] = [[
            'at'      => now()->toIso8601String(),
            'by'      => $this->adminName(),
            'version' => $src->version,
            'note'    => 'Duplicated from ' . $src->key,
        ]];

        $p = RuntimeProfile::create($attrs);

        return ResponseHelper::sendResponse($this->presentRuntimeProfile($p), 'Runtime profile duplicated.', true, 201);
    }

    /* ═══════════════════════════════════════════════════════════
     * Cache Profiles
     * ═══════════════════════════════════════════════════════════ */

    public function cacheIndex()
    {
        $rows = CacheProfile::orderBy('key')->get()
            ->map(fn ($p) => $this->presentCacheProfile($p))
            ->values();

        return ResponseHelper::sendResponse($rows, 'Cache profiles fetched.');
    }

    public function cacheShow($id)
    {
        $p = $this->findByIdOrKey(CacheProfile::class, $id);
        if (!$p) {
            return ResponseHelper::sendResponse(null, 'Cache profile not found.', false, 404);
        }

        return ResponseHelper::sendResponse($this->presentCacheProfile($p), 'Cache profile fetched.');
    }

    public function cacheStore(Request $request)
    {
        $request->validate([
            'key'  => 'required|string|max:64',
            'name' => 'required|string|max:120',
        ]);

        $key = Str::slug($request->input('key'), '_');
        if (CacheProfile::where('key', $key)->exists()) {
            return ResponseHelper::sendResponse(null, 'Cache key already exists.', false, 422);
        }

        $p = new CacheProfile();
        $this->fillCacheProfile($p, $request, $key);
        $p->history = $this->appendHistory([], 'Created cache profile');
        $p->save();

        return ResponseHelper::sendResponse($this->presentCacheProfile($p), 'Cache profile created.', true, 201);
    }

    public function cacheUpdate(Request $request, $id)
    {
        $p = $this->findByIdOrKey(CacheProfile::class, $id);
        if (!$p) {
            return ResponseHelper::sendResponse(null, 'Cache profile not found.', false, 404);
        }

        $this->fillCacheProfile($p, $request, $p->key);
        $p->history = $this->appendHistory($p->history ?? [], 'Updated cache profile', $p->version);
        $p->save();

        return ResponseHelper::sendResponse($this->presentCacheProfile($p), 'Cache profile saved.');
    }

    public function cacheDestroy($id)
    {
        $p = $this->findByIdOrKey(CacheProfile::class, $id);
        if (!$p) {
            return ResponseHelper::sendResponse(null, 'Cache profile not found.', false, 404);
        }
        $p->delete();

        return ResponseHelper::sendResponse(null, 'Cache profile deleted.');
    }

    /** POST /admin/device-control/cache-profiles/{id}/publish */
    public function cachePublish($id)
    {
        $p = $this->findByIdOrKey(CacheProfile::class, $id);
        if (!$p) {
            return ResponseHelper::sendResponse(null, 'Cache profile not found.', false, 404);
        }

        $p->status = 'published';
        $p->published_at = now();
        $p->history = $this->appendHistory($p->history ?? [], 'Published cache profile', $p->version);
        $p->save();

        return ResponseHelper::sendResponse($this->presentCacheProfile($p), 'Cache profile published.');
    }

    /** POST /admin/device-control/cache-profiles/{id}/rollback */
    public function cacheRollback($id)
    {
        $p = $this->findByIdOrKey(CacheProfile::class, $id);
        if (!$p) {
            return ResponseHelper::sendResponse(null, 'Cache profile not found.', false, 404);
        }

        $p->status = 'draft';
        $p->history = $this->appendHistory($p->history ?? [], 'Rolled back to draft', $p->version);
        $p->save();

        return ResponseHelper::sendResponse($this->presentCacheProfile($p), 'Cache profile rolled back to draft.');
    }

    /** POST /admin/device-control/cache-profiles/{id}/duplicate */
    public function cacheDuplicate(Request $request, $id)
    {
        $src = $this->findByIdOrKey(CacheProfile::class, $id);
        if (!$src) {
            return ResponseHelper::sendResponse(null, 'Cache profile not found.', false, 404);
        }

        $baseKey = $request->input('key') ?: ($src->key . '_copy');
        $key = Str::slug($baseKey, '_');
        $n = 1;
        $candidate = $key;
        while (CacheProfile::where('key', $candidate)->exists()) {
            $candidate = $key . '_' . $n++;
        }

        $attrs = collect($src->getAttributes())
            ->except(['_id', 'id', 'created_at', 'updated_at'])
            ->all();
        $attrs['key'] = $candidate;
        $attrs['name'] = $request->input('name') ?: ($src->name . ' Copy');
        $attrs['status'] = 'draft';
        $attrs['published_at'] = null;
        $attrs['affected_devices'] = 0;
        $attrs['history'] = [[
            'at'      => now()->toIso8601String(),
            'by'      => $this->adminName(),
            'version' => $src->version,
            'note'    => 'Duplicated from ' . $src->key,
        ]];

        $p = CacheProfile::create($attrs);

        return ResponseHelper::sendResponse($this->presentCacheProfile($p), 'Cache profile duplicated.', true, 201);
    }

    /* ═══════════════════════════════════════════════════════════
     * Telemetry + Problem Devices
     * ═══════════════════════════════════════════════════════════ */

    /** GET /admin/device-control/telemetry */
    public function telemetryIndex(Request $request)
    {
        $q = DeviceTelemetry::query()->orderBy('reported_at', 'desc');

        if ($request->filled('status')) {
            $q->where('status', $request->input('status'));
        }
        if ($request->filled('profile_key')) {
            $q->where('profile_key', $request->input('profile_key'));
        }
        if ($request->filled('manufacturer')) {
            $q->where('manufacturer', $request->input('manufacturer'));
        }
        if ($request->filled('search')) {
            $s = $request->input('search');
            $q->where(function ($w) use ($s) {
                $w->where('name', 'like', "%{$s}%")
                    ->orWhere('model', 'like', "%{$s}%")
                    ->orWhere('device_id', 'like', "%{$s}%")
                    ->orWhere('user_id', 'like', "%{$s}%")
                    ->orWhere('manufacturer', 'like', "%{$s}%");
            });
        }

        $rows = $q->limit((int) $request->input('limit', 100))->get()
            ->map(fn ($d) => $this->presentTelemetry($d))
            ->values();

        return ResponseHelper::sendResponse($rows, 'Device telemetry fetched.');
    }

    /** GET /admin/device-control/problem-devices */
    public function problemDevicesIndex(Request $request)
    {
        $q = ProblemDevice::query()->orderBy('last_seen_at', 'desc');

        if ($request->filled('status')) {
            $q->where('status', $request->input('status'));
        }
        if ($request->filled('severity')) {
            $q->where('severity', $request->input('severity'));
        }
        if ($request->filled('problem_type')) {
            $q->where('problem_type', $request->input('problem_type'));
        }
        if ($request->filled('search')) {
            $s = $request->input('search');
            $q->where(function ($w) use ($s) {
                $w->where('device_group', 'like', "%{$s}%")
                    ->orWhere('crash_signature', 'like', "%{$s}%")
                    ->orWhere('crash_cause', 'like', "%{$s}%")
                    ->orWhere('group_id', 'like', "%{$s}%")
                    ->orWhere('affected_screen', 'like', "%{$s}%")
                    ->orWhere('manufacturer', 'like', "%{$s}%");
            });
        }

        $rows = $q->limit((int) $request->input('limit', 100))->get()
            ->map(fn ($d) => $this->presentProblemDevice($d))
            ->values();

        return ResponseHelper::sendResponse($rows, 'Problem devices fetched.');
    }

    /** GET /admin/device-control/problem-devices/{id} */
    public function problemDevicesShow($id)
    {
        $p = $this->findByIdOrKey(ProblemDevice::class, $id, 'group_id');
        if (!$p) {
            return ResponseHelper::sendResponse(null, 'Problem group not found.', false, 404);
        }

        return ResponseHelper::sendResponse($this->presentProblemDevice($p), 'Problem group fetched.');
    }

    /** PUT /admin/device-control/problem-devices/{id} — status / notes update */
    public function problemDevicesUpdate(Request $request, $id)
    {
        $p = $this->findByIdOrKey(ProblemDevice::class, $id, 'group_id');
        if (!$p) {
            return ResponseHelper::sendResponse(null, 'Problem group not found.', false, 404);
        }

        foreach (['status', 'severity', 'profile_key', 'cache_profile_key', 'runtime_profile_key'] as $field) {
            if ($request->has($field)) {
                $p->{$field} = $request->input($field);
            }
        }
        $p->save();

        return ResponseHelper::sendResponse($this->presentProblemDevice($p), 'Problem group updated.');
    }

    /* ═══════════════════════════════════════════════════════════
     * Fill / Present helpers
     * ═══════════════════════════════════════════════════════════ */

    private function findByIdOrKey(string $model, string $id, string $keyField = 'key')
    {
        $row = $model::find($id);
        if ($row) {
            return $row;
        }

        return $model::where($keyField, $id)->first();
    }

    private function adminName(): string
    {
        return optional(auth()->user())->name
            ?? optional(auth()->user())->username
            ?? 'Admin';
    }

    private function appendHistory(?array $history, string $note, ?string $version = null): array
    {
        $history = is_array($history) ? $history : [];
        array_unshift($history, [
            'at'      => now()->toIso8601String(),
            'by'      => $this->adminName(),
            'version' => $version,
            'note'    => $note,
        ]);

        return array_slice($history, 0, 50);
    }

    private function fillDeviceProfile(DeviceProfile $p, Request $request, string $key): void
    {
        $p->key = $key;
        $p->name = $request->input('name', $p->name);
        $p->description = $request->input('description', $p->description ?? '');
        $p->priority = (int) $request->input('priority', $p->priority ?? 1);
        $p->color = $request->input('color', $p->color ?? '#6366f1');
        $p->version = $request->input('version', $p->version ?? 'v1.0.0');
        $p->platform = $request->input('platform', $p->platform ?? 'shared');
        $p->status = $request->input('status', $p->status ?? 'draft');
        $p->hardware = $request->input('hardware', $p->hardware ?? ['ram' => [], 'cpu' => []]);
        $p->cache_profile_key = $request->input('cache_profile_key', $p->cache_profile_key ?? $key);
        $p->runtime_profile_key = $request->input('runtime_profile_key', $p->runtime_profile_key ?? $key);
        $p->cache_dependency_mode = $request->input('cache_dependency_mode', $p->cache_dependency_mode ?? 'latest');
        $p->runtime_dependency_mode = $request->input('runtime_dependency_mode', $p->runtime_dependency_mode ?? 'latest');
        $p->assignment = $request->input('assignment', $p->assignment ?? []);
        $p->fallback = $request->input('fallback', $p->fallback ?? []);
        $p->memory = $request->input('memory', $p->memory ?? []);
        $p->cache = $request->input('cache', $p->cache ?? []);
        $p->api = $request->input('api', $p->api ?? []);
        $p->feed = $request->input('feed', $p->feed ?? []);
        $p->video = $request->input('video', $p->video ?? []);
        $p->reels = $request->input('reels', $p->reels ?? []);
        $p->rendering = $request->input('rendering', $p->rendering ?? []);
        $p->network = $request->input('network', $p->network ?? []);
        if ($request->has('assigned_devices')) {
            $p->assigned_devices = (int) $request->input('assigned_devices');
        }
        if ($request->input('status') === 'published' && !$p->published_at) {
            $p->published_at = now();
            $p->published_by = $this->adminName();
        }
    }

    private function fillRuntimeProfile(RuntimeProfile $p, Request $request, string $key): void
    {
        $p->key = $key;
        $p->name = $request->input('name', $p->name);
        $p->description = $request->input('description', $p->description ?? '');
        $p->version = $request->input('version', $p->version ?? 'v1.0.0');
        $p->status = $request->input('status', $p->status ?? 'draft');
        $p->linked_device_profiles = $request->input('linked_device_profiles', $p->linked_device_profiles ?? []);
        $p->affected_devices = (int) $request->input('affected_devices', $p->affected_devices ?? 0);
        $p->api = $request->input('api', $p->api ?? []);
        $p->feed = $request->input('feed', $p->feed ?? []);
        $p->video = $request->input('video', $p->video ?? []);
        $p->reels = $request->input('reels', $p->reels ?? []);
        $p->rendering = $request->input('rendering', $p->rendering ?? []);
        $p->network = $request->input('network', $p->network ?? []);
        if ($request->input('status') === 'published' && !$p->published_at) {
            $p->published_at = now();
        }
    }

    private function fillCacheProfile(CacheProfile $p, Request $request, string $key): void
    {
        $p->key = $key;
        $p->name = $request->input('name', $p->name);
        $p->description = $request->input('description', $p->description ?? '');
        $p->version = $request->input('version', $p->version ?? 'v1.0.0');
        $p->status = $request->input('status', $p->status ?? 'draft');
        $p->linked_device_profiles = $request->input('linked_device_profiles', $p->linked_device_profiles ?? []);
        $p->affected_devices = (int) $request->input('affected_devices', $p->affected_devices ?? 0);
        $p->allocation = $request->input('allocation', $p->allocation ?? []);
        $p->categories = $request->input('categories', $p->categories ?? []);
        $p->cleanup = $request->input('cleanup', $p->cleanup ?? []);
        $p->sync = $request->input('sync', $p->sync ?? []);
        if ($request->input('status') === 'published' && !$p->published_at) {
            $p->published_at = now();
        }
    }

    /**
     * Live fleet size per device profile_key from device_telemetry.
     * Empty / missing keys count as 0 — never fall back to seeded assigned_devices.
     *
     * @return array<string, int>
     */
    private function telemetryCountsByProfileKey(): array
    {
        if ($this->telemetryCountCache !== null) {
            return $this->telemetryCountCache;
        }

        $counts = [];
        foreach (DeviceTelemetry::query()->get(['profile_key']) as $row) {
            $key = (string) ($row->profile_key ?? '');
            if ($key === '') {
                $key = '_unassigned';
            }
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        $this->telemetryCountCache = $counts;

        return $counts;
    }

    private function liveAssignedDevices(?string $profileKey): int
    {
        if ($profileKey === null || $profileKey === '') {
            return 0;
        }

        return (int) ($this->telemetryCountsByProfileKey()[$profileKey] ?? 0);
    }

    /** Sum of live telemetry for linked device-profile keys. */
    private function liveAffectedForLinked($linked): int
    {
        $keys = is_array($linked) ? $linked : [];
        $counts = $this->telemetryCountsByProfileKey();
        $sum = 0;
        foreach ($keys as $k) {
            $sum += (int) ($counts[(string) $k] ?? 0);
        }

        return $sum;
    }

    private function presentDeviceProfile(DeviceProfile $p): array
    {
        return [
            'id'                       => (string) $p->_id,
            'key'                      => $p->key,
            'name'                     => $p->name,
            'description'              => $p->description,
            'priority'                 => (int) $p->priority,
            'color'                    => $p->color,
            'version'                  => $p->version,
            'platform'                 => $p->platform ?? 'shared',
            'status'                   => $p->status,
            'hardware'                 => $p->hardware ?? [],
            'cache_profile_key'        => $p->cache_profile_key,
            'runtime_profile_key'      => $p->runtime_profile_key,
            'cache_dependency_mode'    => $p->cache_dependency_mode ?? 'latest',
            'runtime_dependency_mode'  => $p->runtime_dependency_mode ?? 'latest',
            'assignment'               => $p->assignment ?? [],
            'fallback'                 => $p->fallback ?? [],
            'memory'                   => $p->memory ?? [],
            'cache'                    => $p->cache ?? [],
            'api'                      => $p->api ?? [],
            'feed'                     => $p->feed ?? [],
            'video'                    => $p->video ?? [],
            'reels'                    => $p->reels ?? [],
            'rendering'                => $p->rendering ?? [],
            'network'                  => $p->network ?? [],
            'assigned_devices'         => $this->liveAssignedDevices((string) $p->key),
            'published_by'             => $p->published_by,
            'published_at'             => optional($p->published_at)->toIso8601String(),
            'history'                  => $p->history ?? [],
            'updated_at'               => optional($p->updated_at)->toIso8601String(),
            'created_at'               => optional($p->created_at)->toIso8601String(),
        ];
    }

    private function presentRuntimeProfile(RuntimeProfile $p): array
    {
        $linked = $p->linked_device_profiles ?? [];

        return [
            'id'                     => (string) $p->_id,
            'key'                    => $p->key,
            'name'                   => $p->name,
            'description'            => $p->description,
            'version'                => $p->version,
            'status'                 => $p->status,
            'linked_device_profiles' => $linked,
            'affected_devices'       => $this->liveAffectedForLinked($linked),
            'api'                    => $p->api ?? [],
            'feed'                   => $p->feed ?? [],
            'video'                  => $p->video ?? [],
            'reels'                  => $p->reels ?? [],
            'rendering'              => $p->rendering ?? [],
            'network'                => $p->network ?? [],
            'history'                => $p->history ?? [],
            'published_at'           => optional($p->published_at)->toIso8601String(),
            'updated_at'             => optional($p->updated_at)->toIso8601String(),
            'created_at'             => optional($p->created_at)->toIso8601String(),
        ];
    }

    private function presentCacheProfile(CacheProfile $p): array
    {
        $linked = $p->linked_device_profiles ?? [];

        return [
            'id'                     => (string) $p->_id,
            'key'                    => $p->key,
            'name'                   => $p->name,
            'description'            => $p->description,
            'version'                => $p->version,
            'status'                 => $p->status,
            'linked_device_profiles' => $linked,
            'affected_devices'       => $this->liveAffectedForLinked($linked),
            'allocation'             => $p->allocation ?? [],
            'categories'             => $p->categories ?? [],
            'cleanup'                => $p->cleanup ?? [],
            'sync'                   => $p->sync ?? [],
            'history'                => $p->history ?? [],
            'published_at'           => optional($p->published_at)->toIso8601String(),
            'updated_at'             => optional($p->updated_at)->toIso8601String(),
            'created_at'             => optional($p->created_at)->toIso8601String(),
        ];
    }

    private function presentTelemetry(DeviceTelemetry $d): array
    {
        return [
            'id'                 => (string) $d->_id,
            'device_id'          => $d->device_id,
            'user_id'            => $d->user_id,
            'name'               => $d->name,
            'model'              => $d->model,
            'manufacturer'       => $d->manufacturer,
            'os'                 => $d->os,
            'os_version'         => $d->os_version,
            'ram'                => $d->ram,
            'ram_class'          => $d->ram_class,
            'cpu_tier'           => $d->cpu_tier,
            'profile_key'        => $d->profile_key,
            'cache_used_pct'     => (int) ($d->cache_used_pct ?? 0),
            'memory_usage_pct'   => (int) ($d->memory_usage_pct ?? 0),
            'fps'                => (int) ($d->fps ?? 0),
            'health_score'       => (int) ($d->health_score ?? 0),
            'crash'              => $d->crash,
            'crash_count'        => (int) ($d->crash_count ?? 0),
            'status'             => $d->status,
            'app_version'        => $d->app_version,
            'app_version_bucket' => $d->app_version_bucket,
            'last_seen_at'       => optional($d->last_seen_at)->toIso8601String(),
            'last_seen_bucket'   => $d->last_seen_bucket,
            'reported_at'        => optional($d->reported_at)->toIso8601String(),
        ];
    }

    private function presentProblemDevice(ProblemDevice $p): array
    {
        return [
            'id'                   => (string) $p->_id,
            'group_id'             => $p->group_id,
            'device_group'         => $p->device_group,
            'manufacturer'         => $p->manufacturer,
            'models'               => $p->models ?? [],
            'os'                   => $p->os,
            'os_version'           => $p->os_version,
            'ram'                  => $p->ram,
            'ram_class'            => $p->ram_class,
            'cpu_tier'             => $p->cpu_tier,
            'profile_key'          => $p->profile_key,
            'cache_profile_key'    => $p->cache_profile_key,
            'runtime_profile_key'  => $p->runtime_profile_key,
            'affected_devices'     => (int) ($p->affected_devices ?? 0),
            'problem_type'         => $p->problem_type,
            'crash_cause'          => $p->crash_cause,
            'crash_signature'      => $p->crash_signature,
            'affected_screen'      => $p->affected_screen,
            'app_version'          => $p->app_version,
            'severity'             => $p->severity,
            'status'               => $p->status,
            'first_seen_at'        => optional($p->first_seen_at)->toIso8601String(),
            'last_seen_at'         => optional($p->last_seen_at)->toIso8601String(),
            'crash_rate'           => (float) ($p->crash_rate ?? 0),
            'trend'                => $p->trend,
            'memory_at_crash'      => (int) ($p->memory_at_crash ?? 0),
            'cpu_usage'            => (int) ($p->cpu_usage ?? 0),
            'active_api_calls'     => (int) ($p->active_api_calls ?? 0),
            'pending_requests'     => (int) ($p->pending_requests ?? 0),
            'feed_items_mounted'   => (int) ($p->feed_items_mounted ?? 0),
            'video_players'        => (int) ($p->video_players ?? 0),
            'cache_usage'          => (int) ($p->cache_usage ?? 0),
        ];
    }
}
