<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class FeedReason extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'feed_reasons';

    protected $fillable = ['title', 'reason'];
}
