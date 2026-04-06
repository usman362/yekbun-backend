<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class VotingCategory extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'voting_categories';

    protected $fillable = ['name'];
}
