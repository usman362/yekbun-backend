<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class VideoPlay extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $collection = 'video_play';

    protected $fillable = ['user_id', 'video_id'];
}
