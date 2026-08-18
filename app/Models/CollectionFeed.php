<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class CollectionFeed extends Model
{
    use UsesLegacyId;

    protected $connection = 'mongodb';
    protected $table = 'collection_feeds';

    protected $fillable = ['collection_id', 'feed_id', 'user_id'];

    public function collection()
    {
        return $this->belongsTo(Collection::class, 'collection_id');
    }

    public function feed()
    {
        return $this->belongsTo(Feed::class, 'feed_id');
    }
}
