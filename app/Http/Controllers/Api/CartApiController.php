<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helpers;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\ZercashProduct;
use App\Traits\BuildsZercashCartLines;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * Server-side Zercash cart.
 *
 * The cart stores only a {product_id, qty} reference per user — never a price. Every read
 * re-prices the lines from the live product table (via BuildsZercashCartLines), so the
 * cart the mobile app shows is always in sync with admin price changes and matches exactly
 * what POST /checkout will charge. The user can then check out the whole cart in one call
 * (POST /checkout with no `items`), which clears the cart on success.
 *
 * Routes (all auth-protected):
 *   GET    /api/cart                  → current cart, priced + totalled
 *   POST   /api/cart/items            → add/increment  { product_id, qty? }
 *   PUT    /api/cart/items/{id}       → set quantity   { qty }      (id = product_id or cart row id)
 *   DELETE /api/cart/items/{id}       → remove one line             (id = product_id or cart row id)
 *   POST   /api/cart/clear            → empty the cart
 */
class CartApiController extends Controller
{
    use BuildsZercashCartLines;

    /** GET /api/cart — priced cart + totals. */
    public function index()
    {
        return ResponseHelper::sendResponse($this->cartPayload(), 'Cart fetched successfully.');
    }

    /** POST /api/cart/items — add a product (or bump its quantity if already in the cart). */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|string',
            'qty'        => 'nullable|integer|min:1|max:99',
        ]);
        if ($validator->fails()) {
            return ResponseHelper::sendResponse(
                ['errors' => $validator->errors()->toArray()],
                $validator->errors()->first() ?: 'Validation failed.',
                false,
                422
            );
        }

        $product = ZercashProduct::find($request->product_id);
        if (!$product || ($product->status ?? 'active') !== 'active') {
            return ResponseHelper::sendResponse(null, 'Product is unavailable.', false, 410);
        }

        $qty = max(1, (int) ($request->qty ?? 1));

        $row = Cart::where('user_id', (string) Auth::id())
            ->where('product_id', (string) $product->_id)
            ->first();

        if ($row) {
            $row->qty = min(99, (int) ($row->qty ?? 1) + $qty);
            $row->save();
        } else {
            $row = new Cart();
            $row->user_id    = (string) Auth::id();
            $row->product_id = (string) $product->_id;
            $row->qty        = $qty;
            $row->created_at = Carbon::now();
            $row->save();
        }

        return ResponseHelper::sendResponse($this->cartPayload(), 'Item added to cart.');
    }

    /** PUT /api/cart/items/{id} — set the exact quantity for a line. */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'qty' => 'required|integer|min:1|max:99',
        ]);
        if ($validator->fails()) {
            return ResponseHelper::sendResponse(
                ['errors' => $validator->errors()->toArray()],
                $validator->errors()->first() ?: 'Validation failed.',
                false,
                422
            );
        }

        $row = $this->findRow($id);
        if (!$row) {
            return ResponseHelper::sendResponse(null, 'Cart item not found.', false, 404);
        }

        $row->qty = max(1, min(99, (int) $request->qty));
        $row->save();

        return ResponseHelper::sendResponse($this->cartPayload(), 'Cart item updated.');
    }

    /** DELETE /api/cart/items/{id} — remove a line (id = product_id or cart row id). */
    public function destroy($id)
    {
        $row = $this->findRow($id);
        if (!$row) {
            return ResponseHelper::sendResponse(null, 'Cart item not found.', false, 404);
        }
        $row->delete();

        return ResponseHelper::sendResponse($this->cartPayload(), 'Cart item removed.');
    }

    /** POST /api/cart/clear — empty the whole cart. */
    public function clear()
    {
        Cart::where('user_id', (string) Auth::id())->delete();
        return ResponseHelper::sendResponse($this->cartPayload(), 'Cart cleared successfully.');
    }

    /* ───────────────────────── helpers ───────────────────────── */

    /** Resolve a cart row by product_id first (mobile-friendly), then by raw cart _id. */
    private function findRow($id)
    {
        $base = Cart::where('user_id', (string) Auth::id());
        return (clone $base)->where('product_id', (string) $id)->first()
            ?? (clone $base)->where('_id', $id)->first();
    }

    /**
     * Build the full cart response: every line re-priced from the live product table, plus
     * totals. Lines whose product was deleted/deactivated are silently dropped here AND
     * removed from the cart so the user never gets stuck with a dead line.
     */
    private function cartPayload(): array
    {
        $rows = Cart::where('user_id', (string) Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();

        $items = [];
        $stale = [];
        foreach ($rows as $row) {
            // Price one row at a time so a single dead product doesn't blank the whole cart.
            $priced = $this->buildCartLines([
                ['product_id' => $row->product_id, 'qty' => $row->qty],
            ]);
            if ($priced['unavailable'] !== null) {
                $stale[] = $row->_id;
                continue;
            }
            $line = $priced['lines'][0];
            $line['cart_item_id'] = (string) $row->_id;
            $line['image'] = Helpers::mediaUrl($line['image']);
            $items[] = $line;
        }

        // Drop any products that vanished since they were added.
        if ($stale) {
            Cart::whereIn('_id', $stale)->delete();
        }

        $totalZer  = array_sum(array_column($items, 'line_zer'));
        $totalFiat = array_sum(array_column($items, 'line_fiat'));
        $cashback  = array_sum(array_column($items, 'cashback_zer'));
        $itemCount = array_sum(array_column($items, 'qty'));

        return [
            'items'  => $items,
            'totals' => [
                'lines'      => count($items),
                'item_count' => $itemCount,
                'total_zer'  => round($totalZer, 2),
                'total_fiat' => round($totalFiat, 2),
                'cashback'   => round($cashback, 2),
            ],
        ];
    }
}
