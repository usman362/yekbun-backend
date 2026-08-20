<?php

use App\Models\ConversationParticipant;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('chat.conversation.{conversationId}', function ($user, $conversationId) {
    $participant = ConversationParticipant::where('conversation_id', (string) $conversationId)
        ->where('user_id', (string) $user->getKey())
        ->whereNull('removed_at')
        ->first();

    return $participant !== null;
});

Broadcast::channel('chat.user.{userId}', function ($user, $userId) {
    return (string) $user->getKey() === (string) $userId;
});
