<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class Conversation extends Model
{
    use UsesLegacyId;

    protected $connection = 'mongodb';
    protected $table = 'conversations';

    protected $fillable = [
        'type',            // 'private' | 'group'
        'name',            // group name (null for private)
        'image',           // group avatar (CDN path)
        'description',     // group description
        'created_by',      // user_id who created the conversation
        'pinned_message_id',
        'last_message_id',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function participants()
    {
        return $this->hasMany(ConversationParticipant::class, 'conversation_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'conversation_id');
    }

    public function lastMessage()
    {
        return $this->belongsTo(Message::class, 'last_message_id');
    }

    public function pinnedMessage()
    {
        return $this->belongsTo(Message::class, 'pinned_message_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
