<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class ClipsLikes extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'clips_likes';

    protected $guarded = [];

    public function clip() { return $this->belongsTo(Clips::class, 'clip_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
}
