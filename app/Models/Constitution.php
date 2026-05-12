<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class Constitution extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'constitutions';
    protected $guarded = [];
}
