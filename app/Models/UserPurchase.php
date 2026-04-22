<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class UserPurchase extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $collection = 'user_purchases';

    protected $fillable = [
        'user_id',
        'platform',
        'type',
        'product_id',
        'transaction_id',
        'purchase_token',
        'is_valid',
        'expires_at',
        'raw_response',
    ];

    protected $casts = [
        'is_valid' => 'boolean',
        'expires_at' => 'datetime',
        'raw_response' => 'array',
    ];
}

