<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class UserVisitor extends Model
{
    use UsesLegacyId;

    protected $connection = 'mongodb';
    protected $table = 'users_visitors';

    protected $fillable = [
        'user_id',
        'visitor_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function visitor()
    {
        return $this->belongsTo(User::class, 'visitor_id');
    }
}
