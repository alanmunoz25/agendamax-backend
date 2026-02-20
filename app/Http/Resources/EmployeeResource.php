<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Employee
 */
class EmployeeResource extends JsonResource
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
            'name' => $this->whenLoaded('user', fn () => $this->user->name),
            'photo_url' => $this->photo_url,
            'bio' => $this->bio,
            'is_active' => $this->is_active,
            'services' => $this->whenLoaded('services', function () {
                return $this->services->map(fn ($service) => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'price' => $service->price,
                    'duration' => $service->duration,
                ]);
            }),
        ];
    }
}
