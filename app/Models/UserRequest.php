<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class UserRequest extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'user_requests';

    protected $fillable = [
        'user_id',
        'request_id',
        'status',
    ];
}
