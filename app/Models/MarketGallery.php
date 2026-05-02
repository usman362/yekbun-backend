<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
//use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\Model;

class MarketGallery extends Model
{
    use HasFactory;
    protected $fillable = [
        'gallery'
    ];
}
