<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartApiController extends Controller
{
    public function index()
    {
        $items = Cart::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($item) { return $this->formatCartItem($item); });

        return ResponseHelper::sendResponse(['items' => $items, 'count' => $items->count()], 'Cart fetched successfully.');
    }

    public function store(Request $request)
    {
        $request->validate(['title' => 'required|string|max:255', 'price' => 'required|numeric|min:0']);

        $existQuery = Cart::where('user_id', Auth::id());
        if ($request->has('data_id') && $request->data_id) {
            $existQuery->where('data_id', $request->data_id);
        } else {
            $existQuery->where('title', $request->title);
        }
        $existing = $existQuery->first();

        if ($existing) {
            $existing->quantity = ($existing->quantity ?? 1) + ($request->quantity ?? 1);
            $existing->save();
            return ResponseHelper::sendResponse($this->formatCartItem($existing), 'Cart item quantity updated.');
        }

        $cart = new Cart();
        $cart->user_id = Auth::id();
        $cart->title = $request->title;
        $cart->subtitle = $request->subtitle ?? '';
        $cart->price = (float) $request->price;
        $cart->quantity = (int) ($request->quantity ?? 1);
        $cart->image = $request->image ?? null;
        $cart->type = $request->type ?? 'product';
        $cart->data_id = $request->data_id ?? null;
        $cart->save();

        return ResponseHelper::sendResponse($this->formatCartItem($cart), 'Item added to cart.');
    }

    public function update(Request $request, $id)
    {
        $cart = Cart::where('_id', $id)->where('user_id', Auth::id())->first();
        if (!$cart) return ResponseHelper::sendResponse([], 'Cart item not found.', false, 404);

        if ($request->has('quantity')) $cart->quantity = max(1, (int) $request->quantity);
        if ($request->has('title')) $cart->title = $request->title;
        if ($request->has('price')) $cart->price = (float) $request->price;
        $cart->save();

        return ResponseHelper::sendResponse($this->formatCartItem($cart), 'Cart item updated.');
    }

    public function destroy($id)
    {
        $cart = Cart::where('_id', $id)->where('user_id', Auth::id())->first();
        if (!$cart) return ResponseHelper::sendResponse([], 'Cart item not found.', false, 404);
        $cart->delete();
        return ResponseHelper::sendResponse([], 'Cart item removed.');
    }

    public function clear()
    {
        Cart::where('user_id', Auth::id())->delete();
        return ResponseHelper::sendResponse([], 'Cart cleared successfully.');
    }

    private function formatCartItem($item)
    {
        return [
            'id' => $item->_id,
            'title' => $item->title,
            'subtitle' => $item->subtitle ?? '',
            'price' => (float) ($item->price ?? 0),
            'quantity' => (int) ($item->quantity ?? 1),
            'image' => $item->image ?? null,
            'type' => $item->type ?? null,
            'data_id' => $item->data_id ?? null,
        ];
    }
}
