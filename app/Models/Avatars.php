<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class Avatars extends Model
{
    
    use UsesLegacyId;
protected $connection = 'mongodb';
}