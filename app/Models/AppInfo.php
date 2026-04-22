<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class AppInfo extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'app_info';

    protected $fillable = [
        'city_zipcode', 'zipcode', 'company_name',
        'address', 'house_number', 'description'
    ];
}
