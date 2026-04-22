<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class FeedShare extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'feed_share';

    protected $fillable = ['user_id', 'feed_id', 'feed_type'];

    public function user() { return $this->belongsTo(User::class); }
}
