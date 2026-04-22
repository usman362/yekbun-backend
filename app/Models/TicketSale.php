<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class TicketSale extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $collection = 'ticket_sales';

    protected $fillable = ['event_id', 'user_id', 'total'];
}
