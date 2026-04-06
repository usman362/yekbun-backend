<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class UserImei extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'user_imeis';

    protected $fillable = [
        'user_id',
        'device_imei',
    ];
}
