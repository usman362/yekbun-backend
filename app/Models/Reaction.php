<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class Reaction extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'reactions';

    protected $fillable = ['user_id', 'emoji_id', 'feed_id', 'news_id', 'history_id', 'vote_id', 'music_id'];

    public function feed() { return $this->belongsTo(Feed::class); }
}
