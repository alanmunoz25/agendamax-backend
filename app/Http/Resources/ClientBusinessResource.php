<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Business
 */
class ClientBusinessResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isPrimary = $user && $user->business_id === $this->id;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'logo_url' => $this->logo_url,
            'address' => $this->address,
            'sector' => $this->sector,
            'province' => $this->province,
            'status' => $this->status,
            'pivot' => [
                'status' => $this->pivot->status,
                'joined_at' => $this->pivot->joined_at?->toIso8601String(),
                'blocked_at' => $this->pivot->blocked_at?->toIso8601String(),
                'blocked_reason' => $this->pivot->blocked_reason,
            ],
            'is_blocked' => $this->pivot->status === 'blocked',
            'is_primary' => $isPrimary,
        ];
    }
}
