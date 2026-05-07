<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Events\EnrollmentCreated;
use App\Events\EnrollmentPaid;
use App\Models\Business;
use App\Models\Course;
use App\Models\User;
use App\Services\EnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

class EnrollmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private EnrollmentService $enrollmentService;

    private Business $business;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enrollmentService = new EnrollmentService;

        $this->business = Business::factory()->create();

        $this->course = Course::factory()->create([
            'business_id' => $this->business->id,
            'price' => 500,
            'capacity' => 10,
            'is_active' => true,
        ]);

        // Auth as super admin so global scopes don't interfere
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin);
    }

    public function test_enroll_creates_enrollment_and_user(): void
    {
        Event::fake([EnrollmentCreated::class]);

        $enrollment = $this->enrollmentService->enroll($this->course, [
            'name' => 'Test Student',
            'email' => 'student@example.com',
            'phone' => '809-555-0001',
        ]);

        $this->assertDatabaseHas('enrollments', [
            'id' => $enrollment->id,
            'course_id' => $this->course->id,
            'customer_email' => 'student@example.com',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'student@example.com',
            'role' => 'lead',
            'primary_business_id' => $this->business->id,
        ]);
    }

    public function test_enroll_free_course_auto_confirms(): void
    {
        Event::fake([EnrollmentCreated::class]);

        $freeCourse = Course::factory()->create([
            'business_id' => $this->business->id,
            'price' => 0,
        ]);

        $enrollment = $this->enrollmentService->enroll($freeCourse, [
            'name' => 'Free Student',
            'email' => 'free@example.com',
        ]);

        $this->assertEquals('confirmed', $enrollment->status);
        $this->assertEquals('free', $enrollment->payment_status);
        $this->assertNotNull($enrollment->enrolled_at);
    }

    public function test_enroll_paid_course_stays_pending(): void
    {
        Event::fake([EnrollmentCreated::class]);

        $enrollment = $this->enrollmentService->enroll($this->course, [
            'name' => 'Paid Student',
            'email' => 'paid@example.com',
        ]);

        $this->assertEquals('lead', $enrollment->status);
        $this->assertEquals('pending', $enrollment->payment_status);
        $this->assertNull($enrollment->enrolled_at);
    }

    public function test_enroll_reuses_existing_lead_user(): void
    {
        Event::fake([EnrollmentCreated::class]);

        $existingUser = User::factory()->create([
            'email' => 'existing@example.com',
            'business_id' => $this->business->id,
            'role' => 'lead',
        ]);

        $enrollment = $this->enrollmentService->enroll($this->course, [
            'name' => 'Existing Lead',
            'email' => 'existing@example.com',
        ]);

        $this->assertEquals($existingUser->id, $enrollment->user_id);

        // Should not duplicate user
        $this->assertEquals(
            1,
            User::where('email', 'existing@example.com')
                ->where('primary_business_id', $this->business->id)
                ->count()
        );
    }

    public function test_enroll_rejects_duplicate(): void
    {
        Event::fake([EnrollmentCreated::class]);

        $this->enrollmentService->enroll($this->course, [
            'name' => 'First',
            'email' => 'dup@example.com',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('This email is already enrolled in this course.');

        $this->enrollmentService->enroll($this->course, [
            'name' => 'Second',
            'email' => 'dup@example.com',
        ]);
    }

    public function test_enroll_rejects_when_at_capacity(): void
    {
        Event::fake([EnrollmentCreated::class]);

        $smallCourse = Course::factory()->create([
            'business_id' => $this->business->id,
            'capacity' => 1,
            'price' => 0,
        ]);

        $this->enrollmentService->enroll($smallCourse, [
            'name' => 'First',
            'email' => 'first@example.com',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('maximum capacity');

        $this->enrollmentService->enroll($smallCourse, [
            'name' => 'Second',
            'email' => 'second@example.com',
        ]);
    }

    public function test_confirm_updates_status(): void
    {
        Event::fake([EnrollmentCreated::class]);

        $enrollment = $this->enrollmentService->enroll($this->course, [
            'name' => 'To Confirm',
            'email' => 'confirm@example.com',
        ]);

        $this->enrollmentService->confirm($enrollment);

        $enrollment->refresh();
        $this->assertEquals('confirmed', $enrollment->status);
        $this->assertNotNull($enrollment->enrolled_at);
    }

    public function test_cancel_updates_status(): void
    {
        Event::fake([EnrollmentCreated::class]);

        $enrollment = $this->enrollmentService->enroll($this->course, [
            'name' => 'To Cancel',
            'email' => 'cancel@example.com',
        ]);

        $this->enrollmentService->cancel($enrollment);

        $enrollment->refresh();
        $this->assertEquals('cancelled', $enrollment->status);
    }

    public function test_mark_as_paid_updates_payment_fields(): void
    {
        Event::fake([EnrollmentCreated::class, EnrollmentPaid::class]);

        $enrollment = $this->enrollmentService->enroll($this->course, [
            'name' => 'To Pay',
            'email' => 'pay@example.com',
        ]);

        $this->enrollmentService->markAsPaid($enrollment, [
            'provider' => 'stripe',
            'reference' => 'ref_123',
            'amount' => 500.00,
        ]);

        $enrollment->refresh();
        $this->assertEquals('paid', $enrollment->payment_status);
        $this->assertEquals('stripe', $enrollment->payment_provider);
        $this->assertEquals('ref_123', $enrollment->payment_reference);
        $this->assertEquals('confirmed', $enrollment->status);

        Event::assertDispatched(EnrollmentPaid::class);
    }

    public function test_enroll_dispatches_event(): void
    {
        Event::fake([EnrollmentCreated::class]);

        $this->enrollmentService->enroll($this->course, [
            'name' => 'Event Test',
            'email' => 'event@example.com',
        ]);

        Event::assertDispatched(EnrollmentCreated::class);
    }
}
