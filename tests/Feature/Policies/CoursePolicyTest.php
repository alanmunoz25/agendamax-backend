<?php

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Models\Business;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoursePolicyTest extends TestCase
{
    use RefreshDatabase;

    private Business $business1;

    private Business $business2;

    private User $admin1;

    private User $admin2;

    private User $superAdmin;

    private User $clientUser;

    private Course $course1;

    private Course $course2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business1 = Business::factory()->create();
        $this->business2 = Business::factory()->create();

        $this->admin1 = User::factory()->create([
            'business_id' => $this->business1->id,
            'role' => 'business_admin',
        ]);

        $this->admin2 = User::factory()->create([
            'business_id' => $this->business2->id,
            'role' => 'business_admin',
        ]);

        $this->superAdmin = User::factory()->superAdmin()->create();

        $this->clientUser = User::factory()->create([
            'business_id' => $this->business1->id,
            'role' => 'client',
        ]);

        $this->course1 = Course::factory()->create([
            'business_id' => $this->business1->id,
        ]);

        $this->course2 = Course::factory()->create([
            'business_id' => $this->business2->id,
        ]);
    }

    public function test_super_admin_can_perform_all_actions(): void
    {
        $this->assertTrue($this->superAdmin->can('viewAny', Course::class));
        $this->assertTrue($this->superAdmin->can('view', $this->course1));
        $this->assertTrue($this->superAdmin->can('create', Course::class));
        $this->assertTrue($this->superAdmin->can('update', $this->course1));
        $this->assertTrue($this->superAdmin->can('delete', $this->course1));
        $this->assertTrue($this->superAdmin->can('view', $this->course2));
    }

    public function test_business_admin_can_manage_own_courses(): void
    {
        $this->assertTrue($this->admin1->can('viewAny', Course::class));
        $this->assertTrue($this->admin1->can('view', $this->course1));
        $this->assertTrue($this->admin1->can('create', Course::class));
        $this->assertTrue($this->admin1->can('update', $this->course1));
        $this->assertTrue($this->admin1->can('delete', $this->course1));
    }

    public function test_business_admin_cannot_manage_other_business_courses(): void
    {
        $this->assertFalse($this->admin1->can('view', $this->course2));
        $this->assertFalse($this->admin1->can('update', $this->course2));
        $this->assertFalse($this->admin1->can('delete', $this->course2));
    }

    public function test_client_cannot_manage_courses(): void
    {
        $this->assertFalse($this->clientUser->can('create', Course::class));
    }

    public function test_employee_cannot_manage_courses(): void
    {
        $employee = User::factory()->create([
            'business_id' => $this->business1->id,
            'role' => 'employee',
        ]);

        $this->assertFalse($employee->can('create', Course::class));
        $this->assertFalse($employee->can('update', $this->course1));
        $this->assertFalse($employee->can('delete', $this->course1));
    }
}
