<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class Collection extends Model
{
    use UsesLegacyId;

    protected $connection = 'mongodb';
    protected $table = 'collections';

    protected $fillable = ['title', 'image', 'user_id', 'visibility'];

    public function feeds()
    {
        return $this->belongsToMany(Feed::class, 'collection_feeds', 'collection_id', 'feed_id');
    }

    /** Pivot rows — same pattern as UserPlaylist on a playlist group. */
    public function items()
    {
        return $this->hasMany(CollectionFeed::class, 'collection_id');
    }
}
