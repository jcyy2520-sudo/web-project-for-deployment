<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * WebSocketService - Real-time bidirectional communication
 * 
 * Handles WebSocket connections, subscriptions, and broadcasting
 * Replaces SSE with true bidirectional real-time communication
 */
class WebSocketService
{
    private const ACTIVE_CONNECTIONS_KEY = 'ws:active_connections';
    private const USER_SUBSCRIPTIONS_KEY = 'ws:user_subscriptions:';
    private const CONVERSATION_SUBSCRIBERS_KEY = 'ws:conversation_subscribers:';
    private const CONNECTION_TIMEOUT = 3600; // 1 hour

    /**
     * Register a WebSocket connection
     */
    public function registerConnection(string $connectionId, int $userId, string $sessionId): void
    {
        try {
            $connectionData = [
                'connection_id' => $connectionId,
                'user_id' => $userId,
                'session_id' => $sessionId,
                'connected_at' => now()->toDateTimeString(),
                'last_activity' => now()->toDateTimeString(),
            ];

            // Store connection in cache
            Cache::put(
                "ws:connection:{$connectionId}",
                $connectionData,
                now()->addSeconds(self::CONNECTION_TIMEOUT)
            );

            // Add to active connections list
            Cache::push(
                self::ACTIVE_CONNECTIONS_KEY,
                $connectionId
            );

            // Subscribe user to their own channel
            $this->subscribeToChannel($userId, "user:{$userId}", $connectionId);

            Log::info("WebSocket connected: {$connectionId} for user {$userId}");
        } catch (\Exception $e) {
            Log::error('Failed to register WebSocket connection: ' . $e->getMessage());
        }
    }

    /**
     * Unregister a WebSocket connection
     */
    public function unregisterConnection(string $connectionId): void
    {
        try {
            $connectionData = Cache::get("ws:connection:{$connectionId}");
            
            if ($connectionData) {
                $userId = $connectionData['user_id'];
                
                // Remove from active connections
                $activeConnections = Cache::get(self::ACTIVE_CONNECTIONS_KEY, []);
                Cache::put(
                    self::ACTIVE_CONNECTIONS_KEY,
                    array_diff($activeConnections, [$connectionId]),
                    now()->addSeconds(self::CONNECTION_TIMEOUT)
                );

                // Clean up subscriptions
                $this->unsubscribeFromAll($connectionId);

                // Delete connection data
                Cache::forget("ws:connection:{$connectionId}");

                Log::info("WebSocket disconnected: {$connectionId}");
            }
        } catch (\Exception $e) {
            Log::error('Failed to unregister WebSocket connection: ' . $e->getMessage());
        }
    }

    /**
     * Subscribe connection to a channel
     */
    public function subscribeToChannel(int $userId, string $channel, string $connectionId): void
    {
        try {
            $subscriptionKey = self::USER_SUBSCRIPTIONS_KEY . $userId;
            $subscriptions = Cache::get($subscriptionKey, []);
            
            if (!isset($subscriptions[$channel])) {
                $subscriptions[$channel] = [];
            }
            
            if (!in_array($connectionId, $subscriptions[$channel])) {
                $subscriptions[$channel][] = $connectionId;
            }

            Cache::put(
                $subscriptionKey,
                $subscriptions,
                now()->addSeconds(self::CONNECTION_TIMEOUT)
            );

            Log::debug("Connection {$connectionId} subscribed to {$channel}");
        } catch (\Exception $e) {
            Log::error('Failed to subscribe to channel: ' . $e->getMessage());
        }
    }

    /**
     * Subscribe to a conversation channel
     */
    public function subscribeToConversation(string $conversationId, int $userId, string $connectionId): void
    {
        try {
            $subscriberKey = self::CONVERSATION_SUBSCRIBERS_KEY . $conversationId;
            $subscribers = Cache::get($subscriberKey, []);
            
            if (!isset($subscribers[$userId])) {
                $subscribers[$userId] = [];
            }
            
            if (!in_array($connectionId, $subscribers[$userId])) {
                $subscribers[$userId][] = $connectionId;
            }

            Cache::put(
                $subscriberKey,
                $subscribers,
                now()->addSeconds(self::CONNECTION_TIMEOUT)
            );

            Log::debug("User {$userId} subscribed to conversation {$conversationId}");
        } catch (\Exception $e) {
            Log::error('Failed to subscribe to conversation: ' . $e->getMessage());
        }
    }

    /**
     * Broadcast a message to a channel
     */
    public function broadcast(string $channel, array $data): void
    {
        try {
            // Add metadata
            $data['broadcast_at'] = now()->toDateTimeString();
            $data['broadcast_id'] = Str::uuid()->toString();

            // Parse channel to get user ID if applicable
            if (str_starts_with($channel, 'user:')) {
                $userId = (int)str_replace('user:', '', $channel);
                $subscriptionKey = self::USER_SUBSCRIPTIONS_KEY . $userId;
                $subscriptions = Cache::get($subscriptionKey, []);

                if (isset($subscriptions[$channel])) {
                    foreach ($subscriptions[$channel] as $connectionId) {
                        $this->sendToConnection($connectionId, $data);
                    }
                }
            } elseif (str_starts_with($channel, 'conversation:')) {
                $conversationId = str_replace('conversation:', '', $channel);
                $subscriberKey = self::CONVERSATION_SUBSCRIBERS_KEY . $conversationId;
                $subscribers = Cache::get($subscriberKey, []);

                foreach ($subscribers as $connectionIds) {
                    foreach ($connectionIds as $connectionId) {
                        $this->sendToConnection($connectionId, $data);
                    }
                }
            }

            Log::debug("Broadcasted to channel: {$channel}");
        } catch (\Exception $e) {
            Log::error('Failed to broadcast message: ' . $e->getMessage());
        }
    }

