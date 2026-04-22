<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class BackgroundFeed extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $collection = 'background_feeds';

    protected $fillable = ['name', 'image'];
}
