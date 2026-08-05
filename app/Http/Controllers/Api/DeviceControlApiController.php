<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\CacheProfile;
use App\Models\DeviceProfile;
use App\Models\DeviceTelemetry;
use App\Models\ProblemDevice;
use App\Models\RuntimeProfile;
use Illuminate\Http\Request;

/**
 * Public / authenticated Device Control endpoints for the mobile app.
 *
 *  GET  /api/app/device-profile        — resolve Entry→Ultra profile from hardware
 *  POST /api/app/device-telemetry      — heartbeat / health snapshot
 *  POST /api/app/device-crash          — optional crash/ANR report
 *  POST /api/app/device-cache-current  — update per-category cache "current" MB for a device
 */
class DeviceControlApiController extends Controller
{
    /**
     * Resolve the active device + runtime + cache pack for this handset.
     *
     * Query (or JSON body for POST alias):
     *  - ram          "4 GB" | "6 GB" | "8 GB" | "12 GB" | raw number
     *  - ram_class    "4" | "6" | "8" | "12+"   (optional if ram given)
     *  - cpu_tier     "low" | "mid" | "high" | "flagship" | "entry"
     *  - os           "Android" | "iOS"
     *  - os_version   "14"
     *  - manufacturer "Samsung"
     *  - model        "SM-A145F"
     *  - platform     "android" | "ios" | "shared"
     */
    public function resolve(Request $request)
    {
        $ramClass = $this->normalizeRamClass(
            $request->input('ram_class') ?: $request->input('ram')
        );
        $cpuTier = strtolower((string) ($request->input('cpu_tier') ?: $request->input('cpu') ?: 'mid'));
        $platform = strtolower((string) ($request->input('platform') ?: ''));
        if (!$platform && $request->filled('os')) {
            $platform = strtolower($request->input('os')) === 'ios' ? 'ios' : 'android';
        }

        // On app use: ensure a device profile exists for this hardware (create if missing).
        $ensured = $this->ensureDeviceProfileForHardware($request, $ramClass, $cpuTier);
        $profileCreated = $ensured['created'];

        $profiles = DeviceProfile::where('status', 'published')
            ->orderBy('priority', 'desc')
            ->get();

        $matched = null;
        foreach ($profiles as $p) {
            if ($this->matchesHardware($p, $ramClass, $cpuTier, $platform, $request)) {
                $matched = $p;
                break; // highest priority first
            }
        }

        // Prefer the ensured profile (may be newly created / draft) when no published match.
        if (!$matched && $ensured['profile']) {
            $matched = $ensured['profile'];
        }

        // Fallback: lowest priority published (usually Entry), else any Entry key.
        if (!$matched) {
            $matched = DeviceProfile::where('status', 'published')->orderBy('priority')->first()
                ?? DeviceProfile::where('key', 'entry')->first();
        }

        if (!$matched) {
            return ResponseHelper::sendResponse(null, 'No device profile configured.', false, 404);
        }

        // Touch usage counters so dashboard sees live activity.
        $matched->assigned_devices = (int) ($matched->assigned_devices ?? 0) + ($request->filled('device_id') ? 0 : 0);
        // Upsert telemetry when device_id sent on resolve (app open = record device).
        if ($request->filled('device_id')) {
            $this->upsertTelemetryFromRequest($request, $matched->key, $ramClass, $cpuTier);
            // Count unique devices on this profile approximately via telemetry.
            $matched->assigned_devices = (int) DeviceTelemetry::where('profile_key', $matched->key)->count();
            $matched->save();
        }

        $runtime = RuntimeProfile::where('key', $matched->runtime_profile_key)
            ->where('status', 'published')
            ->first()
            ?? RuntimeProfile::where('key', $matched->runtime_profile_key)->first();

        $cache = CacheProfile::where('key', $matched->cache_profile_key)
            ->where('status', 'published')
            ->first()
            ?? CacheProfile::where('key', $matched->cache_profile_key)->first();

        return ResponseHelper::sendResponse([
            'matched_by' => [
                'ram_class' => $ramClass,
                'cpu_tier'  => $cpuTier,
                'platform'  => $platform ?: 'shared',
            ],
            'profile_created' => $profileCreated,
            'profile' => $this->presentDevice($matched),
            'runtime' => $runtime ? $this->presentRuntime($runtime) : null,
            'cache'   => $cache ? $this->presentCache($cache) : null,
        ], $profileCreated
            ? 'Device profile created from app use and resolved.'
            : 'Device profile resolved.');
    }

