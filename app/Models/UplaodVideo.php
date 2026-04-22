<?php

namespace App\Models;

use Spatie\Activitylog\LogOptions;
//use Illuminate\Database\Eloquent\Model;
use Jenssegers\Mongodb\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UplaodVideo extends Model
{
    use HasFactory , LogsActivity;
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }
    public function videocategory(){
        return $this->belongsTo(UploadVideoCategory::class , 'category_id');
    }
}
