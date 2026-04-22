<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class ClipTemplates extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'clips_templates';

    protected $guarded = [];

    public function clips() { return $this->hasMany(Clips::class, 'template_id'); }
}