    /**
     * Mobile heartbeat — upsert by device_id.
     * Auth optional; if JWT present, user_id is taken from auth when not sent.
     * Also ensures a device profile exists for this hardware (app use → record).
     */
    public function telemetry(Request $request)
    {
        $request->validate([
            'device_id' => 'required|string|max:120',
        ]);

        $ramClass = $this->normalizeRamClass(
            $request->input('ram_class') ?: $request->input('ram')
        );
        $cpuTier = strtolower((string) ($request->input('cpu_tier') ?: $request->input('cpu') ?: 'mid'));

        $ensured = $this->ensureDeviceProfileForHardware($request, $ramClass, $cpuTier);
        $profile = $ensured['profile'];
        $profileKey = $request->input('profile_key')
            ?: ($profile?->key)
            ?: $this->inferTierKey($ramClass, $cpuTier);

        $deviceId = $request->input('device_id');
        $userId = $request->input('user_id')
            ?: (optional(auth()->user())->id ? (string) auth()->user()->id : null);

        $row = DeviceTelemetry::firstOrNew(['device_id' => $deviceId]);
        $row->fill([
            'user_id'            => $userId ?: $row->user_id,
            'name'               => $request->input('name', $row->name),
            'model'              => $request->input('model', $row->model),
            'manufacturer'       => $request->input('manufacturer', $row->manufacturer),
            'os'                 => $request->input('os', $row->os),
            'os_version'         => $request->input('os_version', $row->os_version),
            'ram'                => $request->input('ram', $row->ram),
            'ram_class'          => $ramClass ?: $row->ram_class,
            'cpu_tier'           => $request->input('cpu_tier', $row->cpu_tier) ?: $cpuTier,
            'profile_key'        => $profileKey,
            'cache_used_pct'     => (int) $request->input('cache_used_pct', $row->cache_used_pct ?? 0),
            'memory_usage_pct'   => (int) $request->input('memory_usage_pct', $row->memory_usage_pct ?? 0),
            'fps'                => (int) $request->input('fps', $row->fps ?? 0),
            'health_score'       => (int) $request->input('health_score', $row->health_score ?? 0),
            'crash'              => $request->input('crash', $row->crash ?? 'None'),
            'crash_count'        => (int) $request->input('crash_count', $row->crash_count ?? 0),
            'status'             => $request->input('status', $row->status ?? 'healthy'),
            'app_version'        => $request->input('app_version', $row->app_version),
            'app_version_bucket' => $request->input('app_version_bucket', $row->app_version_bucket),
            'last_seen_bucket'   => $request->input('last_seen_bucket', 'online'),
            'last_seen_at'       => now(),
            'reported_at'        => now(),
        ]);
        $row->save();

        if ($profile) {
            $profile->assigned_devices = (int) DeviceTelemetry::where('profile_key', $profile->key)->count();
            $profile->save();
        }

        return ResponseHelper::sendResponse([
            'device_id'        => $row->device_id,
            'profile_key'      => $row->profile_key,
            'profile_created'  => $ensured['created'],
            'status'           => $row->status,
            'reported_at'      => optional($row->reported_at)->toIso8601String(),
        ], $ensured['created']
            ? 'Telemetry saved. Device profile created from app use.'
            : 'Telemetry saved.');
    }

