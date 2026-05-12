<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class ZercashSaleManager extends Model
{
    use UsesLegacyId;

    protected $connection = 'mongodb';
    protected $table = 'zercash_sale_managers';

    protected $guarded = [];

    protected $casts = [
        'zer_in_treasur' => 'float',
        'total_shops'    => 'integer',
        'total_win'      => 'float',
        'sort_order'     => 'integer',
    ];
}
