<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
//use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class FavouriteArtist extends Model
{
    
    use UsesLegacyId;
use HasFactory;
    public $fillable = [
        'user_id',
        'artist_id'
    ];
    protected $casts = ['artist_id' => 'array'];
    // protected $attributes = ['artist_id' => '[]' ];
}
