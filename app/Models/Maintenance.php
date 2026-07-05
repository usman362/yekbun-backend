<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

/**
 * Single-row config for Maintenance Mode: a full-platform master switch plus per-category /
 * per-subcategory online/offline toggles. The mobile app reads this to know what's offline.
 */
class Maintenance extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'maintenance';

    protected $guarded = [];

    protected $casts = [
        'full_platform' => 'boolean',
        'categories'    => 'array',
    ];
}
