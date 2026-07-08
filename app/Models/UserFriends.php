<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class UserFriends extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'user_friends';

    protected $fillable = [
        'user_id',
        'friend_id',
        'user_type',
        'status',
    ];

    /** Owner side of the friendship row. */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** The connected friend on this row. */
    public function friend()
    {
        return $this->belongsTo(User::class, 'friend_id');
    }
}
