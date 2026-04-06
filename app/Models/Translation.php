<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Translation extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'translations';

    protected $fillable = ['text_id', 'translation', 'language_id', 'language_code', 'keyword', 'translated'];

    public function language() { return $this->belongsTo(Language::class, 'language_id'); }
}
