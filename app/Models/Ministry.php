<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class Ministry extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'ministries';
    protected $guarded = [];

    protected $casts = [
        'opening_times' => 'array',
        'is_24_7'       => 'boolean',
    ];
}
