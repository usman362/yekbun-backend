<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Event extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'events';

    protected $fillable = [
        'title', 'description', 'event_category_id', 'user_id', 'user_type',
        'start_date', 'start_time', 'end_time', 'country', 'state', 'city',
        'zipcode', 'address', 'image', 'sound', 'status',
        'promoter_first_name', 'promoter_last_name', 'promoter_email',
        'promoter_phone_number', 'promoter_rojava_name', 'promoter_rojava_id',
        'ticket_sale', 'online_sale', 'price', 'price_male', 'price_male_notification',
        'price_female', 'price_female_notification', 'price_kids', 'price_kids_notification',
    ];

    protected $casts = [
        'start_time' => 'datetime:Y-m-d',
        'end_time' => 'datetime:Y-m-d',
    ];

    public function category() { return $this->belongsTo(EventCategory::class, 'event_category_id'); }
    public function tickets() { return $this->hasMany(Ticket::class); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
    public function ticketSales() { return $this->hasMany(TicketSale::class, 'event_id'); }
}
