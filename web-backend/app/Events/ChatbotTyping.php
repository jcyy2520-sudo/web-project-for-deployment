<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ChatbotTyping - Broadcast when chatbot is generating a response
 * 
 * Shows typing indicator to user while AI is processing
 */
class ChatbotTyping implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $userId;
    public string $conversationId;
    public bool $isTyping;

    public function __construct(int $userId, string $conversationId, bool $isTyping = true)
    {
        $this->userId = $userId;
        $this->conversationId = $conversationId;
        $this->isTyping = $isTyping;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.' . $this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'chatbot.typing';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'is_typing' => $this->isTyping,
        ];
    }
}
