<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class ProfileBanner extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'profile_banners';

    protected $fillable = ['image', 'status'];
}
