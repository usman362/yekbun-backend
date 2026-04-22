<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class UserVideo extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'user_videos';

    protected $fillable = ['user_id', 'video', 'type', 'thumbnail'];
}
