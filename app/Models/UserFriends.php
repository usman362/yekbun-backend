<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class UserFriends extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'user_friends';

    protected $fillable = [
        'user_id',
        'friend_id',
        'user_type',
        'status',
    ];
}
