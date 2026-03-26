<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseControllerTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $businessAdmin;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create();

        $this->businessAdmin = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'business_admin',
        ]);

        $this->course = Course::factory()->create([
            'business_id' => $this->business->id,
            'title' => 'Test Course',
            'slug' => 'test-course',
            'price' => 1000,
            'modality' => 'presencial',
        ]);
    }

    public function test_business_admin_can_view_courses_index(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->get('/courses');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Courses/Index')
            ->has('courses.data', 1)
        );
    }

    public function test_business_admin_can_view_course_create_form(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->get('/courses/create');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Courses/Create')
        );
    }

    public function test_business_admin_can_create_course(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->post('/courses', [
                'title' => 'New Course',
                'description' => 'A description for the new course.',
                'price' => 500,
                'modality' => 'online',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('courses', [
            'business_id' => $this->business->id,
            'title' => 'New Course',
            'slug' => 'new-course',
        ]);
    }

    public function test_business_admin_can_view_course_show(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->get("/courses/{$this->course->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Courses/Show')
            ->has('course', fn ($course) => $course
                ->where('id', $this->course->id)
                ->where('title', 'Test Course')
                ->etc()
            )
        );
    }

    public function test_business_admin_can_edit_course(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->get("/courses/{$this->course->id}/edit");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Courses/Edit')
            ->has('course')
        );
    }

    public function test_business_admin_can_update_course(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->put("/courses/{$this->course->id}", [
                'title' => 'Updated Title',
                'description' => 'Updated description.',
                'price' => 2000,
                'modality' => 'online',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('courses', [
            'id' => $this->course->id,
            'title' => 'Updated Title',
        ]);
    }

    public function test_business_admin_can_delete_course_without_enrollments(): void
    {
        $response = $this->actingAs($this->businessAdmin)
            ->delete("/courses/{$this->course->id}");

        $response->assertRedirect('/courses');

        $this->assertDatabaseMissing('courses', [
            'id' => $this->course->id,
        ]);
    }

    public function test_business_admin_cannot_delete_course_with_enrollments(): void
    {
        Enrollment::factory()->create([
            'business_id' => $this->business->id,
            'course_id' => $this->course->id,
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->delete("/courses/{$this->course->id}");

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('courses', [
            'id' => $this->course->id,
        ]);
    }

    public function test_courses_index_can_be_searched(): void
    {
        Course::factory()->create([
            'business_id' => $this->business->id,
            'title' => 'Unique Searchable Title',
        ]);

        $response = $this->actingAs($this->businessAdmin)
            ->get('/courses?search=Unique Searchable');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('courses.data', 1)
            ->where('courses.data.0.title', 'Unique Searchable Title')
        );
    }

    public function test_unauthenticated_user_cannot_access_courses(): void
    {
        $this->get('/courses')->assertRedirect('/login');
    }

    public function test_client_role_cannot_create_courses(): void
    {
        $client = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'client',
        ]);

        $response = $this->actingAs($client)
            ->get('/courses/create');

        $response->assertForbidden();
    }
}