    /**
     * Update per-device cache "current" size (MB) by category type.
     *
     * Does NOT change the shared Cache Profile max caps — only this device's usage snapshot.
     *
     * Single:
     *   { "device_id": "DEV-1", "type": "video", "current": 15 }
     *
     * Batch:
     *   { "device_id": "DEV-1", "categories": [
     *       { "type": "video", "current": 15 },
     *       { "type": "feed",  "current": 11 }
     *   ]}
     *
     * Alias fields: category / id for type; current_size / value for current.
     */
    public function cacheCurrent(Request $request)
    {
        $request->validate([
            'device_id' => 'required|string|max:120',
            'type'      => 'nullable|string|max:64',
            'category'  => 'nullable|string|max:64',
            'id'        => 'nullable|string|max:64',
            'current'   => 'nullable|numeric|min:0|max:100000',
            'current_size' => 'nullable|numeric|min:0|max:100000',
            'value'     => 'nullable|numeric|min:0|max:100000',
            'categories' => 'nullable|array|max:40',
            'categories.*.type' => 'nullable|string|max:64',
            'categories.*.category' => 'nullable|string|max:64',
            'categories.*.id' => 'nullable|string|max:64',
            'categories.*.current' => 'nullable|numeric|min:0|max:100000',
            'categories.*.current_size' => 'nullable|numeric|min:0|max:100000',
            'categories.*.value' => 'nullable|numeric|min:0|max:100000',
            'profile_key' => 'nullable|string|max:64',
        ]);

        $updates = [];
        $batch = $request->input('categories');
        if (is_array($batch) && count($batch) > 0) {
            foreach ($batch as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $parsed = $this->parseCacheCurrentItem($item);
                if ($parsed) {
                    $updates[] = $parsed;
                }
            }
        } else {
            $parsed = $this->parseCacheCurrentItem($request->all());
            if ($parsed) {
                $updates[] = $parsed;
            }
        }

        if (empty($updates)) {
            return ResponseHelper::sendResponse(null, 'Provide type + current, or categories[].', false, 422);
        }

        $allowed = $this->allowedCacheCategoryTypes();
        foreach ($updates as $u) {
            if (!in_array($u['type'], $allowed, true)) {
                return ResponseHelper::sendResponse(
                    ['allowed_types' => $allowed],
                    "Unknown category type: {$u['type']}",
                    false,
                    422
                );
            }
        }

        $deviceId = (string) $request->input('device_id');
        $row = DeviceTelemetry::firstOrNew(['device_id' => $deviceId]);

        if ($request->filled('profile_key')) {
            $row->profile_key = (string) $request->input('profile_key');
        } elseif (!$row->profile_key) {
            $ramClass = $this->normalizeRamClass($request->input('ram_class') ?: $request->input('ram'));
            $cpuTier = strtolower((string) ($request->input('cpu_tier') ?: 'mid'));
            $row->profile_key = $this->inferTierKey($ramClass, $cpuTier);
        }

        $map = is_array($row->cache_categories) ? $row->cache_categories : [];
        $now = now()->toIso8601String();
        $applied = [];

        foreach ($updates as $u) {
            $type = $u['type'];
            $current = $u['current'];
            $prev = is_array($map[$type] ?? null) ? $map[$type] : [];
            $map[$type] = [
                'current'    => $current,
                'updated_at' => $now,
            ];
            $applied[] = [
                'type'         => $type,
                'current'      => $current,
                'previous'     => isset($prev['current']) ? (float) $prev['current'] : null,
                'updated_at'   => $now,
            ];
        }

        $row->cache_categories = $map;
        $row->last_seen_at = now();
        $row->reported_at = now();
        if (!$row->status) {
            $row->status = 'healthy';
        }
        $row->save();

        // Attach max_size from published cache profile when available (read-only hint).
        $maxByType = [];
        $cacheKey = $row->profile_key
            ?: $request->input('cache_profile_key')
            ?: $request->input('profile_key');
        if ($cacheKey) {
            $cache = CacheProfile::where('key', $cacheKey)->first();
            if ($cache && is_array($cache->categories)) {
                foreach ($cache->categories as $cat) {
                    if (!is_array($cat)) {
                        continue;
                    }
                    $cid = (string) ($cat['id'] ?? $cat['type'] ?? '');
                    if ($cid !== '') {
                        $maxByType[$cid] = (int) ($cat['max_size'] ?? $cat['maxSize'] ?? 0);
                    }
                }
            }
        }

        foreach ($applied as &$a) {
            $a['max_size'] = $maxByType[$a['type']] ?? null;
        }
        unset($a);

        $totalCurrent = 0;
        foreach ($map as $entry) {
            if (is_array($entry) && isset($entry['current'])) {
                $totalCurrent += (float) $entry['current'];
            }
        }

        return ResponseHelper::sendResponse([
            'device_id'         => $row->device_id,
            'profile_key'       => $row->profile_key,
            'updated'           => $applied,
            'cache_categories'  => $map,
            'total_current_mb'  => round($totalCurrent, 2),
        ], 'Cache current updated.');
    }

