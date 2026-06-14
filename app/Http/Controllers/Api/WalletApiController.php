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
            // Wallet already exists (created during KYC approval) — just set/update the PIN.
            // Don't mirror wallet fields back onto the user document; the wallets collection
            // is the source of truth and getUserDetails() reads from it directly.
            $wallet->pin = bcrypt($request->pin);
            $wallet->save();

            return ResponseHelper::sendResponse([
                'wallet'      => $wallet,
                'userDetails' => $this->getUserDetails($user),
            ], 'Wallet PIN set successfully.');
        }

        $wallet = new Wallet();
        $wallet->user_id    = (string) $user->getKey();
        $wallet->pin        = bcrypt($request->pin);
        $wallet->status     = 'under_review';
        $wallet->created_at = Carbon::now();
        $wallet->save();

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

        // Wallet is source of truth — don't mirror status to user doc.
        $user = User::find($request->user_id);

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

        // Welcome bonus on first verify-pin.
        //
        // IMPORTANT: We can't rely on the wallet's `welcome_bonus_claimed` flag alone — if the
        // wallet ever gets re-created (admin re-approve, manual fix, user_id type mismatch
        // between ObjectId and string, etc.) the flag resets to false and the user would claim
        // the bonus a SECOND time. Transaction history is the durable source of truth, so we
        // check both: flag OR an existing welcome_bonus deposit row for this user.
        $bonusGiven = false;
        $hasBonusTxn = Transaction::where('category', 'welcome_bonus')
            ->where(function ($q) use ($user) {
                // Match both ObjectId and string representations of the user id since legacy
                // rows may have either form depending on how they were inserted.
                $q->where('user_id', (string) $user->_id)
                  ->orWhere('user_id', $user->_id);
            })
            ->exists();

        if (empty($wallet->welcome_bonus_claimed) && !$hasBonusTxn) {
            $bonusAmount = 300;
            $wallet->balance = ($wallet->balance ?? 0) + $bonusAmount;
            $wallet->welcome_bonus_claimed = true;
            $wallet->welcome_bonus_amount = $bonusAmount;
            $wallet->welcome_bonus_at = Carbon::now();
            $wallet->save();

            // Create bonus transaction
            $transaction = new Transaction();
            $transaction->user_id = (string) $user->_id;
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
        } elseif (empty($wallet->welcome_bonus_claimed) && $hasBonusTxn) {
            // Recovery path: bonus transaction exists in history but the wallet's flag is unset
            // (e.g. wallet got re-created via admin approve). Sync the flag back so we never
            // re-enter this branch again. We DON'T add to balance — that was credited the first
            // time the bonus was claimed.
            $wallet->welcome_bonus_claimed = true;
            $wallet->save();
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

        // Wallet collection is source of truth — no mirror to user doc.
        $user = User::find($request->user_id);

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

    /**
     * GET /api/wallet/cashback-balance
     *
     * Total claimable cashback = the sum of the user's PENDING `category=cashback` rows.
     * This is exactly the number the mobile "My Cashback" / transfer screen shows before the
     * user taps "Transfer to my wallet".
     */
    public function cashbackBalance(Request $request)
    {
        $user = User::find(Auth::id());
        if (!$user) {
            return ResponseHelper::sendResponse(null, 'User not found.', false, 404);
        }

        $base = Transaction::where('user_id', Auth::id())
            ->where('category', 'cashback')
            ->where('status', 'PENDING');

        return ResponseHelper::sendResponse([
            'cashback_balance' => round((float) (clone $base)->sum('amount'), 2),
            'count'            => (clone $base)->count(),
            'currency'         => 'ZER',
        ], 'Cashback balance fetched.');
    }

    /**
     * POST /api/wallet/cashback/transfer
     *
     * Sweep ALL pending cashback into the wallet balance:
     *   1. sum every PENDING `category=cashback` row,
     *   2. add it to wallet.balance,
     *   3. flip those rows to COMPLETED (claimed) — they leave the claimable balance and now
     *      count as realised cashback. mapDepositRow renders them as "Cashback Transfer".
     *
     * Idempotent in practice: once rows are COMPLETED a second call finds nothing to transfer.
     */
    public function transferCashback(Request $request)
    {
        $user = User::find(Auth::id());
        if (!$user) {
            return ResponseHelper::sendResponse(null, 'User not found.', false, 404);
        }

        $wallet = Wallet::where('user_id', (string) $user->_id)->first();
        if (!$wallet || !in_array($wallet->status ?? '', ['activated', 'active', 'approved'], true)) {
            return ResponseHelper::sendResponse(
                ['has_wallet' => (bool) $wallet, 'wallet_status' => $wallet->status ?? null],
                'Your wallet is not active yet.',
                false,
                403
            );
        }

        $pending = Transaction::where('user_id', Auth::id())
            ->where('category', 'cashback')
            ->where('status', 'PENDING')
            ->get();

        $total = round((float) $pending->sum('amount'), 2);
        if ($total <= 0) {
            return ResponseHelper::sendResponse(
                ['transferred' => 0, 'balance' => round((float) ($wallet->balance ?? 0), 2)],
                'No cashback available to transfer.',
                false,
                400
            );
        }

        $now = Carbon::now();

        // Credit the wallet.
        $wallet->balance = round((float) ($wallet->balance ?? 0) + $total, 2);
        $wallet->save();

        // Mark each pending cashback row as claimed.
        foreach ($pending as $tx) {
            $tx->status     = 'COMPLETED';
            $tx->claimed_at = $now;
            $tx->save();
        }

        return ResponseHelper::sendResponse([
            'transferred'      => $total,
            'count'            => $pending->count(),
            'balance'          => round((float) $wallet->balance, 2),
            'cashback_balance' => 0,
            'currency'         => 'ZER',
        ], 'Cashback transferred to your wallet.');
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

        // Source of truth: read everything from the wallets collection. The user document
        // intentionally no longer carries wallet_id / wallet_status / wallet_balance — those
        // got stripped on admin approve so we keep a single source of truth.
        $wallet = Wallet::where('user_id', (string) $user->_id)->first();
        $status = $wallet->status ?? null;
        $activeStatuses = ['activated', 'active', 'approved'];

        if (!$wallet || !in_array($status, $activeStatuses, true)) {
            return ResponseHelper::sendResponse([
                'has_wallet'    => (bool) $wallet,
                'wallet_status' => $status,
            ], 'Wallet not active.', false, 403);
        }

        $walletType = $request->query('type', 'private'); // private or business

        // Fetch settings for exchange rates
        $setting = ZercashSetting::where('key', 'general')->where('is_active', true)->first();
        $cashbackPercent = $setting->transaction_fee_percent ?? 5;
        $currency = $setting->default_currency ?? 'EUR';

        // Calculate totals from transactions
        $userId = Auth::id();
        $deposits = Transaction::where('user_id', $userId)->where('transaction_type', 'deposit')->where('status', 'COMPLETED')->sum('amount');
        $cashbacks = Transaction::where('user_id', $userId)->where('category', 'cashback')->where('status', 'COMPLETED')->sum('amount');
        $expenses = Transaction::where('user_id', $userId)->whereIn('transaction_type', ['purchase', 'payment', 'expense'])->where('status', 'COMPLETED')->sum('amount');

        // Overall payments chart — sum of ALL completed transactions per period. Used by the
        // single bar-chart card on the dashboard. Per-section charts (deposits / cashbacks /
        // transactions) are returned on /wallet/payments.
        $chart = $this->buildChartFor($this->defaultChartQuery($userId));

        // Recent activity lists — dashboard renders all four cards inline on the main wallet
        // screen, so we send everything in one response. The /wallet/payments endpoint
        // returns the same lists (minus my_cashbacks) for the dedicated Payments page.
        $depositsList = Transaction::where('user_id', $userId)
            ->where('transaction_type', 'deposit')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn($tx) => $this->mapDepositRow($tx, $currency))
            ->values();

        $latestCashbacks = Transaction::where('user_id', $userId)
            ->whereIn('transaction_type', ['purchase', 'payment', 'expense', 'payout'])
            ->where('cashback_amount', '>', 0)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn($tx) => $this->mapCashbackEarnedRow($tx, $currency))
            ->values();

        // "My cashback" = unclaimed cashback balance card on the main wallet screen
        // (user can sweep it to wallet). Status PENDING only.
        $myCashbacks = Transaction::where('user_id', $userId)
            ->where('category', 'cashback')
            ->where('status', 'PENDING')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn($tx) => $this->mapCashbackRow($tx, $currency))
            ->values();

        $latestTransactions = Transaction::where('user_id', $userId)
            ->whereIn('transaction_type', ['purchase', 'payment', 'expense', 'payout'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn($tx) => $this->mapTransactionRow($tx, $currency))
            ->values();

        // Cashback balance = total claimable amount (sum of all PENDING cashback rows).
        // This is what the user can sweep into their main wallet balance.
        $cashbackBalance = Transaction::where('user_id', $userId)
            ->where('category', 'cashback')
            ->where('status', 'PENDING')
            ->sum('amount');

        return ResponseHelper::sendResponse([
            'wallet_id'           => $this->maskWalletId((string) $wallet->_id),
            'wallet_type'         => $walletType,
            'currency'            => $currency,
            'balance'             => round($wallet->balance ?? 0, 2),
            'zer_balance'         => round($wallet->zer_balance ?? $wallet->balance ?? 0, 2),
            'cashback_balance'    => round($cashbackBalance, 2),
            'cashback_percent'    => $cashbackPercent,
            'expire_at'           => $wallet->expire_at ?? null,
            'summary'             => [
                'deposits'  => round($deposits, 2),
                'cashbacks' => round($cashbacks, 2),
                'expenses'  => round($expenses, 2),
            ],
            'chart'               => $chart,
            'deposits'            => $depositsList,
            'latest_cashbacks'    => $latestCashbacks,
            'my_cashbacks'        => $myCashbacks,
            'latest_transactions' => $latestTransactions,
        ], 'Wallet dashboard fetched.');
    }

    /**
     * GET /api/wallet/payments
     *
     * The "Payments" screen on mobile. Each section is `{ chart, items }` so the page can
     * render a chart card + list per category without juggling sibling fields:
     *
     *   - `chart`               : overall bar chart (week/month/year) — top of the page
     *   - `deposits.chart`      : chart for deposit transactions only
     *   - `deposits.items`      : recent deposit txns (welcome bonus / cashback transfer
     *                             / zercash charging)
     *   - `latest_cashbacks.chart` : chart of cashback_amount earned per period
     *   - `latest_cashbacks.items` : purchase txns where cashback was earned (carries
     *                             cashback_percent / cashback_amount per row)
     *   - `latest_transactions.chart` : chart of purchase / payment / expense amounts
     *   - `latest_transactions.items` : recent purchase txns
     */
    public function payments(Request $request)
    {
        $user = User::find(Auth::id());
        if (!$user) {
            return ResponseHelper::sendResponse(null, 'User not found.', false, 404);
        }

        $userId = Auth::id();
        $setting = ZercashSetting::where('key', 'general')->where('is_active', true)->first();
        $currency = $setting->default_currency ?? 'EUR';

        // Base-query factories — each section's chart sums only that section's transactions.
        // Closures so buildChartFor() can re-instantiate the query inside its date loop.
        $depositsQuery     = fn () => Transaction::where('user_id', $userId)
            ->where('status', 'COMPLETED')
            ->where('transaction_type', 'deposit');

        $cashbacksQuery    = fn () => Transaction::where('user_id', $userId)
            ->where('status', 'COMPLETED')
            ->whereIn('transaction_type', ['purchase', 'payment', 'expense', 'payout'])
            ->where('cashback_amount', '>', 0);

        $transactionsQuery = fn () => Transaction::where('user_id', $userId)
            ->where('status', 'COMPLETED')
            ->whereIn('transaction_type', ['purchase', 'payment', 'expense', 'payout']);

        // Overall chart kept (same as before) — bar chart at the top of the Payments page.
        $chart = $this->buildChartFor($this->defaultChartQuery($userId));

        // Per-section charts — cashbacks sum the cashback_amount field, not the purchase amount.
        $depositsChart     = $this->buildChartFor($depositsQuery);
        $cashbacksChart    = $this->buildChartFor($cashbacksQuery, 'cashback_amount');
        $transactionsChart = $this->buildChartFor($transactionsQuery);

        // Recent activity lists.
        $depositsList = Transaction::where('user_id', $userId)
            ->where('transaction_type', 'deposit')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn($tx) => $this->mapDepositRow($tx, $currency))
            ->values();

        $latestCashbacks = Transaction::where('user_id', $userId)
            ->whereIn('transaction_type', ['purchase', 'payment', 'expense', 'payout'])
            ->where('cashback_amount', '>', 0)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn($tx) => $this->mapCashbackEarnedRow($tx, $currency))
            ->values();

        $latestTransactions = Transaction::where('user_id', $userId)
            ->whereIn('transaction_type', ['purchase', 'payment', 'expense', 'payout'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn($tx) => $this->mapTransactionRow($tx, $currency))
            ->values();

        // Each section bundles its own chart + items so the mobile page can render
        // "{title} {chart} {list}" cards without juggling sibling keys.
        return ResponseHelper::sendResponse([
            'currency'            => $currency,
            'chart'               => $chart, // overall (kept for backward compatibility)
            'deposits'            => [
                'chart' => $depositsChart,
                'items' => $depositsList,
            ],
            'latest_cashbacks'    => [
                'chart' => $cashbacksChart,
                'items' => $latestCashbacks,
            ],
            'latest_transactions' => [
                'chart' => $transactionsChart,
                'items' => $latestTransactions,
            ],
        ], 'Wallet payments fetched.');
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Helpers — chart builders + row mappers used by dashboard()
    // ─────────────────────────────────────────────────────────────────────────────────

    /** Map a status string to the hex chip color the mobile UI uses on cards. */
    private function statusColor(string $status): string
    {
        return match (strtoupper($status)) {
            'COMPLETED', 'COMPLETE', 'SUCCESS', 'APPROVED' => '#22C55E', // emerald
            'PENDING', 'IN_CART', 'UNDER_REVIEW'          => '#F59E0B', // amber
            'FAILED', 'REJECTED', 'CANCELLED'             => '#EF4444', // rose
            default                                       => '#94A3B8', // slate
        };
    }

    /** Format a Transaction's date for UI display ("10 Nov 2025"). */
    private function formatTxDate($tx): string
    {
        if (!empty($tx->date)) {
            try { return Carbon::parse($tx->date)->format('d M Y'); } catch (\Throwable) {}
        }
        return $tx->created_at ? Carbon::parse($tx->created_at)->format('d M Y') : '';
    }

    /**
     * Deposit row shape — covers the three "My Deposit" cards the mobile UI renders:
     *   - YekBûn Welcome    (icon: welcome_bonus)
     *   - Cashback Transfer (icon: cashback)        → user sweeps my_cashbacks to wallet
     *   - Zêrcash Charging  (icon: recharge)        → user adds money to wallet
     *
     * Mobile maps `icon` (a stable string) to its own asset. Final agreed icon strings:
     *   welcome_bonus / cashback / recharge — these are the ONLY three the mobile app
     * understands. Any other category falls back to the stored category string.
     */
    private function mapDepositRow($tx, string $defaultCurrency): array
    {
        $category = strtolower((string) ($tx->category ?? 'deposit'));

        // Normalize category → {icon, title}. Aliases for "top up" rolled into
        // recharge so historic rows render the same way. Old cashback_transfer /
        // zercash_charging strings get mapped to the new icon names too.
        $iconMap = [
            'welcome_bonus'     => ['icon' => 'welcome_bonus', 'title' => 'YekBûn Welcome'],
            'cashback_transfer' => ['icon' => 'cashback',      'title' => 'Cashback Transfer'],
            'cashback'          => ['icon' => 'cashback',      'title' => 'Cashback Transfer'],
            'recharge'          => ['icon' => 'recharge',      'title' => 'Zêrcash Charging'],
            'zercash_charging'  => ['icon' => 'recharge',      'title' => 'Zêrcash Charging'],
            'top_up'            => ['icon' => 'recharge',      'title' => 'Zêrcash Charging'],
            'topup'             => ['icon' => 'recharge',      'title' => 'Zêrcash Charging'],
            'charge'            => ['icon' => 'recharge',      'title' => 'Zêrcash Charging'],
        ];

        $mapped = $iconMap[$category] ?? ['icon' => $category, 'title' => 'Deposit'];

        return [
            'id'           => (string) $tx->_id,
            // Stored description wins if present, otherwise the normalized title from the map.
            'title'        => $tx->description ?? $mapped['title'],
            'cb_id'        => $tx->tId ?? 'CB-ID',
            'date'         => $this->formatTxDate($tx),
            'amount'       => round($tx->amount ?? 0, 2),
            'type'         => 'income',
            'currency'     => $tx->currency ?? $defaultCurrency,
            'icon'         => $mapped['icon'],
            'status_color' => $this->statusColor($tx->status ?? 'COMPLETED'),
        ];
    }

    /** Cashback row shape — used by both `latest_cashbacks` and `my_cashbacks`. */
    private function mapCashbackRow($tx, string $defaultCurrency): array
    {
        return [
            'id'           => (string) $tx->_id,
            'title'        => $tx->description ?? 'YekBûn Cashback',
            'cb_id'        => $tx->tId ?? 'CB-ID',
            'date'         => $this->formatTxDate($tx),
            'amount'       => round($tx->amount ?? 0, 2),
            'currency'     => $tx->currency ?? $defaultCurrency,
            'status'       => strtolower($tx->status ?? 'pending'),
            'status_color' => $this->statusColor($tx->status ?? 'PENDING'),
            'icon'         => 'cashback',
        ];
    }

    /** Purchase / expense row shape for `latest_transactions`. */
    private function mapTransactionRow($tx, string $defaultCurrency): array
    {
        return [
            'id'           => (string) $tx->_id,
            'merchant'     => $tx->shop_name ?? $tx->description ?? 'Unknown',
            'cb_id'        => $tx->tId ?? 'CB-ID',
            'date'         => $this->formatTxDate($tx),
            'amount'       => round($tx->amount ?? 0, 2),
            'currency'     => $tx->currency ?? $defaultCurrency,
            'status'       => strtolower($tx->status ?? 'pending'),
            'status_color' => $this->statusColor($tx->status ?? 'PENDING'),
            'icon'         => $tx->icon ?? $tx->category ?? ($tx->transaction_type ?? 'transaction'),
            'category'     => $tx->category ?? 'shopping',
        ];
    }

    /**
     * Row shape for `latest_cashbacks` — same data as `mapTransactionRow` but trades
     * `status` / `status_color` for `cashback_percent` / `cashback_amount`. Mobile renders
     * a small chip like "5% 25.00" beside the purchase amount instead of a status pill.
     *
     * If the transaction has an explicit `cashback_percent` field we use that; otherwise we
     * derive it from `cashback_amount / amount * 100` so older rows still render correctly.
     */
    private function mapCashbackEarnedRow($tx, string $defaultCurrency): array
    {
        $purchaseAmount = (float) ($tx->amount ?? 0);
        $cashbackAmount = (float) ($tx->cashback_amount ?? 0);
        $cashbackPercent = isset($tx->cashback_percent)
            ? (float) $tx->cashback_percent
            : ($purchaseAmount > 0 ? round(($cashbackAmount / $purchaseAmount) * 100, 2) : 0);

        return [
            'id'               => (string) $tx->_id,
            'merchant'         => $tx->shop_name ?? $tx->description ?? 'Unknown',
            'cb_id'            => $tx->tId ?? 'CB-ID',
            'date'             => $this->formatTxDate($tx),
            'amount'           => round($purchaseAmount, 2),
            'currency'         => $tx->currency ?? $defaultCurrency,
            'cashback_percent' => $cashbackPercent,
            'cashback_amount'  => round($cashbackAmount, 2),
            'icon'             => $tx->icon ?? $tx->category ?? ($tx->transaction_type ?? 'transaction'),
            'category'         => $tx->category ?? 'shopping',
        ];
    }

    /**
     * Default base-query factory: all of the user's COMPLETED transactions. Used for the
     * top-level overall chart. Other sections pass their own factory closure to filter
     * (e.g. deposits only, or cashback-earning purchases only).
     */
    private function defaultChartQuery($userId): callable
    {
        return fn () => Transaction::where('user_id', $userId)->where('status', 'COMPLETED');
    }

    /** Convenience — chart series wrapper that builds week/month/year in one go. */
    private function buildChartFor(callable $baseQuery, string $sumField = 'amount'): array
    {
        return [
            'weekly'  => $this->buildWeeklyChart($baseQuery, $sumField),
            'monthly' => $this->buildMonthlyChart($baseQuery, $sumField),
            'yearly'  => $this->buildYearlyChart($baseQuery, $sumField),
        ];
    }

    /**
     * Weekly chart: last 7 days, one bucket per day with `{day, date, amount, is_today}`.
     *
     * @param  callable  $baseQuery  Closure returning a fresh Transaction query (no date filter).
     * @param  string    $sumField   Which numeric field to sum (default `amount`; cashback
     *                               charts pass `cashback_amount`).
     */
    private function buildWeeklyChart(callable $baseQuery, string $sumField = 'amount'): array
    {
        $dayLabels = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $dayTotal = $baseQuery()
                ->where('date', $date->format('Y-m-d'))
                ->sum($sumField);
            $data[] = [
                'day'      => $dayLabels[$date->dayOfWeek],
                'date'     => $date->format('Y-m-d'),
                'amount'   => round($dayTotal, 2),
                'is_today' => $i === 0,
            ];
        }
        return $data;
    }

    /**
     * Monthly chart: 12 months of the CURRENT calendar year, one bucket per month.
     * Mobile dev's expected shape is `{month: "Jan", amount: 120}`. Empty months come back
     * as 0 so the line chart stays continuous across the year.
     */
    private function buildMonthlyChart(callable $baseQuery, string $sumField = 'amount'): array
    {
        $data = [];
        $year = (int) Carbon::now()->format('Y');
        for ($m = 1; $m <= 12; $m++) {
            $start = Carbon::create($year, $m, 1)->startOfMonth();
            $end   = $start->copy()->endOfMonth();
            $amount = $baseQuery()
                ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                ->sum($sumField);
            $data[] = [
                'month'  => $start->format('M'),
                'amount' => round($amount, 2),
            ];
        }
        return $data;
    }

    /**
     * Yearly chart: last 5 years inclusive of current year. Shape `{year: "2024", amount: 4200}`.
     */
    private function buildYearlyChart(callable $baseQuery, string $sumField = 'amount'): array
    {
        $data = [];
        $currentYear = (int) Carbon::now()->format('Y');
        for ($i = 4; $i >= 0; $i--) {
            $year  = $currentYear - $i;
            $start = Carbon::create($year, 1, 1)->startOfYear();
            $end   = $start->copy()->endOfYear();
            $amount = $baseQuery()
                ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                ->sum($sumField);
            $data[] = [
                'year'   => (string) $year,
                'amount' => round($amount, 2),
            ];
        }
        return $data;
    }

    // ─── DEPOSITS ────────────────────────────────────────────────── /** * GET /api/wallet/deposits * List user's deposit transactions. * * Query: ?page=1&per_page=10 */
    public function deposits(Request $request)
    {
        $perPage = $request->query('per_page', 10);
        $deposits = Transaction::where('user_id', Auth::id())->where('transaction_type', 'deposit')->orderBy('created_at', 'desc')->paginate($perPage);
        $items = $deposits->map(function ($tx) {
            return ['id' => $tx->_id, 'tId' => $tx->tId ?? '', 'description' => $tx->description ?? 'Deposit', 'category' => $tx->category ?? 'deposit', 'amount' => round($tx->amount ?? 0, 2), 'currency' => $tx->currency ?? 'ZER', 'status' => $tx->status ?? 'COMPLETED', 'type' => $tx->status, 'date' => $tx->date ?? ($tx->created_at ? Carbon::parse($tx->created_at)->format('d M Y') : ''),];
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
        // Wallet info — pulled from wallets collection (source of truth, NOT user fields).
        $wallet = Wallet::where('user_id', (string) $user->_id)->first();
        $walletInfo = [
            'has_wallet'    => (bool) $wallet,
            'wallet_id'     => $wallet ? $this->maskWalletId((string) $wallet->_id) : null,
            'wallet_status' => $wallet->status ?? null,
            'balance'       => round($wallet->balance ?? 0, 2),
            'zer_balance'   => round($wallet->zer_balance ?? $wallet->balance ?? 0, 2),
        ];
        // Open Terminal, Transactions count, Zer Status
        $transactionsCount = Transaction::where('user_id', $user->_id)->count();
        $depositChange = 0; // Percentage change - calculate if needed
        $expenseChange = 0;
        $terminalStats = ['open_terminal' => 0, 'transactions' => $transactionsCount, 'deposit_change' => $depositChange . '%', 'expense_change' => $expenseChange . '%', 'zer_status' => round($wallet->zer_balance ?? $wallet->balance ?? 0, 2),]; // Channel info (if user has a channel)
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
