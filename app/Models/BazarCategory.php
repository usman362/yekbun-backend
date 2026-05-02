<?php

namespace App\Models;

//use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BazarCategory extends Model
{
    
    use UsesLegacyId;
use HasFactory;
    
    protected $fillable=[
        'name'
    ];

    public function bazarsubcategory(){
        return $this->hasMany(SubCategoryBazar::class , 'category_id');
    }

    public function sub_categories(){
        return $this->hasMany(SubCategoryBazar::class , 'category_id' , 'id' , 'sub_category_bazars');
    }
}
