<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class Region extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'regions';

    protected $fillable = ['name', 'country_id', 'shortcode', 'status'];

    public function country() { return $this->belongsTo(Country::class); }
    public function cities() { return $this->hasMany(City::class); }
    public function users() { return $this->hasMany(User::class); }
    public function artists() { return $this->hasMany(Artist::class); }
}
