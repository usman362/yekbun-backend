<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class PolicyAndTerm extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'policy_and_terms';

    protected $fillable = ['name', 'privacy_policy', 'disclaimer'];
}
