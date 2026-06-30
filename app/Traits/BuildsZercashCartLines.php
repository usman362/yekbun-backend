<?php

namespace App\Traits;

use App\Models\Setting;
use App\Models\ZercashProduct;

/**
 * Shared cart-line pricing for the Zercash cart + checkout flow.
 *
 * Both the cart preview (GET /cart) and the actual purchase (POST /checkout) must price
 * lines IDENTICALLY — always from the live product row, never from a client-supplied
 * price. Keeping the logic in one trait means the price the user sees in their cart is
 * guaranteed to match what they're charged at checkout.
 */
trait BuildsZercashCartLines
{
    /** Categories paid in ZER (wallet balance debit). */
    private array $zerPricedCategories = ['upgrade_music_playlist', 'streaming_minutes'];

    /** Categories paid in fiat that CREDIT zer to the wallet on completion. */
    private array $zerPackageCategories = ['standard_zer_package', 'business_zer_package'];

    /** Categories that grant a subscription tier (paid in fiat). */
    private array $planCategories = ['choose_your_plan'];

    /** Map a product category to its purchase "kind" (drives the post-payment side effects). */
    protected function classifyKind(string $category): string
    {
        if (in_array($category, $this->planCategories, true))        return 'plan';
        if (in_array($category, $this->zerPackageCategories, true))  return 'zer_package';
        if (in_array($category, $this->zerPricedCategories, true))   return 'zer_priced';
        return 'other';
    }

    /** Map a product category to its Cashback Manager rule id (null = no rule). */
    protected function cashbackRuleIdFor(string $category): ?string
    {
        return [
            'upgrade_music_playlist' => 'playlist',
            'streaming_minutes'      => 'streaming',
            'choose_your_plan'       => 'upgrade',
            'standard_zer_package'   => 'zer_package',
            'business_zer_package'   => 'zer_package',
        ][$category] ?? null;
    }

    /**
     * Load the admin's Cashback Manager rules keyed by id (only enabled ones).
     * Each: ['kind' => 'percent'|'fixed', 'value' => float, 'min' => float].
     */
    protected function loadCashbackRules(): array
    {
        $row = Setting::where('group', 'zercash_cashback')->first();
        $items = is_array($row?->data ?? null) ? $row->data : [];
        $map = [];
        foreach ($items as $it) {
            if (empty($it['enabled']) || empty($it['id'])) continue;
            $map[$it['id']] = [
                'kind'  => $it['kind'] ?? 'percent',
                'value' => (float) ($it['value'] ?? 0),
                'min'   => (float) ($it['minPurchase'] ?? 0),
            ];
        }
        return $map;
    }

    /**
     * Re-price a list of {product_id, qty} rows against the live product table.
     *
     * @param  iterable  $items  each element: ['product_id' => string, 'qty' => int|null]
     * @return array{
     *     lines: array<int,array>,
     *     total_zer: float, total_fiat: float, cashback: float,
     *     item_count: int, unavailable: string|null
     * }  `unavailable` is the first product_id that no longer exists / is inactive (null = all good).
     */
    protected function buildCartLines(iterable $items): array
    {
        $lines = [];
        $totalZer = 0.0;
        $totalFiat = 0.0;
        $cashback = 0.0;
        $itemCount = 0;
        $cashbackRules = $this->loadCashbackRules();

        foreach ($items as $row) {
            $productId = is_array($row) ? ($row['product_id'] ?? null) : null;
            $product = $productId ? ZercashProduct::find($productId) : null;

            if (!$product || ($product->status ?? 'active') !== 'active') {
                return [
                    'lines' => [], 'total_zer' => 0.0, 'total_fiat' => 0.0,
                    'cashback' => 0.0, 'item_count' => 0,
                    'unavailable' => (string) $productId,
                ];
            }

            $qty = max(1, (int) ($row['qty'] ?? 1));
            $zerCost  = (float) ($product->zer_amount  ?? 0);
            $fiatCost = (float) ($product->fiat_amount ?? 0);

            $category = $product->category ?? '';
            $kind = $this->classifyKind($category);

            $lineZerTotal  = $zerCost  * $qty;
            $lineFiatTotal = $fiatCost * $qty;
            // Cashback off the ZER amount for zer-priced items, off the fiat amount otherwise.
            $amountBasis = $kind === 'zer_priced' ? $lineZerTotal : $lineFiatTotal;

            // Cashback is driven by the admin's Cashback Manager rule for this category:
            //   - fixed   → flat `value` (e.g. always 10 Zer), once the min purchase is met
            //   - percent → amountBasis × value%
            // If there is no rule for the category, fall back to the product's own percent.
            $rule = $cashbackRules[$this->cashbackRuleIdFor($category) ?? ''] ?? null;
            if ($rule) {
                if ($amountBasis < $rule['min']) {
                    $lineCashback = 0.0;
                    $cashPct = 0.0;
                } elseif ($rule['kind'] === 'fixed') {
                    $lineCashback = round((float) $rule['value'], 2);
                    $cashPct = 0.0;
                } else {
                    $cashPct = (float) $rule['value'];
                    $lineCashback = round($amountBasis * $cashPct / 100, 2);
                }
            } else {
                $cashPct = (float) ($product->cashback_percent ?? 0);
                $lineCashback = round($amountBasis * $cashPct / 100, 2);
            }

            $totalZer  += $lineZerTotal;
            $totalFiat += $lineFiatTotal;
            $cashback  += $lineCashback;
            $itemCount += $qty;

            $lines[] = [
                'product_id'   => (string) $product->_id,
                'name'         => $product->name,
                'image'        => $product->image ?? null,
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

        return [
            'lines'       => $lines,
            'total_zer'   => round($totalZer, 2),
            'total_fiat'  => round($totalFiat, 2),
            'cashback'    => round($cashback, 2),
            'item_count'  => $itemCount,
            'unavailable' => null,
        ];
    }
}
