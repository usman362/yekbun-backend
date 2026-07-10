<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class FeedViews extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'feed_views';

    protected $fillable = ['user_id', 'feed_id', 'feed_type'];

    public function user() { return $this->belongsTo(User::class, 'user_id'); }
    public function admin_feed() { return $this->belongsTo(PopFeeds::class, 'feed_id'); }
    public function user_feed() { return $this->belongsTo(Feed::class, 'feed_id'); }
    public function history() { return $this->belongsTo(History::class, 'feed_id'); }
    public function ai_video() { return $this->belongsTo(AIVideo::class, 'feed_id'); }
    public function clip() { return $this->belongsTo(Clips::class, 'feed_id'); }
}
