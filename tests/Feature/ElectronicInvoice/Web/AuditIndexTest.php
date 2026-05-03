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

class AuditIndexTest extends TestCase
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

        $feConfig = BusinessFeConfig::factory()->create(['business_id' => $this->business->id]);
        $feConfig->forceFill(['activo' => true])->save();
    }

    public function test_audit_index_requires_authentication(): void
    {
        $this->get(route('electronic-invoice.audit.index'))
            ->assertRedirect(route('login'));
    }

    public function test_audit_index_renders_page(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('electronic-invoice.audit.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('ElectronicInvoice/Audit/Index')
            ->has('logs')
            ->has('actions')
            ->has('filters')
        );
    }

    public function test_audit_index_shows_only_own_business_logs(): void
    {
        $otherBusiness = Business::factory()->create();
        $ownEcf = Ecf::factory()->create(['business_id' => $this->business->id]);
        $otherEcf = Ecf::factory()->create(['business_id' => $otherBusiness->id]);

        EcfAuditLog::factory()->count(3)->create([
            'business_id' => $this->business->id,
            'ecf_id' => $ownEcf->id,
        ]);
        EcfAuditLog::factory()->count(5)->create([
            'business_id' => $otherBusiness->id,
            'ecf_id' => $otherEcf->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('electronic-invoice.audit.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('logs.total', 3)
        );
    }

    public function test_audit_index_filters_by_action(): void
    {
        $ecf = Ecf::factory()->create(['business_id' => $this->business->id]);

        EcfAuditLog::factory()->count(2)->create([
            'business_id' => $this->business->id,
            'ecf_id' => $ecf->id,
            'action' => 'sign',
        ]);
        EcfAuditLog::factory()->create([
            'business_id' => $this->business->id,
            'ecf_id' => $ecf->id,
            'action' => 'send',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('electronic-invoice.audit.index', ['action' => 'sign']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('logs.total', 2)
            ->where('filters.action', 'sign')
        );
    }

    public function test_audit_index_filters_by_ecf_id(): void
    {
        $ecf1 = Ecf::factory()->create(['business_id' => $this->business->id]);
        $ecf2 = Ecf::factory()->create(['business_id' => $this->business->id]);

        EcfAuditLog::factory()->count(2)->create([
            'business_id' => $this->business->id,
            'ecf_id' => $ecf1->id,
        ]);
        EcfAuditLog::factory()->create([
            'business_id' => $this->business->id,
            'ecf_id' => $ecf2->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('electronic-invoice.audit.index', ['ecf_id' => $ecf1->id]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('logs.total', 2)
        );
    }

    public function test_audit_index_paginates_at_50(): void
    {
        $ecf = Ecf::factory()->create(['business_id' => $this->business->id]);

        EcfAuditLog::factory()->count(60)->create([
            'business_id' => $this->business->id,
            'ecf_id' => $ecf->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('electronic-invoice.audit.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('logs.per_page', 50)
            ->where('logs.total', 60)
        );
    }

    public function test_audit_index_includes_distinct_actions(): void
    {
        $ecf = Ecf::factory()->create(['business_id' => $this->business->id]);

        EcfAuditLog::factory()->create([
            'business_id' => $this->business->id,
            'ecf_id' => $ecf->id,
            'action' => 'sign',
        ]);
        EcfAuditLog::factory()->create([
            'business_id' => $this->business->id,
            'ecf_id' => $ecf->id,
            'action' => 'send',
        ]);
        EcfAuditLog::factory()->create([
            'business_id' => $this->business->id,
            'ecf_id' => $ecf->id,
            'action' => 'sign',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('electronic-invoice.audit.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('actions', 2)
        );
    }

    public function test_audit_enforces_multi_tenant_isolation(): void
    {
        $otherBusiness = Business::factory()->create();
        $otherAdmin = User::factory()->create([
            'business_id' => $otherBusiness->id,
            'role' => 'business_admin',
            'email_verified_at' => now(),
        ]);

        $ecf = Ecf::factory()->create(['business_id' => $this->business->id]);
        EcfAuditLog::factory()->count(5)->create([
            'business_id' => $this->business->id,
            'ecf_id' => $ecf->id,
        ]);

        $response = $this->actingAs($otherAdmin)
            ->get(route('electronic-invoice.audit.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('logs.total', 0)
        );
    }
}
