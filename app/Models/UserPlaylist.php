<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class UserPlaylist extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'user_playlists';

    protected $fillable = ['playlist_id', 'user_id', 'media_id', 'type'];

    public function song() { return $this->belongsTo(Song::class, 'media_id'); }
    public function video() { return $this->belongsTo(VideoClip::class, 'media_id'); }
}
