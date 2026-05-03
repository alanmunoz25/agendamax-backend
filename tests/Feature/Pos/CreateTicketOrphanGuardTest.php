<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Models\Business;
use App\Models\Service;
use App\Models\User;
use App\Services\PosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CreateTicketOrphanGuardTest extends TestCase
{
    use RefreshDatabase;

    private PosService $posService;

    private Business $business;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->posService = app(PosService::class);
        $this->business = Business::factory()->create(['pos_commissions_enabled' => false]);

        $this->service = Service::factory()->create([
            'business_id' => $this->business->id,
            'price' => '500.00',
            'is_active' => true,
        ]);
    }

    /**
     * @return array{
     *   items: array<int, array{type: string, item_id: int, name: string, unit_price: string, qty: int}>,
     *   discount_amount: string,
     *   itbis_pct: string,
     *   tip_amount: string,
     *   payments: array<int, array{method: string, amount: string}>,
     *   ecf_requested: bool
     * }
     */
    private function baseTicketData(array $overrides = []): array
    {
        return array_merge([
            'items' => [
                [
                    'type' => 'service',
                    'item_id' => $this->service->id,
                    'name' => $this->service->name,
                    'unit_price' => '500.00',
                    'qty' => 1,
                ],
            ],
            'discount_amount' => '0.00',
            'itbis_pct' => '18',
            'tip_amount' => '0.00',
            'payments' => [['method' => 'cash', 'amount' => '590.00']],
            'ecf_requested' => false,
        ], $overrides);
    }

    public function test_super_admin_without_business_id_throws_domain_exception(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'business_id' => null]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('business_id is required');

        $this->posService->createTicket($this->baseTicketData(), $superAdmin);
    }

    public function test_super_admin_with_explicit_business_id_creates_ticket(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'business_id' => null]);

        $ticket = $this->posService->createTicket(
            $this->baseTicketData(['business_id' => $this->business->id]),
            $superAdmin
        );

        $this->assertNotNull($ticket->id);
        $this->assertEquals($this->business->id, $ticket->business_id);
        $this->assertEquals('paid', $ticket->status);
    }
}
