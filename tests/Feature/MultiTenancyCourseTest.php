<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiTenancyCourseTest extends TestCase
{
    use RefreshDatabase;

    private Business $business1;

    private Business $business2;

    private User $admin1;

    private User $admin2;

    private User $superAdmin;

    private Course $course1;

    private Course $course2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business1 = Business::factory()->create(['slug' => 'business-one']);
        $this->business2 = Business::factory()->create(['slug' => 'business-two']);

        $this->admin1 = User::factory()->create([
            'business_id' => $this->business1->id,
            'role' => 'business_admin',
        ]);

        $this->admin2 = User::factory()->create([
            'business_id' => $this->business2->id,
            'role' => 'business_admin',
        ]);

        $this->superAdmin = User::factory()->superAdmin()->create();

        $this->course1 = Course::factory()->create([
            'business_id' => $this->business1->id,
            'title' => 'Course Business 1',
        ]);

        $this->course2 = Course::factory()->create([
            'business_id' => $this->business2->id,
            'title' => 'Course Business 2',
        ]);
    }

    public function test_business_admin_only_sees_own_courses(): void
    {
        $response = $this->actingAs($this->admin1)
            ->get('/courses');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('courses.data', 1)
            ->where('courses.data.0.title', 'Course Business 1')
        );
    }

    public function test_business_admin_cannot_view_other_business_course(): void
    {
        $response = $this->actingAs($this->admin1)
            ->get("/courses/{$this->course2->id}");

        // Global scope filters it out, resulting in 404
        $response->assertNotFound();
    }

    public function test_business_admin_cannot_update_other_business_course(): void
    {
        $response = $this->actingAs($this->admin1)
            ->put("/courses/{$this->course2->id}", [
                'title' => 'Hacked Title',
            ]);

        $response->assertNotFound();

        $this->course2->refresh();
        $this->assertEquals('Course Business 2', $this->course2->title);
    }

    public function test_business_admin_cannot_delete_other_business_course(): void
    {
        $response = $this->actingAs($this->admin1)
            ->delete("/courses/{$this->course2->id}");

        $response->assertNotFound();

        $this->assertDatabaseHas('courses', [
            'id' => $this->course2->id,
        ]);
    }

    public function test_global_scope_filters_courses_by_business(): void
    {
        $this->actingAs($this->admin1);

        $courses = Course::all();

        $this->assertCount(1, $courses);
        $this->assertEquals($this->course1->id, $courses->first()->id);
    }

    public function test_super_admin_can_see_all_courses(): void
    {
        $this->actingAs($this->superAdmin);

        $courses = Course::all();

        $this->assertCount(2, $courses);
    }

    public function test_public_api_isolates_by_business(): void
    {
        $response = $this->getJson("/api/v1/businesses/{$this->business1->id}/courses");

        $response->assertOk();

        $titles = collect($response->json('data'))->pluck('title')->toArray();

        $this->assertContains('Course Business 1', $titles);
        $this->assertNotContains('Course Business 2', $titles);
    }
}
