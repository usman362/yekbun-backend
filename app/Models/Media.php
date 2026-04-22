<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class Media extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'media';

    protected $fillable = ['title', 'category_id', 'images'];

    public function media_category() { return $this->belongsTo(MediaCategory::class, 'category_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
