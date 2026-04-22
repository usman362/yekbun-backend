<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class SosPopup extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $collection = 'sos_popups';

    protected $fillable = ['user_id', 'sos_id'];

    public function user() { return $this->belongsTo(User::class, 'user_id'); }
    public function sos() { return $this->belongsTo(PopFeeds::class, 'sos_id', '_id')->where('type', 'SOS'); }
}
