<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class FeedLikes extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'feed_likes';

    protected $fillable = ['user_id', 'feed_id', 'feed_type'];

    public function user() { return $this->belongsTo(User::class); }
    public function admin_feed() { return $this->belongsTo(PopFeeds::class, 'feed_id'); }
    public function user_feed() { return $this->belongsTo(Feed::class, 'feed_id'); }
    public function history() { return $this->belongsTo(History::class, 'feed_id'); }
}
