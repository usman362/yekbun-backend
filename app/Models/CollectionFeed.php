<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class CollectionFeed extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'collection_feeds';

    protected $fillable = ['collection_id', 'feed_id'];
}
