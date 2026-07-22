<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

/**
 * Device Control — hardware-tier profile (Entry / Low / Balanced / High / Ultra).
 * Links to a cache_profile key + runtime_profile key; mobile resolves by hardware match.
 */
class DeviceProfile extends Model
{
    use UsesLegacyId;

    protected $connection = 'mongodb';
    protected $table = 'device_profiles';

    protected $guarded = [];

    protected $casts = [
        'priority'                 => 'integer',
        'assigned_devices'         => 'integer',
        'hardware'                 => 'array',
        'assignment'               => 'array',
        'fallback'                 => 'array',
        'memory'                   => 'array',
        'cache'                    => 'array',
        'api'                      => 'array',
        'feed'                     => 'array',
        'video'                    => 'array',
        'reels'                    => 'array',
        'rendering'                => 'array',
        'network'                  => 'array',
        'history'                  => 'array',
        'published_at'             => 'datetime',
    ];
}
