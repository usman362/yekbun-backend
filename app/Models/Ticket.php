<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class Ticket extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $collection = 'tickets';

    protected $fillable = [
        'event_id', 'name', 'description', 'price',
        'price_male', 'price_female', 'price_kids',
        'quantity', 'total_sales', 'start_sale', 'end_sale', 'status',
    ];

    public function event() { return $this->belongsTo(Event::class); }
}
