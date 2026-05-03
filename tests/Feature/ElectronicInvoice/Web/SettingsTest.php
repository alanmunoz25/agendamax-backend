<?php

declare(strict_types=1);

namespace Tests\Feature\ElectronicInvoice\Web;

use App\Models\Business;
use App\Models\BusinessFeConfig;
use App\Models\NcfRango;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
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

    public function test_settings_requires_authentication(): void
    {
        $this->get(route('electronic-invoice.settings'))
            ->assertRedirect(route('login'));
    }

    public function test_settings_renders_onboarding_when_no_config(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('electronic-invoice.settings'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('ElectronicInvoice/Settings')
            ->where('feConfig', null)
            ->has('sequences')
        );
    }

    public function test_settings_renders_with_existing_config(): void
    {
        $feConfig = BusinessFeConfig::factory()->create(['business_id' => $this->business->id]);

        $response = $this->actingAs($this->admin)
            ->get(route('electronic-invoice.settings'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('ElectronicInvoice/Settings')
            ->has('feConfig')
            ->has('feConfig.rnc_emisor')
            ->has('feConfig.razon_social')
            ->has('feConfig.ambiente')
            ->has('feConfig.activo')
            ->has('feConfig.has_certificate')
        );
    }

    public function test_settings_includes_sequences(): void
    {
        NcfRango::factory()->count(3)->create(['business_id' => $this->business->id]);

        $response = $this->actingAs($this->admin)
            ->get(route('electronic-invoice.settings'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('sequences', 3)
        );
    }

    public function test_settings_only_shows_own_business_sequences(): void
    {
        $otherBusiness = Business::factory()->create();
        NcfRango::factory()->count(2)->create(['business_id' => $this->business->id]);
        NcfRango::factory()->count(5)->create(['business_id' => $otherBusiness->id]);

        $response = $this->actingAs($this->admin)
            ->get(route('electronic-invoice.settings'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('sequences', 2)
        );
    }

    public function test_settings_update_requires_authentication(): void
    {
        $this->put(route('electronic-invoice.settings.update'), [])
            ->assertRedirect(route('login'));
    }

    public function test_settings_update_validates_required_fields(): void
    {
        $response = $this->actingAs($this->admin)
            ->put(route('electronic-invoice.settings.update'), []);

        $response->assertSessionHasErrors(['rnc_emisor', 'razon_social', 'ambiente']);
    }

    public function test_settings_update_validates_ambiente_enum(): void
    {
        $response = $this->actingAs($this->admin)
            ->put(route('electronic-invoice.settings.update'), [
                'rnc_emisor' => '132036352',
                'razon_social' => 'EMPRESA DEMO SRL',
                'ambiente' => 'INVALID_ENV',
            ]);

        $response->assertSessionHasErrors(['ambiente']);
    }

    public function test_settings_update_creates_config_when_none_exists(): void
    {
        $response = $this->actingAs($this->admin)
            ->put(route('electronic-invoice.settings.update'), [
                'rnc_emisor' => '132036352',
                'razon_social' => 'EMPRESA DEMO SRL',
                'ambiente' => 'TestECF',
                'activo' => false,
            ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('business_fe_configs', [
            'business_id' => $this->business->id,
            'rnc_emisor' => '132036352',
        ]);
    }

    public function test_settings_update_updates_existing_config(): void
    {
        $feConfig = BusinessFeConfig::factory()->create([
            'business_id' => $this->business->id,
            'rnc_emisor' => '111111111',
        ]);

        $response = $this->actingAs($this->admin)
            ->put(route('electronic-invoice.settings.update'), [
                'rnc_emisor' => '999999999',
                'razon_social' => 'EMPRESA ACTUALIZADA SRL',
                'ambiente' => 'TestECF',
                'activo' => false,
            ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('business_fe_configs', [
            'id' => $feConfig->id,
            'rnc_emisor' => '999999999',
        ]);
    }

    public function test_settings_update_forbidden_for_employee(): void
    {
        $employeeUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($employeeUser)
            ->put(route('electronic-invoice.settings.update'), [
                'rnc_emisor' => '132036352',
                'razon_social' => 'EMPRESA DEMO SRL',
                'ambiente' => 'TestECF',
            ]);

        $response->assertForbidden();
    }

    public function test_settings_update_enforces_business_isolation(): void
    {
        $otherBusiness = Business::factory()->create();
        $otherAdmin = User::factory()->create([
            'business_id' => $otherBusiness->id,
            'role' => 'business_admin',
            'email_verified_at' => now(),
        ]);

        BusinessFeConfig::factory()->create(['business_id' => $this->business->id]);

        $this->actingAs($otherAdmin)
            ->put(route('electronic-invoice.settings.update'), [
                'rnc_emisor' => '999999999',
                'razon_social' => 'ATACANTE SRL',
                'ambiente' => 'TestECF',
            ]);

        // Our config should remain unchanged
        $this->assertDatabaseMissing('business_fe_configs', [
            'business_id' => $this->business->id,
            'rnc_emisor' => '999999999',
        ]);
    }

    public function test_test_connectivity_requires_fe_config(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('electronic-invoice.settings.test-connectivity'));

        $response->assertSessionHas('error');
    }

    public function test_upload_certificate_forbidden_for_employee(): void
    {
        $employeeUser = User::factory()->create([
            'business_id' => $this->business->id,
            'role' => 'employee',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($employeeUser)
            ->post(route('electronic-invoice.settings.upload-certificate'), []);

        $response->assertForbidden();
    }

    public function test_upload_certificate_validates_file_required(): void
    {
        BusinessFeConfig::factory()->create(['business_id' => $this->business->id]);

        $response = $this->actingAs($this->admin)
            ->post(route('electronic-invoice.settings.upload-certificate'), []);

        $response->assertSessionHasErrors(['certificate', 'password']);
    }
}
