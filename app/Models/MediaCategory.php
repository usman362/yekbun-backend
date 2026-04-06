<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class MediaCategory extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'media_categories';

    protected $fillable = ['name', 'status'];

    public function media() { return $this->hasMany(Media::class, 'category_id'); }
}
