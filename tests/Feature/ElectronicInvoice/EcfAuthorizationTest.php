<?php

declare(strict_types=1);

namespace Tests\Feature\ElectronicInvoice;

use App\Models\Business;
use App\Models\BusinessFeConfig;
use App\Models\Ecf;
use App\Models\EcfReceived;
use App\Models\NcfRango;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EcfAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Business $business1;

    private Business $business2;

    private User $superAdmin;

    private User $businessAdmin1;

    private User $businessAdmin2;

    private User $employee1;

    private User $client1;

    private BusinessFeConfig $feConfig1;

    private Ecf $ecf1;

    private EcfReceived $received1;

    private NcfRango $sequence1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business1 = Business::factory()->create();
        $this->business2 = Business::factory()->create();

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

        $this->client1 = User::factory()->create([
            'role' => 'client',
            'business_id' => $this->business1->id,
        ]);

        $this->feConfig1 = BusinessFeConfig::factory()->create([
            'business_id' => $this->business1->id,
        ]);

        // forceFill to set guarded fields in factory context
        $this->feConfig1->forceFill(['activo' => true])->save();

        $this->ecf1 = Ecf::factory()->create(['business_id' => $this->business1->id]);

        $this->received1 = EcfReceived::factory()->create(['business_id' => $this->business1->id]);

        // Use a transient NcfRango instance (no DB insert) — we test policy logic directly on the model.
        $this->sequence1 = new NcfRango;
        $this->sequence1->business_id = $this->business1->id;
    }

    // --- EcfPolicy ---

    public function test_employee_cannot_create_ecf(): void
    {
        $this->assertFalse($this->employee1->can('create', Ecf::class));
    }

    public function test_business_admin_can_emit_ecf(): void
    {
        $this->assertTrue($this->businessAdmin1->can('create', Ecf::class));
    }

    public function test_super_admin_can_create_ecf(): void
    {
        $this->assertTrue($this->superAdmin->can('create', Ecf::class));
    }

    public function test_employee_can_view_ecf_of_own_business(): void
    {
        $this->assertTrue($this->employee1->can('view', $this->ecf1));
    }

    public function test_employee_cannot_view_ecf_of_other_business(): void
    {
        $ecfOther = Ecf::factory()->create(['business_id' => $this->business2->id]);
        $this->assertFalse($this->employee1->can('view', $ecfOther));
    }

    public function test_business_admin_cannot_view_ecf_of_other_business(): void
    {
        $ecfOther = Ecf::factory()->create(['business_id' => $this->business2->id]);
        $this->assertFalse($this->businessAdmin1->can('view', $ecfOther));
    }

    public function test_employee_cannot_cancel_ecf(): void
    {
        $this->assertFalse($this->employee1->can('cancel', $this->ecf1));
    }

    public function test_business_admin_can_cancel_own_ecf(): void
    {
        $this->assertTrue($this->businessAdmin1->can('cancel', $this->ecf1));
    }

    public function test_employee_cannot_resend_ecf(): void
    {
        $this->assertFalse($this->employee1->can('resend', $this->ecf1));
    }

    // --- BusinessFeConfigPolicy ---

    public function test_client_cannot_view_business_fe_config(): void
    {
        $this->assertFalse($this->client1->can('view', $this->feConfig1));
    }

    public function test_employee_cannot_view_business_fe_config(): void
    {
        $this->assertFalse($this->employee1->can('view', $this->feConfig1));
    }

    public function test_business_admin_can_view_own_fe_config(): void
    {
        $this->assertTrue($this->businessAdmin1->can('view', $this->feConfig1));
    }

    public function test_business_admin_cannot_view_other_business_fe_config(): void
    {
        $feConfig2 = BusinessFeConfig::factory()->create(['business_id' => $this->business2->id]);
        $this->assertFalse($this->businessAdmin1->can('view', $feConfig2));
    }

    public function test_business_admin_cannot_modify_other_business_config(): void
    {
        $feConfig2 = BusinessFeConfig::factory()->create(['business_id' => $this->business2->id]);
        $this->assertFalse($this->businessAdmin1->can('update', $feConfig2));
    }

    public function test_employee_cannot_update_fe_config(): void
    {
        $this->assertFalse($this->employee1->can('update', $this->feConfig1));
    }

    public function test_employee_cannot_upload_certificate(): void
    {
        $this->assertFalse($this->employee1->can('uploadCertificate', $this->feConfig1));
    }

    public function test_super_admin_cannot_upload_certificate_for_business(): void
    {
        // uploadCertificate is restricted to business_admin only
        $this->assertFalse($this->superAdmin->can('uploadCertificate', $this->feConfig1));
    }

    public function test_business_admin_can_upload_certificate_for_own_config(): void
    {
        $this->assertTrue($this->businessAdmin1->can('uploadCertificate', $this->feConfig1));
    }

    // --- NcfRangoPolicy ---

    public function test_employee_cannot_modify_sequences(): void
    {
        $this->assertFalse($this->employee1->can('create', NcfRango::class));
        $this->assertFalse($this->employee1->can('update', $this->sequence1));
        $this->assertFalse($this->employee1->can('delete', $this->sequence1));
    }

    public function test_super_admin_cannot_create_sequence_via_policy(): void
    {
        // super_admin is allowed to view but not create (only business_admin creates ranges)
        $this->assertFalse($this->superAdmin->can('create', NcfRango::class));
    }

    public function test_business_admin_can_crud_own_sequences(): void
    {
        $this->assertTrue($this->businessAdmin1->can('create', NcfRango::class));
        $this->assertTrue($this->businessAdmin1->can('update', $this->sequence1));
        $this->assertTrue($this->businessAdmin1->can('delete', $this->sequence1));
    }

    // --- EcfReceivedPolicy ---

    public function test_business_admin_can_approve_and_reject_received_ecf(): void
    {
        $this->assertTrue($this->businessAdmin1->can('approve', $this->received1));
        $this->assertTrue($this->businessAdmin1->can('reject', $this->received1));
    }

    public function test_employee_cannot_approve_received_ecf(): void
    {
        $this->assertFalse($this->employee1->can('approve', $this->received1));
    }

    public function test_business_admin_cannot_approve_received_ecf_of_other_business(): void
    {
        $receivedOther = EcfReceived::factory()->create(['business_id' => $this->business2->id]);
        $this->assertFalse($this->businessAdmin1->can('approve', $receivedOther));
    }

    // --- StoreIssuedEcfRequest authorize integration (HTTP layer) ---

    public function test_employee_cannot_post_to_issued_store(): void
    {
        $this->actingAs($this->employee1)
            ->post(route('electronic-invoice.issued.store'), [])
            ->assertStatus(403);
    }

    public function test_client_cannot_post_to_issued_store(): void
    {
        $this->actingAs($this->client1)
            ->post(route('electronic-invoice.issued.store'), [])
            ->assertStatus(403);
    }
}
