<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class News extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'news';

    protected $fillable = [
        'title', 'description', 'user_type', 'image', 'image_type',
        'start_date', 'end_date', 'comments', 'voice_comments',
        'share', 'emotion', 'status'
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'image' => 'array',
    ];

    protected $attributes = [
        'image' => '[]'
    ];

    public function news_category() { return $this->belongsTo(NewsCategory::class, 'category_id'); }
    public function gallery() { return $this->hasMany(PostGallery::class); }
}
