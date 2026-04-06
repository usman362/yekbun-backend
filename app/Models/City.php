<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class City extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'cities_orig';

    protected $fillable = ['name', 'country_id', 'region_id', 'zipcode', 'status'];

    public function region() { return $this->belongsTo(Region::class); }
    public function country() { return $this->belongsTo(Country::class); }
    public function users() { return $this->hasMany(User::class); }
}
