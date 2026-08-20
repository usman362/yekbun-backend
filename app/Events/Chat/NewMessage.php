<?php

namespace App\Events\Chat;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewMessage implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $message;
    public string $conversationId;

    public function __construct(Message $message)
    {
        $this->conversationId = (string) $message->conversation_id;
        $this->message = $this->transform($message);
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('chat.conversation.' . $this->conversationId)];
    }

    public function broadcastAs(): string
    {
        return 'message.new';
    }

    private function transform(Message $message): array
    {
        $message->load('sender');
        $sender = $message->sender;

        return [
            'id'              => (string) $message->getKey(),
            'conversation_id' => $this->conversationId,
            'sender_id'       => (string) $message->sender_id,
            'sender'          => $sender ? [
                'id'       => (string) $sender->getKey(),
                'name'     => $sender->name,
                'username' => $sender->username,
                'image'    => $sender->image,
            ] : null,
            'type'            => $message->type,
            'body'            => $message->body,
            'media'           => $message->media,
            'reply_to_id'     => $message->reply_to_id,
            'forwarded_from_id' => $message->forwarded_from_id,
            'created_at'      => $message->created_at?->toISOString(),
        ];
    }
}
