<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class Artist extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $collection = 'artists';

    protected $fillable = ['name', 'city', 'dob', 'gender', 'image', 'status', 'province_id', 'total_views'];

    public function songs() { return $this->hasMany(Song::class, 'artist_id'); }
    public function videos() { return $this->hasMany(VideoClip::class, 'artist_id'); }
    public function province() { return $this->belongsTo(Region::class, 'province_id'); }
}
