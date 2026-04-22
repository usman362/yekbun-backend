<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class Setting extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $collection = 'settings';

    protected $guarded = [];
}
