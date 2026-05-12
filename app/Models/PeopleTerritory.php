<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class PeopleTerritory extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'people_territory';
    protected $guarded = [];
}
