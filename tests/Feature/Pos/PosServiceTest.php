<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Jobs\EmitEcfJob;
use App\Models\Business;
use App\Models\CommissionRecord;
use App\Models\CommissionRule;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\Service;
use App\Models\Tip;
use App\Models\User;
use App\Services\PosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PosServiceTest extends TestCase
{
    use RefreshDatabase;

    private PosService $posService;

    private Business $business;

    private User $cashier;

    private Employee $employee;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->posService = app(PosService::class);

        $this->business = Business::factory()->create(['pos_commissions_enabled' => true]);

        $this->cashier = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'business_admin',
        ]);

        $employeeUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
        ]);

        $this->employee = Employee::factory()->create([
            'business_id' => $this->business->id,
            'user_id' => $employeeUser->id,
            'is_active' => true,
        ]);

        $this->service = Service::factory()->create([
            'business_id' => $this->business->id,
            'price' => '850.00',
            'is_active' => true,
        ]);
    }

    /**
     * Helper: build a minimal ticket payload.
     *
     * @return array<string, mixed>
     */
    private function buildTicketPayload(array $overrides = []): array
    {
        return array_merge([
            'appointment_id' => null,
            'client_id' => null,
            'client_name' => 'Walk-in Cliente',
            'employee_id' => $this->employee->id,
            'items' => [
                [
                    'type' => 'service',
                    'item_id' => $this->service->id,
                    'name' => $this->service->name,
                    'unit_price' => '850.00',
                    'qty' => 1,
                    'employee_id' => $this->employee->id,
                ],
            ],
            'discount_amount' => '0',
            'itbis_pct' => '18',
            'tip_amount' => '0',
            'payments' => [
                ['method' => 'cash', 'amount' => '1003.00', 'cash_tendered' => '1100.00'],
            ],
            'ecf_requested' => false,
        ], $overrides);
    }

    /** @test */
    public function test_create_ticket_creates_tip_record(): void
    {
        $payload = $this->buildTicketPayload(['tip_amount' => '127.50']);

        $ticket = $this->posService->createTicket($payload, $this->cashier);

        $this->assertEquals('127.50', $ticket->tip_amount);

        $tip = Tip::withoutGlobalScopes()->where('business_id', $this->business->id)->first();
        $this->assertNotNull($tip);
        $this->assertEquals($this->employee->id, $tip->employee_id);
        $this->assertEquals('127.50', $tip->amount);
        $this->assertEquals('cash', $tip->payment_method);
    }

    /** @test */
    public function test_walkin_generates_commission_when_enabled(): void
    {
        $this->business->update(['pos_commissions_enabled' => true]);

        // Create an open payroll period
        PayrollPeriod::factory()->create([
            'business_id' => $this->business->id,
            'status' => 'open',
            'starts_on' => now()->startOfMonth(),
            'ends_on' => now()->endOfMonth(),
        ]);

        // Create a commission rule
        CommissionRule::factory()->create([
            'business_id' => $this->business->id,
            'employee_id' => null,
            'service_id' => null,
            'type' => 'percentage',
            'value' => '10.00',
            'is_active' => true,
        ]);

        $payload = $this->buildTicketPayload();

        $this->posService->createTicket($payload, $this->cashier);

        $commission = CommissionRecord::withoutGlobalScopes()
            ->where('business_id', $this->business->id)
            ->where('employee_id', $this->employee->id)
            ->whereNull('appointment_id')
            ->first();

        $this->assertNotNull($commission);
        $this->assertEquals('85.00', $commission->commission_amount);
    }

    /** @test */
    public function test_walkin_no_commission_when_disabled(): void
    {
        $this->business->update(['pos_commissions_enabled' => false]);

        $payload = $this->buildTicketPayload();

        $this->posService->createTicket($payload, $this->cashier);

        $commissionCount = CommissionRecord::withoutGlobalScopes()
            ->where('business_id', $this->business->id)
            ->count();

        $this->assertEquals(0, $commissionCount);
    }

    /** @test */
    public function test_ecf_job_dispatched_when_requested(): void
    {
        Queue::fake();

        $payload = $this->buildTicketPayload([
            'ecf_requested' => true,
            'ecf_type' => 'consumidor_final',
        ]);

        $this->posService->createTicket($payload, $this->cashier);

        Queue::assertPushed(EmitEcfJob::class);
    }

    /** @test */
    public function test_ecf_job_not_dispatched_when_not_requested(): void
    {
        Queue::fake();

        $payload = $this->buildTicketPayload(['ecf_requested' => false]);

        $this->posService->createTicket($payload, $this->cashier);

        Queue::assertNotPushed(EmitEcfJob::class);
    }

    /** @test */
    public function test_ticket_number_is_generated_correctly(): void
    {
        $payload = $this->buildTicketPayload();

        $ticket = $this->posService->createTicket($payload, $this->cashier);

        $expectedNumber = 'TKT-'.now()->year.'-'.str_pad((string) $ticket->id, 4, '0', STR_PAD_LEFT);
        $this->assertEquals($expectedNumber, $ticket->ticket_number);
    }

    /** @test */
    public function test_mixed_payment_creates_multiple_payment_records(): void
    {
        $payload = $this->buildTicketPayload([
            'payments' => [
                ['method' => 'cash', 'amount' => '500.00'],
                ['method' => 'card', 'amount' => '503.00', 'reference' => 'AUTH-1234'],
            ],
        ]);

        $ticket = $this->posService->createTicket($payload, $this->cashier);

        $this->assertCount(2, $ticket->payments);
        $this->assertEquals('cash', $ticket->payments->firstWhere('method', 'cash')->method);
        $this->assertEquals('card', $ticket->payments->firstWhere('method', 'card')->method);
    }
}
