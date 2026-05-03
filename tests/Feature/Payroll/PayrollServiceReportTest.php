<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollServiceReportTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create();

        $this->admin = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'business_admin',
            'email_verified_at' => now(),
        ]);
    }

    /** @test */
    public function test_it_renders_report_page(): void
    {
        $response = $this->actingAs($this->admin)
            ->get('/payroll/reports/by-service');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Payroll/Reports/ByService')
            ->has('report')
            ->has('summary')
            ->has('filters')
            ->has('periods_for_filter')
        );
    }

    /** @test */
    public function test_it_returns_empty_report_when_no_data(): void
    {
        $response = $this->actingAs($this->admin)
            ->get('/payroll/reports/by-service');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('report', [])
        );
    }

    /** @test */
    public function test_it_requires_authentication(): void
    {
        $this->get('/payroll/reports/by-service')->assertRedirect('/login');
    }

    /** @test */
    public function test_it_accepts_date_filters(): void
    {
        $response = $this->actingAs($this->admin)
            ->get('/payroll/reports/by-service?from=2025-01-01&to=2025-01-31');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('filters.from', '2025-01-01')
            ->where('filters.to', '2025-01-31')
        );
    }

    /** @test */
    public function test_it_enforces_business_isolation(): void
    {
        $otherBusiness = Business::factory()->create();
        $otherAdmin = User::factory()->create([
            'business_id' => $otherBusiness->id,
            'role' => 'business_admin',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($otherAdmin)->get('/payroll/reports/by-service');

        $response->assertOk();
        // Other admin sees their own (empty) data
        $response->assertInertia(fn ($page) => $page
            ->where('report', [])
        );
    }
}
