<?php

namespace App\Console\Commands;

use App\Models\CacheProfile;
use App\Models\DeviceProfile;
use App\Models\DeviceTelemetry;
use App\Models\ProblemDevice;
use App\Models\RuntimeProfile;
use Illuminate\Console\Command;

/**
 * Seed Device Control defaults (Entry / Low / Balanced / High / Ultra) plus
 * sample telemetry + problem-device rows for admin UI scaffolding.
 *
 * Idempotent by `key` / `device_id` / `group_id`.
 *
 *   php artisan device-control:seed-defaults
 *   php artisan device-control:seed-defaults --force   # wipe + reseed
 */
class SeedDeviceControlDefaults extends Command
{
    protected $signature = 'device-control:seed-defaults {--force : Delete existing Device Control docs before seeding}';

    protected $description = 'Seed Device Control profiles (Entry→Ultra), sample telemetry and problem devices.';

    private const AFFECTED = [
        'entry' => 742, 'low' => 2640, 'balanced' => 5310, 'high' => 2918, 'ultra' => 870,
    ];

    public function handle(): int
    {
        if ($this->option('force')) {
            DeviceProfile::query()->delete();
            RuntimeProfile::query()->delete();
            CacheProfile::query()->delete();
            DeviceTelemetry::query()->delete();
            ProblemDevice::query()->delete();
            $this->warn('Existing Device Control collections cleared (--force).');
        }

        $this->seedRuntimeProfiles();
        $this->seedCacheProfiles();
        $this->seedDeviceProfiles();
        $this->seedTelemetry();
        $this->seedProblemDevices();

        $this->info('Device Control defaults ready.');
        $this->line('  device_profiles:   ' . DeviceProfile::count());
        $this->line('  runtime_profiles:  ' . RuntimeProfile::count());
        $this->line('  cache_profiles:    ' . CacheProfile::count());
        $this->line('  device_telemetry:  ' . DeviceTelemetry::count());
        $this->line('  problem_devices:   ' . ProblemDevice::count());

        return self::SUCCESS;
    }

    /* ─── Runtime ─── */

    private function seedRuntimeProfiles(): void
    {
        foreach ($this->runtimeDefs() as $def) {
            RuntimeProfile::updateOrCreate(['key' => $def['key']], $def);
        }
        $this->info('Runtime profiles seeded.');
    }

