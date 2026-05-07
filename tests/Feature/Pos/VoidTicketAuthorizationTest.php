<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Models\Business;
use App\Models\Employee;
use App\Models\PosTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Authorization tests for VoidPosTicketRequest.
 *
 * Covers: super_admin allow, business_admin allow (own business), employee own-ticket allow,
 * cross-business deny, and employee deny on another employee's ticket.
 */
class VoidTicketAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $superAdmin;

    private User $adminA;

    private User $employeeUser;

    private Employee $employeeRecord;

    private PosTicket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create();

        $this->superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'business_id' => null,
        ]);

        $this->adminA = User::factory()->create([
            'role' => 'business_admin',
            'business_id' => $this->business->id,
        ]);

        $this->employeeUser = User::factory()->create([
            'role' => 'employee',
            'business_id' => $this->business->id,
        ]);

        $this->employeeRecord = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $this->employeeUser->id,
            'is_active' => true,
        ]);

        $this->ticket = PosTicket::factory()->create([
            'business_id' => $this->business->id,
            'cashier_id' => $this->employeeUser->id,
            'status' => 'paid',
        ]);
    }

    public function test_super_admin_can_void_any_ticket(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->post(route('pos.tickets.void', $this->ticket), [
                'reason' => 'Super admin voiding for audit purposes.',
            ]);

        $response->assertRedirect();
        $this->ticket->refresh();
        $this->assertEquals('voided', $this->ticket->status);
    }

    public function test_business_admin_can_void_own_business_ticket(): void
    {
        $response = $this->actingAs($this->adminA)
            ->post(route('pos.tickets.void', $this->ticket), [
                'reason' => 'Admin anulando ticket de su negocio.',
            ]);

        $response->assertRedirect();
        $this->ticket->refresh();
        $this->assertEquals('voided', $this->ticket->status);
    }

    public function test_business_admin_cannot_void_other_business_ticket(): void
    {
        $otherBusiness = Business::factory()->create();
        $otherAdmin = User::factory()->create([
            'role' => 'business_admin',
            'business_id' => $otherBusiness->id,
        ]);

        $response = $this->actingAs($otherAdmin)
            ->post(route('pos.tickets.void', $this->ticket), [
                'reason' => 'Intento de anulación cross-business.',
            ]);

        // Cross-tenant: route model binding returns 404 (BelongsToBusiness global scope)
        // or 403 from the FormRequest authorize() — either denies access.
        $response->assertStatus(404);
    }

    public function test_employee_can_void_own_ticket(): void
    {
        $response = $this->actingAs($this->employeeUser)
            ->post(route('pos.tickets.void', $this->ticket), [
                'reason' => 'Empleado anula su propio ticket de caja.',
            ]);

        $response->assertRedirect();
        $this->ticket->refresh();
        $this->assertEquals('voided', $this->ticket->status);
    }

    public function test_employee_cannot_void_another_employees_ticket(): void
    {
        $otherEmployee = User::factory()->create([
            'role' => 'employee',
            'business_id' => $this->business->id,
        ]);

        Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $otherEmployee->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($otherEmployee)
            ->post(route('pos.tickets.void', $this->ticket), [
                'reason' => 'Intento de anular ticket de otro empleado.',
            ]);

        $response->assertStatus(403);
    }
}
