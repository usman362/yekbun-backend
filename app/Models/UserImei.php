<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class UserImei extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'user_imeis';

    protected $fillable = [
        'user_id',
        'device_imei',
    ];
}
