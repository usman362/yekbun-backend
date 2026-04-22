<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class ProfileBanner extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'profile_banners';

    protected $fillable = ['image', 'status'];
}
