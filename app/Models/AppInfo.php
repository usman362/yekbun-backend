<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class AppInfo extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'app_info';

    protected $fillable = [
        'city_zipcode', 'zipcode', 'company_name',
        'address', 'house_number', 'description'
    ];
}