    /**
     * Lightweight crash / ANR report — upserts or bumps a problem group.
     * Links to an existing device profile (does NOT create profiles — that happens on app use / resolve).
     */
    public function crash(Request $request)
    {
        $request->validate([
            'device_id'    => 'required|string|max:120',
            'problem_type' => 'required|string|max:64',
        ]);

        $signature = $request->input('crash_signature')
            ?: ($request->input('problem_type') . ' · ' . ($request->input('crash_cause') ?: 'unknown'));

        $ramClass = $this->normalizeRamClass(
            $request->input('ram_class') ?: $request->input('ram')
        );
        $cpuTier = strtolower((string) ($request->input('cpu_tier') ?: $request->input('cpu') ?: 'mid'));
        $tierKey = $this->inferTierKey($ramClass, $cpuTier);

        // Link only — do not create profiles from crashes.
        $profile = DeviceProfile::where('key', $request->input('profile_key') ?: $tierKey)->first()
            ?? DeviceProfile::where('status', 'published')->orderBy('priority')->first();

        $group = ProblemDevice::where('crash_signature', $signature)->first();
        $isNewGroup = !$group;
        if (!$group) {
            $group = new ProblemDevice();
            $group->group_id = 'PG-' . strtoupper(substr(md5($signature), 0, 8));
            $group->first_seen_at = now();
            $group->affected_devices = 1;
            $group->status = 'Open';
            $group->severity = $request->input('severity', 'High');
            $group->trend = 'up';
            $group->crash_rate = (float) $request->input('crash_rate', 0);
        } else {
            $group->affected_devices = (int) $group->affected_devices + 1;
        }

        $profileKey = $request->input('profile_key')
            ?: ($profile?->key)
            ?: $group->profile_key
            ?: $tierKey;

        $group->fill([
            'device_group'        => $request->input('device_group', $request->input('name', $group->device_group)),
            'manufacturer'        => $request->input('manufacturer', $group->manufacturer),
            'models'              => array_values(array_unique(array_filter(array_merge(
                $group->models ?? [],
                [$request->input('model')]
            )))),
            'os'                  => $request->input('os', $group->os),
            'os_version'          => $request->input('os_version', $group->os_version),
            'ram'                 => $request->input('ram', $group->ram),
            'ram_class'           => $ramClass ?: $group->ram_class,
            'cpu_tier'            => $request->input('cpu_tier', $group->cpu_tier) ?: $cpuTier,
            'profile_key'         => $profileKey,
            'cache_profile_key'   => $request->input('cache_profile_key')
                ?: ($profile?->cache_profile_key)
                ?: $group->cache_profile_key
                ?: $profileKey,
            'runtime_profile_key' => $request->input('runtime_profile_key')
                ?: ($profile?->runtime_profile_key)
                ?: $group->runtime_profile_key
                ?: $profileKey,
            'problem_type'        => $request->input('problem_type'),
            'crash_cause'         => $request->input('crash_cause', $group->crash_cause),
            'crash_signature'     => $signature,
            'affected_screen'     => $request->input('affected_screen', $group->affected_screen),
            'app_version'         => $request->input('app_version', $group->app_version),
            'memory_at_crash'     => (int) $request->input('memory_at_crash', $group->memory_at_crash ?? 0),
            'cpu_usage'           => (int) $request->input('cpu_usage', $group->cpu_usage ?? 0),
            'cache_usage'         => (int) $request->input('cache_usage', $group->cache_usage ?? 0),
            'last_seen_at'        => now(),
        ]);
        $group->save();

        if ($request->filled('device_id')) {
            $tel = DeviceTelemetry::firstOrNew(['device_id' => $request->input('device_id')]);
            $tel->fill([
                'name'         => $request->input('name', $tel->name),
                'model'        => $request->input('model', $tel->model),
                'manufacturer' => $request->input('manufacturer', $tel->manufacturer),
                'os'           => $request->input('os', $tel->os),
                'os_version'   => $request->input('os_version', $tel->os_version),
                'ram'          => $request->input('ram', $tel->ram),
                'ram_class'    => $ramClass ?: $tel->ram_class,
                'cpu_tier'     => $request->input('cpu_tier', $tel->cpu_tier) ?: $cpuTier,
                'profile_key'  => $profileKey,
                'status'       => $tel->status ?: 'critical',
            ]);
            $tel->crash = $request->input('problem_type', $tel->crash);
            $tel->crash_count = (int) ($tel->crash_count ?? 0) + 1;
            $tel->last_seen_at = now();
            $tel->reported_at = now();
            $tel->save();
        }

        return ResponseHelper::sendResponse([
            'group_id'            => $group->group_id,
            'affected_devices'    => (int) $group->affected_devices,
            'status'              => $group->status,
            'is_new_group'        => $isNewGroup,
            'profile_key'         => $profileKey,
            'cache_profile_key'   => $group->cache_profile_key,
            'runtime_profile_key' => $group->runtime_profile_key,
        ], 'Crash report accepted.', true, 201);
    }

