<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class VideoClipViews extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'video_clip_views';

    protected $fillable = ['user_id', 'artist_id'];
}
