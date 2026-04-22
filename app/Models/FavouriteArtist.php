<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
//use Illuminate\Database\Eloquent\Model;
use Jenssegers\Mongodb\Eloquent\Model;

class FavouriteArtist extends Model
{
    use HasFactory;
    public $fillable = [
        'user_id',
        'artist_id'
    ];
    protected $casts = ['artist_id' => 'array'];
    // protected $attributes = ['artist_id' => '[]' ];
}
