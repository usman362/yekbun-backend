<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class MessageReaction extends Model
{
    use UsesLegacyId;

    protected $connection = 'mongodb';
    protected $table = 'message_reactions';

    protected $fillable = [
        'message_id',
        'user_id',
        'emoji',
    ];

    public function message()
    {
        return $this->belongsTo(Message::class, 'message_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
