<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class VideoClip extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'video_clips';

    protected $fillable = [
        'name', 'video_file_name', 'category_id', 'music_id', 'artist_id',
        'thumbnail', 'video', 'file_size', 'video_file_size', 'length',
        'video_file_length', 'status', 'custom_id',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($clip) {
            $last = self::orderBy('id', 'desc')->first();
            $lastId = $last ? intval(substr($last->custom_id, 3)) : 0;
            $clip->custom_id = 'VC-' . str_pad($lastId + 1, 3, '0', STR_PAD_LEFT);
        });
    }

    public function category() { return $this->belongsTo(MusicCategory::class, 'category_id'); }
    public function artist() { return $this->belongsTo(Artist::class, 'artist_id'); }
    public function playlists() { return $this->hasMany(UserPlaylist::class, 'media_id'); }
}
