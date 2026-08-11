<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

/**
 * Agent Event Center queue item (claim / process / run / reply).
 */
class AgentEvent extends Model
{
    use UsesLegacyId;

    protected $connection = 'mongodb';
    protected $table = 'agent_events';

    protected $guarded = [];

    protected $casts = [
        'thread_messages' => 'array',
        'payload' => 'array',
        'result' => 'array',
        'task' => 'array',
        'activity_log' => 'array',
        'is_duplicate' => 'boolean',
        'claimed_at' => 'datetime',
        'created_at_event' => 'datetime',
    ];
}
