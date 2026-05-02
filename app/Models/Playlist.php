<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
//use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class Playlist extends Model
{
    
    use UsesLegacyId;
use HasFactory;
            protected $fillable = [
                "user_id",
                "playlist_name",
                "visibility",
                "is_music",
                "is_feed",
                "is_vote",
                "is_news",
                "is_history"
            ];

    public function PlaylistMusics(){
        return $this->hasOne(PlaylistMusic::class);
    }
}
