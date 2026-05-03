<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Models\Business;
use App\Models\PosTicket;
use App\Models\User;
use App\Services\PosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoidTicketGuardTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $cashier;

    private PosService $posService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create();

        $this->cashier = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'business_admin',
        ]);

        $this->posService = app(PosService::class);
    }

    public function test_void_throws_domain_exception_when_ticket_has_ecf_ncf(): void
    {
        $ticket = PosTicket::factory()->create([
            'business_id' => $this->business->id,
            'cashier_id' => $this->cashier->id,
            'status' => 'paid',
        ]);
        $ticket->forceFill(['ecf_ncf' => 'E320000000001'])->save();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/e-CF emitido/');

        $this->posService->voidTicket($ticket, 'Razón de anulación válida.', $this->cashier);
    }

    public function test_void_succeeds_when_ticket_has_no_ecf_ncf(): void
    {
        $ticket = PosTicket::factory()->create([
            'business_id' => $this->business->id,
            'cashier_id' => $this->cashier->id,
            'status' => 'paid',
        ]);

        // Ensure ecf_ncf is null
        $this->assertNull($ticket->ecf_ncf);

        $voided = $this->posService->voidTicket($ticket, 'Anulación por solicitud del cliente.', $this->cashier);

        $this->assertEquals('voided', $voided->status);
        $this->assertNotNull($voided->voided_at);
        $this->assertEquals($this->cashier->id, $voided->voided_by);
    }
}
