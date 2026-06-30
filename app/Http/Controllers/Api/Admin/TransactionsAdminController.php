<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ResponseHelper;
use App\Models\Transaction;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TransactionsAdminController extends Controller
{
    public function index(Request $request)
    {
        $mode   = $request->get('mode', 'daily');
        $search = $request->get('search', '');
        $page   = (int) $request->get('page', 1);
        $limit  = min((int) $request->get('per_page', 20), 100);

        $dateFormat = match ($mode) {
            'monthly' => '%Y-%m',
            'yearly'  => '%Y',
            default   => '%Y-%m-%d',
        };

        $pipeline = [];

        if ($search) {
            $pipeline[] = [
                '$match' => [
                    'created_at' => ['$regex' => $search, '$options' => 'i'],
                ],
            ];
        }

        $pipeline[] = [
            '$addFields' => [
                'date_group' => [
                    '$dateToString' => [
                        'format' => $dateFormat,
                        'date'   => '$created_at',
                    ],
                ],
            ],
        ];

        $pipeline[] = [
            '$group' => [
                '_id'           => '$date_group',
                'transactions'  => ['$sum' => 1],
                'internalPaid'  => [
                    '$sum' => [
                        '$cond' => [
                            ['$eq' => ['$type', 'internal']],
                            ['$ifNull' => ['$amount', 0]],
                            0,
                        ],
                    ],
                ],
                'internalTA' => [
                    '$sum' => [
                        '$cond' => [
                            ['$eq' => ['$type', 'internal']],
                            ['$ifNull' => ['$tax', 0]],
                            0,
                        ],
                    ],
                ],
                'internalCB' => [
                    '$sum' => [
                        '$cond' => [
                            ['$eq' => ['$type', 'internal']],
                            ['$ifNull' => ['$cashback', 0]],
                            0,
                        ],
                    ],
                ],
                'externalPaid' => [
                    '$sum' => [
                        '$cond' => [
                            ['$eq' => ['$type', 'external']],
                            ['$ifNull' => ['$amount', 0]],
                            0,
                        ],
                    ],
                ],
                'externalTA' => [
                    '$sum' => [
                        '$cond' => [
                            ['$eq' => ['$type', 'external']],
                            ['$ifNull' => ['$tax', 0]],
                            0,
                        ],
                    ],
                ],
                'externalCB' => [
                    '$sum' => [
                        '$cond' => [
                            ['$eq' => ['$type', 'external']],
                            ['$ifNull' => ['$cashback', 0]],
                            0,
                        ],
                    ],
                ],
                'cashback' => ['$sum' => ['$ifNull' => ['$cashback', 0]]],
                'total'    => ['$sum' => ['$ifNull' => ['$amount', 0]]],
                'totalTA'  => ['$sum' => ['$ifNull' => ['$tax', 0]]],
                'totalCB'  => ['$sum' => ['$ifNull' => ['$cashback', 0]]],
            ],
        ];

        $pipeline[] = ['$sort' => ['_id' => -1]];

        $countPipeline = array_merge($pipeline, [['$count' => 'total']]);
        $countResult   = Transaction::raw(function ($collection) use ($countPipeline) {
            return $collection->aggregate($countPipeline)->toArray();
        });
        $totalItems = $countResult[0]['total'] ?? 0;

        $pipeline[] = ['$skip' => ($page - 1) * $limit];
        $pipeline[] = ['$limit' => $limit];

        $results = Transaction::raw(function ($collection) use ($pipeline) {
            return $collection->aggregate($pipeline)->toArray();
        });

        $rows = collect($results)->map(function ($row, $index) use ($page, $limit) {
            return [
                'id'           => (string) (($page - 1) * $limit + $index + 1),
                'date'         => $row['_id'] ?? '',
                'transactions' => $row['transactions'] ?? 0,
                'internalPaid' => round($row['internalPaid'] ?? 0, 2),
                'internalTA'   => round($row['internalTA'] ?? 0, 2),
                'internalCB'   => round($row['internalCB'] ?? 0, 2),
                'externalPaid' => round($row['externalPaid'] ?? 0, 2),
                'externalTA'   => round($row['externalTA'] ?? 0, 2),
                'externalCB'   => round($row['externalCB'] ?? 0, 2),
                'cashback'     => round($row['cashback'] ?? 0, 2),
                'total'        => round($row['total'] ?? 0, 2),
                'totalTA'      => round($row['totalTA'] ?? 0, 2),
                'totalCB'      => round($row['totalCB'] ?? 0, 2),
            ];
        })->values();

        return ResponseHelper::sendResponse([
            'rows'      => $rows,
            'total'     => $totalItems,
            'page'      => $page,
            'last_page' => max(1, ceil($totalItems / $limit)),
        ], 'Transactions fetched');
    }

    /**
     * GET /admin/transactions/details?mode=daily|monthly|yearly&date=<bucket-key>
     * Individual transactions inside one table row's period bucket (for the details popup).
     * `date` is the same group key the table row carries: 2026-06-25 / 2026-06 / 2026.
     */
    public function details(Request $request)
    {
        $mode = $request->get('mode', 'daily');
        $date = (string) $request->get('date', '');

        [$start, $end] = $this->bucketRange($mode, $date);
        if (!$start) {
            return ResponseHelper::sendResponse(['items' => []], 'Invalid date.', false, 422);
        }

        $items = Transaction::whereBetween('created_at', [$start, $end])
            ->orderBy('created_at', 'desc')
            ->limit(300)
            ->get()
            ->map(fn ($t) => $this->mapDetail($t))
            ->values();

        return ResponseHelper::sendResponse(['items' => $items], 'Transaction details fetched.');
    }

    /** Resolve a bucket key + mode into a [start, end] Carbon range. */
    private function bucketRange(string $mode, string $date): array
    {
        try {
            if ($mode === 'yearly') {
                $s = Carbon::createFromFormat('Y', $date)->startOfYear();
                return [$s, $s->copy()->endOfYear()];
            }
            if ($mode === 'monthly') {
                $s = Carbon::createFromFormat('Y-m', $date)->startOfMonth();
                return [$s, $s->copy()->endOfMonth()];
            }
            $s = Carbon::parse($date)->startOfDay();
            return [$s, $s->copy()->endOfDay()];
        } catch (\Throwable $e) {
            return [null, null];
        }
    }

    /** Map a transaction row into the popup's TxDetail shape. */
    private function mapDetail($t): array
    {
        $u = $t->user_id ? User::find($t->user_id) : null;

        $statusMap = ['COMPLETED' => 'Completed', 'PENDING' => 'Pending', 'FAILED' => 'Refunded', 'REFUNDED' => 'Refunded'];
        $catMap = [
            'upgrade_music_playlist' => 'Playlist',
            'streaming_minutes'      => 'Streaming',
            'choose_your_plan'       => 'Upgrade',
            'standard_zer_package'   => 'Market',
            'business_zer_package'   => 'Market',
        ];

        return [
            'id'          => $t->tId ?: ('TX-' . substr((string) $t->_id, -6)),
            'username'    => $u->name ?? ($u->username ?? 'User'),
            'userId'      => $u ? ('U-' . substr((string) $u->_id, -4)) : '',
            'walletId'    => '',
            'dateTime'    => $t->created_at,
            'amount'      => (float) ($t->amount ?? 0),
            'cashback'    => (float) ($t->cashback_amount ?? $t->cashback ?? 0),
            'paymentType' => ($t->type ?? 'external') === 'internal' ? 'Internal' : 'External',
            'category'    => $catMap[$t->category ?? ''] ?? 'Shop',
            'shopName'    => $t->shop_name ?? '',
            'productName' => $t->description ?? '',
            'status'      => $statusMap[strtoupper((string) ($t->status ?? 'COMPLETED'))] ?? 'Completed',
            'invoice'     => $t->order_number ?? $t->invoice_id ?? '',
        ];
    }

    public function stats()
    {
        $now       = Carbon::now();
        $last30    = $now->copy()->subDays(30);
        $last60    = $now->copy()->subDays(60);

        $current   = Transaction::where('created_at', '>=', $last30)->count();
        $previous  = Transaction::where('created_at', '>=', $last60)
                        ->where('created_at', '<', $last30)->count();

        $totalAmount  = Transaction::where('created_at', '>=', $last30)->sum('amount') ?? 0;
        $totalCB      = Transaction::where('created_at', '>=', $last30)->sum('cashback') ?? 0;

        $internal = Transaction::where('created_at', '>=', $last30)
                        ->where('type', 'internal')->sum('amount') ?? 0;
        $external = Transaction::where('created_at', '>=', $last30)
                        ->where('type', 'external')->sum('amount') ?? 0;

        return ResponseHelper::sendResponse([
            'totalTransactions' => $current,
            'previousPeriod'    => $previous,
            'change'            => $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : 0,
            'totalAmount'       => round($totalAmount, 2),
            'totalCashback'     => round($totalCB, 2),
            'internalTotal'     => round($internal, 2),
            'externalTotal'     => round($external, 2),
        ], 'Transaction stats');
    }
}
