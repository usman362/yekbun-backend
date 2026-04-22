<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class Cart extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $collection = 'carts';
    protected $guarded = [];
}
