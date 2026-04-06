<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class FeedShare extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'feed_share';

    protected $fillable = ['user_id', 'feed_id', 'feed_type'];

    public function user() { return $this->belongsTo(User::class); }
}
