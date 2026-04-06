<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class UserCode extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'user_codes';

    protected $fillable = [
        'user_id',
        'code',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
