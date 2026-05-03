<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Models\Business;
use App\Models\CommissionRule;
use App\Models\Employee;
use App\Models\PosShift;
use App\Models\PosTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Business $business1;

    private Business $business2;

    private User $superAdmin;

    private User $businessAdmin1;

    private User $businessAdmin2;

    private User $employee1;

    private User $employee2;

    private User $client1;

    private Employee $employeeRecord1;

    private Employee $employeeRecord2;

    private PosTicket $ticket1;

    private PosShift $shift1;

    private CommissionRule $rule1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business1 = Business::factory()->create(['pos_commissions_enabled' => true]);
        $this->business2 = Business::factory()->create(['pos_commissions_enabled' => true]);

        $this->superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->businessAdmin1 = User::factory()->create([
            'role' => 'business_admin',
            'business_id' => $this->business1->id,
        ]);

        $this->businessAdmin2 = User::factory()->create([
            'role' => 'business_admin',
            'business_id' => $this->business2->id,
        ]);

        $this->employee1 = User::factory()->create([
            'role' => 'employee',
            'business_id' => $this->business1->id,
        ]);

        $this->employee2 = User::factory()->create([
            'role' => 'employee',
            'business_id' => $this->business1->id,
        ]);

        $this->client1 = User::factory()->create([
            'role' => 'client',
            'business_id' => $this->business1->id,
        ]);

        $this->employeeRecord1 = Employee::factory()->create([
            'business_id' => $this->business1->id,
            'user_id' => $this->employee1->id,
        ]);

        $this->employeeRecord2 = Employee::factory()->create([
            'business_id' => $this->business1->id,
            'user_id' => $this->employee2->id,
        ]);

        $this->ticket1 = PosTicket::factory()->create([
            'business_id' => $this->business1->id,
            'cashier_id' => $this->businessAdmin1->id,
        ]);

        $this->shift1 = PosShift::factory()->create([
            'business_id' => $this->business1->id,
            'cashier_id' => $this->employee1->id,
        ]);

        $this->rule1 = CommissionRule::factory()->create([
            'business_id' => $this->business1->id,
        ]);
    }

    // --- PosTicketPolicy ---

    public function test_client_cannot_create_pos_ticket(): void
    {
        $this->assertFalse($this->client1->can('create', PosTicket::class));
    }

    public function test_employee_can_create_pos_ticket(): void
    {
        $this->assertTrue($this->employee1->can('create', PosTicket::class));
    }

    public function test_business_admin_can_create_pos_ticket(): void
    {
        $this->assertTrue($this->businessAdmin1->can('create', PosTicket::class));
    }

    public function test_super_admin_can_create_pos_ticket(): void
    {
        $this->assertTrue($this->superAdmin->can('create', PosTicket::class));
    }

    public function test_employee_cannot_void_ticket(): void
    {
        $this->assertFalse($this->employee1->can('void', $this->ticket1));
    }

    public function test_client_cannot_void_ticket(): void
    {
        $this->assertFalse($this->client1->can('void', $this->ticket1));
    }

    public function test_business_admin_can_void_ticket(): void
    {
        $this->assertTrue($this->businessAdmin1->can('void', $this->ticket1));
    }

    public function test_business_admin_cannot_void_ticket_of_other_business(): void
    {
        $ticketOther = PosTicket::factory()->create([
            'business_id' => $this->business2->id,
            'cashier_id' => $this->businessAdmin2->id,
        ]);
        $this->assertFalse($this->businessAdmin1->can('void', $ticketOther));
    }

    // --- PosShiftPolicy ---

    public function test_employee_cannot_close_other_employees_shift(): void
    {
        // shift1 belongs to employee1; employee2 should not be able to close it
        $this->assertFalse($this->employee2->can('close', $this->shift1));
    }

    public function test_employee_can_close_own_shift(): void
    {
        $this->assertTrue($this->employee1->can('close', $this->shift1));
    }

    public function test_business_admin_can_close_any_shift_in_own_business(): void
    {
        $this->assertTrue($this->businessAdmin1->can('close', $this->shift1));
    }

    public function test_business_admin_cannot_close_shift_of_other_business(): void
    {
        $shiftOther = PosShift::factory()->create([
            'business_id' => $this->business2->id,
            'cashier_id' => $this->businessAdmin2->id,
        ]);
        $this->assertFalse($this->businessAdmin1->can('close', $shiftOther));
    }

    // --- CommissionRulePolicy ---

    public function test_client_cannot_crud_commission_rules(): void
    {
        $this->assertFalse($this->client1->can('create', CommissionRule::class));
        $this->assertFalse($this->client1->can('update', $this->rule1));
        $this->assertFalse($this->client1->can('delete', $this->rule1));
        $this->assertFalse($this->client1->can('view', $this->rule1));
    }

    public function test_employee_cannot_crud_commission_rules(): void
    {
        $this->assertFalse($this->employee1->can('create', CommissionRule::class));
        $this->assertFalse($this->employee1->can('update', $this->rule1));
        $this->assertFalse($this->employee1->can('delete', $this->rule1));
    }

    public function test_business_admin_can_crud_commission_rules_for_own_business(): void
    {
        $this->assertTrue($this->businessAdmin1->can('create', CommissionRule::class));
        $this->assertTrue($this->businessAdmin1->can('update', $this->rule1));
        $this->assertTrue($this->businessAdmin1->can('delete', $this->rule1));
    }

    public function test_business_admin_cannot_crud_commission_rules_of_other_business(): void
    {
        $ruleOther = CommissionRule::factory()->create(['business_id' => $this->business2->id]);
        $this->assertFalse($this->businessAdmin1->can('update', $ruleOther));
        $this->assertFalse($this->businessAdmin1->can('delete', $ruleOther));
    }
}
