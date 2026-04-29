<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @mixin \App\Models\Course
 */
class CourseResource extends JsonResource
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
            'slug' => $this->slug,
            'description' => Str::limit($this->description, 200),
            'cover_image' => $this->cover_image ? Storage::url($this->cover_image) : null,
            'instructor_name' => $this->instructor_name,
            'duration_text' => $this->duration_text,
            'price' => $this->price,
            'currency' => $this->currency,
            'modality' => $this->modality,
            'is_active' => $this->is_active,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'schedule_text' => $this->schedule_text,
            'remaining_capacity' => $this->remaining_capacity,
            'enrollments_count' => $this->whenCounted('enrollments'),
        ];
    }
}
