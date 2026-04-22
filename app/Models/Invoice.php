<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class Invoice extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $collection = 'invoices';
    protected $guarded = [];
}
