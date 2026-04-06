<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class ResetUserPassword extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'reset_user_passwords';

    protected $fillable = [
        'user_id',
        'email',
        'code',
        'token',
        'password_token',
    ];
}
