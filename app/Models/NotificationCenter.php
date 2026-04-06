<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class NotificationCenter extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'notifications_center';

    protected $fillable = ['title', 'description', 'user_id', 'send_by_id', 'user_image', 'type', 'is_read', 'read_at'];

    public function user() { return $this->belongsTo(User::class, 'user_id'); }
    public function send_by() { return $this->belongsTo(User::class, 'send_by_id'); }
}
