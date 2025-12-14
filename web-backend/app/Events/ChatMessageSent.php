<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ChatMessageSent - Broadcast when a chat message is sent
 * 
 * Enables real-time chat updates via WebSocket
 */
class ChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $userId;
    public string $conversationId;
    public string $role;
    public string $message;
    public array $meta;
    public string $timestamp;

    public function __construct(
        int $userId,
        string $conversationId,
        string $role,
        string $message,
        array $meta = []
    ) {
        $this->userId = $userId;
        $this->conversationId = $conversationId;
        $this->role = $role;
        $this->message = $message;
        $this->meta = $meta;
        $this->timestamp = now()->toIso8601String();
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.' . $this->userId),
            new PrivateChannel('conversation.' . $this->conversationId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'role' => $this->role,
            'message' => $this->message,
            'meta' => $this->meta,
            'timestamp' => $this->timestamp,
        ];
    }
}
