<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class UserPlaylistGroup extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'user_playlist_groups';

    protected $fillable = ['user_id', 'title', 'bg_image', 'type', 'limit'];

    public function playlists() { return $this->hasMany(UserPlaylist::class, 'playlist_id'); }
}
