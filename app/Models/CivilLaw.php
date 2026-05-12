<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class CivilLaw extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'civil_laws';
    protected $guarded = [];
}
