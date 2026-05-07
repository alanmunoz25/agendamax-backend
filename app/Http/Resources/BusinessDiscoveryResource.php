<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Business
 */
class BusinessDiscoveryResource extends JsonResource
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
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'address' => $this->address,
            'logo_url' => $this->logo_url,
            'sector' => $this->sector,
            'province' => $this->province,
            'country' => $this->country,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'services_count' => $this->whenCounted('services'),
            'employees_count' => $this->whenCounted('employees'),
            'distance_km' => $this->when(
                isset($this->resource->distance_km) && $this->resource->distance_km !== null,
                fn () => round((float) $this->resource->distance_km, 2)
            ),
        ];
    }
}
