<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helpers;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\ZercashProduct;
use App\Models\ZercashSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use MongoDB\Laravel\Eloquent\Model as MongoModel;

/**
 * Public Zercash endpoints — products, plans, cashback rules, country availability,
 * shops, sale managers, FAQs, site settings + a couple of authenticated user-scoped
 * wallet routes.
 *
 * Implementation note (Jun 2026): every method here used to issue a raw
 * `DB::connection('mongodb')->collection(...)` query. That broke on the production
 * server (the installed `mongodb/laravel-mongodb` build doesn't expose the same
 * `collection()` helper as locally), giving 500s on every public Zercash route.
 * We've reworked the controller to use Eloquent models throughout — they go through
 * the same MongoDB driver but use the documented API, so the same code runs reliably
 * in both environments. Eloquent's `whereHas` / `pluck` / `paginate` also makes the
 * intent clearer than the chained raw-query builders.
 */
class ZercashApiController extends Controller
{
    /* ─────────────────────────────────────────────────────────────────────
     *  Products + plans + categories
     * ──────────────────────────────────────────────────────────────────── */

    /**
     * Normalise a product to a STABLE shape for the mobile/public client.
     *
     * MongoDB is schemaless, so a raw model only serialises the fields that physically
     * exist on that document. Older seeded rows were saved without `fiat_amount` /
     * `usd_amount`, so those keys silently vanished from the response for some products
     * and not others — the mobile app then saw "missing price". This forces every product
     * to carry the full set of price/meta fields (defaulting to 0 / null), so the client
     * can rely on a consistent contract regardless of how each row was created.
     */
    private function transformProduct(ZercashProduct $p): array
    {
        return [
            '_id'              => (string) $p->_id,
            'category'         => $p->category ?? '',
            'name'             => $p->name ?? '',
            'description'      => $p->description ?? null,
            'image'            => Helpers::mediaUrl($p->image),
            'badge'            => $p->badge ?? '',
            'zer_amount'       => (float) ($p->zer_amount ?? 0),
            'fiat_amount'      => (float) ($p->fiat_amount ?? 0),
            'usd_amount'       => (float) ($p->usd_amount ?? 0),
            'fiat_currency'    => $p->fiat_currency ?? 'EUR',
            'cashback_percent' => (float) ($p->cashback_percent ?? 0),
            'songs_count'      => (int) ($p->songs_count ?? 0),
            'features'         => is_array($p->features) ? $p->features : [],
            'status'           => $p->status ?? 'active',
            'sort_order'       => (int) ($p->sort_order ?? 0),
            'created_at'       => $p->created_at,
            'updated_at'       => $p->updated_at,
        ];
    }

    public function products(Request $request)
    {
        $query = ZercashProduct::where('status', 'active');
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }
        $products = $query->orderBy('sort_order')->orderBy('name')->get()
            ->map(fn ($p) => $this->transformProduct($p))
            ->values();

