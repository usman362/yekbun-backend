<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PayPalController extends Controller
{
    public function keys()
    {
        $keys = [
            'client_id' => env('PAYPAL_CLIENT_ID'),
            'mode' => env('PAYPAL_MODE', 'sandbox'),
        ];
        return ResponseHelper::sendResponse($keys, 'PayPal keys has been fetch successfully');
    }

    public function createOrder(Request $request)
    {
        $request->validate(['amount' => 'required|numeric|min:0.1']);
        // PayPal order creation placeholder
        return response()->json([
            'message' => 'Configure PayPal SDK for order creation.',
            'amount' => $request->amount,
        ]);
    }

    public function captureOrder(Request $request)
    {
        $request->validate(['order_id' => 'required|string']);
        // PayPal capture placeholder
        return response()->json([
            'status' => 'pending',
            'message' => 'Configure PayPal SDK for order capture.',
        ]);
    }
}
