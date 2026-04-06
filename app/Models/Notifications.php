<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Notifications extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'notifications';

    protected $guarded = [];
}
