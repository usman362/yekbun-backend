<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class PolicyAndTerm extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'policy_and_terms';

    protected $fillable = ['name', 'privacy_policy', 'disclaimer'];
}
