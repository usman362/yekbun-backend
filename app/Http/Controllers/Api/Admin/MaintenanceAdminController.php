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
            // Normalise so we never store junk.
            $config->categories = collect($request->categories)->map(function ($c) {
                return [
                    'key'     => (string) ($c['key'] ?? \Illuminate\Support\Str::slug($c['name'] ?? 'category')),
                    'name'    => (string) ($c['name'] ?? 'Category'),
                    'section' => (string) ($c['section'] ?? 'Header'),
                    'subcategories' => collect($c['subcategories'] ?? [])->map(fn($s) => [
                        'key'    => (string) ($s['key'] ?? \Illuminate\Support\Str::slug($s['name'] ?? 'sub')),
                        'name'   => (string) ($s['name'] ?? 'Sub'),
                        'online' => (bool) ($s['online'] ?? true),
                        'eta'    => $s['eta'] ?? null,
                    ])->values()->all(),
                ];
            })->values()->all();
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
        $categories = $config->categories ?? [];
        $online = 0; $offline = 0; $catsWithOffline = 0;
        foreach ($categories as $cat) {
            $hasOffline = false;
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
        if ($config) return $config;

        $config = new Maintenance();
        $config->full_platform = false;
        $config->categories = $this->defaults();
        $config->save();
        return $config;
    }

    /** Default app sections (admin can rename/retoggle; counts match the design). */
    private function defaults(): array
    {
        $sub = fn($name) => ['key' => \Illuminate\Support\Str::slug($name), 'name' => $name, 'online' => true, 'eta' => null];
        $cat = fn($name, $section, $subs) => [
            'key' => \Illuminate\Support\Str::slug($name), 'name' => $name, 'section' => $section,
            'subcategories' => array_map($sub, $subs),
        ];

        return [
            $cat('Kurdistan', 'Header', ['Surveys', 'Officials', 'Complaints']),
            $cat('Wallet', 'Header', ['Deposit', 'Send Money', 'Cashback', 'Transactions']),
            $cat('Search', 'Header', ['Users', 'Content', 'Tags']),
            $cat('Location', 'Top Navigation', ['Rojava', 'Bakur', 'Basûr', 'Rojhilat']),
            $cat('Multimedia', 'Top Navigation', ['Music', 'Songs', 'Video Clips', 'AI Videos', 'History', 'YekTV']),
            $cat('YekTV', 'Top Navigation', ['Live', 'Channels', 'Shows', 'Schedule']),
        ];
    }
}
