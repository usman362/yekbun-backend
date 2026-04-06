<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class ArtistFavorite extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'artist_favorites';

    protected $fillable = ['user_id', 'artist_id'];
}
