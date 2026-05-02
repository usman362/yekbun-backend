<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class ZarokSeries extends Model
{
    
    use UsesLegacyId;
use HasFactory;

    protected $connection = 'mongodb';
    protected $table = 'zarok_series';

    protected $fillable = [
        'name',
        'date',
        'description',
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


    public function seasons()
    {
        return $this->hasMany(ZarokSeriesSeason::class, 'series_id', '_id');
    }

    public function episodes()
    {
        return $this->hasMany(ZarokSeriesEpisode::class, 'series_id', '_id');
    }
    
}
