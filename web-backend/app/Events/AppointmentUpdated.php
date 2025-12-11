<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AppointmentUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $appointment;

    /**
     * Create a new event instance.
     */
    public function __construct($appointment)
    {
        $this->appointment = $appointment;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>|null
     */
    public function broadcastOn(): array
    {
        // Only broadcast if a real broadcasting driver is configured (not 'log' or 'null')
        $driver = config('broadcasting.default');
        if (in_array($driver, ['log', 'null', ''])) {
            return [];
        }

        return [new Channel('appointments')];
    }

    /**
     * Determine if this event should broadcast.
     */
    public function broadcastWhen(): bool
    {
        // Only broadcast if pusher is properly configured
        $driver = config('broadcasting.default');
        return !in_array($driver, ['log', 'null', '']);
    }

    /**
     * Data to broadcast with the event.
     */
    public function broadcastWith(): array
    {
        return [
            'appointment' => $this->appointment,
            'timestamp' => now()->toDateTimeString(),
        ];
    }

    /**
     * Broadcast event name used by clients
     */
    public function broadcastAs(): string
    {
        return 'AppointmentUpdated';
    }
}
