<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Business
 */
class BusinessPublicResource extends JsonResource
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
            'invitation_code' => $this->invitation_code,
            'sector' => $this->sector,
            'province' => $this->province,
            'country' => $this->country,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'services_count' => $this->whenCounted('services'),
            'employees_count' => $this->whenCounted('employees'),
            'services' => ServiceResource::collection($this->whenLoaded('services')),
            'categories' => ServiceCategoryResource::collection($this->whenLoaded('serviceCategories')),
            'employees' => PublicEmployeeResource::collection($this->whenLoaded('employees')),
        ];
    }
}
