<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Language extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'languages';

    protected $guarded = [];

    public function translations() { return $this->hasMany(Translation::class, 'language_id'); }
}
