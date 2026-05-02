<?php

namespace App\Models;

//use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SubCategoryBazar extends Model
{
    
    use UsesLegacyId;
use HasFactory ;
    
    protected $fillable=[
        'category_id',
        'name',
        'city',
        'state'
    ];
    public function bazar_category(){
        return $this->belongsTo(BazarCategory::class , 'category_id');
    }

    public function category(){
        return $this->belongsTo(BazarCategory::class , 'category_id' , 'id' , 'bazar_categories');
    }
}
