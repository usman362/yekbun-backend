<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class Stateslocations extends Model
{
    
    use UsesLegacyId;
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
