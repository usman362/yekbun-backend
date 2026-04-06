<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class UserImage extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'user_images';

    protected $fillable = ['user_id', 'image', 'type'];
}
