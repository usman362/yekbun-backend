<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Cities extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'cities';

    protected $fillable = ['name', 'country_id', 'region_id', 'zipcode', 'status'];

    public function country()
    {
        return $this->belongsTo(Countries::class, 'country_id', 'conid');
    }
}
