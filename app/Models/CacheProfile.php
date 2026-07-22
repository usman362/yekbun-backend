<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

/**
 * Device Control — cache allocation / cleanup / sync policy pack.
 */
class CacheProfile extends Model
{
    use UsesLegacyId;

    protected $connection = 'mongodb';
    protected $table = 'cache_profiles';

    protected $guarded = [];

    protected $casts = [
        'affected_devices'       => 'integer',
        'linked_device_profiles' => 'array',
        'allocation'             => 'array',
        'categories'             => 'array',
        'cleanup'                => 'array',
        'sync'                   => 'array',
        'history'                => 'array',
        'published_at'           => 'datetime',
    ];
}
