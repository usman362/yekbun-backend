<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class Invoice extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'invoices';
    protected $guarded = [];

    protected $casts = [
        'items'           => 'array',
        'subtotal'        => 'float',
        'total_zer'       => 'float',
        'total_fiat'      => 'float',
        'cashback_earned' => 'float',
        'date'            => 'datetime',
    ];

    /** Sequential human-friendly invoice number, e.g. "INV-1001". */
    public static function generateInvoiceId(): string
    {
        $last = self::orderBy('created_at', 'desc')->first();
        if (!$last || empty($last->invoice_id)) {
            return 'INV-1001';
        }
        $n = (int) str_replace('INV-', '', (string) $last->invoice_id);
        return 'INV-' . ($n + 1);
    }
}
