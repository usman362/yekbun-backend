<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class Translation extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'translations';

    protected $fillable = ['text_id', 'translation', 'language_id', 'language_code', 'keyword', 'translated'];

    public function language() { return $this->belongsTo(Language::class, 'language_id'); }
}
