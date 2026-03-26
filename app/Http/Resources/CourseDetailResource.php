<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * @mixin \App\Models\Course
 */
class CourseDetailResource extends CourseResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'description' => $this->description,
            'syllabus' => $this->syllabus,
            'instructor_bio' => $this->instructor_bio,
            'enrollment_deadline' => $this->enrollment_deadline?->toDateString(),
            'is_featured' => $this->is_featured,
            'meta' => $this->meta,
            'created_at' => $this->created_at?->toIso8601String(),
        ]);
    }
}
