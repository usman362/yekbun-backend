<?php

namespace App\Models;

//use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UploadVideoCategory extends Model
{
    use HasFactory ;
    protected $fillable=[
        'category'
    ];
}
