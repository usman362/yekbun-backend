<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class History extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $collection = 'histories';

    protected $fillable = ['title', 'category_id', 'language', 'image', 'video'];

    protected $casts = [
        'image' => 'array',
        'video' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($history) {
            $lastHistory = self::orderBy('created_at', 'desc')->first();
            $lastId = $lastHistory ? intval(substr($lastHistory->custom_id, 2)) : 99;
            $history->custom_id = 'HS-' . ($lastId + 1);
        });
    }

    public function history_category() { return $this->belongsTo(HistoryCategory::class, 'category_id'); }
    public function gallery() { return $this->hasMany(PostGallery::class); }
    public function comments() { return $this->hasMany(FeedComments::class)->where('feed_type', 'history'); }
    public function voice_comments() { return $this->hasMany(FeedComments::class, 'feed_id')->where('comment_type', 'audio'); }
    public function shares() { return $this->hasMany(FeedShare::class, 'feed_id'); }
    public function likes() { return $this->hasMany(FeedLikes::class, 'feed_id'); }
    public function views() { return $this->hasMany(FeedViews::class, 'feed_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
