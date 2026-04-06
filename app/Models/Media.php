<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Media extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'media';

    protected $fillable = ['title', 'category_id', 'images'];

    public function media_category() { return $this->belongsTo(MediaCategory::class, 'category_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
