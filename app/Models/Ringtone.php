<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Ringtone extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'ringtones';

    protected $fillable = ['fileName', 'filePath', 'fileSize', 'ringType'];
}
