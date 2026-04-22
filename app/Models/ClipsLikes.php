<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class ClipsLikes extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'clips_likes';

    protected $guarded = [];

    public function clip() { return $this->belongsTo(Clips::class, 'clip_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
}
