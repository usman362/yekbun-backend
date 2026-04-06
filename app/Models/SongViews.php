<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class SongViews extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'song_views';
    protected $fillable = ['user_id', 'artist_id'];
}
