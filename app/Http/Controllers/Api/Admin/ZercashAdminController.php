<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ResponseHelper;
use App\Helpers\Helpers;
use App\Models\Transaction;
use App\Models\Payment;
use App\Models\Order;
use App\Models\Wallet;
use App\Models\User;
use App\Models\Setting;
use App\Models\KycVerification;
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
        $payoutWallets = Wallet::whereIn('status', ['active', 'activated', 'approved'])->count();
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

    // ────────────────────────────────────────────────────────────────────────────
    //  Cashback Manager + Zercash Countries
    //
    //  Both are list-based admin configs. Rather than spinning up dedicated collections
    //  we piggyback on the existing `settings` table using distinct `group` keys
    //  (`zercash_cashback`, `zercash_countries`). The whole list is replaced on save —
    //  simple and matches how zercash settings already work.
    // ────────────────────────────────────────────────────────────────────────────

    /** Defaults used the first time admin loads the Cashback Manager tab. */
    private function defaultCashbackItems(): array
    {
        return [
            ['id' => 'playlist',  'title' => 'Playlist',             'description' => 'Users receive cashback after playlist purchases.',  'enabled' => true,  'kind' => 'percent', 'value' => 2,   'minPurchase' => 0,  'icon' => 'TrendingUp'],
            ['id' => 'streaming', 'title' => 'Streaming Minutes',    'description' => 'Cashback for streaming minutes spent on premium content.', 'enabled' => true,  'kind' => 'fixed',   'value' => 5,   'minPurchase' => 30, 'icon' => 'Coins'],
            ['id' => 'upgrade',   'title' => 'Account Upgrade',      'description' => 'Reward users when upgrading their account tier.',   'enabled' => false, 'kind' => 'percent', 'value' => 3,   'minPurchase' => 0,  'icon' => 'Wallet'],
            ['id' => 'market',    'title' => 'Market',               'description' => 'Cashback rewards for purchases inside the market.', 'enabled' => true,  'kind' => 'percent', 'value' => 1.5, 'minPurchase' => 10, 'icon' => 'Gift'],
            ['id' => 'brands',    'title' => 'YekBûn Brands',        'description' => 'Cashback rewards for official YekBûn products.',    'enabled' => true,  'kind' => 'fixed',   'value' => 10,  'minPurchase' => 50, 'icon' => 'Gift'],
            ['id' => 'events',    'title' => 'Events',               'description' => 'Cashback for event ticket purchases.',              'enabled' => false, 'kind' => 'percent', 'value' => 2,   'minPurchase' => 0,  'icon' => 'TrendingUp'],
            ['id' => 'channel',   'title' => 'Channel Subscription', 'description' => 'Reward subscribers of premium channels.',           'enabled' => true,  'kind' => 'fixed',   'value' => 3,   'minPurchase' => 0,  'icon' => 'Coins'],
            ['id' => 'delivery',  'title' => 'Delivery Services',    'description' => 'Cashback on delivery orders processed via Zercash.','enabled' => false, 'kind' => 'percent', 'value' => 1,   'minPurchase' => 5,  'icon' => 'Wallet'],
        ];
    }

    /** Defaults used the first time admin loads the Zercash Countries tab. */
    private function defaultCountries(): array
    {
        return [
            ['id' => 'sy', 'name' => 'Syria',   'flag' => '🇸🇾', 'status' => 'Active',     'enabled' => true,  'note' => 'Zercash payments enabled'],
            ['id' => 'iq', 'name' => 'Iraq',    'flag' => '🇮🇶', 'status' => 'Active',     'enabled' => true,  'note' => 'Zercash payments enabled'],
            ['id' => 'de', 'name' => 'Germany', 'flag' => '🇩🇪', 'status' => 'Active',     'enabled' => true,  'note' => 'Zercash payments enabled'],
            ['id' => 'tr', 'name' => 'Turkey',  'flag' => '🇹🇷', 'status' => 'Restricted', 'enabled' => false, 'note' => 'Limited payment support'],
            ['id' => 'ae', 'name' => 'UAE',     'flag' => '🇦🇪', 'status' => 'Pending',    'enabled' => false, 'note' => 'Pending treasury approval'],
            ['id' => 'lb', 'name' => 'Lebanon', 'flag' => '🇱🇧', 'status' => 'Restricted', 'enabled' => false, 'note' => 'Limited payment support'],
        ];
    }

    /** GET /admin/zercash/cashback — full cashback items list. Seeds defaults on first read. */
    public function cashback()
    {
        $row = Setting::where('group', 'zercash_cashback')->first();
        $items = is_array($row->data ?? null) && count($row->data) > 0
            ? array_values($row->data)
            : $this->defaultCashbackItems();
        return ResponseHelper::sendResponse(['items' => $items], 'Cashback items fetched');
    }

    /** PUT /admin/zercash/cashback — replaces the whole items list. */
    public function updateCashback(Request $request)
    {
        $items = $request->input('items', []);
        if (!is_array($items)) {
            return ResponseHelper::sendResponse(null, 'items must be an array', false, 422);
        }

        Setting::updateOrCreate(
            ['group' => 'zercash_cashback'],
            ['group' => 'zercash_cashback', 'data' => $items, 'updated_at' => now()]
        );

        return ResponseHelper::sendResponse(['items' => $items], 'Cashback items saved');
    }

    /** GET /admin/zercash/countries — country availability matrix. Seeds defaults on first read. */
    public function countries()
    {
        $row = Setting::where('group', 'zercash_countries')->first();
        $items = is_array($row->data ?? null) && count($row->data) > 0
            ? array_values($row->data)
            : $this->defaultCountries();
        return ResponseHelper::sendResponse(['items' => $items], 'Zercash countries fetched');
    }

    /** PUT /admin/zercash/countries — replaces the whole country list. */
    public function updateCountries(Request $request)
    {
        $items = $request->input('items', []);
        if (!is_array($items)) {
            return ResponseHelper::sendResponse(null, 'items must be an array', false, 422);
        }

        Setting::updateOrCreate(
            ['group' => 'zercash_countries'],
            ['group' => 'zercash_countries', 'data' => $items, 'updated_at' => now()]
        );

        return ResponseHelper::sendResponse(['items' => $items], 'Zercash countries saved');
    }

    /**
     * GET /admin/zercash/pending-requests
     *
     * Returns the top N pending Zercash-style requests aggregated from multiple sources so the
     * navbar dropdown shows a unified list:
     *  - Pending wallet KYC submissions  (type = "KYC")
     *  - Pending wallet records          (type = "Wallet")
     *  - Pending transactions            (type = "Top-up" / "Withdraw" / "Transfer" — derived
     *                                     from the transaction's `category` / `type`).
     *
     * Each row carries enough data for the dropdown row + a `userId` the front-end uses to deep
     * link into the manage-users page.
     */
    public function pendingRequests(Request $request)
    {
        $limit = min((int) $request->get('limit', 10), 50);

        $pendingKyc = KycVerification::where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        $pendingWallets = Wallet::whereIn('status', ['pending', 'under_review'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        $pendingTx = Transaction::where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        $userIds = $pendingKyc->pluck('user_id')
            ->merge($pendingWallets->pluck('user_id'))
            ->merge($pendingTx->pluck('user_id'))
            ->filter()->map(fn ($id) => (string) $id)->unique()->values()->toArray();

        $users = User::whereIn('_id', $userIds)->get()->keyBy('_id');

        // Source of truth is the kyc/wallet rows themselves — by querying those with
        // status='pending' above we've already filtered out resolved requests. No need to inspect
        // the user record (which we no longer write wallet/kyc flags to).
        $resolvedUserIds = [];

        $rows = collect();

        foreach ($pendingKyc as $kyc) {
            // Skip if the user has already been resolved via the admin actions endpoint.
            if (in_array((string) $kyc->user_id, $resolvedUserIds, true)) continue;
            $u = $users->get((string) $kyc->user_id);
            $rows->push([
                'id'        => 'kyc-' . (string) $kyc->_id,
                'kind'      => 'kyc',
                'userId'    => (string) ($u->_id ?? $kyc->user_id),
                'userName'  => $u->name ?? 'Unknown',
                'initials'  => $this->initials($u->name ?? $u->username ?? '?'),
                'avatar'    => Helpers::mediaUrl($u->image ?? null) ?? '',
                'type'      => 'KYC',
                'amount'    => '—',
                'status'    => 'Pending',
                'time'      => $kyc->created_at ? Carbon::parse($kyc->created_at)->diffForHumans() : '',
                'createdAt' => $kyc->created_at,
            ]);
        }

        foreach ($pendingWallets as $w) {
            if (in_array((string) $w->user_id, $resolvedUserIds, true)) continue;
            // Skip if this user already has a pending KYC row — same logical request.
            if ($pendingKyc->contains(fn ($k) => (string) $k->user_id === (string) $w->user_id)) {
                continue;
            }
            $u = $users->get((string) $w->user_id);
            $rows->push([
                'id'        => 'wallet-' . (string) $w->_id,
                'kind'      => 'wallet',
                'userId'    => (string) ($u->_id ?? $w->user_id),
                'userName'  => $u->name ?? 'Unknown',
                'initials'  => $this->initials($u->name ?? $u->username ?? '?'),
                'avatar'    => Helpers::mediaUrl($u->image ?? null) ?? '',
                'type'      => 'Wallet',
                'amount'    => number_format((float) ($w->balance ?? 0), 2) . ' ZER',
                'status'    => 'Pending',
                'time'      => $w->created_at ? Carbon::parse($w->created_at)->diffForHumans() : '',
                'createdAt' => $w->created_at,
            ]);
        }

        foreach ($pendingTx as $tx) {
            if (in_array((string) $tx->user_id, $resolvedUserIds, true)) continue;
            $u = $users->get((string) $tx->user_id);
            $rows->push([
                'id'        => 'tx-' . (string) $tx->_id,
                'kind'      => 'transaction',
                'userId'    => (string) ($u->_id ?? $tx->user_id),
                'userName'  => $u->name ?? 'Unknown',
                'initials'  => $this->initials($u->name ?? $u->username ?? '?'),
                'avatar'    => Helpers::mediaUrl($u->image ?? null) ?? '',
                'type'      => $this->mapTransactionType($tx),
                'amount'    => number_format((float) ($tx->amount ?? 0), 2) . ' ZER',
                'status'    => 'Pending',
                'time'      => $tx->created_at ? Carbon::parse($tx->created_at)->diffForHumans() : '',
                'createdAt' => $tx->created_at,
            ]);
        }

        $sorted = $rows
            ->sortByDesc(fn ($r) => $r['createdAt'] ? Carbon::parse($r['createdAt'])->timestamp : 0)
            ->take($limit)
            ->values()
            ->map(function ($r) {
                unset($r['createdAt']);
                return $r;
            });

        return ResponseHelper::sendResponse([
            'requests' => $sorted,
            // `$rows` is already filtered to exclude resolved users, so its count is the true
            // number of outstanding pending requests for badges.
            'total'    => $rows->count(),
        ], 'Pending Zercash requests fetched');
    }

    private function initials(string $name): string
    {
        $clean = trim($name);
        if ($clean === '') return '??';
        $parts = preg_split('/\s+/', $clean) ?: [];
        if (count($parts) >= 2) {
            return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1));
        }
        return strtoupper(mb_substr($clean, 0, 2));
    }

    private function mapTransactionType($tx): string
    {
        $category = (string) ($tx->category ?? '');
        $type = (string) ($tx->type ?? '');
        return match (true) {
            $category === 'payout' || $type === 'withdraw' => 'Withdraw',
            $category === 'deposit' || $type === 'deposit' => 'Top-up',
            $category === 'transfer' || $type === 'transfer' => 'Transfer',
            default => ucfirst($category ?: $type ?: 'Transaction'),
        };
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
