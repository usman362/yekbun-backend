<?php

namespace App\Models;

use Spatie\Activitylog\LogOptions;
//use Illuminate\Database\Eloquent\Model;
use Jenssegers\Mongodb\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SubCategoryBazar extends Model
{
    use HasFactory , LogsActivity;
    
    protected $fillable=[
        'category_id',
        'name',
        'city',
        'state'
    ];
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }
    public function bazar_category(){
        return $this->belongsTo(BazarCategory::class , 'category_id');
    }

    public function category(){
        return $this->belongsTo(BazarCategory::class , 'category_id' , 'id' , 'bazar_categories');
    }
}