    private function runtimeDefs(): array
    {
        $tiers = [
            'entry'    => ['p' => 2,  'q' => '240p',  'feed' => 5,  'video' => 1, 'r' => 'minimal',  'net' => 'conservative', 'dec' => 'hardware', 'ba' => 128, 'va' => 256,  'image' => 'low',      'anim' => 'off'],
            'low'      => ['p' => 3,  'q' => '480p',  'feed' => 8,  'video' => 2, 'r' => 'minimal',  'net' => 'conservative', 'dec' => 'hardware', 'ba' => 192, 'va' => 384,  'image' => 'medium',   'anim' => 'reduced'],
            'balanced' => ['p' => 6,  'q' => '720p',  'feed' => 12, 'video' => 3, 'r' => 'balanced', 'net' => 'balanced',     'dec' => 'hybrid',   'ba' => 256, 'va' => 512,  'image' => 'high',     'anim' => 'standard'],
            'high'     => ['p' => 10, 'q' => '1080p', 'feed' => 20, 'video' => 4, 'r' => 'rich',     'net' => 'aggressive',   'dec' => 'hybrid',   'ba' => 384, 'va' => 768,  'image' => 'high',     'anim' => 'rich'],
            'ultra'    => ['p' => 16, 'q' => 'auto',  'feed' => 30, 'video' => 6, 'r' => 'rich',     'net' => 'aggressive',   'dec' => 'hybrid',   'ba' => 512, 'va' => 1024, 'image' => 'original', 'anim' => 'rich'],
        ];

        $meta = [
            'entry'    => ['Entry Runtime',    'Ultra-light runtime for low-end devices.'],
            'low'      => ['Low Runtime',      'Reduced runtime footprint for 4 GB devices.'],
            'balanced' => ['Balanced Runtime', 'Default runtime for 6–8 GB devices.'],
            'high'     => ['High Runtime',     'Premium runtime for 8–12 GB devices.'],
            'ultra'    => ['Ultra Runtime',    'Maximum runtime fidelity for flagship devices.'],
        ];

        $out = [];
        foreach ($tiers as $key => $preset) {
            $tier = $key;
            $out[] = [
                'key'                    => $key,
                'name'                   => $meta[$key][0],
                'description'            => $meta[$key][1],
                'version'                => 'v1.14.2',
                'status'                 => 'published',
                'linked_device_profiles' => [$key],
                'affected_devices'       => self::AFFECTED[$key],
                'published_at'           => now(),
                'api' => [
                    'max_parallel'     => $preset['p'],
                    'background'       => true,
                    'retry'            => 'smart',
                    'queue_size'       => $preset['p'] * 4,
                    'timeout_ms'       => 15000,
                    'connection_pool'  => $preset['p'],
                    'priority'         => 'balanced',
                    'prefetch'         => $tier !== 'entry',
                    'lazy_loading'     => in_array($tier, ['entry', 'low'], true),
                    'offline'          => 'cache-first',
                ],
                'feed' => [
                    'batch_size'      => $preset['feed'],
                    'preload'         => max(1, (int) round($preset['feed'] / 4)),
                    'render_distance' => max(1, (int) round($preset['feed'] / 4)),
                    'refresh_policy'  => 'pull',
                    'api_strategy'    => 'hybrid',
                    'cache_usage'     => 60,
                    'lazy_rendering'  => true,
                    'memory_budget'   => $preset['ba'],
                    'strategy'        => in_array($tier, ['entry', 'low'], true) ? 'lazy' : (in_array($tier, ['high', 'ultra'], true) ? 'eager' : 'adaptive'),
                ],
                'video' => [
                    'autoplay'          => $tier !== 'entry',
                    'preload'           => $preset['video'] * 2,
                    'buffer'            => $preset['video'] * 4,
                    'decoder'           => $preset['dec'],
                    'hardware_decoder'  => true,
                    'software_decoder'  => $tier !== 'entry',
                    'quality'           => $preset['q'],
                    'max_active'        => $preset['video'],
                    'pause_strategy'    => 'off-screen',
                    'resume_strategy'   => 'smart',
                    'memory_budget'     => $preset['va'],
                ],
                'reels' => [
                    'initial'                  => max(1, $preset['video'] - 1),
                    'next_preload'             => max(1, (int) round($preset['video'] / 2)),
                    'unseen_pct'               => 40,
                    'new_pct'                  => 25,
                    'popular_pct'              => 20,
                    'following_pct'            => 10,
                    'watched_pct'              => 5,
                    'scroll_cache'             => $preset['video'] + 2,
                    'video_queue'              => $preset['video'] * 2,
                    'recommendation_refresh'   => $tier === 'entry' ? 30 : 15,
                    'strategy'                 => in_array($tier, ['entry', 'low'], true) ? 'lazy' : (in_array($tier, ['high', 'ultra'], true) ? 'eager' : 'adaptive'),
                ],
                'rendering' => [
                    'image_quality'          => $preset['image'],
                    'image_compression'      => $tier === 'entry' ? 80 : ($tier === 'ultra' ? 40 : 60),
                    'animation'              => $preset['anim'],
                    'blur'                   => ! in_array($tier, ['entry', 'low'], true),
                    'shadows'                => $tier !== 'entry',
                    'transition_quality'     => $tier === 'entry' ? 'minimal' : ($tier === 'ultra' ? 'premium' : 'smooth'),
                    'virtualization'         => true,
                    'flat_list_optimization' => true,
                    'window_size'            => $tier === 'entry' ? 5 : ($tier === 'ultra' ? 21 : 11),
                    'strategy'               => $preset['r'],
                ],
                'network' => [
                    'wifi'               => true,
                    'mobile'             => true,
                    'weak_network_mode'  => in_array($tier, ['entry', 'low'], true),
                    'offline_mode'       => true,
                    'background_sync'    => $tier !== 'entry',
                    'adaptive_download'  => true,
                    'bandwidth_saver'    => in_array($tier, ['entry', 'low'], true),
                    'mode'               => $preset['net'],
                ],
                'history' => [[
                    'at' => now()->toIso8601String(),
                    'by' => 'System',
                    'version' => 'v1.14.2',
                    'note' => 'Seeded default runtime profile',
                ]],
            ];
        }

        return $out;
    }

    /* ─── Cache ─── */

    private function seedCacheProfiles(): void
    {
        foreach ($this->cacheDefs() as $def) {
            CacheProfile::updateOrCreate(['key' => $def['key']], $def);
        }
        $this->info('Cache profiles seeded.');
    }

