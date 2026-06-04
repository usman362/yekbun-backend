<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ResponseHelper;
use App\Helpers\Helpers;
use App\Models\ZercashProduct;
use App\Models\ZercashSaleManager;
use Illuminate\Http\Request;

class ProductsAdminController extends Controller
{
    /**
     * GET /admin/products
     * Returns all Zercash products grouped by category for the ProductsPage.
     */
    public function index()
    {
        $this->seedDefaultProductsIfEmpty();
        $this->seedDefaultSaleManagersIfEmpty();

        $products = ZercashProduct::orderBy('sort_order')->orderBy('name')->get()
            ->map(function ($p) {
                return [
                    'id'                => (string) $p->_id,
                    'category'          => $p->category ?? '',
                    'name'              => $p->name ?? '',
                    'description'       => $p->description ?? '',
                    'image'             => Helpers::mediaUrl($p->image),
                    'badge'             => $p->badge ?? '',
                    'zer_amount'        => (float) ($p->zer_amount ?? 0),
                    'fiat_amount'       => (float) ($p->fiat_amount ?? 0),
                    'fiat_currency'     => $p->fiat_currency ?? 'EUR',
                    'cashback_percent'  => (float) ($p->cashback_percent ?? 0),
                    'songs_count'       => (int) ($p->songs_count ?? 0),
                    'features'          => is_array($p->features) ? $p->features : [],
                    'status'            => $p->status ?? 'active',
                    'sort_order'        => (int) ($p->sort_order ?? 0),
                ];
            })->values();

        $saleManagers = ZercashSaleManager::orderBy('sort_order')->orderBy('name')->get()
            ->map(function ($m) {
                return [
                    'id'              => (string) $m->_id,
                    'name'            => $m->name ?? '',
                    'country'         => $m->country ?? '',
                    'code'            => $m->code ?? '',
                    'city'            => $m->city ?? '',
                    'zer_in_treasur'  => (float) ($m->zer_in_treasur ?? 0),
                    'total_shops'     => (int) ($m->total_shops ?? 0),
                    'join_date'       => $m->join_date ?? '',
                    'total_win'       => (float) ($m->total_win ?? 0),
                    'image'           => Helpers::mediaUrl($m->image),
                    'sort_order'      => (int) ($m->sort_order ?? 0),
                    'status'          => $m->status ?? 'active',
                ];
            })->values();

        return ResponseHelper::sendResponse([
            'products'      => $products,
            'saleManagers'  => $saleManagers,
        ], 'Products fetched');
    }

    public function storeProduct(Request $request)
    {
        $validated = $request->validate([
            'category'         => 'required|string|max:100',
            'name'             => 'required|string|max:255',
            'description'      => 'nullable|string|max:1000',
            'image'            => 'nullable|string|max:500',
            'badge'            => 'nullable|string|max:100',
            'zer_amount'       => 'nullable|numeric|min:0',
            'fiat_amount'      => 'nullable|numeric|min:0',
            'fiat_currency'    => 'nullable|string|max:10',
            'cashback_percent' => 'nullable|numeric|min:0',
            'songs_count'      => 'nullable|integer|min:0',
            'features'         => 'nullable|array',
            'status'           => 'nullable|string|max:20',
            'sort_order'       => 'nullable|integer|min:0',
        ]);

        $product = ZercashProduct::create(array_merge($validated, [
            'fiat_currency' => strtoupper($validated['fiat_currency'] ?? 'EUR'),
            'status'        => $validated['status'] ?? 'active',
            'sort_order'    => $validated['sort_order'] ?? 0,
        ]));

        return ResponseHelper::sendResponse(['id' => (string) $product->_id], 'Product created', true, 201);
    }

    public function updateProduct(Request $request, string $id)
    {
        $product = ZercashProduct::find($id);
        if (!$product) {
            return ResponseHelper::sendResponse(null, 'Product not found', false, 404);
        }

        $validated = $request->validate([
            'category'         => 'sometimes|string|max:100',
            'name'             => 'sometimes|string|max:255',
            'description'      => 'nullable|string|max:1000',
            'image'            => 'nullable|string|max:500',
            'badge'            => 'nullable|string|max:100',
            'zer_amount'       => 'nullable|numeric|min:0',
            'fiat_amount'      => 'nullable|numeric|min:0',
            'fiat_currency'    => 'nullable|string|max:10',
            'cashback_percent' => 'nullable|numeric|min:0',
            'songs_count'      => 'nullable|integer|min:0',
            'features'         => 'nullable|array',
            'status'           => 'nullable|string|max:20',
            'sort_order'       => 'nullable|integer|min:0',
        ]);

        if (isset($validated['fiat_currency'])) {
            $validated['fiat_currency'] = strtoupper($validated['fiat_currency']);
        }

        $product->update($validated);

        return ResponseHelper::sendResponse(['id' => (string) $product->_id], 'Product updated');
    }

