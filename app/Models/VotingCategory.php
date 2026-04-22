<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class VotingCategory extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'voting_categories';

    protected $fillable = ['name'];
}
