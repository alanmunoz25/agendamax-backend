<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Business;
use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseControllerTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create(['status' => 'active']);
    }

    public function test_can_list_courses_for_business(): void
    {
        Course::factory()->count(3)->create([
            'business_id' => $this->business->id,
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/businesses/{$this->business->id}/courses");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'title', 'slug', 'description', 'price', 'currency', 'modality', 'is_active'],
                ],
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_only_active_courses_returned(): void
    {
        Course::factory()->create([
            'business_id' => $this->business->id,
            'is_active' => true,
        ]);

        Course::factory()->inactive()->create([
            'business_id' => $this->business->id,
        ]);

        $response = $this->getJson("/api/v1/businesses/{$this->business->id}/courses");

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_can_view_course_detail_by_slug(): void
    {
        $course = Course::factory()->create([
            'business_id' => $this->business->id,
            'title' => 'My Course',
            'slug' => 'my-course',
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/businesses/{$this->business->id}/courses/my-course");

        $response->assertOk()
            ->assertJsonFragment(['title' => 'My Course'])
            ->assertJsonFragment(['slug' => 'my-course']);
    }

    public function test_returns_404_for_nonexistent_business(): void
    {
        $response = $this->getJson('/api/v1/businesses/99999/courses');

        $response->assertNotFound();
    }

    public function test_returns_404_for_nonexistent_slug(): void
    {
        $response = $this->getJson("/api/v1/businesses/{$this->business->id}/courses/nonexistent");

        $response->assertNotFound();
    }

    public function test_courses_from_other_business_not_visible(): void
    {
        $otherBusiness = Business::factory()->create();

        Course::factory()->create([
            'business_id' => $otherBusiness->id,
            'slug' => 'other-course',
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/businesses/{$this->business->id}/courses/other-course");

        $response->assertNotFound();
    }

    public function test_response_includes_remaining_capacity(): void
    {
        Course::factory()->create([
            'business_id' => $this->business->id,
            'slug' => 'capped-course',
            'capacity' => 20,
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/businesses/{$this->business->id}/courses/capped-course");

        $response->assertOk()
            ->assertJsonPath('data.remaining_capacity', 20);
    }

    public function test_pagination_works(): void
    {
        Course::factory()->count(20)->create([
            'business_id' => $this->business->id,
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/businesses/{$this->business->id}/courses?per_page=5");

        $response->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.last_page', 4);
    }
}
