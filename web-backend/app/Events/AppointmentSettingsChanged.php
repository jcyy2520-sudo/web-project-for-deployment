<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AppointmentSettingsChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $settings;
    public $action; // 'updated'
    public $timestamp;

    public function __construct($settings, $action = 'updated')
    {
        $this->settings = $settings;
        $this->action = $action;
        $this->timestamp = now()->toIso8601String();
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn()
    {
        return new Channel('appointment-settings');
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith()
    {
        return [
            'action' => $this->action,
            'data' => $this->settings,
            'timestamp' => $this->timestamp,
            'type' => 'AppointmentSettingsChanged'
        ];
    }

    /**
     * Get the name of the event to broadcast as.
     */
    public function broadcastAs()
    {
        return 'AppointmentSettingsChanged';
    }
}
