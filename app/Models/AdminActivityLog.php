<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class AdminActivityLog extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'admin_activity_logs';
    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
    ];
}
