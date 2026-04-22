<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class AnimationEmoji extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'animation_emojis';

    protected $fillable = ['emoji'];
}
