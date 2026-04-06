<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;

class StripeController extends Controller
{
    public function index(Request $request)
    {
        // Stripe checkout placeholder - configure Stripe SDK
        $transaction_id = 'YK' . mt_rand(100000000, 999999999);

        $payment = new Payment();
        $payment->payment_id = 'stripe_' . time();
        $payment->payer_id = mt_rand(100000000, 999999999);
        $payment->payer_email = $request->email;
        $payment->amount = $request->amount;
        $payment->currency = env('STRIPE_CURRENCY', 'usd');
        $payment->payment_status = 'pending';
        $payment->type = 'stripe';
        $payment->transaction_id = $transaction_id;
        $payment->status = 0;
        $payment->user_id = $request->user_id;
        $payment->level = $request->level;
        $payment->save();

        return response()->json(['success' => true, 'data' => 'Configure Stripe SDK for checkout.', 'transaction_id' => $transaction_id]);
    }

    public function update(Request $request)
    {
        $payment = Payment::where('transaction_id', $request->transaction_id)->first();
        if ($payment) {
            if (isset($payment->user_id)) {
                $user = User::find($payment->user_id);
                if ($user) {
                    $user->level = $payment->level;
                    $user->save();
                }
            }
            $payment->payment_status = 'approved';
            $payment->status = 1;
            $payment->save();
        }
        return response()->json(['success' => true, 'message' => 'Transaction updated.']);
    }

    public function success()
    {
        return response()->json(['success' => true, 'message' => 'Payment successful.']);
    }
}
