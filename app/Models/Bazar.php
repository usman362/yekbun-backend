<?php

namespace App\Models;

use Spatie\Activitylog\LogOptions;
//use Illuminate\Database\Eloquent\Model;
use Jenssegers\Mongodb\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Bazar extends Model
{
    use HasFactory, LogsActivity;
    protected $fillable = [
        'title',
        'image',
        'category_id',
        'user_id',
        'price',
        'status',
        'warranty'
    ];
    protected $casts = [
        'image' => 'array'
     ];
     protected $attributes = [
        'image' => '[]'
     ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }
    
    // public function getImageAttribute($value)
    // {
    //     return json_decode($value);
    // }

    // public function setImageAttribute($value)
    // {
    //     $this->attributes['image'] = json_encode($value);
    // }
    public function bazar_category(){
        
        return $this->belongsTo(BazarCategory::class ,'category_id');
    }

    public function subcategory()
    {
        return $this->hasOne(SubCategoryBazar::class, 'id', 'subcategory_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
