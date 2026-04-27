<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Stateslocations extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'states';

    public function country()
    {
        return $this->belongsTo(Countrylocations::class, 'country_id', 'conid');
    }

    public function cities()
    {
        return $this->hasMany(Citylocations::class, 'stid', 'state_id');
    }
}
