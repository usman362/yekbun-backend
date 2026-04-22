<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
//use Illuminate\Database\Eloquent\Model;
use Jenssegers\Mongodb\Eloquent\Model;

class PlaylistMusic extends Model
{
    use HasFactory;

    public $fillable = [
        'playlist_id',
        'musics'
    ];

    public function playlist(){
        return $this->belongsTo(Playlist::class);
    }
    protected $casts = [
        'musics' => 'array'
     ];
     protected $attributes = [
        'musics' => '[]'
     ];

}
