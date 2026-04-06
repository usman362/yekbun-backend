<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ResponseHelper;
use App\Models\Transaction;
use App\Models\Payment;
use App\Models\Order;
use App\Models\Wallet;
use App\Models\User;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ZercashAdminController extends Controller
{
    /**
     * Overview dashboard data for ZercashOverviewPage
     */
    public function overview()
    {
        $now    = Carbon::now();
        $last30 = $now->copy()->subDays(30);
        $last60 = $now->copy()->subDays(60);

        // ── Top Stats (7 cards) ──
        $upgradesCurrent  = Transaction::where('created_at', '>=', $last30)->where('category', 'upgrade')->count();
        $upgradesPrevious = Transaction::where('created_at', '>=', $last60)->where('created_at', '<', $last30)->where('category', 'upgrade')->count();

        $playlistCurrent  = Transaction::where('created_at', '>=', $last30)->where('category', 'playlist')->count();
        $playlistPrevious = Transaction::where('created_at', '>=', $last60)->where('created_at', '<', $last30)->where('category', 'playlist')->count();

        $tvCurrent  = Transaction::where('created_at', '>=', $last30)->where('category', 'tv')->count();
        $tvPrevious = Transaction::where('created_at', '>=', $last60)->where('created_at', '<', $last30)->where('category', 'tv')->count();

        $streamCurrent  = Transaction::where('created_at', '>=', $last30)->where('category', 'streaming')->count();
        $streamPrevious = Transaction::where('created_at', '>=', $last60)->where('created_at', '<', $last30)->where('category', 'streaming')->count();

        $zerPkgCurrent  = Transaction::where('created_at', '>=', $last30)->where('category', 'zer_package')->count();
        $zerPkgPrevious = Transaction::where('created_at', '>=', $last60)->where('created_at', '<', $last30)->where('category', 'zer_package')->count();

        $externalCurrent  = Transaction::where('created_at', '>=', $last30)->where('type', 'external')->sum('amount');
        $externalPrevious = Transaction::where('created_at', '>=', $last60)->where('created_at', '<', $last30)->where('type', 'external')->sum('amount');

        $internalCurrent  = Transaction::where('created_at', '>=', $last30)->where('type', 'internal')->sum('amount');
        $internalPrevious = Transaction::where('created_at', '>=', $last60)->where('created_at', '<', $last30)->where('type', 'internal')->sum('amount');

        $topStats = [
            $this->buildStat('Upgrades', 'U', $upgradesCurrent, $upgradesPrevious),
            $this->buildStat('Playlists', 'P', $playlistCurrent, $playlistPrevious),
            $this->buildStat('YekbûnTV', 'Y', $tvCurrent, $tvPrevious),
            $this->buildStat('Streaming', 'S', $streamCurrent, $streamPrevious),
            $this->buildStat('Zêr Package', 'Z', $zerPkgCurrent, $zerPkgPrevious),
            $this->buildStat('External', 'E', round($externalCurrent, 2), round($externalPrevious, 2)),
            $this->buildStat('Internal', 'I', round($internalCurrent, 2), round($internalPrevious, 2)),
        ];

        // ── Wallet Cards (5 cards) ──
        $totalZer      = Wallet::sum('balance') ?? 0;
        $totalCashback = Transaction::where('cashback', '>', 0)->sum('cashback') ?? 0;
        $payoutWallets = Wallet::where('status', 'active')->count();
        $feeCollected  = Transaction::sum('fee') ?? 0;
        $taxCollected  = Transaction::sum('tax') ?? 0;

        $walletCards = [
            [
                'label'  => 'Total Zêr',
                'value'  => $this->formatNumber($totalZer),
                'badges' => [
                    ['text' => 'Active', 'color' => 'emerald'],
                ],
            ],
            [
                'label'  => 'Cashback overview',
                'value'  => $this->formatNumber($totalCashback),
                'badges' => [
                    ['text' => 'Distributed', 'color' => 'blue'],
                ],
            ],
            [
                'label'  => 'Payout Wallets',
                'value'  => (string) $payoutWallets,
                'badges' => [
                    ['text' => 'Active', 'color' => 'emerald'],
                ],
            ],
            [
                'label'  => 'Fee Wallet',
                'value'  => $this->formatNumber($feeCollected),
                'badges' => [
                    ['text' => 'Collected', 'color' => 'amber'],
                ],
            ],
            [
                'label'  => 'Tax Wallet',
                'value'  => $this->formatNumber($taxCollected),
                'badges' => [
                    ['text' => 'Collected', 'color' => 'amber'],
                ],
            ],
        ];

        // ── Shop Requests ──
        $shopRequests = Order::where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->limit(4)
            ->get()
            ->map(function ($order) {
                $user = User::find($order->user_id);
                return [
                    'name'     => $user->name ?? 'Unknown',
                    'location' => $user->city ?? $user->country ?? '',
                ];
            })->values();

        // ── New Wallets ──
        $newWallets = Wallet::orderBy('created_at', 'desc')
            ->limit(4)
            ->get()
            ->map(function ($wallet) {
                $user = User::find($wallet->user_id);
                return [
                    'name'     => $user->name ?? 'Unknown',
                    'location' => $user->city ?? $user->country ?? '',
                    'card'     => substr($wallet->_id, -6),
                    'balance'  => $this->formatNumber($wallet->balance ?? 0),
                ];
            })->values();

        // ── Best Sellers ──
        $bestSellersPipeline = [
            ['$match' => ['created_at' => ['$gte' => new \MongoDB\BSON\UTCDateTime($last30->getTimestamp() * 1000)]]],
            ['$group' => [
                '_id'   => '$user_id',
                'total' => ['$sum' => '$amount'],
                'count' => ['$sum' => 1],
            ]],
            ['$sort' => ['total' => -1]],
            ['$limit' => 4],
        ];

        $bestSellersRaw = Transaction::raw(function ($collection) use ($bestSellersPipeline) {
            return $collection->aggregate($bestSellersPipeline)->toArray();
        });

        $bestSellers = collect($bestSellersRaw)->map(function ($item) use ($last60, $last30) {
            $user     = User::find($item['_id']);
            $prev     = Transaction::where('user_id', $item['_id'])
                            ->where('created_at', '>=', $last60)
                            ->where('created_at', '<', $last30)
                            ->sum('amount');
            $change   = $prev > 0 ? round((($item['total'] - $prev) / $prev) * 100, 1) : 0;

            return [
                'name'   => $user->name ?? 'Unknown',
                'sub'    => $item['count'] . ' transactions',
                'value'  => $this->formatNumber($item['total']),
                'change' => ($change >= 0 ? '+' : '') . $change . '%',
            ];
        })->values();

        // ── Payout Requests ──
        $payoutRequests = Transaction::where('category', 'payout')
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->limit(4)
            ->get()
            ->map(function ($tx) {
                $user = User::find($tx->user_id);
                return [
                    'name'     => $user->name ?? 'Unknown',
                    'location' => $user->city ?? $user->country ?? '',
                ];
            })->values();

        return ResponseHelper::sendResponse([
            'topStats'       => $topStats,
            'walletCards'    => $walletCards,
            'shopRequests'   => $shopRequests,
            'newWallets'     => $newWallets,
            'bestSellers'    => $bestSellers,
            'payoutRequests' => $payoutRequests,
        ], 'Zercash overview fetched');
    }

    /**
     * Get Zercash settings
     */
    public function settings()
    {
        $settings = Setting::where('group', 'zercash')->first();

        $defaults = $this->getDefaultSettings();

        $data = $settings ? array_merge($defaults, $settings->data ?? []) : $defaults;

        return ResponseHelper::sendResponse($data, 'Zercash settings fetched');
    }

    /**
     * Update Zercash settings
     */
    public function updateSettings(Request $request)
    {
        $data = $request->all();

        Setting::updateOrCreate(
            ['group' => 'zercash'],
            ['group' => 'zercash', 'data' => $data, 'updated_at' => now()]
        );

        return ResponseHelper::sendResponse($data, 'Zercash settings updated');
    }

    // ── Helpers ──

    private function buildStat(string $label, string $letter, $current, $previous): array
    {
        $change = $previous > 0
            ? round((($current - $previous) / $previous) * 100, 1)
            : ($current > 0 ? 100 : 0);

        return [
            'label'  => $label,
            'letter' => $letter,
            'value'  => is_float($current) ? $this->formatNumber($current) : (string) $current,
            'change' => ($change >= 0 ? '+' : '') . $change . '%',
        ];
    }

    private function formatNumber($value): string
    {
        if ($value >= 1000000) {
            return round($value / 1000000, 1) . 'M';
        }
        if ($value >= 1000) {
            return round($value / 1000, 1) . 'K';
        }
        return (string) round($value, 2);
    }

    private function getDefaultSettings(): array
    {
        return [
            // Zer Settings
            'allowedCurrency' => true, 'euroEnabled' => true, 'dollarEnabled' => true,
            'backingValue' => true, 'backingEuro' => 0.01, 'backingEuroOn' => true, 'backingDollar' => 0.01, 'backingDollarOn' => true,
            'treasurySell' => true, 'treasuryEuro' => 0.01, 'treasuryEuroOn' => true, 'treasuryDollar' => 0.01, 'treasuryDollarOn' => true,
            // Treasur
            'treasurReserve' => true, 'treasurReserveVal' => 10,
            // Transactions
            'internalPayments' => true, 'intPlaylist' => 0.50, 'intStream' => 0.30, 'intUpgrade' => 1.00,
            'externalPayments' => true, 'extPlaylist' => 0.50, 'extStream' => 0.30, 'extUpgrade' => 1.00,
            // Reseller
            'salemanager' => true, 'smEuro' => 0.009, 'smEuroOn' => true, 'smDollar' => 0.009, 'smDollarOn' => true,
            'partnershop' => true, 'psEuro' => 0.009, 'psEuroOn' => true, 'psDollar' => 0.009, 'psDollarOn' => true,
            'webapp' => true, 'waEuro' => 0.009, 'waEuroOn' => true, 'waDollar' => 0.009, 'waDollarOn' => true,
            // Wallet
            'zerGift' => true, 'zerGiftVal' => 5,
            'walletReserve' => true, 'walletReserveVal' => 10,
            // Shop
            'payoutFee' => true, 'payoutFeeVal' => 2.5,
            'shopWallet' => true, 'shopWalletVal' => 100,
            // System
            'txAlert1' => true, 'txAlert1Val' => 1000,
            'txAlert2' => true, 'txAlert2Val' => 5000,
        ];
    }
}
