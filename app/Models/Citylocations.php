<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class Citylocations extends Model
{
    
    use UsesLegacyId;
protected $connection = 'mongodb';
    protected $table = 'cities';

    public function country()
    {
        return $this->belongsTo(Countrylocations::class, 'country_id', 'conid');
    }

    public function state()
    {
        return $this->belongsTo(Stateslocations::class, 'state_id', 'stid');
    }
}
