<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class StoryTime extends Model
{
    
    use UsesLegacyId;
protected $connection = 'mongodb';
    use HasFactory;

    protected $fillable = [
          'length',  // Add this field for storing story time length
        'is_active'
    ];

    
}