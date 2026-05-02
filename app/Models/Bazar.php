<?php

namespace App\Models;

//use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Bazar extends Model
{
    
    use UsesLegacyId;
use HasFactory;
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
