<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
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
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'business_id' => $this->business_id,
            'avatar_url' => $this->avatar_url,
            'notes' => $this->when($this->notes !== null, $this->notes),
            'source' => $this->when($this->source !== null, $this->source),
            'interested_service_id' => $this->when($this->interested_service_id !== null, $this->interested_service_id),
            'business' => new BusinessResource($this->whenLoaded('business')),
        ];
    }
}
