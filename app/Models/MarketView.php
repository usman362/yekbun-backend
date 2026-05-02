<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
//use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\Model;

class MarketView extends Model
{
    use HasFactory;
    protected $fillable = [
        'mail_contact',
        'message',
        'phone',
        'phone',
        'address'
    ];
}
