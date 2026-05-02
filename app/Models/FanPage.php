<?php

namespace App\Models;

//use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FanPage extends Model
{
    
    use UsesLegacyId;
use HasFactory;
    
    protected $fillable=[
        'user_id',
        'fanpage_name',
        'category_id',
        'status',
    ];

    public function category()
    {
        return $this->belongsTo(FanPageType::class, 'category_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
