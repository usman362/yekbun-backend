<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class Language extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'languages';

    protected $guarded = [];

    public function translations() { return $this->hasMany(Translation::class, 'language_id'); }
}
