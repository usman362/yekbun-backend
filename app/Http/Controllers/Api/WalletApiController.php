<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\KycVerification;
use App\Models\ZercashSetting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class WalletApiController extends Controller
{

    public function createWallet(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pin' => 'required|string|size:4|regex:/^[0-9]{4}$/',
        ]);

        if ($validator->fails()) {
            return ResponseHelper::sendResponse(
                $validator->errors(),
                'Validation error.',
                false,
                422
            );
        }

        $user = Auth::user();

        if (!$user) {
            return ResponseHelper::sendResponse(null, 'User not found.', false, 404);
        }

        $wallet = Wallet::where('user_id', $user->getKey())->first();

        if ($wallet) {
            // Wallet already exists (created during KYC approval) — just set/update the PIN
            $wallet->pin = bcrypt($request->pin);
            $wallet->save();

            $user->wallet_id     = $wallet->getKey();
            $user->wallet_status = $wallet->status ?? $user->wallet_status;
            $user->save();

            return ResponseHelper::sendResponse([
                'wallet'      => $wallet,
                'userDetails' => $this->getUserDetails($user),
            ], 'Wallet PIN set successfully.');
        }

        $wallet = new Wallet();
        $wallet->user_id    = $user->getKey();
        $wallet->pin        = bcrypt($request->pin);
        $wallet->status     = 'under_review';
        $wallet->created_at = Carbon::now();
        $wallet->save();

        // Save wallet_id on user for userDetails response
        $user->wallet_id     = $wallet->getKey();
        $user->wallet_status = 'under_review';
        $user->save();

        return ResponseHelper::sendResponse([
            'wallet'      => $wallet,
            'userDetails' => $this->getUserDetails($user),
        ], 'Wallet created successfully.');
    }


    public function activateWallet(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return ResponseHelper::sendResponse(
                $validator->errors(),
                'Validation error.',
                false,
                422
            );
        }

        $wallet = Wallet::where('user_id', $request->user_id)->first();

        if (!$wallet) {
            return ResponseHelper::sendResponse(null, 'Wallet not found.', false, 404);
        }

        $wallet->status = 'activated';
        $wallet->activated_at = Carbon::now();
        $wallet->save();

        // Sync wallet status on user
        $user = User::find($request->user_id);
        if ($user) {
            $user->wallet_status = 'activated';
            $user->save();
        }

        return ResponseHelper::sendResponse([
            'wallet'      => $wallet,
            'userDetails' => $user ? $this->getUserDetails($user) : null,
        ], 'Wallet activated.');
    }


    public function verifyPin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pin' => 'required|string|size:4',
        ]);

        if ($validator->fails()) {
            return ResponseHelper::sendResponse(
                $validator->errors(),
                'Validation error.',
                false,
                422
            );
        }

        $user = Auth::user();

        $wallet = Wallet::where('user_id', $user->_id)->first();

        if (!$wallet) {
            return ResponseHelper::sendResponse(null, 'Wallet not found.', false, 404);
        }

        if (!password_verify($request->pin, $wallet->pin)) {
            return ResponseHelper::sendResponse(null, 'Invalid PIN.', false, 401);
        }

        // Welcome bonus on first verify-pin
        $bonusGiven = false;
        if (empty($wallet->welcome_bonus_claimed)) {
            $bonusAmount = 300;
            $wallet->balance = ($wallet->balance ?? 0) + $bonusAmount;
            $wallet->welcome_bonus_claimed = true;
            $wallet->welcome_bonus_amount = $bonusAmount;
            $wallet->welcome_bonus_at = Carbon::now();
            $wallet->save();

            // Create bonus transaction
            $transaction = new Transaction();
            $transaction->user_id = $user->_id;
            $transaction->transaction_type = 'deposit';
            $transaction->category = 'welcome_bonus';
            $transaction->amount = $bonusAmount;
            $transaction->currency = 'ZER';
            $transaction->status = 'COMPLETED';
            $transaction->description = 'Welcome Bonus';
            $transaction->date = Carbon::now()->format('Y-m-d');
            $transaction->created_at = Carbon::now();
            $transaction->save();

            $bonusGiven = true;
        }

        return ResponseHelper::sendResponse([
            'verified'     => true,
            'bonus_given'  => $bonusGiven,
            'bonus_amount' => $bonusGiven ? 300 : 0,
            'userDetails'  => $this->getUserDetails($user),
        ], $bonusGiven ? 'PIN verified. Welcome bonus added!' : 'PIN verified.');
    }


    public function changePin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_pin' => 'required|string|size:4',
            'new_pin'     => 'required|string|size:4|regex:/^[0-9]{4}$/',
        ]);

        if ($validator->fails()) {
            return ResponseHelper::sendResponse(
                $validator->errors(),
                'Validation error.',
                false,
                422
            );
        }

        $user = Auth::user();

        $wallet = Wallet::where('user_id', $user->_id)->first();

        if (!$wallet) {
            return ResponseHelper::sendResponse(null, 'Wallet not found.', false, 404);
        }

        if (!password_verify($request->current_pin, $wallet->pin)) {
            return ResponseHelper::sendResponse(null, 'Current PIN incorrect.', false, 401);
        }

        $wallet->pin = bcrypt($request->new_pin);
        $wallet->save();

        return ResponseHelper::sendResponse(null, 'PIN changed successfully.');
    }

    public function walletStatus()
    {
        $user = Auth::user();

        if (!$user) {
            return ResponseHelper::sendResponse(null, 'User not found.', false, 404);
        }

        $wallet = Wallet::where('user_id', $user->_id)->first();

        $userDetails = $this->getUserDetails($user);

        if (!$wallet) {
            return ResponseHelper::sendResponse([
                'has_wallet'      => false,
                'wallet_id'       => null,
                'wallet_status'   => 'not_found',
                'status_message'  => 'No wallet found. Please create one.',
                'hold_reason'     => null,
                'expire_at'       => null,
                'created_at'      => null,
                'userDetails'     => $userDetails,
            ], 'No wallet found. Please create one.');
        }

        $statusMessages = [
            'not_found'    => 'No wallet found. Please create one.',
            'pending'      => 'Your request is pending review.',
            'under_review' => 'We will review your request. We will get back soon.',
            'active'       => 'All wallet features are now available.',
            'activated'    => 'All wallet features are now available.',
            'approved'     => 'All wallet features are now available.',
            'on_hold'      => 'Wallet is on Hold. See the reason here.',
            'rejected'     => 'Your wallet request was rejected.',
            'closed'       => 'Wallet is Closed. The account will be removed after 90 Days.',
        ];

        $status = $wallet->status ?? 'under_review';

        return ResponseHelper::sendResponse([
            'has_wallet'      => true,
            'wallet_id'       => $this->maskWalletId($wallet->_id),
            'wallet_status'   => $status,
            // Prefer the message the admin saved on the wallet row (if any); fall back to the
            // static map for legacy/mobile-initiated rows that don't carry a status_message.
            'status_message'  => $wallet->status_message ?? ($statusMessages[$status] ?? 'Unknown status.'),
            'hold_reason'     => $wallet->status_reason ?? null,
            'expire_at'       => $wallet->expire_at ?? null,
            'created_at'      => $wallet->created_at ?? null,
            'userDetails'     => $userDetails,
        ], 'Wallet status fetched.');
    }

    public function updateWalletStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|string',
            'status'  => 'required|in:under_review,activated,on_hold,closed',
            'reason'  => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ResponseHelper::sendResponse(
                $validator->errors(),
                'Validation error.',
                false,
                422
            );
        }

        $wallet = Wallet::where('user_id', $request->user_id)->first();

        if (!$wallet) {
            return ResponseHelper::sendResponse(null, 'Wallet not found.', false, 404);
        }

        $wallet->status = $request->status;
        $wallet->status_reason = $request->reason;
        $wallet->updated_at = Carbon::now();
        $wallet->save();

        // Sync wallet status on user
        $user = User::find($request->user_id);
        if ($user) {
            $user->wallet_status = $request->status;
            $user->save();
        }

        return ResponseHelper::sendResponse([
            'wallet'      => $wallet,
            'userDetails' => $user ? $this->getUserDetails($user) : null,
        ], 'Wallet status updated.');
    }


    public function deposit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount'         => 'required|numeric|min:1',
            'payment_method' => 'required|in:card,paypal,bank',
            'description'    => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ResponseHelper::sendResponse(
                $validator->errors(),
                'Validation error.',
                false,
                422
            );
        }

        $user = Auth::user();

        $wallet = Wallet::where('user_id', $user->_id)->first();

        if (!$wallet) {
            return ResponseHelper::sendResponse(null, 'Wallet not found.', false, 404);
        }

        $wallet->balance += $request->amount;
        $wallet->save();

        $transaction = new Transaction();
        $transaction->user_id = $user->_id;
        $transaction->type = 'deposit';
        $transaction->amount = $request->amount;
        $transaction->payment_method = $request->payment_method;
        $transaction->description = $request->description;
        $transaction->created_at = Carbon::now();
        $transaction->save();

        return ResponseHelper::sendResponse([
            'balance'     => $wallet->balance,
            'userDetails' => $this->getUserDetails($user),
        ], 'Deposit successful.');
    }

    private function getUserDetails($user)
    {
        $user = $user->fresh();

        // Wallet info
        $wallet = Wallet::where('user_id', $user->_id)->first();
        $walletStatusMessages = [
            'not_found'    => 'No wallet found. Please create one.',
            'pending'      => 'Your request is pending review.',
            'under_review' => 'We will review your request. We will get back soon.',
            'active'       => 'All wallet features are now available.',
            'activated'    => 'All wallet features are now available.',
            'approved'     => 'All wallet features are now available.',
            'on_hold'      => 'Wallet is on Hold. See the reason here.',
            'rejected'     => 'Your wallet request was rejected.',
            'closed'       => 'Wallet is Closed. The account will be removed after 90 Days.',
        ];

        if ($wallet) {
            $wStatus = $wallet->status ?? 'under_review';
            $walletData = [
                'has_wallet'            => true,
                // Treat both `active` and `activated` as a valid live wallet.
                'has_valid'             => in_array($wStatus, ['activated', 'active', 'approved'], true),
                'has_pin'               => !empty($wallet->pin),
                'welcome_bonus_claimed' => !empty($wallet->welcome_bonus_claimed),
                'wallet_id'             => $this->maskWalletId($wallet->_id),
                'wallet_status'         => $wStatus,
                // Stored admin message wins; falls back to the static map.
                'status_message'        => $wallet->status_message ?? ($walletStatusMessages[$wStatus] ?? 'Unknown status.'),
                'hold_reason'           => $wallet->status_reason ?? null,
                'balance'               => round($wallet->balance ?? 0, 2),
                'expire_at'             => $wallet->expire_at ?? null,
                'created_at'            => $wallet->created_at ?? null,
            ];
        } else {
            $walletData = [
                'has_wallet'            => false,
                'has_valid'             => false,
                'has_pin'               => false,
                'welcome_bonus_claimed' => false,
                'wallet_id'             => null,
                'wallet_status'         => 'not_found',
                'status_message'        => $walletStatusMessages['not_found'],
                'hold_reason'           => null,
                'balance'               => 0,
                'expire_at'             => null,
                'created_at'            => null,
            ];
        }

        // KYC info
        $kyc = KycVerification::where('user_id', $user->_id)->orderBy('created_at', 'desc')->first();
        $kycStatusMessages = [
            'not_submitted' => 'KYC not submitted yet.',
            'pending'       => 'Your documents are submitted and waiting for review.',
            'under_review'  => 'Our team is currently reviewing your documents.',
            'approved'      => 'All wallet features are now available.',
            'rejected'      => 'Your KYC was rejected. Please resubmit.',
        ];

        if ($kyc) {
            $kycData = [
                'has_kyc'          => true,
                'kyc_id'           => $kyc->_id,
                'kyc_status'       => $kyc->status,
                'status_message'   => $kycStatusMessages[$kyc->status] ?? 'Unknown status.',
                'document_type'    => $kyc->document_type,
                'rejection_reason' => $kyc->rejection_reason ?? null,
                'submitted_at'     => $kyc->submitted_at ? Carbon::parse($kyc->submitted_at)->format('d M Y H:i') : null,
                'reviewed_at'      => $kyc->reviewed_at ? Carbon::parse($kyc->reviewed_at)->format('d M Y H:i') : null,
            ];
        } else {
            $kycData = [
                'has_kyc'          => false,
                'kyc_id'           => null,
                'kyc_status'       => 'not_submitted',
                'status_message'   => $kycStatusMessages['not_submitted'],
                'document_type'    => null,
                'rejection_reason' => null,
                'submitted_at'     => null,
                'reviewed_at'      => null,
            ];
        }

        // Embed wallet & kyc inside user object
        $userData = $user->toArray();
        $userData['wallet'] = $walletData;
        $userData['kyc'] = $kycData;

        return $userData;
    }

    private function maskWalletId($walletId)
    {
        if (strlen($walletId) < 10) return $walletId;
        $parts = explode(' ', $walletId);
        if (count($parts) >= 4) {
            return $parts[0] . ' **** **** ' . $parts[3];
        }
        return $walletId;
    }

    public function dashboard(Request $request)
    {
        $user = User::find(Auth::id());
        if (!$user) {
            return ResponseHelper::sendResponse(null, 'User not found.', false, 404);
        }
        if (empty($user->wallet_id) || ($user->wallet_status ?? '') !== 'activated') {
            return ResponseHelper::sendResponse(['has_wallet' => !empty($user->wallet_id), 'wallet_status' => $user->wallet_status ?? null,], 'Wallet not active.', false, 403);
        }
        $walletType = $request->query('type', 'private');
        // private or business // Fetch settings for exchange rates
        $setting = ZercashSetting::where('key', 'general')->where('is_active', true)->first();
        $cashbackPercent = $setting->transaction_fee_percent ?? 5;
        $currency = $setting->default_currency ?? 'EUR'; // Calculate totals from transactions
        $userId = Auth::id();
        $deposits = Transaction::where('user_id', $userId)->where('transaction_type', 'deposit')->where('status', 'COMPLETED')->sum('amount');
        $cashbacks = Transaction::where('user_id', $userId)->where('category', 'cashback')->where('status', 'COMPLETED')->sum('amount');
        $expenses = Transaction::where('user_id', $userId)->whereIn('transaction_type', ['purchase', 'payment', 'expense'])->where('status', 'COMPLETED')->sum('amount'); // Weekly chart data (last 7 days)
        $weeklyData = [];
        $dayLabels = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $dayTotal = Transaction::where('user_id', $userId)->where('status', 'COMPLETED')->where('date', $date->format('Y-m-d'))->sum('amount');
            $weeklyData[] = ['day' => $dayLabels[$date->dayOfWeek], 'date' => $date->format('Y-m-d'), 'amount' => round($dayTotal, 2), 'is_today' => $i === 0,];
        }
        return ResponseHelper::sendResponse(['wallet_id' => $this->maskWalletId($user->wallet_id), 'wallet_type' => $walletType, 'expire_at' => $user->wallet_expire_at ?? null, 'balance' => round($user->wallet_balance ?? 0, 2), 'zer_balance' => round($user->zer_balance ?? 0, 2), 'cashback_percent' => $cashbackPercent, 'currency' => $currency, 'summary' => ['deposits' => round($deposits, 2), 'cashbacks' => round($cashbacks, 2), 'expenses' => round($expenses, 2),], 'weekly_chart' => $weeklyData,], 'Wallet dashboard fetched.');
    }

    // ─── DEPOSITS ────────────────────────────────────────────────── /** * GET /api/wallet/deposits * List user's deposit transactions. * * Query: ?page=1&per_page=10 */
    public function deposits(Request $request)
    {
        $perPage = $request->query('per_page', 10);
        $deposits = Transaction::where('user_id', Auth::id())->where('transaction_type', 'deposit')->orderBy('created_at', 'desc')->paginate($perPage);
        $items = $deposits->map(function ($tx) {
            return ['id' => $tx->_id, 'tId' => $tx->tId ?? '', 'description' => $tx->description ?? 'Deposit', 'category' => $tx->category ?? 'deposit', 'amount' => round($tx->amount ?? 0, 2), 'currency' => $tx->currency ?? 'ZER', 'status' => $tx->status ?? 'COMPLETED', 'type' => 'INCOME', 'date' => $tx->date ?? ($tx->created_at ? Carbon::parse($tx->created_at)->format('d M Y') : ''),];
        });
        return ResponseHelper::sendResponse(['items' => $items, 'current_page' => $deposits->currentPage(), 'last_page' => $deposits->lastPage(), 'total' => $deposits->total(),], 'Deposits fetched.');
    }

    // ─── CASHBACKS ───────────────────────────────────────────────── /** * GET /api/wallet/cashbacks * List user's cashback transactions. * * Query: ?page=1&per_page=10 */
    public function cashbacks(Request $request)
    {
        $perPage = $request->query('per_page', 10);
        $cashbacks = Transaction::where('user_id', Auth::id())->where('category', 'cashback')->orderBy('created_at', 'desc')->paginate($perPage);
        $items = $cashbacks->map(function ($tx) {
            return [
                'id' => $tx->_id,
                'tId' => $tx->tId ?? '',
                'description' => $tx->description ?? 'Cashback',
                'shop_name' => $tx->shop_name ?? $tx->description ?? '',
                'amount' => round($tx->amount ?? 0, 2),
                'currency' => $tx->currency ?? 'ZER',
                'status' => $tx->status ?? 'PENDING', // PENDING, COMPLETED, FAILED
                'date' => $tx->date ?? ($tx->created_at ? Carbon::parse($tx->created_at)->format('d M Y') : ''),
            ];
        });
        return ResponseHelper::sendResponse(['items' => $items, 'current_page' => $cashbacks->currentPage(), 'last_page' => $cashbacks->lastPage(), 'total' => $cashbacks->total(),], 'Cashbacks fetched.');
    }

    // ─── PAYOUTS / EXPENSES ──────────────────────────────────────── /** * GET /api/wallet/payouts * List user's payout/expense transactions. * * Query: ?page=1&per_page=10 */
    public function payouts(Request $request)
    {
        $perPage = $request->query('per_page', 10);
        $payouts = Transaction::where('user_id', Auth::id())->whereIn('transaction_type', ['purchase', 'payment', 'payout', 'expense'])->orderBy('created_at', 'desc')->paginate($perPage);
        $items = $payouts->map(function ($tx) {
            return [
                'id' => $tx->_id,
                'tId' => $tx->tId ?? '',
                'description' => $tx->description ?? 'Payment',
                'shop_name' => $tx->shop_name ?? '',
                'amount' => round($tx->amount ?? 0, 2),
                'currency' => $tx->currency ?? 'ZER',
                'status' => $tx->status ?? 'COMPLETED', // IN_CART, COMPLETED, PENDING
                'date' => $tx->date ?? ($tx->created_at ? Carbon::parse($tx->created_at)->format('d M Y') : ''),
            ];
        });
        return ResponseHelper::sendResponse(['items' => $items, 'current_page' => $payouts->currentPage(), 'last_page' => $payouts->lastPage(), 'total' => $payouts->total(),], 'Payouts fetched.');
    }

    // ─── ALL TRANSACTIONS (COMBINED) ─────────────────────────────── /** * GET /api/wallet/transactions * All transactions with filters. * * Query: ?type=deposit|cashback|purchase|all &status=COMPLETED|PENDING|FAILED &page=1&per_page=20 */
    public function transactions(Request $request)
    {
        $perPage = $request->query('per_page', 20);
        $type = $request->query('type', 'all');
        $status = $request->query('status');
        $query = Transaction::where('user_id', Auth::id())->orderBy('created_at', 'desc');
        if ($type !== 'all') {
            if ($type === 'cashback') {
                $query->where('category', 'cashback');
            } else {
                $query->where('transaction_type', $type);
            }
        }
        if ($status) {
            $query->where('status', $status);
        }
        $transactions = $query->paginate($perPage);
        $items = $transactions->map(function ($tx) {
            $txType = $tx->transaction_type ?? 'other';
            $isIncome = in_array($txType, ['deposit', 'refund']) || ($tx->category ?? '') === 'welcome_bonus';
            return ['id' => $tx->_id, 'tId' => $tx->tId ?? '', 'description' => $tx->description ?? ucfirst($txType), 'transaction_type' => $txType, 'category' => $tx->category ?? $txType, 'amount' => round($tx->amount ?? 0, 2), 'currency' => $tx->currency ?? 'ZER', 'status' => $tx->status ?? 'PENDING', 'type' => $isIncome ? 'INCOME' : 'EXPENSE', 'shop_name' => $tx->shop_name ?? null, 'date' => $tx->date ?? ($tx->created_at ? Carbon::parse($tx->created_at)->format('d M Y') : ''),];
        });
        return ResponseHelper::sendResponse(['items' => $items, 'current_page' => $transactions->currentPage(), 'last_page' => $transactions->lastPage(), 'total' => $transactions->total(),], 'Transactions fetched.');
    }

    public function quickAccess()
    {
        $user = User::find(Auth::id());
        if (!$user) {
            return ResponseHelper::sendResponse(null, 'User not found.', false, 404);
        }
        // Wallet info
        $walletInfo = ['has_wallet' => !empty($user->wallet_id), 'wallet_id' => $user->wallet_id ? $this->maskWalletId($user->wallet_id) : null, 'wallet_status' => $user->wallet_status ?? null, 'balance' => round($user->wallet_balance ?? 0, 2), 'zer_balance' => round($user->zer_balance ?? 0, 2),];
        // Open Terminal, Transactions count, Zer Status
        $transactionsCount = Transaction::where('user_id', $user->_id)->count();
        $depositChange = 0; // Percentage change - calculate if needed
        $expenseChange = 0;
        $terminalStats = ['open_terminal' => 0, 'transactions' => $transactionsCount, 'deposit_change' => $depositChange . '%', 'expense_change' => $expenseChange . '%', 'zer_status' => round($user->zer_balance ?? 0, 2),]; // Channel info (if user has a channel)
        $channelInfo = ['has_channel' => !empty($user->channel_name), 'channel_name' => $user->channel_name ?? null, 'channel_id' => $user->channel_id ?? null, 'member_since' => $user->created_at ? Carbon::parse($user->created_at)->format('d-m-Y') : null, 'channel_status' => $user->channel_status ?? 'activated', 'status_message' => $user->channel_status_message ?? 'We wish good luck here', 'followers' => $user->followers_count ?? 0, 'members' => $user->members_count ?? 0, 'feeds' => $user->feeds_count ?? 0, 'follower_change' => '+25%', 'member_change' => '+25%', 'feed_change' => '+25%',]; // Shop info (if user has a shop)
        $shopInfo = ['has_shop' => !empty($user->shop_name), 'shop_name' => $user->shop_name ?? null, 'shop_id' => $user->shop_id ?? null, 'member_since' => $user->shop_created_at ?? ($user->created_at ? Carbon::parse($user->created_at)->format('d-m-Y') : null), 'shop_status' => $user->shop_status ?? 'activated', 'status_message' => $user->shop_status_message ?? 'We wish good luck here', 'followers' => $user->shop_followers_count ?? 0, 'reviews' => $user->shop_reviews_count ?? 0, 'offers' => $user->shop_offers_count ?? 0, 'follower_change' => '+25%', 'review_change' => '+25%', 'offer_change' => '+25%',];
        return ResponseHelper::sendResponse(['wallet' => $walletInfo, 'terminal' => $terminalStats, 'channel' => $channelInfo, 'shop' => $shopInfo,], 'Quick access data fetched.');
    }

    // ─── WALLET CHART DATA ───────────────────────────────────────── /** * GET /api/wallet/chart * Chart data for wallet balance over time. * * Query: ?period=week|month|year (default: week) */
    public function chartData(Request $request)
    {
        $period = $request->query('period', 'week');
        $userId = Auth::id();
        $data = [];
        switch ($period) {
            case 'month': // Last 30 days, grouped by day
                for ($i = 29; $i >= 0; $i--) {
                    $date = Carbon::now()->subDays($i);
                    $income = Transaction::where('user_id', $userId)->where('transaction_type', 'deposit')->where('status', 'COMPLETED')->where('date', $date->format('Y-m-d'))->sum('amount');
                    $expense = Transaction::where('user_id', $userId)->whereIn('transaction_type', ['purchase', 'payment', 'expense'])->where('status', 'COMPLETED')->where('date', $date->format('Y-m-d'))->sum('amount');
                    $data[] = ['label' => $date->format('d'), 'date' => $date->format('Y-m-d'), 'income' => round($income, 2), 'expense' => round($expense, 2), 'net' => round($income - $expense, 2),];
                }
                break;
            case 'year': // Last 12 months
                for ($i = 11; $i >= 0; $i--) {
                    $month = Carbon::now()->subMonths($i);
                    $start = $month->copy()->startOfMonth()->format('Y-m-d');
                    $end = $month->copy()->endOfMonth()->format('Y-m-d');
                    $income = Transaction::where('user_id', $userId)->where('transaction_type', 'deposit')->where('status', 'COMPLETED')->whereBetween('date', [$start, $end])->sum('amount');
                    $expense = Transaction::where('user_id', $userId)->whereIn('transaction_type', ['purchase', 'payment', 'expense'])->where('status', 'COMPLETED')->whereBetween('date', [$start, $end])->sum('amount');
                    $data[] = ['label' => $month->format('M'), 'date' => $month->format('Y-m'), 'income' => round($income, 2), 'expense' => round($expense, 2), 'net' => round($income - $expense, 2),];
                }
                break;
            default: // week
                $dayLabels = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];
                for ($i = 6; $i >= 0; $i--) {
                    $date = Carbon::now()->subDays($i);
                    $income = Transaction::where('user_id', $userId)->where('transaction_type', 'deposit')->where('status', 'COMPLETED')->where('date', $date->format('Y-m-d'))->sum('amount');
                    $expense = Transaction::where('user_id', $userId)->whereIn('transaction_type', ['purchase', 'payment', 'expense'])->where('status', 'COMPLETED')->where('date', $date->format('Y-m-d'))->sum('amount');
                    $data[] = ['label' => $dayLabels[$date->dayOfWeek], 'date' => $date->format('Y-m-d'), 'income' => round($income, 2), 'expense' => round($expense, 2), 'net' => round($income - $expense, 2), 'is_today' => $i === 0,];
                }
                break;
        }
        return ResponseHelper::sendResponse(['period' => $period, 'data' => $data,], 'Chart data fetched.');
    }
}
