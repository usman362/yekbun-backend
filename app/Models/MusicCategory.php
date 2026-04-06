<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class MusicCategory extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'music_categories';

    protected $fillable = ['name', 'status'];
}