    /**
     * Send a message to a specific connection
     */
    public function sendToConnection(string $connectionId, array $data): void
    {
        try {
            // Store message in queue for delivery
            Cache::push(
                "ws:queue:{$connectionId}",
                [
                    'data' => $data,
                    'queued_at' => now()->toDateTimeString(),
                ]
            );

            // Update last activity
            $connectionData = Cache::get("ws:connection:{$connectionId}");
            if ($connectionData) {
                $connectionData['last_activity'] = now()->toDateTimeString();
                Cache::put(
                    "ws:connection:{$connectionId}",
                    $connectionData,
                    now()->addSeconds(self::CONNECTION_TIMEOUT)
                );
            }
        } catch (\Exception $e) {
            Log::error("Failed to send to connection {$connectionId}: " . $e->getMessage());
        }
    }

    /**
     * Broadcast to all conversation subscribers (for real-time updates)
     */
    public function broadcastToConversation(string $conversationId, array $data): void
    {
        $this->broadcast("conversation:{$conversationId}", $data);
    }

    /**
     * Broadcast to all user's connections
     */
    public function broadcastToUser(int $userId, array $data): void
    {
        $this->broadcast("user:{$userId}", $data);
    }

    /**
     * Broadcast a real-time data update
     */
    public function broadcastUpdate(string $type, array $payload): void
    {
        $this->broadcast('updates', [
            'type' => $type,
            'event' => 'data_update',
            'payload' => $payload,
        ]);
    }

    /**
     * Get active connections for a user
     */
    public function getUserConnections(int $userId): array
    {
        try {
            $subscriptionKey = self::USER_SUBSCRIPTIONS_KEY . $userId;
            $subscriptions = Cache::get($subscriptionKey, []);

            $connections = [];
            if (isset($subscriptions["user:{$userId}"])) {
                foreach ($subscriptions["user:{$userId}"] as $connectionId) {
                    $connectionData = Cache::get("ws:connection:{$connectionId}");
                    if ($connectionData) {
                        $connections[] = $connectionData;
                    }
                }
            }

            return $connections;
        } catch (\Exception $e) {
            Log::error('Failed to get user connections: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get conversation subscribers
     */
    public function getConversationSubscribers(string $conversationId): array
    {
        try {
            $subscriberKey = self::CONVERSATION_SUBSCRIBERS_KEY . $conversationId;
            $subscribers = Cache::get($subscriberKey, []);

            $result = [];
            foreach ($subscribers as $userId => $connectionIds) {
                $result[$userId] = count($connectionIds);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to get conversation subscribers: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Unsubscribe from all channels
     */
    public function unsubscribeFromAll(string $connectionId): void
    {
        try {
            $connectionData = Cache::get("ws:connection:{$connectionId}");
            if (!$connectionData) {
                return;
            }

            $userId = $connectionData['user_id'];
            $subscriptionKey = self::USER_SUBSCRIPTIONS_KEY . $userId;
            $subscriptions = Cache::get($subscriptionKey, []);

            foreach ($subscriptions as $channel => &$connectionIds) {
                $connectionIds = array_diff($connectionIds, [$connectionId]);
            }

            Cache::put(
                $subscriptionKey,
                $subscriptions,
                now()->addSeconds(self::CONNECTION_TIMEOUT)
            );
        } catch (\Exception $e) {
            Log::error('Failed to unsubscribe from all channels: ' . $e->getMessage());
        }
    }

    /**
     * Get pending messages for a connection
     */
    public function getPendingMessages(string $connectionId): array
    {
        try {
            $queueKey = "ws:queue:{$connectionId}";
            $messages = Cache::get($queueKey, []);
            
            // Clear the queue
            Cache::forget($queueKey);
            
            return $messages;
        } catch (\Exception $e) {
            Log::error("Failed to get pending messages: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Clean up expired connections
     */
    public function cleanupExpiredConnections(): void
    {
        try {
            $activeConnections = Cache::get(self::ACTIVE_CONNECTIONS_KEY, []);

            foreach ($activeConnections as $connectionId) {
                $connectionData = Cache::get("ws:connection:{$connectionId}");
                
                if (!$connectionData) {
                    $this->unregisterConnection($connectionId);
                }
            }

            Log::debug('Cleaned up expired WebSocket connections');
        } catch (\Exception $e) {
            Log::error('Failed to cleanup connections: ' . $e->getMessage());
        }
    }

    /**
     * Get connection statistics
     */
    public function getStatistics(): array
    {
        try {
            $activeConnections = Cache::get(self::ACTIVE_CONNECTIONS_KEY, []);
            $activeCount = count(array_filter($activeConnections, function ($id) {
                return Cache::has("ws:connection:{$id}");
            }));

            return [
                'active_connections' => $activeCount,
                'total_registered' => count($activeConnections),
                'timestamp' => now()->toDateTimeString(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get WebSocket statistics: ' . $e->getMessage());
            return [];
        }
    }
}
