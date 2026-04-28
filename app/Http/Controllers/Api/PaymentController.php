<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserPlaylistGroup;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function charge(Request $request)
    {
        try {
            // PayPal charge placeholder - integrate with PayPal SDK
            return response()->json(['message' => 'PayPal charge endpoint. Configure PayPal SDK.'], 200);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function success(Request $request)
    {
        if ($request->has('payment_id')) {
            return view('content.paypal.success');
        }
        return response()->json(['success' => false, 'data' => 'Transaction is declined.']);
    }

    public function error()
    {
        return response()->json(['success' => false, 'data' => 'User Cancelled the payment.']);
    }

    public function payment_details($payment_id)
    {
        $payment = Payment::where('payment_id', $payment_id)->first();
        return response()->json(['success' => true, 'data' => $payment]);
    }

    public function paymentList()
    {
        // Mirror old project: return grouped payment method records
        // (apple_pay, bank_transfer, google_pay, paypal) — mobile parses these keys.
        $applePays = collect(DB::connection('mongodb')->collection('apple_pays')->get());
        $bankTransfers = collect(DB::connection('mongodb')->collection('bank_transfers')->get());
        $googlePays = collect(DB::connection('mongodb')->collection('google_pays')->get());
        $payPal = collect(DB::connection('mongodb')->collection('paypals')->get());

        $payments = [
            'apple_pay' => $applePays,
            'bank_transfer' => $bankTransfers,
            'google_pay' => $googlePays,
            'paypal' => $payPal,
        ];

        return ResponseHelper::sendResponse($payments, 'Payments List Fetch');
    }

    public function storeTransaction(Request $request)
    {
        try {
            $transaction = new Transaction();
            $transaction->tId = $request->tId;
            $transaction->amount = $request->amount;
            $transaction->status = $request->status;
            $transaction->subscription_type = $request->subscription_type;
            $transaction->transaction_type = $request->transaction_type;
            $transaction->userType = $request->userType;
            $transaction->user_id = $request->user_id;
            $transaction->date = Carbon::now()->format('Y-m-d');
            $transaction->save();

            $user = User::find($request->user_id);
            if ($request->status === 'COMPLETED' && $user) {
                $current = Carbon::now();
                $newExpiry = $request->subscription_type === 'monthly'
                    ? Carbon::parse($current->copy()->addMonth())->format('Y-m-d')
                    : Carbon::parse($current->copy()->addYear())->format('Y-m-d');

                $level = ['Cultivated' => 0, 'Educated' => 1, 'Academic' => 2];
                $user->expired_at = $newExpiry;
                $user->level = $level[$request->userType] ?? 0;
                $user->user_type = Str::lower($request->userType);
                $user->subscription_type = $request->subscription_type;
                $user->congrats_popup = 1;
                $user->force_logout = 1;
                $user->save();
            }

            $invoice = new Invoice();
            $invoice->user_id = $user ? $user->getKey() : $request->user_id;
            $invoice->first_name = $user->name ?? '';
            $invoice->last_name = $user->last_name ?? '';
            $invoice->email = $user->email ?? '';
            $invoice->city = $user->city ?? '';
            $invoice->country = $user->country ?? '';
            $invoice->transaction_id = $request->tId;
            $invoice->status = $request->status;
            $invoice->date = $transaction->date;
            $invoice->transaction_type = $request->transaction_type;
            $invoice->invoice_id = $this->generateInvoiceId();
            $invoice->items = [[
                'amount' => $request->amount,
                'title' => 'Subscription Plan',
                'subscription_type' => $request->subscription_type,
                'userType' => $request->userType,
                'description' => $request->userType . ' - ' . ucfirst($request->subscription_type) . ' Subscription',
                'quantity' => 1,
            ]];
            $invoice->save();

            return ResponseHelper::sendResponse($transaction, 'Transaction stored successfully.');
        } catch (Exception $e) {
            return ResponseHelper::sendResponse([], 'Failed to stored Transaction.', false, 403);
        }
    }

    public function addtoCart()
    {
        $cart = Cart::where('user_id', Auth::id())->get()->groupBy('type');
        return ResponseHelper::sendResponse($cart, 'Cart fetched successfully!');
    }

    public function deleteCart($id)
    {
        $cart = Cart::find($id);
        if ($cart) $cart->delete();
        return ResponseHelper::sendResponse([], 'Cart deleted successfully!');
    }

    public function storeaddtoCart(Request $request)
    {
        $request->validate(['data_id' => 'required']);
        $exist = Cart::where('user_id', Auth::id())->where('data_id', $request->data_id)->exists();
        if ($exist) return ResponseHelper::sendResponse([], 'Cart already added!', false, 403);

        $cart = new Cart();
        $cart->type = $request->type;
        $cart->title = $request->title;
        $cart->data_id = $request->data_id;
        $cart->price = $request->price;
        $cart->user_id = Auth::id();
        $cart->save();
        return ResponseHelper::sendResponse($cart, 'Cart has been added successfully!');
    }

    public function cartPayment()
    {
        $carts = Cart::where('user_id', Auth::id())->get();
        if ($carts->isNotEmpty()) {
            $lastCart = null;
            foreach ($carts as $cart) {
                $playlist = UserPlaylistGroup::find($cart->data_id);
                if ($playlist) {
                    $playlist->type = 'free';
                    $playlist->save();
                }
                $lastCart = $cart;
                $cart->delete();
            }
            return ResponseHelper::sendResponse($lastCart, 'Cart Payment has been paid successfully!');
        }
        return ResponseHelper::sendResponse([], 'Cart is Empty!', false, 404);
    }

    public function getTransactionList()
    {
        $transaction = Transaction::where('user_id', Auth::id())->get();
        return ResponseHelper::sendResponse($transaction, 'Transaction list has been fetched successfully!');
    }

    public function getInvoice($tId)
    {
        $invoice = Invoice::where('transaction_id', $tId)->first();
        return ResponseHelper::sendResponse($invoice, 'Invoice has been fetched successfully!');
    }

    private function generateInvoiceId()
    {
        $lastInvoice = Invoice::orderBy('created_at', 'desc')->first();
        if (!$lastInvoice || empty($lastInvoice->invoice_id)) return 'INV-1001';
        $lastNumber = (int) str_replace('INV-', '', $lastInvoice->invoice_id);
        return 'INV-' . ($lastNumber + 1);
    }
}
