<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class MediaCategory extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'media_categories';

    protected $fillable = ['name', 'status'];

    public function media() { return $this->hasMany(Media::class, 'category_id'); }
}
