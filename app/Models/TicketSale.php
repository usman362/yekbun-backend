<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class TicketSale extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'ticket_sales';

    protected $fillable = ['event_id', 'user_id', 'total'];
}
