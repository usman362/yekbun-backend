<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;
use Illuminate\Support\Facades\Storage;

class PostGallery extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $collection = 'post_galleries';

    protected $guarded = [];

    protected static function boot()
    {
        parent::boot();
        static::deleting(function ($gallery) {
            Storage::delete($gallery->media_url);
        });
    }

    public function news() { return $this->belongsTo(News::class); }
    public function history() { return $this->belongsTo(History::class); }
}
