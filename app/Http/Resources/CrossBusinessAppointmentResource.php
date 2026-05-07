<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps one business + its appointments for the cross-business history endpoint.
 *
 * Expected $this->resource shape:
 *   [
 *     'business'     => Business model instance (with pivot),
 *     'appointments' => Collection<Appointment>,
 *     'is_blocked'   => bool,
 *   ]
 */
class CrossBusinessAppointmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Business $business */
        $business = $this->resource['business'];

        /** @var \Illuminate\Support\Collection $appointments */
        $appointments = $this->resource['appointments'];

        return [
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'slug' => $business->slug,
                'logo_url' => $business->logo_url,
            ],
            'appointments' => AppointmentResource::collection($appointments),
            'total_count' => $appointments->count(),
            'is_blocked' => (bool) $this->resource['is_blocked'],
        ];
    }
}
