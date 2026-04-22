<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class Transaction extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $collection = 'transactions';
    protected $guarded = [];
}
