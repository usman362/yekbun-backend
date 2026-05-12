<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class AdminRole extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'roles';
    protected $guarded = [];

    protected $casts = [
        'permissions' => 'array',
    ];
}
