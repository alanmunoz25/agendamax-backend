<?php

declare(strict_types=1);

namespace Tests\Feature\ElectronicInvoice\Web;

use App\Models\Business;
use App\Models\BusinessFeConfig;
use App\Models\Ecf;
use App\Models\NcfRango;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
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

    public function test_dashboard_requires_authentication(): void
    {
        $this->get(route('electronic-invoice.dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_dashboard_returns_empty_state_when_no_config(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('electronic-invoice.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('ElectronicInvoice/Dashboard')
            ->where('config', null)
            ->where('kpis', null)
            ->where('alerts', [])
            ->where('recent_ecfs', [])
        );
    }

    public function test_dashboard_returns_empty_state_when_config_inactive(): void
    {
        BusinessFeConfig::factory()->create(['business_id' => $this->business->id]);
        // factory defaults activo=false

        $response = $this->actingAs($this->admin)
            ->get(route('electronic-invoice.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('ElectronicInvoice/Dashboard')
            ->where('config', null)
        );
    }

    public function test_dashboard_renders_kpis_when_config_active(): void
    {
        $config = BusinessFeConfig::factory()->create(['business_id' => $this->business->id]);
        $config->forceFill(['activo' => true, 'ambiente' => 'TestECF'])->save();

        $response = $this->actingAs($this->admin)
            ->get(route('electronic-invoice.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('ElectronicInvoice/Dashboard')
            ->has('kpis')
            ->has('kpis.ecfs_today')
            ->has('kpis.accepted_this_month')
            ->has('kpis.in_process')
            ->has('kpis.rejected_this_month')
            ->has('config')
            ->where('config.activo', true)
            ->where('config.ambiente', 'TestECF')
        );
    }

    public function test_dashboard_includes_recent_ecfs(): void
    {
        $config = BusinessFeConfig::factory()->create(['business_id' => $this->business->id]);
        $config->forceFill(['activo' => true])->save();

        Ecf::factory()->count(3)->create(['business_id' => $this->business->id]);

        $response = $this->actingAs($this->admin)
            ->get(route('electronic-invoice.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('recent_ecfs', 3)
        );
    }

    public function test_dashboard_alerts_when_certificate_expiring_soon(): void
    {
        $config = BusinessFeConfig::factory()->create(['business_id' => $this->business->id]);
        $config->forceFill([
            'activo' => true,
            'fecha_vigencia_cert' => now()->addDays(10)->toDateString(),
        ])->save();

        $response = $this->actingAs($this->admin)
            ->get(route('electronic-invoice.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('alerts', 1)
            ->where('alerts.0.type', 'cert_expiring')
        );
    }

    public function test_dashboard_alerts_when_certificate_expired(): void
    {
        $config = BusinessFeConfig::factory()->create(['business_id' => $this->business->id]);
        $config->forceFill([
            'activo' => true,
            'fecha_vigencia_cert' => now()->subDay()->toDateString(),
        ])->save();

        $response = $this->actingAs($this->admin)
            ->get(route('electronic-invoice.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('alerts', 1)
            ->where('alerts.0.type', 'cert_expired')
        );
    }

    public function test_dashboard_alerts_when_sequence_low(): void
    {
        $config = BusinessFeConfig::factory()->create(['business_id' => $this->business->id]);
        $config->forceFill(['activo' => true])->save();

        // Create sequence with only 10 remaining
        NcfRango::factory()->create([
            'business_id' => $this->business->id,
            'tipo_ecf' => 31,
            'secuencia_desde' => 1,
            'secuencia_hasta' => 1000,
            'proximo_secuencial' => 992,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('electronic-invoice.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('alerts', 1)
            ->where('alerts.0.type', 'low_sequence')
        );
    }

    public function test_dashboard_enforces_business_isolation(): void
    {
        $otherBusiness = Business::factory()->create();
        $otherAdmin = User::factory()->create([
            'business_id' => $otherBusiness->id,
            'role' => 'business_admin',
            'email_verified_at' => now(),
        ]);

        $config = BusinessFeConfig::factory()->create(['business_id' => $this->business->id]);
        $config->forceFill(['activo' => true])->save();
        Ecf::factory()->count(5)->create(['business_id' => $this->business->id]);

        // Other admin sees empty data — their business has no config
        $response = $this->actingAs($otherAdmin)
            ->get(route('electronic-invoice.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('config', null)
            ->where('recent_ecfs', [])
        );
    }

    public function test_kpis_count_correctly(): void
    {
        $config = BusinessFeConfig::factory()->create(['business_id' => $this->business->id]);
        $config->forceFill(['activo' => true])->save();

        Ecf::factory()->count(2)->create([
            'business_id' => $this->business->id,
            'status' => 'accepted',
            'fecha_emision' => now()->toDateString(),
        ]);

        Ecf::factory()->create([
            'business_id' => $this->business->id,
            'status' => 'rejected',
            'fecha_emision' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('electronic-invoice.dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('kpis.ecfs_today', 3)
            ->where('kpis.accepted_this_month', 2)
            ->where('kpis.rejected_this_month', 1)
        );
    }
}
