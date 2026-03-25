<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Appointment
 */
class AppointmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_id' => $this->business_id,
            'client_id' => $this->client_id,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'scheduled_until' => $this->scheduled_until?->toIso8601String(),
            'status' => $this->status,
            'notes' => $this->notes,
            'cancellation_reason' => $this->when($this->status === 'cancelled', $this->cancellation_reason),
            'service' => $this->whenLoaded('service', fn () => $this->service ? new ServiceResource($this->service) : null),
            'employee' => $this->whenLoaded('employee', fn () => $this->employee ? new EmployeeResource($this->employee) : null),
            'services' => $this->whenLoaded('services', fn () => $this->services->map(fn ($service) => [
                'service_id' => $service->id,
                'name' => $service->name,
                'duration' => $service->duration,
                'price' => $service->price,
                'employee_id' => $service->pivot->employee_id,
            ])),
            'business' => new BusinessResource($this->whenLoaded('business')),
            'visit' => $this->whenLoaded('visit'),
        ];
    }
}
