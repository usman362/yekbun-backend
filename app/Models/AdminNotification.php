<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class AdminNotification extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'admin_notifications';

    protected $fillable = [
        'otp',
        'push',
        'email',
    ];
}
