<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class Message extends Model
{
    use UsesLegacyId;

    protected $connection = 'mongodb';
    protected $table = 'messages';

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'type',              // 'text' | 'image' | 'voice' | 'file' | 'system'
        'body',              // text content or system message description
        'media',             // array of { path, mime, size, duration?, thumbnail?, original_name? }
        'reply_to_id',       // message_id being replied to
        'forwarded_from_id', // original message_id if forwarded
        'deleted_for',       // array of user_ids who deleted "for me"
        'deleted_for_everyone', // bool
        'delivered_to',      // array of user_ids
        'read_by',           // array of { user_id, read_at }
    ];

    protected $casts = [
        'media'               => 'array',
        'deleted_for'         => 'array',
        'deleted_for_everyone' => 'boolean',
        'delivered_to'        => 'array',
        'read_by'             => 'array',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function replyTo()
    {
        return $this->belongsTo(Message::class, 'reply_to_id');
    }

    public function forwardedFrom()
    {
        return $this->belongsTo(Message::class, 'forwarded_from_id');
    }

    public function reactions()
    {
        return $this->hasMany(MessageReaction::class, 'message_id');
    }
}
