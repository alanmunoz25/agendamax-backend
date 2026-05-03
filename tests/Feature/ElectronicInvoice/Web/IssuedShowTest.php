<?php

declare(strict_types=1);

namespace Tests\Feature\ElectronicInvoice\Web;

use App\Models\Business;
use App\Models\BusinessFeConfig;
use App\Models\Ecf;
use App\Models\EcfAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IssuedShowTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $admin;

    private BusinessFeConfig $feConfig;

    private Ecf $ecf;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create();

        $this->admin = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'business_admin',
            'email_verified_at' => now(),
        ]);

        $this->feConfig = BusinessFeConfig::factory()->create(['business_id' => $this->business->id]);
        $this->feConfig->forceFill(['activo' => true, 'ambiente' => 'TestECF'])->save();

        $this->ecf = Ecf::factory()->create(['business_id' => $this->business->id]);
    }

    public function test_show_requires_authentication(): void
    {
        $this->get(route('electronic-invoice.issued.show', $this->ecf))
            ->assertRedirect(route('login'));
    }

    public function test_show_renders_page_correctly(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('electronic-invoice.issued.show', $this->ecf));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('ElectronicInvoice/Issued/Show')
            ->has('ecf')
            ->has('ecf.id')
            ->has('ecf.numero_ecf')
            ->has('ecf.status')
            ->has('items')
            ->has('audit_logs')
            ->has('config')
            ->has('can')
        );
    }

    public function test_show_returns_not_found_for_other_business_ecf(): void
    {
        $otherBusiness = Business::factory()->create();
        $otherEcf = Ecf::factory()->create(['business_id' => $otherBusiness->id]);

        // BelongsToBusiness global scope causes 404 before the controller's abort_if
        $response = $this->actingAs($this->admin)
            ->get(route('electronic-invoice.issued.show', $otherEcf));

        $response->assertNotFound();
    }

    public function test_show_can_emit_credit_note_when_accepted(): void
    {
        $acceptedEcf = Ecf::factory()->create([
            'business_id' => $this->business->id,
            'status' => 'accepted',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('electronic-invoice.issued.show', $acceptedEcf));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('can.emit_credit_note', true)
            ->where('can.resend', false)
        );
    }

    public function test_show_can_resend_when_error(): void
    {
        $errorEcf = Ecf::factory()->create([
            'business_id' => $this->business->id,
            'status' => 'error',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('electronic-invoice.issued.show', $errorEcf));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('can.emit_credit_note', false)
            ->where('can.resend', true)
        );
    }

    public function test_show_cannot_emit_credit_note_when_draft(): void
    {
        $draftEcf = Ecf::factory()->create([
            'business_id' => $this->business->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('electronic-invoice.issued.show', $draftEcf));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('can.emit_credit_note', false)
            ->where('can.resend', false)
        );
    }

    public function test_show_includes_audit_logs(): void
    {
        EcfAuditLog::factory()->count(3)->create([
            'business_id' => $this->business->id,
            'ecf_id' => $this->ecf->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('electronic-invoice.issued.show', $this->ecf));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('audit_logs', 3)
        );
    }

    public function test_show_enforces_multi_tenant_isolation(): void
    {
        $otherBusiness = Business::factory()->create();
        $otherAdmin = User::factory()->create([
            'business_id' => $otherBusiness->id,
            'role' => 'business_admin',
            'email_verified_at' => now(),
        ]);

        // BelongsToBusiness global scope resolves the ECF under the other admin's business scope
        // causing a 404 (model not found) rather than a 403
        $response = $this->actingAs($otherAdmin)
            ->get(route('electronic-invoice.issued.show', $this->ecf));

        $response->assertNotFound();
    }

    public function test_show_config_includes_ambiente(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('electronic-invoice.issued.show', $this->ecf));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('config.ambiente', 'TestECF')
        );
    }
}
