<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class ArtistFavorite extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $collection = 'artist_favorites';

    protected $fillable = ['user_id', 'artist_id'];
}