    private function cacheDefs(): array
    {
        /**
         * Explicit MB budgets by RAM class (not a flat multiplier).
         * Entry/Low keep video+reels small to avoid OOM on ≤4 GB phones.
         */
        $tiers = [
            'entry' => [
                'name' => 'Entry Cache',
                'description' => 'Ultra-light cache for ≤4 GB devices. Minimal video/reels disk budget; aggressive cleanup.',
                'version' => 'v1.15.0',
                'reserved_system' => 24,
                'expandable' => false,
                'cleanup_mode' => 'automatic',
                'triggers' => ['Low Storage', 'App Start', 'App Close', 'Cache Limit Reached'],
                'wifi_only_sync' => true,
                'mobile_data' => false,
                'max_sync_ratio' => 0.35,
                'cats' => [
                    // id => [max_mb, priority, auto_cleanup, preload, enabled]
                    'system'       => [16, 'critical', false, true,  true],
                    'feed'         => [20, 'high',     true,  true,  true],
                    'video'        => [28, 'high',     true,  false, true],
                    'reels'        => [20, 'high',     true,  true,  true],
                    'image'        => [14, 'medium',   true,  false, true],
                    'music'        => [6,  'medium',   true,  false, true],
                    'chat'         => [10, 'high',     true,  false, true],
                    'maps'         => [4,  'low',      true,  false, false],
                    'notification' => [3,  'medium',   true,  false, true],
                    'offline'      => [12, 'high',     false, true,  true],
                    'downloads'    => [8,  'medium',   false, false, true],
                    'fonts'        => [6,  'critical', false, true,  true],
                    'emoji'        => [3,  'medium',   false, true,  true],
                    'languages'    => [6,  'critical', false, true,  true],
                    'policy'       => [4,  'critical', false, true,  true],
                    'profile'      => [5,  'medium',   true,  false, true],
                    'temp'         => [4,  'low',      true,  false, true],
                ],
            ],
            'low' => [
                'name' => 'Low Cache',
                'description' => 'Reduced footprint for 4 GB devices. Video/reels capped; safe for continuous feed scroll.',
                'version' => 'v1.15.0',
                'reserved_system' => 28,
                'expandable' => true,
                'cleanup_mode' => 'automatic',
                'triggers' => ['Low Storage', 'App Close', 'Cache Limit Reached', 'Background'],
                'wifi_only_sync' => false,
                'mobile_data' => true,
                'max_sync_ratio' => 0.4,
                'cats' => [
                    'system'       => [20, 'critical', false, true,  true],
                    'feed'         => [32, 'high',     true,  true,  true],
                    'video'        => [48, 'high',     true,  false, true],
                    'reels'        => [36, 'high',     true,  true,  true],
                    'image'        => [24, 'medium',   true,  false, true],
                    'music'        => [12, 'medium',   true,  false, true],
                    'chat'         => [14, 'high',     true,  false, true],
                    'maps'         => [8,  'low',      true,  false, true],
                    'notification' => [4,  'medium',   true,  false, true],
                    'offline'      => [20, 'high',     false, true,  true],
                    'downloads'    => [16, 'medium',   false, false, true],
                    'fonts'        => [6,  'critical', false, true,  true],
                    'emoji'        => [4,  'medium',   false, true,  true],
                    'languages'    => [6,  'critical', false, true,  true],
                    'policy'       => [4,  'critical', false, true,  true],
                    'profile'      => [6,  'medium',   true,  false, true],
                    'temp'         => [6,  'low',      true,  false, true],
                ],
            ],
            'balanced' => [
                'name' => 'Balanced Cache',
                'description' => 'Default cache profile for 6–8 GB mid-tier smartphones.',
                'version' => 'v1.15.0',
                'reserved_system' => 32,
                'expandable' => true,
                'cleanup_mode' => 'automatic',
                'triggers' => ['Low Storage', 'App Close', 'Cache Limit Reached'],
                'wifi_only_sync' => false,
                'mobile_data' => true,
                'max_sync_ratio' => 0.5,
                'cats' => [
                    'system'       => [32, 'critical', false, true,  true],
                    'feed'         => [48, 'high',     true,  true,  true],
                    'video'        => [96, 'high',     true,  false, true],
                    'reels'        => [64, 'high',     true,  true,  true],
                    'image'        => [48, 'medium',   true,  false, true],
                    'music'        => [24, 'medium',   true,  false, true],
                    'chat'         => [16, 'high',     true,  false, true],
                    'maps'         => [12, 'low',      true,  false, true],
                    'notification' => [8,  'medium',   true,  false, true],
                    'offline'      => [32, 'high',     false, true,  true],
                    'downloads'    => [48, 'medium',   false, false, true],
                    'fonts'        => [8,  'critical', false, true,  true],
                    'emoji'        => [8,  'medium',   false, true,  true],
                    'languages'    => [8,  'critical', false, true,  true],
                    'policy'       => [8,  'critical', false, true,  true],
                    'profile'      => [8,  'medium',   true,  false, true],
                    'temp'         => [8,  'low',      true,  false, true],
                ],
            ],
            'high' => [
                'name' => 'High Cache',
                'description' => 'Premium cache for 8–12 GB devices. Larger video/reels/offline budgets.',
                'version' => 'v1.15.0',
                'reserved_system' => 40,
                'expandable' => true,
                'cleanup_mode' => 'hybrid',
                'triggers' => ['Low Storage', 'Cache Limit Reached'],
                'wifi_only_sync' => false,
                'mobile_data' => true,
                'max_sync_ratio' => 0.55,
                'cats' => [
                    'system'       => [40, 'critical', false, true,  true],
                    'feed'         => [80, 'high',     true,  true,  true],
                    'video'        => [180,'high',     true,  false, true],
                    'reels'        => [120,'high',     true,  true,  true],
                    'image'        => [72, 'medium',   true,  false, true],
                    'music'        => [40, 'medium',   true,  false, true],
                    'chat'         => [24, 'high',     true,  false, true],
                    'maps'         => [20, 'low',      true,  false, true],
                    'notification' => [10, 'medium',   true,  false, true],
                    'offline'      => [56, 'high',     false, true,  true],
                    'downloads'    => [80, 'medium',   false, false, true],
                    'fonts'        => [10, 'critical', false, true,  true],
                    'emoji'        => [10, 'medium',   false, true,  true],
                    'languages'    => [10, 'critical', false, true,  true],
                    'policy'       => [8,  'critical', false, true,  true],
                    'profile'      => [12, 'medium',   true,  false, true],
                    'temp'         => [12, 'low',      true,  false, true],
                ],
            ],
            'ultra' => [
                'name' => 'Ultra Cache',
                'description' => 'Maximum cache for flagship / 12 GB+ devices. Prefetch-friendly.',
                'version' => 'v1.15.0',
                'reserved_system' => 48,
                'expandable' => true,
                'cleanup_mode' => 'hybrid',
                'triggers' => ['Low Storage', 'Cache Limit Reached'],
                'wifi_only_sync' => false,
                'mobile_data' => true,
                'max_sync_ratio' => 0.6,
                'cats' => [
                    'system'       => [48, 'critical', false, true,  true],
                    'feed'         => [120,'high',     true,  true,  true],
                    'video'        => [320,'high',     true,  false, true],
                    'reels'        => [200,'high',     true,  true,  true],
                    'image'        => [120,'medium',   true,  false, true],
                    'music'        => [64, 'medium',   true,  false, true],
                    'chat'         => [32, 'high',     true,  false, true],
                    'maps'         => [32, 'low',      true,  false, true],
                    'notification' => [12, 'medium',   true,  false, true],
                    'offline'      => [96, 'high',     false, true,  true],
                    'downloads'    => [128,'medium',   false, false, true],
                    'fonts'        => [12, 'critical', false, true,  true],
                    'emoji'        => [12, 'medium',   false, true,  true],
                    'languages'    => [12, 'critical', false, true,  true],
                    'policy'       => [8,  'critical', false, true,  true],
                    'profile'      => [16, 'medium',   true,  false, true],
                    'temp'         => [16, 'low',      true,  false, true],
                ],
            ],
        ];

        $names = [
            'system' => 'System Cache', 'feed' => 'Feed Cache', 'video' => 'Video Cache',
            'reels' => 'Reels Cache', 'image' => 'Image Cache', 'music' => 'Music Cache',
            'chat' => 'Chat Cache', 'maps' => 'Maps Cache', 'notification' => 'Notification Cache',
            'offline' => 'Offline Cache', 'downloads' => 'Downloads', 'fonts' => 'Fonts',
            'emoji' => 'Emoji', 'languages' => 'Languages', 'policy' => 'Policy',
            'profile' => 'Profile Images', 'temp' => 'Temporary Files',
        ];

        $out = [];
        foreach ($tiers as $key => $t) {
            $cats = [];
            $total = 0;
            foreach ($t['cats'] as $id => [$max, $priority, $auto, $preload, $enabled]) {
                $total += (int) $max;
                $cats[] = [
                    'id'           => $id,
                    'name'         => $names[$id] ?? $id,
                    'enabled'      => (bool) $enabled,
                    'current_size' => (int) round($max * 0.55),
                    'max_size'     => (int) $max,
                    'priority'     => $priority,
                    'auto_cleanup' => (bool) $auto,
                    'preload'      => (bool) $preload,
                ];
            }

            $out[] = [
                'key'                    => $key,
                'name'                   => $t['name'],
                'description'            => $t['description'],
                'version'                => $t['version'],
                'status'                 => 'published',
                'linked_device_profiles' => [$key],
                'affected_devices'       => self::AFFECTED[$key],
                'published_at'           => now(),
                'allocation' => [
                    'total_size'      => $total,
                    'mode'            => 'hybrid',
                    'max_size'        => (int) round($total * 1.2),
                    'reserved_system' => $t['reserved_system'],
                    'expandable'      => $t['expandable'],
                ],
                'categories' => $cats,
                'cleanup' => [
                    'mode'                 => $t['cleanup_mode'],
                    'triggers'             => $t['triggers'],
                    'order'                => 'priority',
                    'protected_categories' => ['system', 'fonts', 'languages', 'policy'],
                ],
                'sync' => [
                    'on_login'      => true,
                    'on_app_start'  => true,
                    'background'    => $key !== 'entry',
                    'wifi_only'     => $t['wifi_only_sync'],
                    'mobile_data'   => $t['mobile_data'],
                    'only_changed'  => true,
                    'full_refresh'  => false,
                    'max_sync_size' => (int) round($total * $t['max_sync_ratio']),
                ],
                'history' => [[
                    'at'      => now()->toIso8601String(),
                    'by'      => 'System',
                    'version' => $t['version'],
                    'note'    => 'Memory-tier cache budgets v1.15.0 (Entry→Ultra)',
                ]],
            ];
        }

        return $out;
    }

