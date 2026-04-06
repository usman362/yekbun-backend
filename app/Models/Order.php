<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Order extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'orders';
    protected $guarded = [];

    public static function generateOrderNumber()
    {
        return 'ORD-' . mt_rand(100000, 999999);
    }
}
