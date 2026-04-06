<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class UserRequest extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'user_requests';

    protected $fillable = [
        'user_id',
        'request_id',
        'status',
    ];
}
