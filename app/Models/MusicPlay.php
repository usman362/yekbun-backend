<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class MusicPlay extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'music_play';

    protected $fillable = ['music_id', 'user_id'];
}
