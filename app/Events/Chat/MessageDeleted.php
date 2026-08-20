<?php

namespace App\Events\Chat;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $conversationId,
        public string $messageId,
        public bool $forEveryone = false,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('chat.conversation.' . $this->conversationId)];
    }

    public function broadcastAs(): string
    {
        return 'message.deleted';
    }
}
