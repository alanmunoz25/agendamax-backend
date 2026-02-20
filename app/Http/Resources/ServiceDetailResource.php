<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * @mixin \App\Models\Service
 */
class ServiceDetailResource extends ServiceResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'employees' => EmployeeResource::collection($this->whenLoaded('employees')),
        ]);
    }
}
