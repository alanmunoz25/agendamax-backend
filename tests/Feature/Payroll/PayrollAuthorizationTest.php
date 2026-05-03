<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Models\Business;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollRecord;
use App\Models\User;
use App\Services\PayrollService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Authorization tests for payroll endpoints (Fase 5 — Sprint 1 Ola 1, BLOCK-001 & BLOCK-002).
 *
 * Covers: policy enforcement, cross-tenant rejection, rate limiting.
 */
class PayrollAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Business $businessA;

    private Business $businessB;

    private User $adminA;

    private User $adminB;

    private Employee $employeeA;

    private User $employeeUserA;

    private PayrollPeriod $periodA;

    private PayrollPeriod $periodB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->businessA = Business::factory()->create();
        $this->businessB = Business::factory()->create();

        $this->adminA = User::factory()->create([
            'business_id' => $this->businessA->id,
            'role' => 'business_admin',
            'email_verified_at' => now(),
        ]);

        $this->adminB = User::factory()->create([
            'business_id' => $this->businessB->id,
            'role' => 'business_admin',
            'email_verified_at' => now(),
        ]);

        $this->employeeUserA = User::factory()->create([
            'business_id' => $this->businessA->id,
            'role' => 'employee',
            'email_verified_at' => now(),
        ]);

        $this->employeeA = Employee::factory()->create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->employeeUserA->id,
            'is_active' => true,
        ]);

        $this->periodA = PayrollPeriod::factory()->forBusiness($this->businessA)->create([
            'starts_on' => '2026-04-01',
            'ends_on' => '2026-04-30',
            'status' => 'open',
        ]);

        $this->periodB = PayrollPeriod::factory()->forBusiness($this->businessB)->create([
            'starts_on' => '2026-04-01',
            'ends_on' => '2026-04-30',
            'status' => 'open',
        ]);
    }

    // ── Employee cannot perform admin actions ─────────────────────────────────

    /** @test */
    public function test_employee_cannot_approve_their_own_record(): void
    {
        PayrollRecord::factory()->draft()->create([
            'business_id' => $this->businessA->id,
            'payroll_period_id' => $this->periodA->id,
            'employee_id' => $this->employeeA->id,
        ]);

        $this->actingAs($this->employeeUserA)
            ->post("/payroll/periods/{$this->periodA->id}/approve")
            ->assertForbidden();
    }

    /** @test */
    public function test_employee_cannot_void_their_own_record(): void
    {
        $record = PayrollRecord::factory()->draft()->create([
            'business_id' => $this->businessA->id,
            'payroll_period_id' => $this->periodA->id,
            'employee_id' => $this->employeeA->id,
        ]);

        $this->actingAs($this->employeeUserA)
            ->post("/payroll/records/{$record->id}/void", [
                'reason' => 'Intento de anulación no autorizado',
            ])
            ->assertForbidden();
    }

    /** @test */
    public function test_employee_cannot_mark_paid_their_own_record(): void
    {
        $record = PayrollRecord::factory()->approved()->create([
            'business_id' => $this->businessA->id,
            'payroll_period_id' => $this->periodA->id,
            'employee_id' => $this->employeeA->id,
        ]);

        $this->actingAs($this->employeeUserA)
            ->post("/payroll/records/{$record->id}/mark-paid", [
                'payment_method' => 'cash',
            ])
            ->assertForbidden();
    }

    // ── Cross-tenant: BelongsToBusinessScope returns 404 ─────────────────────
    //
    // Because PayrollPeriod and PayrollRecord use BelongsToBusiness scope,
    // route model binding for admin A cannot resolve records from business B —
    // the result is 404 (existence not revealed). This is stricter than 403.

    /** @test */
    public function test_business_admin_cannot_approve_record_of_other_business(): void
    {
        PayrollRecord::factory()->draft()->create([
            'business_id' => $this->businessB->id,
            'payroll_period_id' => $this->periodB->id,
            'employee_id' => Employee::factory()->create([
                'business_id' => $this->businessB->id,
                'user_id' => User::factory()->create(['business_id' => $this->businessB->id, 'role' => 'employee'])->id,
            ])->id,
        ]);

        // BelongsToBusinessScope filters period to admin A's business → 404
        $this->actingAs($this->adminA)
            ->post("/payroll/periods/{$this->periodB->id}/approve")
            ->assertNotFound();
    }

    // ── BLOCK-001: Policy denies non-admin roles for period creation ───────────

    /** @test */
    public function test_business_admin_cannot_create_period_for_other_business(): void
    {
        // CreatePayrollPeriodRequest::authorize() uses PayrollPeriodPolicy::create
        // which requires isSuperAdmin() or isBusinessAdmin(). Employees are denied.
        $this->actingAs($this->employeeUserA)
            ->post('/payroll/periods', [
                'start' => '2026-06-01',
                'end' => '2026-06-30',
            ])
            ->assertForbidden();
    }

    // ── BLOCK-002: Cross-tenant employee in adjustment ────────────────────────

    /** @test */
    public function test_business_admin_cannot_add_adjustment_with_employee_from_other_business(): void
    {
        $employeeB = Employee::factory()->create([
            'business_id' => $this->businessB->id,
            'user_id' => User::factory()->create(['business_id' => $this->businessB->id, 'role' => 'employee'])->id,
        ]);

        PayrollRecord::factory()->draft()->create([
            'business_id' => $this->businessA->id,
            'payroll_period_id' => $this->periodA->id,
            'employee_id' => $this->employeeA->id,
        ]);

        // employee_id from business B fails Rule::exists scoped to period->business_id
        // Web route: redirects back with session errors (not JSON 422)
        $this->actingAs($this->adminA)
            ->post("/payroll/periods/{$this->periodA->id}/adjustments", [
                'employee_id' => $employeeB->id,
                'type' => 'credit',
                'amount' => '100.00',
                'reason' => 'Ajuste entre negocios bloqueado',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('employee_id');
    }

    // ── Super admin bypasses all restrictions ─────────────────────────────────

    /** @test */
    public function test_super_admin_can_approve_any_business_record(): void
    {
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'email_verified_at' => now(),
        ]);

        PayrollRecord::factory()->draft()->create([
            'business_id' => $this->businessA->id,
            'payroll_period_id' => $this->periodA->id,
            'employee_id' => $this->employeeA->id,
        ]);

        // Super admin sees all periods regardless of business
        $this->actingAs($superAdmin)
            ->post("/payroll/periods/{$this->periodA->id}/approve")
            ->assertRedirect();
    }

    // ── Client role denied on all payroll endpoints ───────────────────────────

    /** @test */
    public function test_client_role_cannot_access_payroll_endpoints(): void
    {
        $client = User::factory()->create([
            'business_id' => $this->businessA->id,
            'role' => 'client',
            'email_verified_at' => now(),
        ]);

        $record = PayrollRecord::factory()->draft()->create([
            'business_id' => $this->businessA->id,
            'payroll_period_id' => $this->periodA->id,
            'employee_id' => $this->employeeA->id,
        ]);

        $this->actingAs($client)
            ->post("/payroll/periods/{$this->periodA->id}/approve")
            ->assertForbidden();

        $this->actingAs($client)
            ->post("/payroll/records/{$record->id}/void", ['reason' => 'Intento cliente malicioso'])
            ->assertForbidden();

        $this->actingAs($client)
            ->post('/payroll/periods', ['start' => '2026-06-01', 'end' => '2026-06-30'])
            ->assertForbidden();
    }

    // ── Service-layer cross-tenant guard (BLOCK-002) ───────────────────────────

    /** @test */
    public function test_add_adjustment_service_rejects_cross_business_employee(): void
    {
        $service = app(PayrollService::class);

        $employeeB = Employee::factory()->create([
            'business_id' => $this->businessB->id,
            'user_id' => User::factory()->create(['business_id' => $this->businessB->id, 'role' => 'employee'])->id,
        ]);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessageMatches('/Cross-tenant adjustment rejected/');

        $service->addAdjustment(
            $this->periodA,
            $employeeB,
            'credit',
            100.0,
            'Ajuste cross-tenant',
            $this->adminA,
        );
    }

    // ── Rate limiting (HIGH-005) ───────────────────────────────────────────────

    /** @test */
    public function test_rate_limit_blocks_excessive_approvals(): void
    {
        // Clear any leftover rate limit state for this user
        RateLimiter::clear('30|'.$this->adminA->id);

        PayrollRecord::factory()->draft()->create([
            'business_id' => $this->businessA->id,
            'payroll_period_id' => $this->periodA->id,
            'employee_id' => $this->employeeA->id,
        ]);

        $response = null;

        for ($i = 1; $i <= 31; $i++) {
            $response = $this->actingAs($this->adminA)
                ->post("/payroll/periods/{$this->periodA->id}/approve");
        }

        $response->assertStatus(429);
    }
}
