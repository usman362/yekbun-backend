<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class AppVersion extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'app_versions';

    protected $fillable = ['version_number'];
}
