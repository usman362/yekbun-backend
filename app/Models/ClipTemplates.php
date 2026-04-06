<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class ClipTemplates extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'clips_templates';

    protected $guarded = [];

    public function clips() { return $this->hasMany(Clips::class, 'template_id'); }
}