        return ResponseHelper::sendResponse($products, 'Products fetched successfully.');
    }

    public function productDetail($id)
    {
        $product = ZercashProduct::find($id);
        if (!$product) {
            return ResponseHelper::sendResponse([], 'Product not found.', false, 404);
        }
        return ResponseHelper::sendResponse($this->transformProduct($product), 'Product fetched successfully.');
    }

    public function categories()
    {
        // Distinct categories among active products — done in PHP to avoid relying on
        // driver-specific `distinct()` support across MongoDB Eloquent versions.
        $categories = ZercashProduct::where('status', 'active')
            ->pluck('category')
            ->filter()
            ->unique()
            ->values();

        return ResponseHelper::sendResponse($categories, 'Categories fetched successfully.');
    }

    public function plans()
    {
        $plans = ZercashProduct::where('category', 'choose_your_plan')
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($p) => $this->transformProduct($p))
            ->values();

        return ResponseHelper::sendResponse($plans, 'Plans fetched successfully.');
    }

    /* ─────────────────────────────────────────────────────────────────────
     *  Zercash settings (admin-managed config the mobile app reads to know
     *  the current cashback %, default currency, exchange rates, etc.)
     * ──────────────────────────────────────────────────────────────────── */

    public function settings()
    {
        $settings = ZercashSetting::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return ResponseHelper::sendResponse($settings, 'Settings fetched successfully.');
    }

    /**
     * GET /api/zercash/cashback-rules — public read of the admin's Cashback Manager.
     * Returns only `enabled=true` rules with mobile-friendly field names.
     */
    public function cashbackRules()
    {
        $row = Setting::where('group', 'zercash_cashback')->first();
        $items = is_array($row?->data ?? null) ? $row->data : [];

        $enabled = array_values(array_filter($items, fn ($it) => !empty($it['enabled'])));
        $rules = array_map(fn ($it) => [
            'id'           => $it['id']           ?? '',
            'title'        => $it['title']        ?? '',
            'description'  => $it['description']  ?? '',
            'kind'         => $it['kind']         ?? 'percent', // 'percent' | 'fixed'
            'value'        => (float) ($it['value']        ?? 0),
            'min_purchase' => (float) ($it['minPurchase']  ?? 0),
            'icon'         => $it['icon']         ?? 'gift',
        ], $enabled);

        return ResponseHelper::sendResponse(['items' => $rules], 'Cashback rules fetched.');
    }

    /**
     * GET /api/zercash/countries — admin-managed country availability matrix.
     * Default returns only `Active` countries (Zercash payments work there). Pass
     * `?include_all=1` to also receive `Restricted` / `Pending` rows so the mobile
     * app can show a "currently unavailable in your region" message.
     */
    public function zercashCountries(Request $request)
    {
        $row = Setting::where('group', 'zercash_countries')->first();
        $items = is_array($row?->data ?? null) ? $row->data : [];

        $includeAll = (bool) $request->query('include_all', false);
        $filtered = $includeAll
            ? $items
            : array_filter($items, fn ($c) => ($c['status'] ?? '') === 'Active');

        $countries = array_map(fn ($c) => [
            'id'     => $c['id']     ?? '',
            'name'   => $c['name']   ?? '',
            'flag'   => $c['flag']   ?? '',
            'status' => $c['status'] ?? 'Restricted',
            'note'   => $c['note']   ?? '',
        ], array_values($filtered));

        return ResponseHelper::sendResponse(['items' => $countries], 'Zercash countries fetched.');
    }

    /* ─────────────────────────────────────────────────────────────────────
     *  Shops / sale managers / FAQs / site settings — collections without
     *  dedicated Eloquent models. We wrap them with a minimal anonymous
     *  model so the same Eloquent-only approach works for them too.
     * ──────────────────────────────────────────────────────────────────── */

    public function shops(Request $request)
    {
        $query = $this->genericModel('shops')->newQuery();
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        $shops = $query->orderBy('created_at', 'desc')->get();

        return ResponseHelper::sendResponse($shops, 'Shops fetched successfully.');
    }

    public function saleManagers()
    {
        $managers = $this->genericModel('zercash_sale_managers')
            ->newQuery()
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->get();

        return ResponseHelper::sendResponse($managers, 'Sale managers fetched successfully.');
    }

    public function faqs(Request $request)
    {
        $query = $this->genericModel('faqs')->newQuery()->where('status', 'active');
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }
        $faqs = $query->orderBy('sort_order')->get();

        return ResponseHelper::sendResponse($faqs, 'FAQs fetched successfully.');
    }

    public function siteSettings()
    {
        $rows = $this->genericModel('site_settings')->newQuery()->get();
        // Some site_settings rows have { key, value }; flatten to a single { key: value } map
        // so the mobile app can do `settings.app_name` without iterating.
        $settings = $rows->pluck('value', 'key');

        return ResponseHelper::sendResponse($settings, 'Site settings fetched successfully.');
    }

    /* ─────────────────────────────────────────────────────────────────────
     *  Authenticated user-scoped routes
     * ──────────────────────────────────────────────────────────────────── */

    public function wallet()
    {
        $user = User::find(Auth::id());
        if (!$user) return ResponseHelper::sendResponse([], 'User not found.', false, 404);

        $setting = ZercashSetting::where('key', 'general')
            ->where('is_active', true)
            ->first();

        $wallet = [
            'balance'          => $user->wallet_balance ?? 0,
            'zer_balance'      => $user->zer_balance ?? 0,
            'cashback_percent' => $setting->transaction_fee_percent ?? 5,
            'currency'         => $setting->default_currency ?? 'EUR',
            'zer_to_euro'      => $setting->zer_to_euro ?? 0.01,
            'zer_to_dollar'    => $setting->zer_to_dollar ?? 0,
        ];

        return ResponseHelper::sendResponse($wallet, 'Wallet fetched successfully.');
    }

    public function walletTransactions(Request $request)
    {
        $transactions = Transaction::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('limit', 20));

        return ResponseHelper::sendResponse($transactions, 'Transactions fetched successfully.');
    }

    public function updateSubscription(Request $request)
    {
        $user = User::find(Auth::id());
        if (!$user) return ResponseHelper::sendResponse([], 'User not found.', false, 404);

        if ($request->has('auto_renew'))        $user->auto_renew = (bool) $request->auto_renew;
        if ($request->has('subscription_type')) $user->subscription_type = $request->subscription_type;
        $user->save();

        return ResponseHelper::sendResponse([
            'subscription_type' => $user->subscription_type,
            'auto_renew'        => $user->auto_renew ?? false,
            'expired_at'        => $user->expired_at,
            'user_type'         => $user->user_type,
        ], 'Subscription updated successfully.');
    }

    /* ─────────────────────────────────────────────────────────────────────
     *  Helper: build a throwaway Eloquent model bound to an arbitrary MongoDB
     *  collection. Used for shops / sale managers / faqs / site settings which
     *  don't have their own dedicated model class. Keeps every public endpoint
     *  on the documented Eloquent path so we don't run into the raw-query
     *  builder incompatibility that caused the May 2026 production 500s.
     * ──────────────────────────────────────────────────────────────────── */
    private function genericModel(string $table): MongoModel
    {
        return new class($table) extends MongoModel {
            protected $connection = 'mongodb';
            public $timestamps = false;
            protected $guarded = [];

            public function __construct(string $table = '')
            {
                if ($table !== '') $this->setTable($table);
                parent::__construct();
            }
        };
    }
}