    /* ─── Device profiles ─── */

    private function seedDeviceProfiles(): void
    {
        foreach ($this->deviceDefs() as $def) {
            DeviceProfile::updateOrCreate(['key' => $def['key']], $def);
        }
        $this->info('Device profiles seeded.');
    }

    private function deviceDefs(): array
    {
        return [
            $this->deviceRow('entry', 'Entry', 1, '#94a3b8',
                'Ultra-light configuration for low-end devices with ≤4 GB RAM.',
                'v1.14.0', ['4'], ['low'], 742, 'published',
                ['total' => 128, 'feed' => 32, 'video' => 40, 'image' => 32, 'audio' => 12, 'chat' => 8, 'map' => 4, 'cleanup' => 'automatic'],
                ['parallel' => 2, 'background' => false, 'retry' => 'exponential', 'queue' => 8],
                ['batch' => 5, 'preload' => 1, 'render_distance' => 1, 'strategy' => 'lazy'],
                ['autoplay' => false, 'buffer' => 4, 'quality' => '240p', 'max_active' => 1, 'decoder' => 'hardware'],
                ['initial' => 1, 'next_preload' => 1, 'scroll_cache' => 2, 'strategy' => 'lazy'],
                ['animation' => 'off', 'image_quality' => 'low', 'blur' => false, 'shadows' => false, 'mode' => 'minimal'],
                ['wifi' => true, 'mobile' => true, 'weak' => true, 'offline' => true, 'mode' => 'conservative'],
                ['max' => 512, 'warn' => 380, 'critical' => 460]
            ),
            $this->deviceRow('low', 'Low', 2, '#f97316',
                'Reduced footprint for 4 GB devices with basic GPUs.',
                'v1.14.1', ['4'], ['low', 'mid'], 2640, 'published',
                ['total' => 256, 'feed' => 64, 'video' => 80, 'image' => 64, 'audio' => 24, 'chat' => 16, 'map' => 8, 'cleanup' => 'hybrid'],
                ['parallel' => 3, 'background' => true, 'retry' => 'smart', 'queue' => 16],
                ['batch' => 8, 'preload' => 2, 'render_distance' => 2, 'strategy' => 'adaptive'],
                ['autoplay' => true, 'buffer' => 8, 'quality' => '480p', 'max_active' => 2, 'decoder' => 'hardware'],
                ['initial' => 2, 'next_preload' => 1, 'scroll_cache' => 3, 'strategy' => 'adaptive'],
                ['animation' => 'reduced', 'image_quality' => 'medium', 'blur' => false, 'shadows' => true, 'mode' => 'balanced'],
                ['wifi' => true, 'mobile' => true, 'weak' => true, 'offline' => true, 'mode' => 'conservative'],
                ['max' => 900, 'warn' => 700, 'critical' => 820]
            ),
            $this->deviceRow('balanced', 'Balanced', 3, '#6366f1',
                'Default profile for 6–8 GB mid-tier smartphones.',
                'v1.14.2', ['6', '8'], ['mid', 'high'], 5310, 'published',
                ['total' => 512, 'feed' => 128, 'video' => 160, 'image' => 128, 'audio' => 48, 'chat' => 32, 'map' => 16, 'cleanup' => 'hybrid'],
                ['parallel' => 6, 'background' => true, 'retry' => 'smart', 'queue' => 32],
                ['batch' => 12, 'preload' => 3, 'render_distance' => 3, 'strategy' => 'adaptive'],
                ['autoplay' => true, 'buffer' => 16, 'quality' => '720p', 'max_active' => 3, 'decoder' => 'hybrid'],
                ['initial' => 3, 'next_preload' => 2, 'scroll_cache' => 5, 'strategy' => 'adaptive'],
                ['animation' => 'standard', 'image_quality' => 'high', 'blur' => true, 'shadows' => true, 'mode' => 'balanced'],
                ['wifi' => true, 'mobile' => true, 'weak' => true, 'offline' => true, 'mode' => 'balanced'],
                ['max' => 1600, 'warn' => 1200, 'critical' => 1440]
            ),
            $this->deviceRow('high', 'High', 4, '#10b981',
                'Premium experience for 8–12 GB high-end devices.',
                'v1.14.2', ['8', '12+'], ['high'], 2918, 'published',
                ['total' => 1024, 'feed' => 256, 'video' => 320, 'image' => 256, 'audio' => 96, 'chat' => 64, 'map' => 32, 'cleanup' => 'hybrid'],
                ['parallel' => 10, 'background' => true, 'retry' => 'smart', 'queue' => 64],
                ['batch' => 20, 'preload' => 5, 'render_distance' => 4, 'strategy' => 'eager'],
                ['autoplay' => true, 'buffer' => 32, 'quality' => '1080p', 'max_active' => 4, 'decoder' => 'hybrid'],
                ['initial' => 5, 'next_preload' => 3, 'scroll_cache' => 8, 'strategy' => 'eager'],
                ['animation' => 'rich', 'image_quality' => 'high', 'blur' => true, 'shadows' => true, 'mode' => 'rich'],
                ['wifi' => true, 'mobile' => true, 'weak' => true, 'offline' => true, 'mode' => 'aggressive'],
                ['max' => 2600, 'warn' => 2000, 'critical' => 2400]
            ),
            $this->deviceRow('ultra', 'Ultra', 5, '#8b5cf6',
                'Maximum fidelity for flagship devices (12 GB+).',
                'v1.13.9', ['12+'], ['flagship'], 870, 'draft',
                ['total' => 2048, 'feed' => 512, 'video' => 640, 'image' => 512, 'audio' => 192, 'chat' => 128, 'map' => 64, 'cleanup' => 'manual'],
                ['parallel' => 16, 'background' => true, 'retry' => 'smart', 'queue' => 128],
                ['batch' => 30, 'preload' => 8, 'render_distance' => 6, 'strategy' => 'eager'],
                ['autoplay' => true, 'buffer' => 64, 'quality' => 'auto', 'max_active' => 6, 'decoder' => 'hybrid'],
                ['initial' => 8, 'next_preload' => 5, 'scroll_cache' => 12, 'strategy' => 'eager'],
                ['animation' => 'rich', 'image_quality' => 'original', 'blur' => true, 'shadows' => true, 'mode' => 'rich'],
                ['wifi' => true, 'mobile' => true, 'weak' => true, 'offline' => true, 'mode' => 'aggressive'],
                ['max' => 4096, 'warn' => 3200, 'critical' => 3800]
            ),
        ];
    }

