<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ChatStreamToken - Broadcast individual tokens during streaming response
 * 
 * Enables real-time streaming of AI responses token by token
 */
class ChatStreamToken implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $userId;
    public string $conversationId;
    public string $token;
    public bool $isDone;
    public ?string $error;

    public function __construct(
        int $userId,
        string $conversationId,
        string $token = '',
        bool $isDone = false,
        ?string $error = null
    ) {
        $this->userId = $userId;
        $this->conversationId = $conversationId;
        $this->token = $token;
        $this->isDone = $isDone;
        $this->error = $error;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.' . $this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'stream.token';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'token' => $this->token,
            'done' => $this->isDone,
            'error' => $this->error,
        ];
    }
}
