<?php

namespace App\Models;

use Spatie\Activitylog\LogOptions;
//use Illuminate\Database\Eloquent\Model;
use Jenssegers\Mongodb\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BazarCategory extends Model
{
    use HasFactory, LogsActivity;
    
    protected $fillable=[
        'name'
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    public function bazarsubcategory(){
        return $this->hasMany(SubCategoryBazar::class , 'category_id');
    }

    public function sub_categories(){
        return $this->hasMany(SubCategoryBazar::class , 'category_id' , 'id' , 'sub_category_bazars');
    }
}
