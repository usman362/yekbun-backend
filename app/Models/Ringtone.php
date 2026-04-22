<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class Ringtone extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'ringtones';

    protected $fillable = ['fileName', 'filePath', 'fileSize', 'ringType'];
}
