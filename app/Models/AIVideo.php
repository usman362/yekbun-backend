<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class AIVideo extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'ai_videos';

    protected $fillable = ['title', 'category_id', 'language', 'image', 'video'];

    protected $casts = [
        'image' => 'array',
        'video' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($ai_video) {
            $lastRecord = self::orderBy('created_at', 'desc')->first();
            $lastId = $lastRecord ? intval(substr($lastRecord->custom_id, 2)) : 99;
            $ai_video->custom_id = 'AI-' . ($lastId + 1);
        });
    }

    public function gallery() { return $this->hasMany(PostGallery::class); }
    public function comments()
    {
        return $this->hasMany(FeedComments::class, 'feed_id')
            ->where('feed_type', 'ai_videos')
            ->where('comment_type', 'normal');
    }
    public function voice_comments()
    {
        return $this->hasMany(FeedComments::class, 'feed_id')
            ->where('feed_type', 'ai_videos')
            ->where('comment_type', 'audio');
    }
    public function shares() { return $this->hasMany(FeedShare::class, 'feed_id'); }
    public function likes() { return $this->hasMany(FeedLikes::class, 'feed_id'); }
    public function views() { return $this->hasMany(FeedViews::class, 'feed_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
