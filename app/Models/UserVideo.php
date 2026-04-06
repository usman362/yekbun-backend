<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class UserVideo extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'user_videos';

    protected $fillable = ['user_id', 'video', 'type', 'thumbnail'];
}
