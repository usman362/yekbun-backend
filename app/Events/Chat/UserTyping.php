<?php

namespace App\Events\Chat;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserTyping implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $conversationId,
        public string $userId,
        public string $userName,
        public bool $isTyping = true,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('chat.conversation.' . $this->conversationId)];
    }

    public function broadcastAs(): string
    {
        return 'user.typing';
    }
}
