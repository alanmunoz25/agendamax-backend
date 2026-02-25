<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PromotionResource extends JsonResource
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
            'title' => $this->title,
            'image_url' => $this->image_path ? Storage::disk('public')->url($this->image_path) : null,
            'url' => $this->url,
            'expires_at' => $this->expires_at?->toDateString(),
            'is_active' => $this->is_active,
        ];
    }
}
