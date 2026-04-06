<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ZercashApiController extends Controller
{
    public function products(Request $request)
    {
        // ZercashProduct model placeholder - return empty for now
        return ResponseHelper::sendResponse([], 'Products fetched successfully.');
    }

    public function productDetail($id)
    {
        return ResponseHelper::sendResponse([], 'Product not found.', false, 404);
    }

    public function categories()
    {
        return ResponseHelper::sendResponse([], 'Categories fetched successfully.');
    }

    public function settings()
    {
        return ResponseHelper::sendResponse([], 'Settings fetched successfully.');
    }

    public function plans()
    {
        return ResponseHelper::sendResponse([], 'Plans fetched successfully.');
    }

    public function shops(Request $request)
    {
        return ResponseHelper::sendResponse([], 'Shops fetched successfully.');
    }

    public function saleManagers()
    {
        return ResponseHelper::sendResponse([], 'Sale managers fetched successfully.');
    }

    public function wallet()
    {
        $user = User::find(Auth::id());
        if (!$user) return ResponseHelper::sendResponse([], 'User not found.', false, 404);

        $wallet = [
            'balance' => $user->wallet_balance ?? 0,
            'zer_balance' => $user->zer_balance ?? 0,
            'currency' => 'EUR',
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
        return ResponseHelper::sendResponse([], 'FAQs fetched successfully.');
    }

    public function siteSettings()
    {
        return ResponseHelper::sendResponse([], 'Site settings fetched successfully.');
    }
}
