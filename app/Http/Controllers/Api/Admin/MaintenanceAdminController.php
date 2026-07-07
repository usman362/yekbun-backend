<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Maintenance;
use Illuminate\Http\Request;

class MaintenanceAdminController extends Controller
{
    /** GET /admin/maintenance — full config + summary (seeds defaults on first load). */
    public function index()
    {
        $config = $this->configOrSeed();
        return ResponseHelper::sendResponse($this->present($config), 'Maintenance config fetched.');
    }

    /** PUT /admin/maintenance — save the whole config from the editor. */
    public function save(Request $request)
    {
        $config = $this->configOrSeed();
        if ($request->has('full_platform')) {
            $config->full_platform = $request->boolean('full_platform');
        }
        if ($request->has('categories') && is_array($request->categories)) {
            $config->categories = $this->normalizeCategories($request->categories);
        }
        $config->save();

        return ResponseHelper::sendResponse($this->present($config), 'Maintenance config saved.');
    }

    /** GET /api/maintenance — PUBLIC: what the mobile app checks. */
    public function status()
    {
        $config = Maintenance::first();
        if (!$config) {
            return response()->json(['full_platform' => false, 'offline' => []]);
        }

        $offline = [];
        foreach (($config->categories ?? []) as $cat) {
            foreach (($cat['subcategories'] ?? []) as $sub) {
                if (empty($sub['online'])) {
                    $offline[] = [
                        'category' => $cat['name'] ?? '',
                        'sub'      => $sub['name'] ?? '',
                        'key'      => $sub['key'] ?? '',
                        'eta'      => $sub['eta'] ?? null,
                    ];
                }
            }
        }

        // HTTP 410 (Gone) whenever ANY maintenance is active — full platform OR any offline
        // section — so the mobile app detects it straight from the status code. Only fully
        // online returns 200. The body still says exactly what's offline.
        $status = ($config->full_platform || count($offline) > 0) ? 410 : 200;

        return response()->json([
            'full_platform' => (bool) $config->full_platform,
            'offline'       => $offline,
        ], $status);
    }

    // ── helpers ──

    private function present(Maintenance $config): array
    {
        $categories = $this->mergeWithDefaults($config->categories ?? []);
        $online = 0; $offline = 0; $catsWithOffline = 0;
        foreach ($categories as $cat) {
            $hasOffline = !($cat['online'] ?? true);
            foreach (($cat['subcategories'] ?? []) as $sub) {
                if (!empty($sub['online'])) $online++; else { $offline++; $hasOffline = true; }
            }
            if ($hasOffline) $catsWithOffline++;
        }

        return [
            'full_platform' => (bool) $config->full_platform,
            'categories'    => $categories,
            'summary'       => [
                'online'          => $online,
                'subs_offline'    => $offline,
                'cats_offline'    => $catsWithOffline,
            ],
        ];
    }

    private function configOrSeed(): Maintenance
    {
        $config = Maintenance::first();
        if ($config) {
            $merged = $this->mergeWithDefaults($config->categories ?? []);
            if ($merged !== ($config->categories ?? [])) {
                $config->categories = $merged;
                $config->save();
            }
            return $config;
        }

        $config = new Maintenance();
        $config->full_platform = false;
        $config->categories = $this->defaults();
        $config->save();
        return $config;
    }

    /** Merge stored rows with the canonical catalog so new sections appear after deploy. */
    private function mergeWithDefaults(array $stored): array
    {
        $storedByKey = collect($stored)->keyBy('key');
        return collect($this->defaults())->map(function ($default) use ($storedByKey) {
            $existing = $storedByKey->get($default['key']);
            if (!$existing) {
                return $default;
            }
            $defaultSubs = collect($default['subcategories'])->keyBy('key');
            $existingSubs = collect($existing['subcategories'] ?? [])->keyBy('key');
            $mergedSubs = $defaultSubs->map(function ($sub) use ($existingSubs) {
                return $existingSubs->get($sub['key']) ?? $sub;
            })->values()->all();

            return array_merge($default, $existing, ['subcategories' => $mergedSubs]);
        })->values()->all();
    }

    private function normalizeCategories(array $categories): array
    {
        return collect($categories)->map(function ($c) {
            return [
                'key'     => (string) ($c['key'] ?? \Illuminate\Support\Str::slug($c['name'] ?? 'category')),
                'name'    => (string) ($c['name'] ?? 'Category'),
                'section' => (string) ($c['section'] ?? 'Header'),
                'online'  => (bool) ($c['online'] ?? true),
                'start'   => $c['start'] ?? null,
                'end'     => $c['end'] ?? null,
                'eta'     => $c['eta'] ?? null,
                'note'    => $c['note'] ?? null,
                'message' => $c['message'] ?? null,
                'subcategories' => collect($c['subcategories'] ?? [])->map(fn ($s) => [
                    'key'     => (string) ($s['key'] ?? \Illuminate\Support\Str::slug($s['name'] ?? 'sub')),
                    'name'    => (string) ($s['name'] ?? 'Sub'),
                    'online'  => (bool) ($s['online'] ?? true),
                    'start'   => $s['start'] ?? null,
                    'end'     => $s['end'] ?? null,
                    'eta'     => $s['eta'] ?? null,
                    'note'    => $s['note'] ?? null,
                    'message' => $s['message'] ?? null,
                ])->values()->all(),
            ];
        })->values()->all();
    }

