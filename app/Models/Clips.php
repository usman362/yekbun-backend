<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Clips extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'clips';

    protected $guarded = [];

    public function template() { return $this->belongsTo(ClipTemplates::class, 'template_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
    public function views() { return $this->hasMany(ClipsViews::class, 'clip_id'); }
    public function likes() { return $this->hasMany(ClipsLikes::class, 'clip_id'); }
}
