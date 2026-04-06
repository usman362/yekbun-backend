<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Reaction extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'reactions';

    protected $fillable = ['user_id', 'emoji_id', 'feed_id', 'news_id', 'history_id', 'vote_id', 'music_id'];

    public function feed() { return $this->belongsTo(Feed::class); }
}
