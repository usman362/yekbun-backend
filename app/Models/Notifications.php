<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class Notifications extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $collection = 'notifications';

    protected $guarded = [];
}
