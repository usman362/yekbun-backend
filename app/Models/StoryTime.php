<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Jenssegers\Mongodb\Eloquent\Model;

class StoryTime extends Model
{
    protected $connection = 'mongodb';
    use HasFactory;

    protected $fillable = [
          'length',  // Add this field for storing story time length
        'is_active'
    ];

    
}