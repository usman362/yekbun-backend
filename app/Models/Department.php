<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class Department extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $collection = 'departments';

    protected $fillable = ['name', 'thumbnail_path', 'parent_id'];

    public function departments() { return $this->hasMany(Department::class, 'parent_id'); }
    public function department() { return $this->hasOne(Department::class, '_id', 'parent_id'); }
}
