<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Jenssegers\Mongodb\Eloquent\Model;

class DeletionCards extends Model
{
    use HasFactory;

    protected $collection = 'deletion_cards';

    // Add the $fillable property to allow mass assignment
    protected $fillable = [
        'card_id', 
        'reason_id', 
        'reason_title', 
        'reason_description' 
        
    ];
}
