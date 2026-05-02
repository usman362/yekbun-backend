<?php

namespace App\Models;

//use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UplaodVideo extends Model
{
    use HasFactory ;
    protected $fillable=[
        'title',
        'thumbnail',
        'description',
        'video',
        'category_id',
        'app'
    ];
    protected $casts = [
        'video' => 'array'
     ];
     protected $attributes = [
        'video' => '[]'
     ];
    public function videocategory(){
        return $this->belongsTo(UploadVideoCategory::class , 'category_id');
    }
}
