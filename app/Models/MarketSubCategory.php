<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
//use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\Model;

class MarketSubCategory extends Model
{
    use HasFactory;
        
    protected $fillable = [
        'mail_contact',
        'message'
    ];
}
