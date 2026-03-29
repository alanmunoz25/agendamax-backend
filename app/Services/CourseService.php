<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Course;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mews\Purifier\Facades\Purifier;
use RuntimeException;

class CourseService
{
    /**
     * Create a new course.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Course
    {
        $data['slug'] = $this->generateUniqueSlug($data['title'], (int) $data['business_id']);

        if (isset($data['syllabus'])) {
            $data['syllabus'] = Purifier::clean($data['syllabus']);
        }

        if (isset($data['cover_image']) && $data['cover_image'] instanceof \Illuminate\Http\UploadedFile) {
            $data['cover_image'] = $data['cover_image']->store('courses', 'public');
        }

        if (isset($data['instructor_image']) && $data['instructor_image'] instanceof \Illuminate\Http\UploadedFile) {
            $data['instructor_image'] = $data['instructor_image']->store('courses/instructors', 'public');
        }

        return Course::create($data);
    }

    /**
     * Update an existing course.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Course $course, array $data): Course
    {
        if (isset($data['title']) && $data['title'] !== $course->title) {
            $data['slug'] = $this->generateUniqueSlug($data['title'], $course->business_id, $course->id);
        }

        if (isset($data['syllabus'])) {
            $data['syllabus'] = Purifier::clean($data['syllabus']);
        }

        if (isset($data['cover_image']) && $data['cover_image'] instanceof \Illuminate\Http\UploadedFile) {
            if ($course->cover_image) {
                Storage::disk('public')->delete($course->cover_image);
            }
            $data['cover_image'] = $data['cover_image']->store('courses', 'public');
        }

        if (isset($data['instructor_image']) && $data['instructor_image'] instanceof \Illuminate\Http\UploadedFile) {
            if ($course->instructor_image) {
                Storage::disk('public')->delete($course->instructor_image);
            }
            $data['instructor_image'] = $data['instructor_image']->store('courses/instructors', 'public');
        }

        $course->update($data);

        return $course->refresh();
    }

    /**
     * Delete a course.
     *
     * @throws RuntimeException
     */
    public function delete(Course $course): void
    {
        if ($course->enrollments()->exists()) {
            throw new RuntimeException('Cannot delete a course that has enrollments.');
        }

        if ($course->cover_image) {
            Storage::disk('public')->delete($course->cover_image);
        }

        $course->delete();
    }

    /**
     * Get enrollable courses for a business.
     *
     * @return Collection<int, Course>
     */
    public function getEnrollableForBusiness(int $businessId): Collection
    {
        return Course::withoutGlobalScopes()
            ->forBusiness($businessId)
            ->enrollable()
            ->orderBy('start_date')
            ->get();
    }

    /**
     * Generate a unique slug for a course within a business.
     */
    private function generateUniqueSlug(string $title, int $businessId, ?int $excludeId = null): string
    {
        $slug = Str::slug($title);
        $original = $slug;
        $counter = 1;

        while ($this->slugExists($slug, $businessId, $excludeId)) {
            $slug = $original.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Check if a slug already exists for a business.
     */
    private function slugExists(string $slug, int $businessId, ?int $excludeId = null): bool
    {
        $query = Course::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('slug', $slug);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}
