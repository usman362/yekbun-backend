<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Song extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'songs';

    protected $fillable = ['name', 'category_id', 'music_id', 'artist_id', 'album_id', 'audio', 'file_size', 'length', 'status'];

    public function category() { return $this->belongsTo(MusicCategory::class, 'category_id'); }
    public function artist() { return $this->belongsTo(Artist::class, 'artist_id'); }
    public function playlists() { return $this->hasMany(UserPlaylist::class, 'media_id'); }
}
