<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Employee
 */
class PublicEmployeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * Intentionally omits email and phone for public safety.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->user?->name,
            'photo_url' => $this->photo_url,
            'bio' => $this->bio,
        ];
    }
}
