<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class Clips extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'clips';

    protected $guarded = [];

    public function template() { return $this->belongsTo(ClipTemplates::class, 'template_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }

    /** Unified views — same feed_views collection as history / AI / feeds (feed_type=clips). */
    public function views()
    {
        return $this->hasMany(FeedViews::class, 'feed_id')->where('feed_type', 'clips');
    }

    /** Legacy clips_views rows (pre store-feeds-views). */
    public function legacy_views()
    {
        return $this->hasMany(ClipsViews::class, 'clip_id');
    }

    public function likes() { return $this->hasMany(ClipsLikes::class, 'clip_id'); }
}
