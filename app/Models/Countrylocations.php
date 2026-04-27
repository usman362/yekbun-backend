<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Countrylocations extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'countries';

    public function cities()
    {
        return $this->hasMany(Citylocations::class, 'country_id', 'conid');
    }

    public function states()
    {
        return $this->hasMany(Stateslocations::class, 'country_id', 'conid');
    }
}
