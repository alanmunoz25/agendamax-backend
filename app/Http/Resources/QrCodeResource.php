<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class QrCodeResource extends JsonResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);

        $data['image_url'] = $this->image_path
            ? Storage::disk('public')->url($this->image_path)
            : null;

        return $data;
    }

    public static function fromModel($model): array
    {
        return (new self($model))->resolve();
    }
}