    /** Default app sections — matches yekbun-premium-ui-main reference catalog. */
    private function defaults(): array
    {
        $sub = fn (string $key, string $name) => [
            'key' => $key, 'name' => $name, 'online' => true,
            'start' => null, 'end' => null, 'eta' => null, 'note' => null, 'message' => null,
        ];
        $cat = fn (string $key, string $name, string $section, array $subs) => [
            'key' => $key, 'name' => $name, 'section' => $section, 'online' => true,
            'start' => null, 'end' => null, 'eta' => null, 'note' => null, 'message' => null,
            'subcategories' => $subs,
        ];

        return [
            $cat('kurdistan', 'Kurdistan', 'Header', [
                $sub('surveys', 'Surveys'), $sub('officials', 'Officials'), $sub('complaints', 'Complaints'),
            ]),
            $cat('wallet', 'Wallet', 'Header', [
                $sub('balance', 'Balance'), $sub('topup', 'Top Up'), $sub('transfers', 'Transfers'), $sub('transactions', 'Transactions'),
            ]),
            $cat('search', 'Search', 'Header', [
                $sub('global-search', 'Global Search'), $sub('filters', 'Filters'), $sub('recent', 'Recent Searches'),
            ]),
            $cat('location', 'Location', 'Top Navigation', [
                $sub('places', 'Places'), $sub('businesses', 'Businesses'), $sub('restaurants', 'Restaurants'), $sub('medical-care', 'Medical Care'),
            ]),
            $cat('multimedia', 'Multimedia', 'Top Navigation', [
                $sub('videos', 'Videos'), $sub('music', 'Music'), $sub('artists', 'Artists'),
                $sub('ai-videos', 'AI Videos'), $sub('history', 'History'), $sub('news', 'News'),
            ]),
            $cat('yektv', 'YekTV', 'Top Navigation', [
                $sub('tv-channels', 'TV Channels'), $sub('live-tv', 'Live TV'), $sub('radio', 'Radio'), $sub('m3u', 'M3U'),
            ]),
            $cat('live', 'Live', 'Top Navigation', [
                $sub('live-streams', 'Live Streams'), $sub('agora', 'Agora'), $sub('events', 'Events'),
            ]),
            $cat('map', 'Map', 'Top Navigation', [
                $sub('navigation', 'Navigation'), $sub('route-planning', 'Route Planning'), $sub('nearby', 'Nearby'),
            ]),
            $cat('calendar', 'Calendar', 'Top Navigation', [
                $sub('events', 'Events'), $sub('holidays', 'Holidays'), $sub('reminders', 'Reminders'),
            ]),
            $cat('market', 'Market', 'Bottom Navigation', [
                $sub('shops', 'Shops'), $sub('services', 'Services'), $sub('requests', 'Requests'),
            ]),
            $cat('friends', 'Friends & Family', 'Bottom Navigation', [
                $sub('family', 'My Family'), $sub('friends', 'My Friends'), $sub('around', 'Around You'),
            ]),
            $cat('feeds', 'Feeds', 'Bottom Navigation', [
                $sub('feeds', 'Feeds'), $sub('clips', 'Clips'), $sub('greetings', 'Greetings'),
                $sub('news', 'News'), $sub('events', 'Events'),
            ]),
            $cat('music', 'Music', 'Bottom Navigation', [
                $sub('music', 'Music'), $sub('videos', 'Videos'), $sub('artists', 'Artists'), $sub('ai-videos', 'AI Videos'),
            ]),
            $cat('officials', 'Officials', 'Bottom Navigation', [
                $sub('constitution', 'Constitution of Kurdistan'), $sub('structure', 'Structure & Ministries'),
                $sub('civil', 'Civil Law & Daily Life'), $sub('holidays', "Holidays & Memory's"),
            ]),
            $cat('mediconn', 'MediConn', 'Modules', [
                $sub('clinic', 'Clinic'), $sub('pharmacy', 'Pharmacy'), $sub('diagnostic', 'Diagnostic'),
                $sub('veterinary', 'Veterinary'), $sub('appointments', 'Appointments'),
                $sub('medical-requests', 'Medical Requests'), $sub('emergency', 'Emergency Help'),
            ]),
            $cat('revan', 'Rêvan', 'Modules', [
                $sub('trips', 'Trips'), $sub('routes', 'Routes'), $sub('drivers', 'Drivers'),
                $sub('vehicles', 'Vehicles'), $sub('setup-vehicle', 'Setup Vehicle'),
                $sub('bookings', 'Bookings'), $sub('live-tracking', 'Live Tracking'),
            ]),
            $cat('rekar', 'Rêkar', 'Modules', [
                $sub('service-requests', 'Service Requests'), $sub('open-tickets', 'Open Tickets'),
                $sub('in-progress', 'In Progress'), $sub('completed', 'Completed'),
                $sub('cancelled', 'Cancelled'), $sub('support-chat', 'Support Chat'),
            ]),
            $cat('reminder', 'Reminder', 'Modules', [
                $sub('today', 'Today'), $sub('upcoming', 'Upcoming'), $sub('completed', 'Completed'),
                $sub('cancelled', 'Cancelled'), $sub('create', 'Create Reminder'),
                $sub('notifications', 'Reminder Notifications'),
            ]),
        ];
    }
}
