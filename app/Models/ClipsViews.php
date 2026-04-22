<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class ClipsViews extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $collection = 'clips_views';

    protected $guarded = [];

    public function clip() { return $this->belongsTo(Clips::class, 'clip_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
}
