<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Jenssegers\Mongodb\Eloquent\Model;

class ZarokMovies extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $table = 'zarok_movies';

    protected $fillable = [
        'name',
        'date',
        'description',
        'banner',
        'label',
        'is_hd',
        'is_4k',
        'is_uhd',
        'is_qhd',
        'is_atm',
        'is_v5',
        'age_section',
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
