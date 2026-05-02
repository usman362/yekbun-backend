<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class Avatars_Feed extends Model
{
    
    use UsesLegacyId;
use HasFactory;

    protected $table = 'avatars_feeds';

    protected $fillable = [
        'avatar_Id',
        'title',
        'image',
        'content',
        'forwards',
        'comments',
        'likes'
    ];
}
