<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class ReportFeeds extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'report_feeds';

    protected $fillable = ['user_id', 'report_type', 'feed_id', 'status'];

    public function user() { return $this->belongsTo(User::class); }
    public function feed() { return $this->belongsTo(Feed::class); }
}
