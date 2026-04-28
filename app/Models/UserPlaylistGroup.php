<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class UserPlaylistGroup extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'user_playlist_groups';

    protected $fillable = ['user_id', 'title', 'bg_image', 'type', 'limit'];

    public function playlists() { return $this->hasMany(UserPlaylist::class, 'playlist_id')->where('type', 'audio'); }

    public function clip_playlists() { return $this->hasMany(UserPlaylist::class, 'playlist_id')->where('type', 'video'); }
}
