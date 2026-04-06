<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class NewsCategory extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'news_categories';

    protected $fillable = ['name', 'status'];

    public function news() { return $this->hasMany(News::class, 'category_id'); }
}
