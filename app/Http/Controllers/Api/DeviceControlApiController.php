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
 *  GET  /api/app/device-profile   — resolve Entry→Ultra profile from hardware
 *  POST /api/app/device-telemetry — heartbeat / health snapshot
 *  POST /api/app/device-crash     — optional crash/ANR report
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

        // Fallback: lowest priority published (usually Entry), else any Entry key.
        if (!$matched) {
            $matched = DeviceProfile::where('status', 'published')->orderBy('priority')->first()
                ?? DeviceProfile::where('key', 'entry')->first();
        }

        if (!$matched) {
            return ResponseHelper::sendResponse(null, 'No device profile configured.', false, 404);
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
            'profile' => $this->presentDevice($matched),
            'runtime' => $runtime ? $this->presentRuntime($runtime) : null,
            'cache'   => $cache ? $this->presentCache($cache) : null,
        ], 'Device profile resolved.');
    }

    /**
     * Mobile heartbeat — upsert by device_id.
     * Auth optional; if JWT present, user_id is taken from auth when not sent.
     */
    public function telemetry(Request $request)
    {
        $request->validate([
            'device_id' => 'required|string|max:120',
        ]);

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
            'ram_class'          => $this->normalizeRamClass($request->input('ram_class') ?: $request->input('ram')) ?: $row->ram_class,
            'cpu_tier'           => $request->input('cpu_tier', $row->cpu_tier),
            'profile_key'        => $request->input('profile_key', $row->profile_key),
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

        return ResponseHelper::sendResponse([
            'device_id'   => $row->device_id,
            'profile_key' => $row->profile_key,
            'status'      => $row->status,
            'reported_at' => optional($row->reported_at)->toIso8601String(),
        ], 'Telemetry saved.');
    }

    /**
     * Lightweight crash / ANR report — upserts or bumps a problem group.
     * Also ensures a Device Profile exists for this hardware (create draft if missing)
     * and links the problem group to that profile.
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

        // Ensure a device profile exists for this hardware (create draft if needed).
        $ensured = $this->ensureDeviceProfileForHardware($request, $ramClass, $cpuTier);
        $profile = $ensured['profile'];
        $profileCreated = $ensured['created'];

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
            ?: $this->inferTierKey($ramClass, $cpuTier);

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

        // Bump device telemetry crash counters when device_id known.
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
            'group_id'           => $group->group_id,
            'affected_devices'   => (int) $group->affected_devices,
            'status'             => $group->status,
            'is_new_group'       => $isNewGroup,
            'profile_key'        => $profileKey,
            'profile_created'    => $profileCreated,
            'profile_status'     => $profile?->status,
            'cache_profile_key'  => $group->cache_profile_key,
            'runtime_profile_key'=> $group->runtime_profile_key,
        ], $profileCreated
            ? 'Crash report accepted. Draft device profile created and linked.'
            : 'Crash report accepted. Linked to existing device profile.', true, 201);
    }

    /**
     * Find matching published profile, else tier key profile, else create a draft.
     *
     * @return array{profile: ?DeviceProfile, created: bool}
     */
    private function ensureDeviceProfileForHardware(Request $request, ?string $ramClass, string $cpuTier): array
    {
        $tierKey = $this->inferTierKey($ramClass, $cpuTier);

        // 1) Prefer an existing published hardware match (same as resolve).
        $published = DeviceProfile::where('status', 'published')->orderBy('priority', 'desc')->get();
        foreach ($published as $p) {
            if ($this->matchesHardware($p, $ramClass, $cpuTier, '', $request)) {
                return ['profile' => $p, 'created' => false];
            }
        }

        // 2) Existing profile by inferred tier key (draft or published).
        $existing = DeviceProfile::where('key', $tierKey)->first();
        if ($existing) {
            return ['profile' => $existing, 'created' => false];
        }

        // 3) Create draft device profile for this hardware tier.
        $safeKey = $tierKey;
        $cacheKey = CacheProfile::where('key', $safeKey)->exists() ? $safeKey : 'entry';
        $runtimeKey = RuntimeProfile::where('key', $safeKey)->exists() ? $safeKey : 'entry';
        if (! CacheProfile::where('key', $cacheKey)->exists()) {
            $cacheKey = CacheProfile::where('status', 'published')->value('key') ?: $cacheKey;
        }
        if (! RuntimeProfile::where('key', $runtimeKey)->exists()) {
            $runtimeKey = RuntimeProfile::where('status', 'published')->value('key') ?: $runtimeKey;
        }

        $name = ucfirst($tierKey);
        $colors = [
            'entry' => '#94a3b8', 'low' => '#f97316', 'balanced' => '#6366f1',
            'high' => '#22c55e', 'ultra' => '#a855f7',
        ];
        $priorities = ['entry' => 1, 'low' => 2, 'balanced' => 3, 'high' => 4, 'ultra' => 5];
        $rams = $ramClass ? [$ramClass] : ['4'];
        $cpus = array_values(array_unique(array_filter([$cpuTier, $cpuTier === 'entry' ? 'low' : null])));

        $p = new DeviceProfile();
        $p->fill([
            'key'                     => $tierKey,
            'name'                    => $name,
            'description'             => "Auto-created from problem device / crash report ({$rams[0]} GB · {$cpuTier}). Review and publish in Device Control.",
            'priority'                => $priorities[$tierKey] ?? 2,
            'color'                   => $colors[$tierKey] ?? '#6366f1',
            'version'                 => 'v1.0.0-auto',
            'platform'                => 'shared',
            'status'                  => 'draft',
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
                'mode' => 'automatic',
                'rule_priority' => $priorities[$tierKey] ?? 2,
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
                'by'      => 'Mobile Crash API',
                'version' => 'v1.0.0-auto',
                'note'    => 'Auto-created from problem device report',
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
}
