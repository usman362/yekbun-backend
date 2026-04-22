<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class Music extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'music';

    protected $fillable = ['name', 'category_id', 'artist_id', 'status'];

    public function category() { return $this->belongsTo(MusicCategory::class, 'category_id'); }
    public function artist() { return $this->belongsTo(Artist::class, 'artist_id'); }
    public function songs() { return $this->hasMany(Song::class, 'music_id'); }
}
