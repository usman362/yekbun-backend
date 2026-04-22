<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class Wallet extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'wallets';

    protected $fillable = [
        'user_id',
        'pin',
        'balance',
        'status',
        'status_reason',
        'welcome_bonus_claimed',
        'expire_at',
    ];

    protected $casts = [
        'balance' => 'float',
        'welcome_bonus_claimed' => 'boolean',
        'expire_at' => 'datetime',
    ];
}
