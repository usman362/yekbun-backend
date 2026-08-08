<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

/**
 * Single LoCoNet operations snapshot for the dashboard (project-level).
 * Seeded from database/data/loconet_seed.json; mutations persist here
 * until the real LoCoNet provider is connected.
 */
class LoconetState extends Model
{
    use UsesLegacyId;

    protected $connection = 'mongodb';
    protected $table = 'loconet_state';

    protected $guarded = [];

    protected $casts = [
        'services' => 'array',
        'overviewKpis' => 'array',
        'chats' => 'array',
        'chatTimeline' => 'array',
        'reports' => 'array',
        'calls' => 'array',
        'streams' => 'array',
        'scheduled' => 'array',
        'streamers' => 'array',
        'balance' => 'array',
        'packages' => 'array',
        'purchaseRequests' => 'array',
        'invoices' => 'array',
        'usageHistory' => 'array',
        'trafficMetrics' => 'array',
        'trafficBreakdown' => 'array',
        'hourlySeries' => 'array',
        'activity' => 'array',
        'auditAccess' => 'array',
        'integrationLogs' => 'array',
        'usageSeries' => 'array',
        'settings' => 'array',
        'integration' => 'array',
    ];
}
