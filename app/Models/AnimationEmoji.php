<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class AnimationEmoji extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'animation_emojis';

    protected $fillable = ['emoji'];
}
