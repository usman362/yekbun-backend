<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class HistoryCategory extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'history_categories';

    protected $fillable = ['name'];
}
