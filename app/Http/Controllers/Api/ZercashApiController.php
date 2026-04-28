<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ZercashApiController extends Controller
{
    public function products(Request $request)
    {
        $query = DB::connection('mongodb')->collection('zercash_products')
            ->where('status', 'active');

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        $products = $query->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get();

        return ResponseHelper::sendResponse($products, 'Products fetched successfully.');
    }

    public function productDetail($id)
    {
        $product = DB::connection('mongodb')->collection('zercash_products')
            ->where('_id', $id)
            ->first();

        if (!$product) {
            return ResponseHelper::sendResponse([], 'Product not found.', false, 404);
        }

        return ResponseHelper::sendResponse($product, 'Product fetched successfully.');
    }

    public function categories()
    {
        $categories = DB::connection('mongodb')->collection('zercash_products')
            ->where('status', 'active')
            ->distinct('category');

        return ResponseHelper::sendResponse($categories, 'Categories fetched successfully.');
    }

    public function settings()
    {
        $settings = DB::connection('mongodb')->collection('zercash_settings')
            ->where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->get();

        return ResponseHelper::sendResponse($settings, 'Settings fetched successfully.');
    }

    public function plans()
    {
        $plans = DB::connection('mongodb')->collection('zercash_products')
            ->where('category', 'choose_your_plan')
            ->where('status', 'active')
            ->orderBy('sort_order', 'asc')
            ->get();

        return ResponseHelper::sendResponse($plans, 'Plans fetched successfully.');
    }

    public function shops(Request $request)
    {
        $query = DB::connection('mongodb')->collection('shops');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $shops = $query->orderBy('created_at', 'desc')->get();

        return ResponseHelper::sendResponse($shops, 'Shops fetched successfully.');
    }

    public function saleManagers()
    {
        $managers = DB::connection('mongodb')->collection('zercash_sale_managers')
            ->where('status', 'active')
            ->orderBy('sort_order', 'asc')
            ->get();

        return ResponseHelper::sendResponse($managers, 'Sale managers fetched successfully.');
    }

    public function wallet()
    {
        $user = User::find(Auth::id());
        if (!$user) return ResponseHelper::sendResponse([], 'User not found.', false, 404);

        $setting = DB::connection('mongodb')->collection('zercash_settings')
            ->where('key', 'general')
            ->where('is_active', true)
            ->first();

        $wallet = [
            'balance' => $user->wallet_balance ?? 0,
            'zer_balance' => $user->zer_balance ?? 0,
            'cashback_percent' => $setting['transaction_fee_percent'] ?? 5,
            'currency' => $setting['default_currency'] ?? 'EUR',
            'zer_to_euro' => $setting['zer_to_euro'] ?? 0.01,
            'zer_to_dollar' => $setting['zer_to_dollar'] ?? 0,
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

        if ($request->has('auto_renew')) $user->auto_renew = (bool) $request->auto_renew;
        if ($request->has('subscription_type')) $user->subscription_type = $request->subscription_type;
        $user->save();

        return ResponseHelper::sendResponse([
            'subscription_type' => $user->subscription_type,
            'auto_renew' => $user->auto_renew ?? false,
            'expired_at' => $user->expired_at,
            'user_type' => $user->user_type,
        ], 'Subscription updated successfully.');
    }

    public function faqs(Request $request)
    {
        $query = DB::connection('mongodb')->collection('faqs')
            ->where('status', 'active');

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        $faqs = $query->orderBy('sort_order', 'asc')->get();

        return ResponseHelper::sendResponse($faqs, 'FAQs fetched successfully.');
    }

    public function siteSettings()
    {
        $rows = DB::connection('mongodb')->collection('site_settings')->get();
        $settings = collect($rows)->pluck('value', 'key');

        return ResponseHelper::sendResponse($settings, 'Site settings fetched successfully.');
    }
}
