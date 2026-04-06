<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class EventCategory extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'event_categories';

    protected $fillable = ['name', 'status'];

    public function events() { return $this->hasMany(Event::class); }
}