    private function upsertTelemetryFromRequest(
        Request $request,
        string $profileKey,
        ?string $ramClass,
        string $cpuTier
    ): void {
        $tel = DeviceTelemetry::firstOrNew(['device_id' => $request->input('device_id')]);
        $tel->fill([
            'user_id'          => $request->input('user_id', $tel->user_id),
            'name'             => $request->input('name', $tel->name),
            'model'            => $request->input('model', $tel->model),
            'manufacturer'     => $request->input('manufacturer', $tel->manufacturer),
            'os'               => $request->input('os', $tel->os),
            'os_version'       => $request->input('os_version', $tel->os_version),
            'ram'              => $request->input('ram', $tel->ram),
            'ram_class'        => $ramClass ?: $tel->ram_class,
            'cpu_tier'         => $request->input('cpu_tier', $tel->cpu_tier) ?: $cpuTier,
            'profile_key'      => $profileKey,
            'status'           => $tel->status ?: 'healthy',
            'last_seen_bucket' => 'online',
            'last_seen_at'     => now(),
            'reported_at'      => now(),
        ]);
        $tel->save();
    }

    /**
     * On mobile app use (resolve / telemetry): find matching profile or create one
     * so it appears in Device Control records.
     *
     * @return array{profile: ?DeviceProfile, created: bool}
     */
    private function ensureDeviceProfileForHardware(Request $request, ?string $ramClass, string $cpuTier): array
    {
        $tierKey = $this->inferTierKey($ramClass, $cpuTier);

        // 1) Prefer existing published hardware match.
        $published = DeviceProfile::where('status', 'published')->orderBy('priority', 'desc')->get();
        foreach ($published as $p) {
            if ($this->matchesHardware($p, $ramClass, $cpuTier, '', $request)) {
                return ['profile' => $p, 'created' => false];
            }
        }

        // 2) Existing profile by tier key (any status).
        $existing = DeviceProfile::where('key', $tierKey)->first();
        if ($existing) {
            return ['profile' => $existing, 'created' => false];
        }

        // 3) Create published profile so app works + dashboard shows the record.
        $cacheKey = CacheProfile::where('key', $tierKey)->exists() ? $tierKey : 'entry';
        $runtimeKey = RuntimeProfile::where('key', $tierKey)->exists() ? $tierKey : 'entry';
        if (! CacheProfile::where('key', $cacheKey)->exists()) {
            $cacheKey = CacheProfile::where('status', 'published')->value('key') ?: $cacheKey;
        }
        if (! RuntimeProfile::where('key', $runtimeKey)->exists()) {
            $runtimeKey = RuntimeProfile::where('status', 'published')->value('key') ?: $runtimeKey;
        }

        $colors = [
            'entry' => '#94a3b8', 'low' => '#f97316', 'balanced' => '#6366f1',
            'high' => '#22c55e', 'ultra' => '#a855f7',
        ];
        $priorities = ['entry' => 1, 'low' => 2, 'balanced' => 3, 'high' => 4, 'ultra' => 5];
        $rams = $ramClass ? [$ramClass] : ['4'];
        $cpus = array_values(array_unique(array_filter([$cpuTier])));

        $p = new DeviceProfile();
        $p->fill([
            'key'                     => $tierKey,
            'name'                    => ucfirst($tierKey),
            'description'             => "Auto-created from mobile app use ({$rams[0]} GB · {$cpuTier}).",
            'priority'                => $priorities[$tierKey] ?? 2,
            'color'                   => $colors[$tierKey] ?? '#6366f1',
            'version'                 => 'v1.0.0-auto',
            'platform'                => 'shared',
            'status'                  => 'published',
            'published_at'            => now(),
            'hardware'                => [
                'ram'      => $rams,
                'cpu_tier' => $cpus ?: ['mid'],
            ],
            'cache_profile_key'       => $cacheKey,
            'runtime_profile_key'     => $runtimeKey,
            'cache_dependency_mode'   => 'latest',
            'runtime_dependency_mode' => 'latest',
            'assigned_devices'        => 0,
            'assignment'              => [
                'mode'           => 'automatic',
                'rule_priority'  => $priorities[$tierKey] ?? 2,
                'match_strategy' => 'all',
            ],
            'fallback'                => [
                'fallback_profile_key' => 'entry',
                'safe_runtime_key'     => 'entry',
                'safe_cache_key'       => 'entry',
                'auto_downgrade'       => true,
            ],
            'memory'                  => ['max' => 900, 'warn' => 700, 'critical' => 820],
            'history'                 => [[
                'at'      => now()->toIso8601String(),
                'by'      => 'Mobile App',
                'version' => 'v1.0.0-auto',
                'note'    => 'Auto-created from mobile app use (resolve/telemetry)',
            ]],
        ]);
        $p->save();

        return ['profile' => $p, 'created' => true];
    }

