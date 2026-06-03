<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Log;
use Throwable;

class TimeLogCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    /**
     * Create a new event instance.
     */
    public function __construct(public int $id, public string $scanned_time, public string $employee_id) {}

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        Log::info("Background worker: Starting broadcast for TimeLog #{$this->id} to Soketi.");

        return [ // @todo Add here the data needed to be passed to the frontend
            'id' => $this->id,
            'scanned_time' => $this->scanned_time,
            'employee_id' => $this->employee_id,
        ];
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('timelogs'),
        ];
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('The broadcast background worker failed: '.$exception->getMessage());
    }
}
