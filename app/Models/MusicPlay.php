<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class MusicPlay extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $collection = 'music_play';

    protected $fillable = ['music_id', 'user_id'];
}
