<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class EventCategory extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $collection = 'event_categories';

    protected $fillable = ['name', 'status'];

    public function events() { return $this->hasMany(Event::class); }
}