    public function destroyProduct(string $id)
    {
        $product = ZercashProduct::find($id);
        if (!$product) {
            return ResponseHelper::sendResponse(null, 'Product not found', false, 404);
        }
        $product->delete();
        return ResponseHelper::sendResponse(['id' => $id], 'Product deleted');
    }

    public function storeSaleManager(Request $request)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'country'        => 'nullable|string|max:100',
            'code'           => 'nullable|string|max:30',
            'city'           => 'nullable|string|max:100',
            'zer_in_treasur' => 'nullable|numeric|min:0',
            'total_shops'    => 'nullable|integer|min:0',
            'join_date'      => 'nullable|string',
            'total_win'      => 'nullable|numeric|min:0',
            'image'          => 'nullable|string|max:500',
            'sort_order'     => 'nullable|integer|min:0',
            'status'         => 'nullable|string|max:20',
        ]);

        $manager = ZercashSaleManager::create(array_merge($validated, [
            'country'    => $validated['country'] ?? 'Rojava',
            'code'       => $validated['code'] ?? '11052',
            'city'       => $validated['city'] ?? 'Qamishlo',
            'join_date'  => $validated['join_date'] ?? now()->toDateString(),
            'status'     => $validated['status'] ?? 'active',
            'sort_order' => $validated['sort_order'] ?? 0,
        ]));

        return ResponseHelper::sendResponse(['id' => (string) $manager->_id], 'Sale manager created', true, 201);
    }

    public function updateSaleManager(Request $request, string $id)
    {
        $manager = ZercashSaleManager::find($id);
        if (!$manager) {
            return ResponseHelper::sendResponse(null, 'Sale manager not found', false, 404);
        }

        $validated = $request->validate([
            'name'           => 'sometimes|string|max:255',
            'country'        => 'nullable|string|max:100',
            'code'           => 'nullable|string|max:30',
            'city'           => 'nullable|string|max:100',
            'zer_in_treasur' => 'nullable|numeric|min:0',
            'total_shops'    => 'nullable|integer|min:0',
            'join_date'      => 'nullable|string',
            'total_win'      => 'nullable|numeric|min:0',
            'image'          => 'nullable|string|max:500',
            'sort_order'     => 'nullable|integer|min:0',
            'status'         => 'nullable|string|max:20',
        ]);

        $manager->update($validated);
        return ResponseHelper::sendResponse(['id' => (string) $manager->_id], 'Sale manager updated');
    }

    public function destroySaleManager(string $id)
    {
        $manager = ZercashSaleManager::find($id);
        if (!$manager) {
            return ResponseHelper::sendResponse(null, 'Sale manager not found', false, 404);
        }
        $manager->delete();
        return ResponseHelper::sendResponse(['id' => $id], 'Sale manager deleted');
    }

    /**
     * POST /admin/products/upload-image
     *
     * Accepts a single image file (multipart `image` field), pushes it to BunnyCDN under
     * `images/products/`, and returns the resulting URL. The admin Products page calls this
     * before submitting a create/update so that the product's `image` column stores just
     * the CDN URL (a short string) — never a base64 data URL that would blow past the
     * column's `max:500` validation.
     *
     * Response: { url: "https://cdn.../images/products/<file>.jpg" }
     */
    public function uploadImage(Request $request)
    {
        $request->validate([
            // 5MB cap is plenty for product cover art / plan icons; raise here if needed.
            'image' => 'required|file|image|max:5120',
        ]);

        $url = Helpers::fileCDNUpload($request->file('image'), 'images/products');

        return ResponseHelper::sendResponse(['url' => $url], 'Image uploaded');
    }

    // ── Seeders (mirror old admin_yekbun defaults so UI is populated on first load) ──

    private function seedDefaultProductsIfEmpty(): void
    {
        if (ZercashProduct::count() > 0) {
            return;
        }

        foreach ($this->defaultProducts() as $item) {
            ZercashProduct::create(array_merge($item, [
                'status'     => 'active',
                'sort_order' => $item['sort_order'] ?? 0,
            ]));
        }
    }

    private function seedDefaultSaleManagersIfEmpty(): void
    {
        if (ZercashSaleManager::count() > 0) {
            return;
        }

        $items = [
            ['name' => 'Salemanager Name', 'country' => 'Rojava', 'code' => '11052', 'city' => 'Qamishlo', 'zer_in_treasur' => 5000, 'total_shops' => 20, 'join_date' => '2025-01-01', 'total_win' => 5000, 'sort_order' => 1, 'status' => 'active'],
            ['name' => 'Salemanager Name', 'country' => 'Rojava', 'code' => '11052', 'city' => 'Qamishlo', 'zer_in_treasur' => 5000, 'total_shops' => 20, 'join_date' => '2025-01-01', 'total_win' => 5000, 'sort_order' => 2, 'status' => 'active'],
            ['name' => 'Salemanager Name', 'country' => 'Rojava', 'code' => '11052', 'city' => 'Qamishlo', 'zer_in_treasur' => 5000, 'total_shops' => 20, 'join_date' => '2025-01-01', 'total_win' => 5000, 'sort_order' => 3, 'status' => 'active'],
        ];

        foreach ($items as $item) {
            ZercashSaleManager::create($item);
        }
    }

    private function defaultProducts(): array
    {
        return [
            // Upgrade Music Playlist
            ['category' => 'upgrade_music_playlist', 'name' => 'Bronze Playlist', 'songs_count' => 50,  'zer_amount' => 1000, 'cashback_percent' => 5, 'sort_order' => 1],
            ['category' => 'upgrade_music_playlist', 'name' => 'Silver Playlist', 'songs_count' => 75,  'zer_amount' => 1000, 'cashback_percent' => 5, 'sort_order' => 2],
            ['category' => 'upgrade_music_playlist', 'name' => 'Gold Playlist',   'songs_count' => 100, 'zer_amount' => 1000, 'cashback_percent' => 5, 'sort_order' => 3],

            // Choose Your Plan
            ['category' => 'choose_your_plan', 'name' => 'Cultivated', 'fiat_amount' => 0,  'fiat_currency' => 'EUR', 'description' => 'For individual use with 30 summaries a month, no ads, and basic support.', 'features' => ['Follow Users', 'Enjoy Music', 'Enjoy Videos', 'Standard Wallet', 'Get cashback', 'and more'], 'badge' => 'Current access', 'sort_order' => 10],
            ['category' => 'choose_your_plan', 'name' => 'Educated',   'fiat_amount' => 25, 'fiat_currency' => 'EUR', 'description' => 'Unlimited summaries, customization, quick support, & app integrations for professionals.', 'features' => ['Follow Users', 'Enjoy Music', 'Enjoy Videos', 'Create Channel', 'Text Comments', 'Voice Comment', 'Business Wallet', 'Get cashback', 'and more'], 'badge' => 'Manage access', 'sort_order' => 11],
            ['category' => 'choose_your_plan', 'name' => 'Academic',   'fiat_amount' => 25, 'fiat_currency' => 'EUR', 'description' => 'Team-focused with priority support, API access, and advanced security options.', 'features' => ['Follow Users', 'Enjoy Music', 'Enjoy Videos', 'Create Channel', 'Text Comments', 'Voice Comment', 'Business Wallet', 'Get cashback', 'and more...'], 'badge' => 'Manage access', 'sort_order' => 12],

            // Streaming Minutes
            ['category' => 'streaming_minutes', 'name' => 'Bronze Stream', 'songs_count' => 60,  'zer_amount' => 500,  'cashback_percent' => 5, 'sort_order' => 20],
            ['category' => 'streaming_minutes', 'name' => 'Silver Stream', 'songs_count' => 120, 'zer_amount' => 1000, 'cashback_percent' => 5, 'sort_order' => 21],
            ['category' => 'streaming_minutes', 'name' => 'Gold Stream',   'songs_count' => 240, 'zer_amount' => 1500, 'cashback_percent' => 5, 'sort_order' => 22],

            // Standard Zêr Package
            ['category' => 'standard_zer_package', 'name' => 'Bronze', 'zer_amount' => 1000, 'fiat_amount' => 9.99,  'fiat_currency' => 'EUR', 'sort_order' => 30],
            ['category' => 'standard_zer_package', 'name' => 'Silver', 'zer_amount' => 2500, 'fiat_amount' => 24.99, 'fiat_currency' => 'EUR', 'sort_order' => 31],
            ['category' => 'standard_zer_package', 'name' => 'Gold',   'zer_amount' => 5000, 'fiat_amount' => 49.99, 'fiat_currency' => 'EUR', 'sort_order' => 32],

            // Business Zêr Package
            ['category' => 'business_zer_package', 'name' => 'Titanium Pack', 'zer_amount' => 10000, 'fiat_amount' => 99.99,  'fiat_currency' => 'EUR', 'description' => 'Use your Balance on YekBûn', 'sort_order' => 40],
            ['category' => 'business_zer_package', 'name' => 'Platinum Pack', 'zer_amount' => 25000, 'fiat_amount' => 249.99, 'fiat_currency' => 'EUR', 'description' => 'Use your Balance on YekBûn', 'sort_order' => 41],
            ['category' => 'business_zer_package', 'name' => 'Diamond Pack',  'zer_amount' => 50000, 'fiat_amount' => 499.99, 'fiat_currency' => 'EUR', 'description' => 'Use your Balance on YekBûn', 'sort_order' => 42],
        ];
    }
}
