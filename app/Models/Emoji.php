<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class Emoji extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'emojis';

    protected $fillable = ['name', 'image'];
}
