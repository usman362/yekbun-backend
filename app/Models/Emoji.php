<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Emoji extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'emojis';

    protected $fillable = ['name', 'image'];
}
