<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

/**
 * Device Control — per-device health snapshot (last-seen telemetry from mobile).
 */
class DeviceTelemetry extends Model
{
    use UsesLegacyId;

    protected $connection = 'mongodb';
    protected $table = 'device_telemetry';

    protected $guarded = [];

    protected $casts = [
        'cache_used_pct'    => 'integer',
        'memory_usage_pct'  => 'integer',
        'fps'               => 'integer',
        'health_score'      => 'integer',
        'crash_count'       => 'integer',
        'last_seen_at'      => 'datetime',
        'reported_at'       => 'datetime',
    ];
}
