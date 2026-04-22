<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Jenssegers\Mongodb\Eloquent\Model;

class Avatars_Feed extends Model
{
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
