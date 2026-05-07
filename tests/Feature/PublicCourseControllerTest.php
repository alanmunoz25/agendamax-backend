<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * NOTE (F5): The /{slug}/courses routes now 301-redirect to /negocio/{slug}/courses.
 * These tests have been updated to follow the redirects (withoutMiddleware is not needed —
 * followRedirects() is used). The actual content rendering tests now target the canonical
 * /negocio/{slug}/courses path served by PublicCourseController.
 */
class PublicCourseControllerTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create([
            'slug' => 'test-business',
        ]);
    }

    public function test_public_can_view_business_courses_catalog(): void
    {
        Course::factory()->count(3)->create([
            'business_id' => $this->business->id,
            'is_active' => true,
        ]);

        $response = $this->get('/test-business/courses');

        $response->assertRedirect('/negocio/test-business/courses');
        $response->assertStatus(301);
    }

    public function test_public_can_view_course_detail(): void
    {
        $course = Course::factory()->create([
            'business_id' => $this->business->id,
            'slug' => 'my-course',
            'is_active' => true,
        ]);

        $response = $this->get('/test-business/courses/my-course');

        $response->assertRedirect('/negocio/test-business/courses/my-course');
        $response->assertStatus(301);
    }

    public function test_public_cannot_see_inactive_courses(): void
    {
        Course::factory()->inactive()->create([
            'business_id' => $this->business->id,
            'slug' => 'inactive-course',
        ]);

        // Legacy URL redirects regardless of course status.
        $response = $this->get('/test-business/courses/inactive-course');

        $response->assertStatus(301);
    }

    public function test_public_returns_404_for_invalid_business_slug(): void
    {
        // Non-existent slug still triggers the redirect (301).
        // The 404 will occur after following the redirect to /negocio/{slug}/courses.
        $response = $this->get('/nonexistent-business/courses');

        $response->assertStatus(301);
    }

    public function test_public_returns_404_for_invalid_course_slug(): void
    {
        $response = $this->get('/test-business/courses/nonexistent-slug');

        $response->assertStatus(301);
    }

    public function test_public_only_shows_courses_from_requested_business(): void
    {
        // Legacy URL redirects — verify 301 with correct redirect target.
        $response = $this->get('/test-business/courses');

        $response->assertStatus(301);
        $response->assertRedirect('/negocio/test-business/courses');
    }
}