    private function deviceRow(
        string $key, string $name, int $priority, string $color, string $description, string $version,
        array $ram, array $cpu, int $assigned, string $status,
        array $cache, array $api, array $feed, array $video, array $reels, array $rendering, array $network, array $memory
    ): array {
        return [
            'key'                     => $key,
            'name'                    => $name,
            'description'             => $description,
            'priority'                => $priority,
            'color'                   => $color,
            'version'                 => $version,
            'platform'                => 'shared',
            'status'                  => $status,
            'hardware'                => [
                'ram'               => $ram,
                'cpu_tier'          => $cpu,
                'gpu_tier'          => null,
                'min_os'            => null,
                'max_os'            => null,
                'min_free_storage'  => null,
                'manufacturers'     => [],
                'device_exceptions' => [],
            ],
            'cache_profile_key'       => $key,
            'runtime_profile_key'     => $key,
            'cache_dependency_mode'   => 'latest',
            'runtime_dependency_mode' => 'latest',
            'assignment' => [
                'mode'                    => 'automatic',
                'rule_priority'           => $priority,
                'match_strategy'          => 'all',
                'reassess_enabled'        => true,
                'reassess_on_app_update'  => true,
                'reassess_on_os_update'   => true,
                'reassess_on_crash_spike' => true,
            ],
            'fallback' => [
                'fallback_profile_key'    => 'entry',
                'safe_runtime_key'        => 'entry',
                'safe_cache_key'          => 'entry',
                'auto_downgrade'          => true,
                'rollback_on_regression'  => true,
                'max_crash_rate'          => 2,
                'max_anr_rate'            => 0.5,
                'memory_pressure_trigger' => 'critical',
                'slow_startup_trigger'    => 6,
            ],
            'memory'           => $memory,
            'cache'            => $cache,
            'api'              => $api,
            'feed'             => $feed,
            'video'            => $video,
            'reels'            => $reels,
            'rendering'        => $rendering,
            'network'          => $network,
            'assigned_devices' => $assigned,
            'published_by'     => $status === 'published' ? 'System' : null,
            'published_at'     => $status === 'published' ? now() : null,
            'history' => [[
                'at' => now()->toIso8601String(),
                'by' => 'System',
                'version' => $version,
                'note' => 'Seeded default device profile',
            ]],
        ];
    }

