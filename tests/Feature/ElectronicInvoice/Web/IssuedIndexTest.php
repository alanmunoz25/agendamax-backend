<?php

declare(strict_types=1);

namespace Tests\Feature\ElectronicInvoice\Web;

use App\Models\Business;
use App\Models\BusinessFeConfig;
use App\Models\Ecf;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IssuedIndexTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;

    private User $admin;

    private BusinessFeConfig $feConfig;

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
    }

    public function test_issued_index_requires_authentication(): void
    {
        $this->get(route('electronic-invoice.issued.index'))
            ->assertRedirect(route('login'));
    }

    public function test_issued_index_renders_page(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('electronic-invoice.issued.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('ElectronicInvoice/Issued/Index')
            ->has('ecfs')
            ->has('config')
            ->has('filters')
        );
    }

    public function test_issued_index_shows_only_own_business_ecfs(): void
    {
        $otherBusiness = Business::factory()->create();
        Ecf::factory()->count(3)->create(['business_id' => $this->business->id]);
        Ecf::factory()->count(5)->create(['business_id' => $otherBusiness->id]);

        $response = $this->actingAs($this->admin)
            ->get(route('electronic-invoice.issued.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('ecfs.total', 3)
        );
    }

    public function test_issued_index_paginates_results(): void
    {
        Ecf::factory()->count(25)->create(['business_id' => $this->business->id]);

        $response = $this->actingAs($this->admin)
            ->get(route('electronic-invoice.issued.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('ecfs.per_page', 20)
            ->where('ecfs.total', 25)
        );
    }

    public function test_issued_index_filters_by_status(): void
    {
        Ecf::factory()->count(2)->create([
            'business_id' => $this->business->id,
            'status' => 'accepted',
        ]);
        Ecf::factory()->create([
            'business_id' => $this->business->id,
            'status' => 'rejected',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('electronic-invoice.issued.index', ['status' => 'accepted']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('ecfs.total', 2)
            ->where('filters.status', 'accepted')
        );
    }

    public function test_issued_index_filters_by_tipo(): void
    {
        Ecf::factory()->count(2)->create([
            'business_id' => $this->business->id,
            'tipo' => '31',
        ]);
        Ecf::factory()->create([
            'business_id' => $this->business->id,
            'tipo' => '32',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('electronic-invoice.issued.index', ['tipo' => '31']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('ecfs.total', 2)
            ->where('filters.tipo', '31')
        );
    }

    public function test_issued_index_filters_by_search(): void
    {
        Ecf::factory()->create([
            'business_id' => $this->business->id,
            'numero_ecf' => 'B0100000001',
            'razon_social_comprador' => 'EMPRESA DEMO SRL',
        ]);
        Ecf::factory()->create([
            'business_id' => $this->business->id,
            'numero_ecf' => 'B0100000002',
            'razon_social_comprador' => 'OTRA EMPRESA SA',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('electronic-invoice.issued.index', ['search' => 'DEMO']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('ecfs.total', 1)
        );
    }

    public function test_issued_index_includes_config(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('electronic-invoice.issued.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('config.ambiente', 'TestECF')
            ->where('config.activo', true)
        );
    }

    public function test_issued_index_employee_can_view(): void
    {
        $employeeUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($employeeUser)
            ->get(route('electronic-invoice.issued.index'));

        $response->assertOk();
    }
}
