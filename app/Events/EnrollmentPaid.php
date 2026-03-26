<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Enrollment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EnrollmentPaid implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Enrollment $enrollment
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('business.'.$this->enrollment->business_id),
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
            'enrollment' => [
                'id' => $this->enrollment->id,
                'course_id' => $this->enrollment->course_id,
                'customer_name' => $this->enrollment->customer_name,
                'customer_email' => $this->enrollment->customer_email,
                'status' => $this->enrollment->status,
                'payment_status' => $this->enrollment->payment_status,
                'amount_paid' => $this->enrollment->amount_paid,
                'course' => $this->enrollment->course ? [
                    'id' => $this->enrollment->course->id,
                    'title' => $this->enrollment->course->title,
                ] : null,
            ],
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'enrollment.paid';
    }
}
