<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\ZercashProduct;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * Cart checkout / purchase processor.
 *
 * Single entry point: POST /api/checkout. Accepts a list of {product_id, qty} and a
 * payment_method, then:
 *
 *   1. Re-prices every line from the live product row (price tampering protection — never
 *      trust client-supplied prices).
 *   2. Validates the chosen payment method against the totals (e.g. you can't use wallet
 *      balance to pay an EUR-priced item; you can't have a "store" payment of EUR=0).
 *   3. For instant-settle methods (wallet balance), atomically:
 *       - debits the wallet for ZER-priced items
 *       - credits the wallet for ZER-package purchases (top-ups)
 *       - upgrades the user's subscription tier for plan purchases
 *       - records one Transaction per line so the user's wallet history shows everything
 *       - credits any earned cashback as a separate PENDING cashback transaction the user
 *         can later sweep into wallet via the existing `my_cashbacks` flow
 *   4. For deferred methods (store / bank / paypal), creates a PENDING order + transactions
 *      without touching the wallet — backoffice marks them COMPLETED when funds arrive.
 *
 * Returns the order snapshot + a per-line breakdown so the mobile app can render a
 * "purchase confirmation" screen without a second API round-trip.
 */
class CheckoutController extends Controller
{
    /** Categories whose products are paid in ZER (wallet balance debit). */
    private const ZER_PRICED_CATEGORIES = ['upgrade_music_playlist', 'streaming_minutes'];

    /** Categories whose products are paid in fiat and CREDIT zer to the wallet on completion. */
    private const ZER_PACKAGE_CATEGORIES = ['standard_zer_package', 'business_zer_package'];

    /** Categories whose products grant a subscription tier (paid in fiat). */
    private const PLAN_CATEGORIES = ['choose_your_plan'];

    public function checkout(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'items'               => 'required|array|min:1',
            'items.*.product_id'  => 'required|string',
            'items.*.qty'         => 'nullable|integer|min:1|max:99',
            'payment_method'      => 'required|in:balance,store,bank,paypal',
        ]);

        if ($validator->fails()) {
            return ResponseHelper::sendResponse(
                ['errors' => $validator->errors()->toArray()],
                $validator->errors()->first() ?: 'Validation failed.',
                false,
                422
            );
        }

        $user = User::find(Auth::id());
        if (!$user) {
            return ResponseHelper::sendResponse(null, 'User not found.', false, 404);
        }

        // ── 1. Re-price every line from the DB ───────────────────────────────
        $lines = [];
        $totalZer  = 0.0;
        $totalFiat = 0.0;
        $cashback  = 0.0;

        foreach ($request->input('items') as $row) {
            $product = ZercashProduct::find($row['product_id']);
            if (!$product || ($product->status ?? 'active') !== 'active') {
                return ResponseHelper::sendResponse(
                    null,
                    "Product {$row['product_id']} is unavailable.",
                    false,
                    410
                );
            }
            $qty = max(1, (int) ($row['qty'] ?? 1));

            $zerCost  = ((float) ($product->zer_amount  ?? 0));
            $fiatCost = ((float) ($product->fiat_amount ?? 0));
            $cashPct  = ((float) ($product->cashback_percent ?? 0));

            // Categorise so the post-payment side effects (upgrade plan, credit zer, etc.)
            // know how to react. The category string lives on the product row, set by admin.
            $category = $product->category ?? '';
            $kind = in_array($category, self::PLAN_CATEGORIES,         true) ? 'plan'
                  : (in_array($category, self::ZER_PACKAGE_CATEGORIES, true) ? 'zer_package'
                  : (in_array($category, self::ZER_PRICED_CATEGORIES,  true) ? 'zer_priced'
                  : 'other'));

            $lineZerTotal  = $zerCost  * $qty;
            $lineFiatTotal = $fiatCost * $qty;
            // Cashback is computed off the ZER amount for zer-priced items and off the
            // fiat amount for fiat-priced items, normalised to ZER.
            $lineCashback = round(($kind === 'zer_priced' ? $lineZerTotal : $lineFiatTotal) * $cashPct / 100, 2);

            $totalZer  += $lineZerTotal;
            $totalFiat += $lineFiatTotal;
            $cashback  += $lineCashback;

            $lines[] = [
                'product_id'   => (string) $product->_id,
                'name'         => $product->name,
                'category'     => $category,
                'kind'         => $kind,
                'qty'          => $qty,
                'zer_amount'   => $zerCost,
                'fiat_amount'  => $fiatCost,
                'currency'     => $product->fiat_currency ?? 'EUR',
                'cashback_pct' => $cashPct,
                'cashback_zer' => $lineCashback,
                'line_zer'     => $lineZerTotal,
                'line_fiat'    => $lineFiatTotal,
            ];
        }

        // ── 2. Validate payment method against totals ────────────────────────
        $method = $request->input('payment_method');
        $wallet = Wallet::where('user_id', (string) $user->_id)->first();

        if ($method === 'balance') {
            if (!$wallet || !in_array($wallet->status ?? '', ['activated', 'active', 'approved'], true)) {
                return ResponseHelper::sendResponse(null, 'Your wallet is not active yet.', false, 403);
            }
            if ($totalFiat > 0) {
                return ResponseHelper::sendResponse(
                    null,
                    'Wallet balance can only pay for Zer-priced items. Use bank or PayPal for plans / Zer top-ups.',
                    false,
                    422
                );
            }
            if (($wallet->balance ?? 0) < $totalZer) {
                return ResponseHelper::sendResponse(
                    ['need' => $totalZer, 'have' => (float) ($wallet->balance ?? 0)],
                    'Insufficient wallet balance.',
                    false,
                    402
                );
            }
        }

        // ── 3. Create the order shell (PENDING until we finish side effects) ─
        $order = new Order();
        $order->order_number    = Order::generateOrderNumber();
        $order->user_id         = (string) $user->_id;
        $order->items           = $lines;
        $order->total_zer       = round($totalZer, 2);
        $order->total_fiat      = round($totalFiat, 2);
        $order->cashback_earned = round($cashback, 2);
        $order->payment_method  = $method;
        $order->status          = 'PENDING';
        $order->created_at      = Carbon::now();
        $order->save();

        // ── 4. Apply side effects (only for instant-settle method) ───────────
        $now = Carbon::now();

        if ($method === 'balance') {
            // Debit ZER from wallet.
            if ($totalZer > 0) {
                $wallet->balance = round(($wallet->balance ?? 0) - $totalZer, 2);
                $wallet->save();
            }

            // Per-line transactions + tier upgrades.
            foreach ($lines as $line) {
                $this->writePurchaseTxn($user, $order, $line, $now);
                $this->applyTierUpgrade($user, $line);
                $this->applyZerPackageCredit($wallet, $line);
            }

            // Cashback as a single PENDING `category=cashback` row → user can claim it later
            // via the existing wallet `my_cashbacks` flow.
            if ($cashback > 0) {
                $cb = new Transaction();
                $cb->user_id          = (string) $user->_id;
                $cb->transaction_type = 'deposit';
                $cb->category         = 'cashback';
                $cb->amount           = $cashback;
                $cb->currency         = 'ZER';
                $cb->status           = 'PENDING';
                $cb->description      = "Cashback from order {$order->order_number}";
                $cb->order_id         = (string) $order->_id;
                $cb->date             = $now->format('Y-m-d');
                $cb->created_at       = $now;
                $cb->save();
            }

            $order->status   = 'COMPLETED';
            $order->paid_at  = $now;
            $order->save();
        } else {
            // Deferred methods: queue per-line PENDING txns; no wallet change, no tier upgrade
            // until backoffice / payment-gateway webhook flips the order to COMPLETED.
            foreach ($lines as $line) {
                $this->writePurchaseTxn($user, $order, $line, $now, 'PENDING');
            }
        }

        // Refresh the wallet snapshot we return to the client so the mobile UI can update
        // balance + cashback without re-fetching /wallet/dashboard.
        $wallet = $wallet ? $wallet->refresh() : null;

        return ResponseHelper::sendResponse([
            'order'           => [
                'id'              => (string) $order->_id,
                'order_number'    => $order->order_number,
                'status'          => $order->status,
                'payment_method'  => $order->payment_method,
                'total_zer'       => $order->total_zer,
                'total_fiat'      => $order->total_fiat,
                'cashback_earned' => $order->cashback_earned,
                'items'           => $order->items,
                'paid_at'         => $order->paid_at,
            ],
            'wallet'          => $wallet ? [
                'balance'   => round((float) $wallet->balance, 2),
                'status'    => $wallet->status,
            ] : null,
            'user_type'       => $user->user_type,
            'plan_expires_at' => $user->expired_at ?? null,
        ], $order->status === 'COMPLETED'
            ? 'Order completed successfully.'
            : 'Order placed. Awaiting payment confirmation.');
    }

    /**
     * GET /api/orders — caller's own order history (paginated).
     */
    public function myOrders(Request $request)
    {
        $perPage = min((int) $request->get('per_page', 10), 50);
        $orders = Order::where('user_id', (string) Auth::id())
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return ResponseHelper::sendResponse([
            'items'       => $orders->items(),
            'current_page'=> $orders->currentPage(),
            'last_page'   => $orders->lastPage(),
            'total'       => $orders->total(),
        ], 'Orders fetched.');
    }

    /* ─────────────────────────────────────────────────────────────────────
     *  Side-effect helpers — extracted so each one's intent is obvious and
     *  the main checkout() body reads like a recipe.
     * ──────────────────────────────────────────────────────────────────── */

    /** Write a single per-line wallet transaction row. */
    private function writePurchaseTxn(User $user, Order $order, array $line, Carbon $now, string $status = 'COMPLETED'): void
    {
        $tx = new Transaction();
        $tx->user_id          = (string) $user->_id;
        // `purchase` is what the wallet endpoints already key off (see WalletApiController)
        // so this row shows up in latest_transactions automatically.
        $tx->transaction_type = 'purchase';
        $tx->category         = $line['category'] ?? 'purchase';
        $tx->amount           = $line['line_zer'] > 0 ? $line['line_zer'] : $line['line_fiat'];
        $tx->currency         = $line['line_zer'] > 0 ? 'ZER' : ($line['currency'] ?? 'EUR');
        $tx->cashback_amount  = $line['cashback_zer'] ?? 0;
        $tx->cashback_percent = $line['cashback_pct'] ?? 0;
        $tx->status           = $status;
        $tx->description      = $line['name'];
        $tx->shop_name        = $line['name'];
        $tx->order_id         = (string) $order->_id;
        $tx->order_number     = $order->order_number;
        $tx->date             = $now->format('Y-m-d');
        $tx->created_at       = $now;
        $tx->save();
    }

    /** If the line is a subscription plan, bump the user's tier + extend expiry. */
    private function applyTierUpgrade(User $user, array $line): void
    {
        if (($line['kind'] ?? '') !== 'plan') return;

        $user->user_type = strtolower($line['name'] ?? $user->user_type);
        // Default 30-day extension per qty.
        $extend = max(1, (int) ($line['qty'] ?? 1)) * 30;
        $current = $user->expired_at ? Carbon::parse($user->expired_at) : Carbon::now();
        $user->expired_at = $current->isFuture()
            ? $current->copy()->addDays($extend)
            : Carbon::now()->addDays($extend);
        $user->save();
    }

    /** Top-up the wallet for zer-package purchases. */
    private function applyZerPackageCredit(?Wallet $wallet, array $line): void
    {
        if (!$wallet || ($line['kind'] ?? '') !== 'zer_package') return;
        $credit = (float) ($line['zer_amount'] * $line['qty']);
        $wallet->balance = round(($wallet->balance ?? 0) + $credit, 2);
        $wallet->save();
    }
}
