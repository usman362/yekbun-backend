<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Jenssegers\Mongodb\Eloquent\Model;

class ZarokStories extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $table = 'zarok_stories';

    protected $fillable = [
        'name',
        'video_file_name',
        'thumbnail',
        'video',
        'file_size',
        'video_file_size',
        'length',
        'video_file_length',
        'status',
    ];
}
