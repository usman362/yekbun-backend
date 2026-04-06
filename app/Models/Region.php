<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Region extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'regions';

    protected $fillable = ['name', 'country_id', 'shortcode', 'status'];

    public function country() { return $this->belongsTo(Country::class); }
    public function cities() { return $this->hasMany(City::class); }
    public function users() { return $this->hasMany(User::class); }
    public function artists() { return $this->hasMany(Artist::class); }
}
