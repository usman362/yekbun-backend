<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class ZercashProduct extends Model
{
    use UsesLegacyId;

    protected $connection = 'mongodb';
    protected $table = 'zercash_products';

    protected $guarded = [];

    protected $casts = [
        'zer_amount'        => 'float',
        'fiat_amount'       => 'float',
        'cashback_percent'  => 'float',
        'songs_count'       => 'integer',
        'features'          => 'array',
        'sort_order'        => 'integer',
    ];
}
