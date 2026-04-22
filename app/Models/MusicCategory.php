<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class MusicCategory extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'music_categories';

    protected $fillable = ['name', 'status'];
}
