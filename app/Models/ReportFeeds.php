<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class ReportFeeds extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'report_feeds';

    protected $fillable = ['user_id', 'report_type', 'feed_id', 'status'];

    public function user() { return $this->belongsTo(User::class); }
    public function feed() { return $this->belongsTo(Feed::class); }
}
