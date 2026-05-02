<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
//use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class PlaylistMusic extends Model
{
    
    use UsesLegacyId;
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