    private function inferTierKey(?string $ramClass, string $cpuTier): string
    {
        $cpu = strtolower($cpuTier);

        if ($ramClass === '12+' || $cpu === 'flagship') {
            return 'ultra';
        }
        if ($ramClass === '8' || $cpu === 'high') {
            return 'high';
        }
        if ($ramClass === '6') {
            return 'balanced';
        }
        if ($cpu === 'entry') {
            return 'entry';
        }
        if ($ramClass === '4' || $cpu === 'low') {
            return 'low';
        }
        if ($cpu === 'mid') {
            return 'balanced';
        }

        return 'balanced';
    }

    /* ── matching ── */

    private function matchesHardware(
        DeviceProfile $p,
        ?string $ramClass,
        string $cpuTier,
        string $platform,
        Request $request
    ): bool {
        $hw = $p->hardware ?? [];
        $pPlatform = strtolower((string) ($p->platform ?? 'shared'));
        if ($platform && $pPlatform !== 'shared' && $pPlatform !== $platform) {
            return false;
        }

        $rams = array_map('strval', $hw['ram'] ?? []);
        if ($ramClass && $rams && ! in_array($ramClass, $rams, true)) {
            // allow "12+" profile to match 12 / 16 etc. already normalized
            return false;
        }

        $cpus = array_map('strtolower', $hw['cpu_tier'] ?? $hw['cpu'] ?? []);
        if ($cpus && ! in_array($cpuTier, $cpus, true)) {
            return false;
        }

        $mfrs = $hw['manufacturers'] ?? [];
        if ($mfrs && $request->filled('manufacturer')) {
            if (! in_array($request->input('manufacturer'), $mfrs, true)) {
                return false;
            }
        }

        $exceptions = $hw['device_exceptions'] ?? [];
        if ($exceptions && $request->filled('model') && in_array($request->input('model'), $exceptions, true)) {
            return false;
        }

        return true;
    }

