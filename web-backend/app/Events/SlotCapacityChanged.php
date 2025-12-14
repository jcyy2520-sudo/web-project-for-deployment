<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SlotCapacityChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $timeSlotCapacity;
    public $action; // 'created', 'updated', 'deleted'
    public $affectedHours;
    public $timestamp;

    public function __construct($timeSlotCapacity, $action = 'updated', $affectedHours = [])
    {
        $this->timeSlotCapacity = $timeSlotCapacity;
        $this->action = $action;
        $this->affectedHours = $affectedHours;
        $this->timestamp = now()->toIso8601String();
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn()
    {
        return new Channel('slot-capacities');
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith()
    {
        return [
            'action' => $this->action,
            'data' => $this->timeSlotCapacity,
            'affected_hours' => $this->affectedHours,
            'timestamp' => $this->timestamp,
            'type' => 'SlotCapacityChanged'
        ];
    }

    /**
     * Get the name of the event to broadcast as.
     */
    public function broadcastAs()
    {
        return 'SlotCapacityChanged';
    }
}
