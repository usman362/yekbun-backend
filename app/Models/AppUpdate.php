<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

/**
 * A single mobile-app release row (App Updates settings module). One row is the "current"
 * release the mobile app checks against via GET /api/app/update.
 */
class AppUpdate extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'app_updates';

    protected $fillable = [
        'version',           // "1.0.5"
        'version_code',      // 105
        'title',             // "Performance & Stability"
        'description',       // "Faster startup and smoother feeds."
        'force_update',      // bool
        'status',            // published | draft
        'is_current',        // bool — the live release the API returns
        'release_date',      // Y-m-d
        'published_by',      // admin name
        'google_play_url',
        'closed_testing_url',
        'downloads',         // int (stat)
        'adoption',          // int % (stat)
    ];

    protected $casts = [
        'version_code' => 'integer',
        'force_update' => 'boolean',
        'is_current'   => 'boolean',
        'downloads'    => 'integer',
        'adoption'     => 'integer',
    ];
}
