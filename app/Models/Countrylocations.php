<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class Countrylocations extends Model
{
    
    use UsesLegacyId;
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
