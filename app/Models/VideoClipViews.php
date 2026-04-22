<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class VideoClipViews extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $collection = 'video_clip_views';

    protected $fillable = ['user_id', 'artist_id'];
}
