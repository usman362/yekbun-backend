<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class DeletionCards extends Model
{
    use HasFactory;

    protected $table = 'deletion_cards';

    // Add the $fillable property to allow mass assignment
    protected $fillable = [
        'card_id', 
        'reason_id', 
        'reason_title', 
        'reason_description' 
        
    ];
}
