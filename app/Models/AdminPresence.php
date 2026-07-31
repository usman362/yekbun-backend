<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class AdminPresence extends Model
{
    use UsesLegacyId;

    protected $connection = 'mongodb';
    protected $table = 'admin_presence';
    protected $guarded = [];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'login_at'     => 'datetime',
    ];
}
