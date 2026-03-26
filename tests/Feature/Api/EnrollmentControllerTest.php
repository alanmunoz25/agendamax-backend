<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Business;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create();

        $this->course = Course::factory()->create([
            'business_id' => $this->business->id,
            'is_active' => true,
            'capacity' => 10,
            'price' => 500,
        ]);
    }

    public function test_can_enroll_in_free_course(): void
    {
        $freeCourse = Course::factory()->create([
            'business_id' => $this->business->id,
            'is_active' => true,
            'price' => 0,
        ]);

        $response = $this->postJson("/api/v1/courses/{$freeCourse->id}/enroll", [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('enrollments', [
            'course_id' => $freeCourse->id,
            'customer_email' => 'john@example.com',
            'status' => 'confirmed',
            'payment_status' => 'free',
        ]);
    }

    public function test_can_enroll_in_paid_course(): void
    {
        $response = $this->postJson("/api/v1/courses/{$this->course->id}/enroll", [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('enrollments', [
            'course_id' => $this->course->id,
            'customer_email' => 'jane@example.com',
            'status' => 'lead',
            'payment_status' => 'pending',
        ]);
    }

    public function test_enrollment_requires_name_and_email(): void
    {
        $response = $this->postJson("/api/v1/courses/{$this->course->id}/enroll", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email']);
    }

    public function test_duplicate_enrollment_rejected(): void
    {
        $this->postJson("/api/v1/courses/{$this->course->id}/enroll", [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $response = $this->postJson("/api/v1/courses/{$this->course->id}/enroll", [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $response->assertUnprocessable();
    }

    public function test_enrollment_respects_capacity(): void
    {
        $smallCourse = Course::factory()->create([
            'business_id' => $this->business->id,
            'is_active' => true,
            'capacity' => 1,
            'price' => 0,
        ]);

        // Fill capacity
        Enrollment::factory()->confirmed()->create([
            'business_id' => $this->business->id,
            'course_id' => $smallCourse->id,
            'status' => 'confirmed',
        ]);

        $response = $this->postJson("/api/v1/courses/{$smallCourse->id}/enroll", [
            'name' => 'Another Person',
            'email' => 'another@example.com',
        ]);

        $response->assertUnprocessable();
    }

    public function test_enrollment_creates_lead_user(): void
    {
        $this->postJson("/api/v1/courses/{$this->course->id}/enroll", [
            'name' => 'New Lead',
            'email' => 'newlead@example.com',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'newlead@example.com',
            'role' => 'lead',
            'business_id' => $this->business->id,
        ]);
    }

    public function test_cannot_enroll_in_inactive_course(): void
    {
        $inactiveCourse = Course::factory()->inactive()->create([
            'business_id' => $this->business->id,
        ]);

        $response = $this->postJson("/api/v1/courses/{$inactiveCourse->id}/enroll", [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $response->assertNotFound();
    }

    public function test_enrollment_with_phone_optional(): void
    {
        $response = $this->postJson("/api/v1/courses/{$this->course->id}/enroll", [
            'name' => 'No Phone',
            'email' => 'nophone@example.com',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('enrollments', [
            'customer_email' => 'nophone@example.com',
            'customer_phone' => null,
        ]);
    }
}
