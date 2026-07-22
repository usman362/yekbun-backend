<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

/**
 * Device Control — runtime policy pack (API / Feed / Video / Reels / Rendering / Network).
 */
class RuntimeProfile extends Model
{
    use UsesLegacyId;

    protected $connection = 'mongodb';
    protected $table = 'runtime_profiles';

    protected $guarded = [];

    protected $casts = [
        'affected_devices'       => 'integer',
        'linked_device_profiles' => 'array',
        'api'                    => 'array',
        'feed'                   => 'array',
        'video'                  => 'array',
        'reels'                  => 'array',
        'rendering'              => 'array',
        'network'                => 'array',
        'history'                => 'array',
        'published_at'           => 'datetime',
    ];
}
