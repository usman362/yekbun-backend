<?php

namespace App\Events\Chat;

use App\Models\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $data;

    public function __construct(
        public string $conversationId,
        public string $action, // 'member_added' | 'member_removed' | 'name_changed' | 'image_changed' | 'admin_changed'
        array $extra = [],
    ) {
        $this->data = array_merge(['action' => $action], $extra);
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('chat.conversation.' . $this->conversationId)];
    }

    public function broadcastAs(): string
    {
        return 'conversation.updated';
    }
}
