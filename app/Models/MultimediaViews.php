<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class MultimediaViews extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'multimedia_views';

    protected $fillable = ['user_id', 'media_id', 'media_type'];

    public function user() { return $this->belongsTo(User::class); }
}
