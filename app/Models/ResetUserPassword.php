<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class ResetUserPassword extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'reset_user_passwords';

    protected $fillable = [
        'user_id',
        'email',
        'code',
        'token',
        'password_token',
    ];
}
