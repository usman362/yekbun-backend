<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class Report extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'reports';

    protected $fillable = [
        'user_id', 'reported_user_id', 'reported_post_id',
        'reported_comment_id', 'reason', 'description',
        'status', 'action_taken',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function reportedUser() { return $this->belongsTo(User::class, 'reported_user_id'); }
}
