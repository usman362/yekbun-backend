<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class FeedReason extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'feed_reasons';

    protected $fillable = ['title', 'reason'];
}
