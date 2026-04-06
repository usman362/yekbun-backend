<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class BackgroundFeed extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'background_feeds';

    protected $fillable = ['name', 'image'];
}
