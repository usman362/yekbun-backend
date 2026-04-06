<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\KycVerification;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class WalletApiController extends Controller
{
    public function createWallet(Request $request)
    {
        $validator = Validator::make($request->all(), ['pin' => 'required|string|size:4|regex:/^[0-9]{4}$/']);
        if ($validator->fails()) return ResponseHelper::sendResponse($validator->errors(), 'Validation error.', false, 422);

        $user = Auth::user();
        if (!$user) return ResponseHelper::sendResponse(null, 'User not found.', false, 404);

        $wallet = Wallet::where('user_id', $user->_id)->first();
        if ($wallet) return ResponseHelper::sendResponse(null, 'Wallet already exists.', false, 400);

        $wallet = new Wallet();
        $wallet->user_id = $user->_id;
        $wallet->pin = bcrypt($request->pin);
        $wallet->status = 'under_review';
        $wallet->save();

        $user->wallet_id = $wallet->_id;
        $user->wallet_status = 'under_review';
        $user->save();

        return ResponseHelper::sendResponse(['wallet' => $wallet, 'userDetails' => $this->getUserDetails($user)], 'Wallet created successfully.');
    }

    public function activateWallet(Request $request)
    {
        $validator = Validator::make($request->all(), ['user_id' => 'required|string']);
        if ($validator->fails()) return ResponseHelper::sendResponse($validator->errors(), 'Validation error.', false, 422);

        $wallet = Wallet::where('user_id', $request->user_id)->first();
        if (!$wallet) return ResponseHelper::sendResponse(null, 'Wallet not found.', false, 404);

        $wallet->status = 'activated';
        $wallet->activated_at = Carbon::now();
        $wallet->save();

        $user = User::find($request->user_id);
        if ($user) { $user->wallet_status = 'activated'; $user->save(); }

        return ResponseHelper::sendResponse(['wallet' => $wallet, 'userDetails' => $user ? $this->getUserDetails($user) : null], 'Wallet activated.');
    }

    public function verifyPin(Request $request)
    {
        $validator = Validator::make($request->all(), ['pin' => 'required|string|size:4']);
        if ($validator->fails()) return ResponseHelper::sendResponse($validator->errors(), 'Validation error.', false, 422);

        $user = Auth::user();
        $wallet = Wallet::where('user_id', $user->_id)->first();
        if (!$wallet) return ResponseHelper::sendResponse(null, 'Wallet not found.', false, 404);
        if (!password_verify($request->pin, $wallet->pin)) return ResponseHelper::sendResponse(null, 'Invalid PIN.', false, 401);

        $bonusGiven = false;
        if (empty($wallet->welcome_bonus_claimed)) {
            $wallet->balance = ($wallet->balance ?? 0) + 300;
            $wallet->welcome_bonus_claimed = true;
            $wallet->welcome_bonus_amount = 300;
            $wallet->save();
            $bonusGiven = true;
        }

        return ResponseHelper::sendResponse(['verified' => true, 'bonus_given' => $bonusGiven, 'bonus_amount' => $bonusGiven ? 300 : 0, 'userDetails' => $this->getUserDetails($user)], $bonusGiven ? 'PIN verified. Welcome bonus added!' : 'PIN verified.');
    }

    public function changePin(Request $request)
    {
        $validator = Validator::make($request->all(), ['current_pin' => 'required|string|size:4', 'new_pin' => 'required|string|size:4|regex:/^[0-9]{4}$/']);
        if ($validator->fails()) return ResponseHelper::sendResponse($validator->errors(), 'Validation error.', false, 422);

        $user = Auth::user();
        $wallet = Wallet::where('user_id', $user->_id)->first();
        if (!$wallet) return ResponseHelper::sendResponse(null, 'Wallet not found.', false, 404);
        if (!password_verify($request->current_pin, $wallet->pin)) return ResponseHelper::sendResponse(null, 'Current PIN incorrect.', false, 401);

        $wallet->pin = bcrypt($request->new_pin);
        $wallet->save();
        return ResponseHelper::sendResponse(null, 'PIN changed successfully.');
    }

    public function walletStatus()
    {
        $user = Auth::user();
        if (!$user) return ResponseHelper::sendResponse(null, 'User not found.', false, 404);

        $wallet = Wallet::where('user_id', $user->_id)->first();
        $statusMessages = [
            'not_found' => 'No wallet found. Please create one.',
            'under_review' => 'We will review your request. We will get back soon.',
            'activated' => 'Wallet is activated. Enjoy...',
            'on_hold' => 'Wallet is on Hold. See the reason here.',
            'closed' => 'Wallet is Closed. The account will be removed after 90 Days.',
        ];

        if (!$wallet) {
            return ResponseHelper::sendResponse(['has_wallet' => false, 'wallet_status' => 'not_found', 'status_message' => $statusMessages['not_found'], 'userDetails' => $this->getUserDetails($user)], 'No wallet found.');
        }

        $status = $wallet->status ?? 'under_review';
        return ResponseHelper::sendResponse(['has_wallet' => true, 'wallet_id' => $this->maskWalletId($wallet->_id), 'wallet_status' => $status, 'status_message' => $statusMessages[$status] ?? 'Unknown status.', 'hold_reason' => $wallet->status_reason ?? null, 'userDetails' => $this->getUserDetails($user)], 'Wallet status fetched.');
    }

    public function updateWalletStatus(Request $request)
    {
        $validator = Validator::make($request->all(), ['user_id' => 'required|string', 'status' => 'required|in:under_review,activated,on_hold,closed', 'reason' => 'nullable|string']);
        if ($validator->fails()) return ResponseHelper::sendResponse($validator->errors(), 'Validation error.', false, 422);

        $wallet = Wallet::where('user_id', $request->user_id)->first();
        if (!$wallet) return ResponseHelper::sendResponse(null, 'Wallet not found.', false, 404);

        $wallet->status = $request->status;
        $wallet->status_reason = $request->reason;
        $wallet->save();

        $user = User::find($request->user_id);
        if ($user) { $user->wallet_status = $request->status; $user->save(); }

        return ResponseHelper::sendResponse(['wallet' => $wallet, 'userDetails' => $user ? $this->getUserDetails($user) : null], 'Wallet status updated.');
    }

    public function deposit(Request $request)
    {
        $validator = Validator::make($request->all(), ['amount' => 'required|numeric|min:1', 'payment_method' => 'required|in:card,paypal,bank']);
        if ($validator->fails()) return ResponseHelper::sendResponse($validator->errors(), 'Validation error.', false, 422);

        $user = Auth::user();
        $wallet = Wallet::where('user_id', $user->_id)->first();
        if (!$wallet) return ResponseHelper::sendResponse(null, 'Wallet not found.', false, 404);

        $wallet->balance += $request->amount;
        $wallet->save();

        $transaction = new Transaction();
        $transaction->user_id = $user->_id;
        $transaction->transaction_type = 'deposit';
        $transaction->amount = $request->amount;
        $transaction->payment_method = $request->payment_method;
        $transaction->description = $request->description;
        $transaction->date = Carbon::now()->format('Y-m-d');
        $transaction->save();

        return ResponseHelper::sendResponse(['balance' => $wallet->balance, 'userDetails' => $this->getUserDetails($user)], 'Deposit successful.');
    }

    public function dashboard()
    {
        $user = User::find(Auth::id());
        if (!$user) return ResponseHelper::sendResponse(null, 'User not found.', false, 404);

        $userId = Auth::id();
        $deposits = Transaction::where('user_id', $userId)->where('transaction_type', 'deposit')->where('status', 'COMPLETED')->sum('amount');
        $cashbacks = Transaction::where('user_id', $userId)->where('category', 'cashback')->where('status', 'COMPLETED')->sum('amount');
        $expenses = Transaction::where('user_id', $userId)->whereIn('transaction_type', ['purchase', 'payment', 'expense'])->where('status', 'COMPLETED')->sum('amount');

        return ResponseHelper::sendResponse([
            'balance' => round($user->wallet_balance ?? 0, 2),
            'summary' => ['deposits' => round($deposits, 2), 'cashbacks' => round($cashbacks, 2), 'expenses' => round($expenses, 2)],
        ], 'Wallet dashboard fetched.');
    }

    public function deposits(Request $request)
    {
        $deposits = Transaction::where('user_id', Auth::id())->where('transaction_type', 'deposit')->orderBy('created_at', 'desc')->paginate($request->query('per_page', 10));
        return ResponseHelper::sendResponse($deposits, 'Deposits fetched.');
    }

    public function cashbacks(Request $request)
    {
        $cashbacks = Transaction::where('user_id', Auth::id())->where('category', 'cashback')->orderBy('created_at', 'desc')->paginate($request->query('per_page', 10));
        return ResponseHelper::sendResponse($cashbacks, 'Cashbacks fetched.');
    }

    public function payouts(Request $request)
    {
        $payouts = Transaction::where('user_id', Auth::id())->whereIn('transaction_type', ['purchase', 'payment', 'payout', 'expense'])->orderBy('created_at', 'desc')->paginate($request->query('per_page', 10));
        return ResponseHelper::sendResponse($payouts, 'Payouts fetched.');
    }

    public function transactions(Request $request)
    {
        $query = Transaction::where('user_id', Auth::id())->orderBy('created_at', 'desc');
        $type = $request->query('type', 'all');
        if ($type !== 'all') {
            $type === 'cashback' ? $query->where('category', 'cashback') : $query->where('transaction_type', $type);
        }
        if ($request->query('status')) $query->where('status', $request->query('status'));
        return ResponseHelper::sendResponse($query->paginate($request->query('per_page', 20)), 'Transactions fetched.');
    }

    public function quickAccess()
    {
        $user = User::find(Auth::id());
        if (!$user) return ResponseHelper::sendResponse(null, 'User not found.', false, 404);
        $transactionsCount = Transaction::where('user_id', $user->_id)->count();
        return ResponseHelper::sendResponse([
            'wallet' => ['has_wallet' => !empty($user->wallet_id), 'balance' => round($user->wallet_balance ?? 0, 2)],
            'terminal' => ['transactions' => $transactionsCount],
        ], 'Quick access data fetched.');
    }

    public function chartData(Request $request)
    {
        $period = $request->query('period', 'week');
        $userId = Auth::id();
        $data = [];
        $dayLabels = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $income = Transaction::where('user_id', $userId)->where('transaction_type', 'deposit')->where('status', 'COMPLETED')->where('date', $date->format('Y-m-d'))->sum('amount');
            $expense = Transaction::where('user_id', $userId)->whereIn('transaction_type', ['purchase', 'payment', 'expense'])->where('status', 'COMPLETED')->where('date', $date->format('Y-m-d'))->sum('amount');
            $data[] = ['label' => $dayLabels[$date->dayOfWeek], 'date' => $date->format('Y-m-d'), 'income' => round($income, 2), 'expense' => round($expense, 2), 'is_today' => $i === 0];
        }
        return ResponseHelper::sendResponse(['period' => $period, 'data' => $data], 'Chart data fetched.');
    }

    private function getUserDetails($user)
    {
        $user = $user->fresh();
        $wallet = Wallet::where('user_id', $user->_id)->first();
        $walletData = $wallet ? ['has_wallet' => true, 'wallet_status' => $wallet->status ?? 'under_review', 'balance' => round($wallet->balance ?? 0, 2)] : ['has_wallet' => false, 'wallet_status' => 'not_found', 'balance' => 0];
        $kyc = KycVerification::where('user_id', $user->_id)->orderBy('created_at', 'desc')->first();
        $kycData = $kyc ? ['has_kyc' => true, 'kyc_status' => $kyc->status] : ['has_kyc' => false, 'kyc_status' => 'not_submitted'];
        $userData = $user->toArray();
        $userData['wallet'] = $walletData;
        $userData['kyc'] = $kycData;
        return $userData;
    }

    private function maskWalletId($walletId)
    {
        if (strlen($walletId) < 10) return $walletId;
        return substr($walletId, 0, 4) . ' **** **** ' . substr($walletId, -4);
    }
}