    /* ─── Telemetry sample ─── */

    private function seedTelemetry(): void
    {
        $rows = [
            ['D-01', 'u_9214', 'Galaxy S24 Ultra', 'SM-S928B', 'Samsung', 'Android', '14', '12 GB', '12+', 'Flagship', 'ultra',    78, 62, 60, 98, 'None',        0,  'healthy',  '3.14.2', 'latest',   'online'],
            ['D-02', 'u_1077', 'iPhone 15 Pro',    'A2848',    'Apple',   'iOS',     '17', '8 GB',  '8',   'Flagship', 'high',     62, 55, 60, 96, 'None',        1,  'healthy',  '3.14.2', 'latest',   'online'],
            ['D-03', 'u_2384', 'Pixel 8',          'GKWS6',    'Google',  'Android', '14', '8 GB',  '8',   'High',     'high',     55, 51, 58, 94, 'None',        0,  'healthy',  '3.14.2', 'latest',   'hour'],
            ['D-04', 'u_4581', 'Redmi Note 12',    '23021RA',  'Xiaomi',  'Android', '13', '6 GB',  '6',   'Mid',      'balanced', 71, 78, 48, 74, 'OutOfMemory', 3,  'warning',  '3.14.2', 'latest',   'today'],
            ['D-05', 'u_6620', 'Galaxy A14',       'SM-A145F', 'Samsung', 'Android', '13', '4 GB',  '4',   'Low',      'low',      84, 92, 32, 42, 'OutOfMemory', 12, 'critical', '3.12.0', 'outdated', 'today'],
            ['D-06', 'u_7712', 'iPhone SE (2020)', 'A2275',    'Apple',   'iOS',     '16', '3 GB',  '4',   'Mid',      'low',      66, 74, 44, 68, 'JavaScript',  2,  'warning',  '3.13.1', 'outdated', 'week'],
            ['D-07', 'u_8809', 'Nokia G10',        'TA-1338',  'Nokia',   'Android', '12', '3 GB',  '4',   'Low',      'entry',    92, 88, 26, 28, 'ANR',         18, 'critical', '3.10.4', 'outdated', 'offline'],
            ['D-08', 'u_1902', 'OnePlus 11',       'CPH2449',  'OnePlus', 'Android', '14', '12 GB', '12+', 'Flagship', 'ultra',    48, 44, 60, 99, 'None',        0,  'healthy',  '3.15.0-b3', 'beta', 'online'],
            ['D-09', 'u_5502', 'Xiaomi 13 Pro',    '2210132C', 'Xiaomi',  'Android', '14', '12 GB', '12+', 'Flagship', 'ultra',    52, 48, 60, 97, 'None',        0,  'healthy',  '3.14.2', 'latest',   'online'],
            ['D-10', 'u_6634', 'Vivo Y36',         'V2247',    'Vivo',    'Android', '13', '8 GB',  '8',   'Mid',      'balanced', 60, 58, 55, 88, 'None',        0,  'healthy',  '3.14.2', 'latest',   'hour'],
            ['D-11', 'u_2299', 'Oppo Reno 8',      'CPH2359',  'Oppo',    'Android', '13', '8 GB',  '8',   'High',     'high',     58, 62, 58, 91, 'None',        0,  'healthy',  '3.14.2', 'latest',   'today'],
            ['D-12', 'u_8121', 'Motorola G54',     'XT2343',   'Motorola','Android', '13', '8 GB',  '8',   'Mid',      'balanced', 68, 66, 52, 82, 'Native',      1,  'warning',  '3.14.2', 'latest',   'today'],
            ['D-13', 'u_5566', 'Nothing Phone 2',  'A065',     'Nothing', 'Android', '14', '12 GB', '12+', 'Flagship', 'high',     41, 39, 60, 99, 'None',        0,  'healthy',  '3.15.0-b3', 'beta', 'online'],
            ['D-14', 'u_4413', 'Huawei P30 Lite',  'MAR-LX1A', 'Huawei',  'Android', '10', '4 GB',  '4',   'Low',      'entry',    88, 82, 30, 38, 'Startup',     6,  'critical', '3.08.0', 'outdated', 'week'],
            ['D-15', 'u_1147', 'Sony Xperia 5 IV', 'XQ-CQ54',  'Sony',    'Android', '13', '8 GB',  '8',   'High',     'high',     50, 47, 60, 95, 'None',        0,  'healthy',  '3.14.2', 'latest',   'online'],
        ];

        foreach ($rows as $r) {
            DeviceTelemetry::updateOrCreate(
                ['device_id' => $r[0]],
                [
                    'device_id'          => $r[0],
                    'user_id'            => $r[1],
                    'name'               => $r[2],
                    'model'              => $r[3],
                    'manufacturer'       => $r[4],
                    'os'                 => $r[5],
                    'os_version'         => $r[6],
                    'ram'                => $r[7],
                    'ram_class'          => $r[8],
                    'cpu_tier'           => $r[9],
                    'profile_key'        => $r[10],
                    'cache_used_pct'     => $r[11],
                    'memory_usage_pct'   => $r[12],
                    'fps'                => $r[13],
                    'health_score'       => $r[14],
                    'crash'              => $r[15],
                    'crash_count'        => $r[16],
                    'status'             => $r[17],
                    'app_version'        => $r[18],
                    'app_version_bucket' => $r[19],
                    'last_seen_bucket'   => $r[20],
                    'last_seen_at'       => now()->subMinutes(rand(1, 600)),
                    'reported_at'        => now(),
                ]
            );
        }
        $this->info('Device telemetry samples seeded.');
    }

