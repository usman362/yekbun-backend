<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ResponseHelper;
use App\Models\Transaction;
use App\Models\Payment;
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
