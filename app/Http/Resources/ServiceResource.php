<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Service
 */
class ServiceResource extends JsonResource
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
            'description' => $this->description,
            'duration' => $this->duration,
            'price' => $this->price,
            'category' => $this->category,
            'is_active' => $this->is_active,
            'service_category' => $this->whenLoaded('serviceCategory', function () {
                return [
                    'id' => $this->serviceCategory->id,
                    'name' => $this->serviceCategory->name,
                    'parent' => $this->when($this->serviceCategory->relationLoaded('parent') && $this->serviceCategory->parent, function () {
                        return [
                            'id' => $this->serviceCategory->parent->id,
                            'name' => $this->serviceCategory->parent->name,
                        ];
                    }),
                ];
            }),
            'employees_count' => $this->whenCounted('employees'),
        ];
    }
}
