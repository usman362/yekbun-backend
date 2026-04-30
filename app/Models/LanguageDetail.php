<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class LanguageDetail extends Model
{
    use UsesLegacyId;

    protected $connection = 'mongodb';
    protected $table = 'language_details';

    protected $fillable = ['section_name', 'main_section', 'section_id', 'language_id', 'keyword', 'translated'];

    public function language()
    {
        return $this->belongsTo(Language::class);
    }
}
