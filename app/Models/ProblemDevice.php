<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

/**
 * Device Control — aggregated problem group (crash / ANR / OOM cluster by device family).
 */
class ProblemDevice extends Model
{
    use UsesLegacyId;

    protected $connection = 'mongodb';
    protected $table = 'problem_devices';

    protected $guarded = [];

    protected $casts = [
        'models'              => 'array',
        'affected_devices'    => 'integer',
        'crash_rate'          => 'float',
        'memory_at_crash'     => 'integer',
        'cpu_usage'           => 'integer',
        'active_api_calls'    => 'integer',
        'pending_requests'    => 'integer',
        'feed_items_mounted'  => 'integer',
        'video_players'       => 'integer',
        'cache_usage'         => 'integer',
        'first_seen_at'       => 'datetime',
        'last_seen_at'        => 'datetime',
    ];
}
