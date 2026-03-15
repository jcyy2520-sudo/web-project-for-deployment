<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'icon',
        'color',
        'related_id',
        'related_type',
        'data',
        'is_read',
        'is_sent',
        'read_at'
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'is_sent' => 'boolean',
        'read_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function markAsRead()
    {
        $this->update([
            'is_read' => true,
            'read_at' => now()
        ]);
    }

    public function markAsUnread()
    {
        $this->update([
            'is_read' => false,
            'read_at' => null
        ]);
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Send a notification to a specific user
     */
    public static function sendToUser($userId, $title, $message, $type = 'general', $data = null)
    {
        return self::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'data' => $data,
        ]);
    }

    /**
     * Send notification to all clients
     */
    public static function sendToAllClients($title, $message, $type = 'general', $data = null)
    {
        $clients = User::where('role', 'client')->where('is_active', true)->get();
        $notifications = [];

        foreach ($clients as $client) {
            $notifications[] = self::create([
                'user_id' => $client->id,
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'data' => $data,
            ]);
        }

        return $notifications;
    }
}
