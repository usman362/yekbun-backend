<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class ZarokStories extends Model
{
    
    use UsesLegacyId;
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
