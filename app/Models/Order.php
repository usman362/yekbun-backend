<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class Order extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'orders';
    protected $guarded = [];

    /**
     * Cast the embedded-array & numeric fields the checkout flow writes. `items` is
     * a snapshot of the cart at purchase time so future product-price changes don't
     * rewrite history; the totals stay floats so we can round consistently in PHP.
     */
    protected $casts = [
        'items'           => 'array',
        'total_zer'       => 'float',
        'total_fiat'      => 'float',
        'cashback_earned' => 'float',
        'paid_at'         => 'datetime',
    ];

    public static function generateOrderNumber()
    {
        return 'ORD-' . mt_rand(100000, 999999);
    }
}
