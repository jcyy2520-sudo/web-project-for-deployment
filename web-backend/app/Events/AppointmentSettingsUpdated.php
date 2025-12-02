<?php

namespace App\Events;

use App\Models\AppointmentSettings;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AppointmentSettingsUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $settings;
    public $oldLimit;
    public $newLimit;
    public $timestamp;

    /**
     * Create a new event instance.
     */
    public function __construct(AppointmentSettings $settings, $oldLimit, $newLimit)
    {
        $this->settings = $settings;
        $this->oldLimit = $oldLimit;
        $this->newLimit = $newLimit;
        $this->timestamp = now();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('appointment-settings'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'settings-updated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->settings->id,
            'old_limit' => $this->oldLimit,
            'new_limit' => $this->newLimit,
            'is_active' => $this->settings->is_active,
            'description' => $this->settings->description,
            'updated_at' => $this->timestamp,
            'message' => "Appointment settings changed from {$this->oldLimit} to {$this->newLimit} bookings per day",
        ];
    }
}
