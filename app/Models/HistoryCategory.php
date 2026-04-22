<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class HistoryCategory extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $collection = 'history_categories';

    protected $fillable = ['name'];
}
