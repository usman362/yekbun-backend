<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class Holiday extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'holidays';
    protected $guarded = [];

    protected $casts = [
        'is_national_day' => 'boolean',
    ];
}
