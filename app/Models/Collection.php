<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class Collection extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $collection = 'collections';

    protected $fillable = ['title', 'image', 'user_id'];

    public function feeds() { return $this->belongsToMany(Feed::class, 'collection_feeds', 'collection_id', 'feed_id'); }
}
