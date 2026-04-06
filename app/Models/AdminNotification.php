<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class AdminNotification extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'admin_notifications';

    protected $fillable = [
        'otp',
        'push',
        'email',
    ];
}