    private function normalizeRamClass($raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $s = strtolower(trim((string) $raw));
        if (preg_match('/12\+|12\s*\+|>=?\s*12/', $s) || (is_numeric($s) && (float) $s >= 12)) {
            return '12+';
        }
        if (preg_match('/\b12\b/', $s)) {
            return '12+';
        }
        if (preg_match('/\b8\b/', $s) || (is_numeric($s) && (float) $s >= 8 && (float) $s < 12)) {
            return '8';
        }
        if (preg_match('/\b6\b/', $s) || (is_numeric($s) && (float) $s >= 6 && (float) $s < 8)) {
            return '6';
        }
        if (preg_match('/\b4\b|u4|under/', $s) || (is_numeric($s) && (float) $s <= 4)) {
            return '4';
        }

        return in_array($s, ['4', '6', '8', '12+'], true) ? $s : null;
    }

    private function presentDevice(DeviceProfile $p): array
    {
        return [
            'key'                 => $p->key,
            'name'                => $p->name,
            'version'             => $p->version,
            'priority'            => (int) $p->priority,
            'status'              => $p->status,
            'cache_profile_key'   => $p->cache_profile_key,
            'runtime_profile_key' => $p->runtime_profile_key,
            'memory'              => $p->memory ?? [],
            'fallback'            => $p->fallback ?? [],
            'assignment'          => $p->assignment ?? [],
            'hardware'            => $p->hardware ?? [],
        ];
    }

    private function presentRuntime(RuntimeProfile $p): array
    {
        return [
            'key'     => $p->key,
            'name'    => $p->name,
            'version' => $p->version,
            'status'  => $p->status,
            'api'     => $p->api ?? [],
            'feed'    => $p->feed ?? [],
            'video'   => $p->video ?? [],
            'reels'   => $p->reels ?? [],
            'rendering' => $p->rendering ?? [],
            'network' => $p->network ?? [],
        ];
    }

    private function presentCache(CacheProfile $p): array
    {
        return [
            'key'         => $p->key,
            'name'        => $p->name,
            'version'     => $p->version,
            'status'      => $p->status,
            'allocation'  => $p->allocation ?? [],
            'categories'  => $p->categories ?? [],
            'cleanup'     => $p->cleanup ?? [],
            'sync'        => $p->sync ?? [],
        ];
    }

    /**
     * @return array{type:string,current:float}|null
     */
    private function parseCacheCurrentItem(array $item): ?array
    {
        $type = strtolower(trim((string) (
            $item['type']
            ?? $item['category']
            ?? $item['id']
            ?? ''
        )));
        if ($type === '') {
            return null;
        }

        $raw = $item['current'] ?? $item['current_size'] ?? $item['value'] ?? null;
        if ($raw === null || $raw === '') {
            return null;
        }

        return [
            'type'    => $type,
            'current' => round((float) $raw, 2),
        ];
    }

    /** @return list<string> */
    private function allowedCacheCategoryTypes(): array
    {
        return [
            'system', 'feed', 'video', 'reels', 'image', 'music', 'chat', 'maps',
            'notification', 'offline', 'downloads', 'fonts', 'emoji', 'languages',
            'policy', 'profile', 'temp',
        ];
    }
}
