<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class Country extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $collection = 'countries_orig';

    protected $fillable = [
        'name', 'code', 'flag_path', 'image_path',
        'icon_code', 'capital_id', 'language_id', 'status',
    ];

    public function regions() { return $this->hasMany(Region::class, 'country_id', '_id'); }
    public function cities() { return $this->hasMany(City::class, 'country_id', '_id'); }
    public function capital() { return $this->hasOne(City::class, 'capital_id', '_id'); }
    public function users() { return $this->hasMany(User::class); }
}
