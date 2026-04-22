<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class NewsCategory extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'news_categories';

    protected $fillable = ['name', 'status'];

    public function news() { return $this->hasMany(News::class, 'category_id'); }
}
