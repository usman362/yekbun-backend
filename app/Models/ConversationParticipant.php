<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class ConversationParticipant extends Model
{
    use UsesLegacyId;

    protected $connection = 'mongodb';
    protected $table = 'conversation_participants';

    protected $fillable = [
        'conversation_id',
        'user_id',
        'role',              // 'member' | 'admin' | 'owner'
        'nickname',          // optional display name override in group
        'muted_until',       // null = not muted, datetime = muted until
        'last_read_at',      // tracks read receipts
        'joined_at',
        'removed_at',        // soft-remove from group without deleting history
    ];

    protected $casts = [
        'muted_until'  => 'datetime',
        'last_read_at' => 'datetime',
        'joined_at'    => 'datetime',
        'removed_at'   => 'datetime',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isActive(): bool
    {
        return is_null($this->removed_at);
    }

    public function isMuted(): bool
    {
        return $this->muted_until && $this->muted_until->isFuture();
    }
}
