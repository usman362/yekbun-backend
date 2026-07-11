<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class NotificationCenter extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'notifications_center';

    protected $fillable = [
        'title', 'description', 'user_id', 'send_by_id', 'user_image', 'type',
        'category', 'image', 'link', 'is_read', 'read_at',
        // Manual trigger pipeline (PDF): created → queued → sending → sent|failed → read
        'status', 'dedupe_key', 'related_id', 'related_type', 'sent_at',
    ];

    public function user() { return $this->belongsTo(User::class, 'user_id'); }
    public function send_by() { return $this->belongsTo(User::class, 'send_by_id'); }
}
