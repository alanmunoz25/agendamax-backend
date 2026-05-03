<?php

declare(strict_types=1);

namespace Tests\Feature\ElectronicInvoice\Web;

use App\Models\Business;
use App\Models\BusinessFeConfig;
use App\Models\NcfRango;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IssuedCreateTest extends TestCase
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

    public function test_create_requires_authentication(): void
    {
        $this->get(route('electronic-invoice.issued.create'))
            ->assertRedirect(route('login'));
    }

    public function test_create_renders_page_with_sequences_and_services(): void
    {
        NcfRango::factory()->create([
            'business_id' => $this->business->id,
            'tipo_ecf' => 31,
            'status' => 'active',
        ]);

        Service::factory()->create([
            'business_id' => $this->business->id,
            'is_active' => true,
            'name' => 'Corte de Cabello',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('electronic-invoice.issued.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('ElectronicInvoice/Issued/Create')
            ->has('sequences')
            ->has('services')
            ->has('config')
        );
    }

    public function test_create_shows_only_active_services_of_own_business(): void
    {
        $otherBusiness = Business::factory()->create();

        Service::factory()->count(2)->create([
            'business_id' => $this->business->id,
            'is_active' => true,
        ]);
        Service::factory()->create([
            'business_id' => $this->business->id,
            'is_active' => false,
        ]);
        Service::factory()->create([
            'business_id' => $otherBusiness->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('electronic-invoice.issued.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('services', 2)
        );
    }

    public function test_store_requires_authentication(): void
    {
        $this->post(route('electronic-invoice.issued.store'), [])
            ->assertRedirect(route('login'));
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('electronic-invoice.issued.store'), []);

        $response->assertSessionHasErrors(['tipo_ecf', 'items']);
    }

    public function test_store_validates_item_structure(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('electronic-invoice.issued.store'), [
                'tipo_ecf' => '31',
                'items' => [
                    ['description' => '', 'qty' => 0, 'unit_price' => -1],
                ],
            ]);

        $response->assertSessionHasErrors();
    }

    public function test_store_fails_when_no_active_sequence(): void
    {
        Http::fake();

        // Valid payload but no NcfRango sequence exists for tipo 31
        $response = $this->actingAs($this->admin)
            ->post(route('electronic-invoice.issued.store'), [
                'tipo_ecf' => '31',
                'tipo_pago' => 'contado',
                'indicador_monto_gravado' => 1,
                'items' => [
                    ['description' => 'Servicio', 'qty' => 1, 'unit_price' => 1000, 'discount_pct' => 0],
                ],
            ]);

        $response->assertSessionHas('error');
    }

    public function test_store_fails_when_config_inactive(): void
    {
        $this->feConfig->forceFill(['activo' => false])->save();

        NcfRango::factory()->create([
            'business_id' => $this->business->id,
            'tipo_ecf' => 31,
            'status' => 'active',
        ]);

        // FormRequest authorize() returns false (403) when config is inactive
        $response = $this->actingAs($this->admin)
            ->post(route('electronic-invoice.issued.store'), [
                'tipo_ecf' => '31',
                'tipo_pago' => 'contado',
                'indicador_monto_gravado' => 1,
                'items' => [
                    ['description' => 'Servicio', 'qty' => 1, 'unit_price' => 1000, 'discount_pct' => 0],
                ],
            ]);

        $response->assertForbidden();
    }

    public function test_store_employee_is_forbidden(): void
    {
        $employeeUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($employeeUser)
            ->post(route('electronic-invoice.issued.store'), [
                'tipo_ecf' => '31',
                'items' => [
                    ['description' => 'Servicio', 'qty' => 1, 'unit_price' => 1000, 'discount_pct' => 0],
                ],
            ]);

        $response->assertForbidden();
    }

    public function test_create_config_includes_ambiente(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('electronic-invoice.issued.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('config.ambiente', 'TestECF')
        );
    }
}