    /* ─── Problem devices sample ─── */

    private function seedProblemDevices(): void
    {
        $rows = [
            [
                'group_id' => 'PG-001', 'device_group' => 'Samsung Galaxy A14', 'manufacturer' => 'Samsung',
                'models' => ['SM-A145F', 'SM-A145M'], 'os' => 'Android', 'os_version' => '13',
                'ram' => '4 GB', 'ram_class' => '4', 'cpu_tier' => 'Low',
                'profile_key' => 'low', 'cache_profile_key' => 'low', 'runtime_profile_key' => 'low',
                'affected_devices' => 4218, 'problem_type' => 'OutOfMemory',
                'crash_cause' => 'OutOfMemory in FeedRenderer.mountItem()',
                'crash_signature' => 'OOM · FeedRenderer#mountItem · 0x7fa2',
                'affected_screen' => 'Feed', 'app_version' => '3.14.2',
                'severity' => 'Critical', 'status' => 'Open', 'crash_rate' => 3.8, 'trend' => 'up',
                'memory_at_crash' => 96, 'cpu_usage' => 88, 'active_api_calls' => 14,
                'pending_requests' => 22, 'feed_items_mounted' => 60, 'video_players' => 4, 'cache_usage' => 92,
            ],
            [
                'group_id' => 'PG-002', 'device_group' => 'Nokia G10', 'manufacturer' => 'Nokia',
                'models' => ['TA-1338'], 'os' => 'Android', 'os_version' => '12',
                'ram' => '3 GB', 'ram_class' => '4', 'cpu_tier' => 'Low',
                'profile_key' => 'entry', 'cache_profile_key' => 'entry', 'runtime_profile_key' => 'entry',
                'affected_devices' => 1890, 'problem_type' => 'ANR',
                'crash_cause' => 'ANR — main thread blocked on FeedScroll',
                'crash_signature' => 'ANR · FeedScroll#onLayout · 0x11ab',
                'affected_screen' => 'Reels', 'app_version' => '3.10.4',
                'severity' => 'Critical', 'status' => 'Under Review', 'crash_rate' => 5.2, 'trend' => 'up',
                'memory_at_crash' => 89, 'cpu_usage' => 94, 'active_api_calls' => 8,
                'pending_requests' => 11, 'feed_items_mounted' => 22, 'video_players' => 3, 'cache_usage' => 88,
            ],
            [
                'group_id' => 'PG-003', 'device_group' => 'Redmi Note 12', 'manufacturer' => 'Xiaomi',
                'models' => ['23021RA'], 'os' => 'Android', 'os_version' => '13',
                'ram' => '6 GB', 'ram_class' => '6', 'cpu_tier' => 'Mid',
                'profile_key' => 'balanced', 'cache_profile_key' => 'balanced', 'runtime_profile_key' => 'balanced',
                'affected_devices' => 980, 'problem_type' => 'VideoDecoder',
                'crash_cause' => 'Video decoder init failed',
                'crash_signature' => 'Native · MediaCodec#configure · 0x gu9',
                'affected_screen' => 'Player', 'app_version' => '3.14.2',
                'severity' => 'High', 'status' => 'Open', 'crash_rate' => 1.4, 'trend' => 'flat',
                'memory_at_crash' => 74, 'cpu_usage' => 71, 'active_api_calls' => 6,
                'pending_requests' => 4, 'feed_items_mounted' => 30, 'video_players' => 5, 'cache_usage' => 71,
            ],
            [
                'group_id' => 'PG-004', 'device_group' => 'iPhone SE (2020)', 'manufacturer' => 'Apple',
                'models' => ['A2275'], 'os' => 'iOS', 'os_version' => '16',
                'ram' => '3 GB', 'ram_class' => '4', 'cpu_tier' => 'Mid',
                'profile_key' => 'low', 'cache_profile_key' => 'low', 'runtime_profile_key' => 'low',
                'affected_devices' => 640, 'problem_type' => 'JavaScriptCrash',
                'crash_cause' => 'NSInvalidArgumentException in ChatModule',
                'crash_signature' => 'JS · ChatModule#render · 0x9921',
                'affected_screen' => 'Chat', 'app_version' => '3.13.1',
                'severity' => 'Warning', 'status' => 'Open', 'crash_rate' => 0.6, 'trend' => 'down',
                'memory_at_crash' => 68, 'cpu_usage' => 62, 'active_api_calls' => 5,
                'pending_requests' => 2, 'feed_items_mounted' => 10, 'video_players' => 0, 'cache_usage' => 66,
            ],
            [
                'group_id' => 'PG-005', 'device_group' => 'Huawei P30 Lite', 'manufacturer' => 'Huawei',
                'models' => ['MAR-LX1A'], 'os' => 'Android', 'os_version' => '10',
                'ram' => '4 GB', 'ram_class' => '4', 'cpu_tier' => 'Low',
                'profile_key' => 'entry', 'cache_profile_key' => 'entry', 'runtime_profile_key' => 'entry',
                'affected_devices' => 1120, 'problem_type' => 'SlowStartup',
                'crash_cause' => 'Startup crash — SIGSEGV',
                'crash_signature' => 'Startup · NativeInit · SIGSEGV',
                'affected_screen' => 'Boot', 'app_version' => '3.08.0',
                'severity' => 'Critical', 'status' => 'Open', 'crash_rate' => 4.1, 'trend' => 'up',
                'memory_at_crash' => 82, 'cpu_usage' => 78, 'active_api_calls' => 0,
                'pending_requests' => 0, 'feed_items_mounted' => 0, 'video_players' => 0, 'cache_usage' => 88,
            ],
        ];

        foreach ($rows as $row) {
            $row['first_seen_at'] = now()->subDays(rand(3, 30));
            $row['last_seen_at'] = now()->subMinutes(rand(5, 180));
            ProblemDevice::updateOrCreate(['group_id' => $row['group_id']], $row);
        }
        $this->info('Problem device groups seeded.');
    }
}
