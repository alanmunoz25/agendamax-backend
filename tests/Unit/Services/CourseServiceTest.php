<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Business;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\CourseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class CourseServiceTest extends TestCase
{
    use RefreshDatabase;

    private CourseService $courseService;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->courseService = new CourseService;

        $this->business = Business::factory()->create();

        // Auth as super admin so global scopes don't interfere
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin);
    }

    public function test_create_generates_unique_slug(): void
    {
        $course = $this->courseService->create([
            'business_id' => $this->business->id,
            'title' => 'My Amazing Course',
            'description' => 'A description.',
            'price' => 100,
            'modality' => 'online',
        ]);

        $this->assertEquals('my-amazing-course', $course->slug);
    }

    public function test_create_appends_suffix_for_duplicate_slug(): void
    {
        $this->courseService->create([
            'business_id' => $this->business->id,
            'title' => 'Duplicate Title',
            'description' => 'First course.',
            'price' => 100,
            'modality' => 'online',
        ]);

        $course2 = $this->courseService->create([
            'business_id' => $this->business->id,
            'title' => 'Duplicate Title',
            'description' => 'Second course.',
            'price' => 200,
            'modality' => 'online',
        ]);

        $this->assertEquals('duplicate-title-1', $course2->slug);
    }

    public function test_create_sanitizes_syllabus_html(): void
    {
        $course = $this->courseService->create([
            'business_id' => $this->business->id,
            'title' => 'HTML Course',
            'description' => 'A course.',
            'price' => 100,
            'modality' => 'online',
            'syllabus' => '<p>Valid content</p><script>alert("xss")</script>',
        ]);

        $this->assertStringNotContainsString('<script>', $course->syllabus);
        $this->assertStringContainsString('Valid content', $course->syllabus);
    }

    public function test_update_preserves_existing_data(): void
    {
        $course = Course::factory()->create([
            'business_id' => $this->business->id,
            'title' => 'Original Title',
            'price' => 1000,
            'modality' => 'presencial',
        ]);

        $updated = $this->courseService->update($course, [
            'title' => 'New Title',
        ]);

        $this->assertEquals('New Title', $updated->title);
        $this->assertEquals('1000.00', $updated->price);
        $this->assertEquals('presencial', $updated->modality);
    }

    public function test_delete_throws_when_enrollments_exist(): void
    {
        $course = Course::factory()->create([
            'business_id' => $this->business->id,
        ]);

        Enrollment::factory()->create([
            'business_id' => $this->business->id,
            'course_id' => $course->id,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot delete a course that has enrollments.');

        $this->courseService->delete($course);
    }

    public function test_delete_succeeds_without_enrollments(): void
    {
        $course = Course::factory()->create([
            'business_id' => $this->business->id,
        ]);

        $courseId = $course->id;

        $this->courseService->delete($course);

        $this->assertDatabaseMissing('courses', ['id' => $courseId]);
    }

    public function test_get_enrollable_filters_correctly(): void
    {
        // Active, enrollable (no deadline)
        Course::factory()->create([
            'business_id' => $this->business->id,
            'is_active' => true,
            'enrollment_deadline' => null,
        ]);

        // Active, enrollable (future deadline)
        Course::factory()->create([
            'business_id' => $this->business->id,
            'is_active' => true,
            'enrollment_deadline' => now()->addMonth(),
        ]);

        // Active but past deadline
        Course::factory()->create([
            'business_id' => $this->business->id,
            'is_active' => true,
            'enrollment_deadline' => now()->subDay(),
        ]);

        // Inactive
        Course::factory()->inactive()->create([
            'business_id' => $this->business->id,
        ]);

        $enrollable = $this->courseService->getEnrollableForBusiness($this->business->id);

        $this->assertCount(2, $enrollable);
    }
}
