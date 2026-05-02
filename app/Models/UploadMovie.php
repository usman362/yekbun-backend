<?php

namespace App\Models;

//use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UploadMovie extends Model
{
    use HasFactory ;
    protected $fillable=[
        'title',
        'thumbnail',
        'description',
        'video',
        'category_id'
    ];
    protected $casts = [
        'movie' => 'array'
     ];
     protected $attributes = [
        'movie' => '[]'
     ];

    public function moviecategory(){
        return $this->belongsTo(UploadMovieCategory::class , 'category_id');
    }
}
