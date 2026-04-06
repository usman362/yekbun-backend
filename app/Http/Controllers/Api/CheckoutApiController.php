<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckoutApiController extends Controller
{
    public function prepare(Request $request)
    {
        $request->validate([
            'shipping' => 'required|array',
            'shipping.firstName' => 'required|string|max:100',
            'shipping.lastName' => 'required|string|max:100',
            'shipping.email' => 'required|email',
            'shipping.phone' => 'required|string|max:30',
            'shipping.address' => 'required|string|max:500',
            'shipping.city' => 'required|string|max:100',
            'shipping.state' => 'required|string|max:100',
            'shipping.zip' => 'required|string|max:20',
        ]);

        $cartItems = Cart::where('user_id', Auth::id())->get();
        if ($cartItems->isEmpty() && (!$request->has('items') || count($request->items) === 0)) {
            return ResponseHelper::sendResponse([], 'Cart is empty.', false, 400);
        }

        $items = $cartItems->isNotEmpty()
            ? $cartItems->map(fn($item) => [
                'id' => $item->_id, 'title' => $item->title,
                'subtitle' => $item->subtitle ?? '', 'price' => (float) ($item->price ?? 0),
                'quantity' => (int) ($item->quantity ?? 1),
            ])->toArray()
            : $request->items;

        $subtotal = collect($items)->sum(fn($item) => ($item['price'] ?? 0) * ($item['quantity'] ?? 1));
        $cashbackPercent = 5;
        $cashback = round($subtotal * ($cashbackPercent / 100), 2);
        $tax = $request->tax ?? round($subtotal * 0.10, 2);
        $total = round($subtotal + $tax - $cashback, 2);

        return ResponseHelper::sendResponse([
            'user_id' => Auth::id(), 'items' => $items, 'shipping' => $request->shipping,
            'subtotal' => $subtotal, 'cashback' => $cashback, 'cashback_percent' => $cashbackPercent,
            'tax' => $tax, 'total' => $total, 'prepared_at' => Carbon::now()->toIso8601String(),
        ], 'Checkout prepared successfully.');
    }

    public function pay(Request $request)
    {
        $request->validate(['checkout' => 'required|array', 'payment_method' => 'required|string|in:card,paypal,bank']);
        $checkout = $request->checkout;

        try {
            $order = Order::create([
                'user_id' => Auth::id(), 'order_number' => 'ORD-' . mt_rand(100000, 999999),
                'items' => $checkout['items'] ?? [], 'shipping' => $checkout['shipping'] ?? [],
                'subtotal' => (float) ($checkout['subtotal'] ?? 0), 'tax' => (float) ($checkout['tax'] ?? 0),
                'total' => (float) ($checkout['total'] ?? 0), 'cashback' => (float) ($checkout['cashback'] ?? 0),
                'payment_method' => $request->payment_method, 'payment_status' => 'completed', 'order_status' => 'confirmed',
            ]);

            $transactionId = 'YK' . mt_rand(100000000, 999999999);
            $transaction = new Transaction();
            $transaction->tId = $transactionId;
            $transaction->amount = $checkout['total'] ?? 0;
            $transaction->status = 'COMPLETED';
            $transaction->transaction_type = 'purchase';
            $transaction->user_id = Auth::id();
            $transaction->order_id = $order->_id;
            $transaction->payment_method = $request->payment_method;
            $transaction->date = Carbon::now()->format('Y-m-d');
            $transaction->save();

            Cart::where('user_id', Auth::id())->delete();

            return ResponseHelper::sendResponse([
                'order' => $order, 'transaction_id' => $transactionId,
            ], 'Payment successful!');
        } catch (Exception $e) {
            return ResponseHelper::sendResponse(['error' => $e->getMessage()], 'Payment processing failed.', false, 500);
        }
    }

    public function orders(Request $request)
    {
        $orders = Order::where('user_id', Auth::id())->orderBy('created_at', 'desc')
            ->paginate($request->get('limit', 20));
        return ResponseHelper::sendResponse($orders, 'Orders fetched successfully.');
    }

    public function orderDetail($id)
    {
        $order = Order::where('_id', $id)->where('user_id', Auth::id())->first();
        if (!$order) return ResponseHelper::sendResponse([], 'Order not found.', false, 404);
        return ResponseHelper::sendResponse($order, 'Order fetched successfully.');
    }
}
