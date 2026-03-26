<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Public/Courses/Index')
            ->has('business')
            ->has('courses.data', 3)
        );
    }

    public function test_public_can_view_course_detail(): void
    {
        $course = Course::factory()->create([
            'business_id' => $this->business->id,
            'slug' => 'my-course',
            'is_active' => true,
        ]);

        $response = $this->get('/test-business/courses/my-course');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Public/Courses/Show')
            ->has('business')
            ->has('course', fn ($c) => $c
                ->where('id', $course->id)
                ->where('slug', 'my-course')
                ->etc()
            )
        );
    }

    public function test_public_cannot_see_inactive_courses(): void
    {
        Course::factory()->inactive()->create([
            'business_id' => $this->business->id,
            'slug' => 'inactive-course',
        ]);

        $response = $this->get('/test-business/courses/inactive-course');

        $response->assertNotFound();
    }

    public function test_public_returns_404_for_invalid_business_slug(): void
    {
        $response = $this->get('/nonexistent-business/courses');

        $response->assertNotFound();
    }

    public function test_public_returns_404_for_invalid_course_slug(): void
    {
        $response = $this->get('/test-business/courses/nonexistent-slug');

        $response->assertNotFound();
    }

    public function test_public_only_shows_courses_from_requested_business(): void
    {
        $otherBusiness = Business::factory()->create(['slug' => 'other-business']);

        Course::factory()->create([
            'business_id' => $this->business->id,
            'is_active' => true,
        ]);

        Course::factory()->create([
            'business_id' => $otherBusiness->id,
            'is_active' => true,
        ]);

        $response = $this->get('/test-business/courses');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('courses.data', 1)
        );
    }
}
