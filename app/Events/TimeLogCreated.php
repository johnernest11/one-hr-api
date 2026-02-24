<?php

namespace App\Events;

use App\Models\DailyTimeRecords\TimeLog;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TimeLogCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public TimeLog $timeLog;

    /**
     * Create a new event instance.
     */
    public function __construct(TimeLog $timeLog)
    {
        $this->timeLog = $timeLog;
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [ // @todo Add here the data needed to be passed to the frontend
            'id' => $this->timeLog->id,
            'scanned_time' => $this->timeLog->scanned_time,
            'employee_id' => $this->timeLog->dailyTimeRecord->employee->agency_employee_no ?? $this->timeLog->dailyTimeRecord->employee->id_number,
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
}
