<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class UserImage extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'user_images';

    protected $fillable = ['user_id', 'image', 'type'];
}
