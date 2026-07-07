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

    /** Sender of the request (the user who sent it). */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Recipient of the request. */
    public function request_user()
    {
        return $this->belongsTo(User::class, 'request_id');
    }
}
