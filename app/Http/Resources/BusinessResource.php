<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Business
 */
class BusinessResource extends JsonResource
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
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'logo_url' => $this->logo_url,
            'invitation_code' => $this->invitation_code,
            'loyalty_stamps_required' => $this->loyalty_stamps_required,
            'loyalty_reward_description' => $this->loyalty_reward_description,
            'services_count' => $this->whenCounted('services'),
            'employees_count' => $this->whenCounted('employees'),
        ];
    }
}
