<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'sender_id',
        'receiver_id',
        'message',
        'read',
        'subject',
        'type',
        'reply_to_message_id',
        'replies_count',
        'conversation_id'
    ];

    protected $casts = [
        'read' => 'boolean'
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function replyToMessage()
    {
        return $this->belongsTo(Message::class, 'reply_to_message_id');
    }

    public function replies()
    {
        return $this->hasMany(Message::class, 'reply_to_message_id');
    }

    /**
     * Scope to get chatbot messages
     */
    public function scopeChatbot($query)
    {
        return $query->where('type', 'chatbot');
    }

    /**
     * Scope to get messages by conversation
     */
    public function scopeConversation($query, $conversationId)
    {
        return $query->where('conversation_id', $conversationId);
    }
}