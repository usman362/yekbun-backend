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

    public static function generateOrderNumber()
    {
        return 'ORD-' . mt_rand(100000, 999999);
    }
}
