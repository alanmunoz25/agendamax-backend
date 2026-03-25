<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Appointment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AppointmentCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Appointment $appointment
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('business.'.$this->appointment->business_id),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'appointment' => [
                'id' => $this->appointment->id,
                'service_id' => $this->appointment->service_id,
                'employee_id' => $this->appointment->employee_id,
                'client_id' => $this->appointment->client_id,
                'scheduled_at' => $this->appointment->scheduled_at->toIso8601String(),
                'status' => $this->appointment->status,
                'client' => [
                    'id' => $this->appointment->client->id,
                    'name' => $this->appointment->client->name,
                    'email' => $this->appointment->client->email,
                ],
                'service' => $this->appointment->service ? [
                    'id' => $this->appointment->service->id,
                    'name' => $this->appointment->service->name,
                    'duration' => $this->appointment->service->duration,
                ] : null,
                'employee' => $this->appointment->employee ? [
                    'id' => $this->appointment->employee->id,
                    'name' => $this->appointment->employee->user->name,
                ] : null,
            ],
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'appointment.created';
    }
}
