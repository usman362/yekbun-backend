<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

/**
 * Citizen "Kurdistan Complaint". Lives in its own collection but reuses the feed
 * engagement tables (FeedLikes / FeedComments keyed by feed_id + feed_type='complaint')
 * so the mobile app can like/comment it through the existing feed endpoints.
 *
 * Only `status = approved` complaints are shown in the public feed; everything starts
 * as `pending` until the moderation team approves it.
 */
class Complaint extends Model
{
    use UsesLegacyId;

    protected $connection = 'mongodb';
    protected $table = 'complaints';
    protected $guarded = [];

    protected $casts = [
        'images'      => 'array',
        'location'    => 'array',
        'reviewed_at' => 'datetime',
    ];

    /** Human-friendly reference shown to the user, e.g. "YB-89231-RT". */
    public static function generateReference(): string
    {
        return 'YB-' . mt_rand(10000, 99999) . '-RT';
    }
}
