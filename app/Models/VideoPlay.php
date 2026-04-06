<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class VideoPlay extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'video_play';

    protected $fillable = ['user_id', 'video_id'];
}
