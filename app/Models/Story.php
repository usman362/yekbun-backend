<?php

namespace App\Models;

//use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Story extends Model
{
    
    use UsesLegacyId;
use HasFactory ;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'media_path',
        'thumbnail_path',
        'duration',
        'app',
        'status'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
