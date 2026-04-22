<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class SongViews extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'song_views';
    protected $fillable = ['user_id', 'artist_id'];
}
