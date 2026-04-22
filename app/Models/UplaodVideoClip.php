<?php

namespace App\Models;

use Spatie\Activitylog\LogOptions;
//use Illuminate\Database\Eloquent\Model;
use Jenssegers\Mongodb\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UplaodVideoClip extends Model
{
    use HasFactory , LogsActivity;
    protected $fillable=[
        'title',
        'category_id',
        'video'
    ];
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }
    public function artist(){
        return $this->belongsTo(Artist::class, 'category_id');
    }
}
