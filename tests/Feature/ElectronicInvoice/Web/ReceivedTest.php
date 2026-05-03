<?php

declare(strict_types=1);

namespace Tests\Feature\ElectronicInvoice\Web;

use App\Models\Business;
use App\Models\BusinessFeConfig;
use App\Models\EcfReceived;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReceivedTest extends TestCase
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

    public function test_received_index_requires_authentication(): void
    {
        $this->get(route('electronic-invoice.received.index'))
            ->assertRedirect(route('login'));
    }

    public function test_received_index_renders_page(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('electronic-invoice.received.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('ElectronicInvoice/Received/Index')
            ->has('received')
            ->has('config')
            ->has('filters')
        );
    }

    public function test_received_index_shows_only_own_business_received(): void
    {
        $otherBusiness = Business::factory()->create();
        EcfReceived::factory()->count(2)->create(['business_id' => $this->business->id]);
        EcfReceived::factory()->count(4)->create(['business_id' => $otherBusiness->id]);

        $response = $this->actingAs($this->admin)
            ->get(route('electronic-invoice.received.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('received.total', 2)
        );
    }

    public function test_received_index_filters_by_status(): void
    {
        EcfReceived::factory()->count(2)->create([
            'business_id' => $this->business->id,
            'status' => 'pending',
        ]);
        EcfReceived::factory()->create([
            'business_id' => $this->business->id,
            'status' => 'accepted',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('electronic-invoice.received.index', ['status' => 'pending']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('received.total', 2)
        );
    }

    public function test_received_index_filters_by_search(): void
    {
        EcfReceived::factory()->create([
            'business_id' => $this->business->id,
            'razon_social_emisor' => 'PROVEEDOR DEMO SRL',
        ]);
        EcfReceived::factory()->create([
            'business_id' => $this->business->id,
            'razon_social_emisor' => 'OTRO PROVEEDOR SA',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('electronic-invoice.received.index', ['search' => 'DEMO']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('received.total', 1)
        );
    }

    public function test_received_show_requires_authentication(): void
    {
        $received = EcfReceived::factory()->create(['business_id' => $this->business->id]);

        $this->get(route('electronic-invoice.received.show', $received))
            ->assertRedirect(route('login'));
    }

    public function test_received_show_renders_page(): void
    {
        $received = EcfReceived::factory()->create([
            'business_id' => $this->business->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('electronic-invoice.received.show', $received));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('ElectronicInvoice/Received/Show')
            ->has('received')
            ->has('config')
            ->has('can')
            ->where('can.approve', true)
            ->where('can.reject', true)
        );
    }

    public function test_received_show_not_found_for_other_business(): void
    {
        $otherBusiness = Business::factory()->create();
        $otherReceived = EcfReceived::factory()->create(['business_id' => $otherBusiness->id]);

        // BelongsToBusiness global scope causes 404 before abort_if
        $response = $this->actingAs($this->admin)
            ->get(route('electronic-invoice.received.show', $otherReceived));

        $response->assertNotFound();
    }

    public function test_received_show_cannot_approve_already_accepted(): void
    {
        $received = EcfReceived::factory()->create([
            'business_id' => $this->business->id,
            'status' => 'accepted',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('electronic-invoice.received.show', $received));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('can.approve', false)
            ->where('can.reject', false)
        );
    }

    public function test_approve_sets_status_to_accepted(): void
    {
        Storage::fake();

        // The approve flow calls enviarAcecfAprobacion which signs the ACECF XML.
        // Upgrade feConfig to include a real in-memory certificate so signing succeeds.
        $password = 'received_approve_test';
        $privKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $dn = ['C' => 'DO', 'O' => 'Test SRL', 'CN' => 'test.do'];
        $csr = openssl_csr_new($dn, $privKey, []);
        $cert = openssl_csr_sign($csr, null, $privKey, 365, []);
        openssl_pkcs12_export($cert, $p12Bytes, $privKey, $password);

        $this->feConfig->certificado_digital = base64_encode($p12Bytes);
        $this->feConfig->forceFill([
            'password_certificado' => Crypt::encryptString($password),
            'certificado_convertido' => true,
        ])->save();

        $received = EcfReceived::factory()->create([
            'business_id' => $this->business->id,
            'status' => 'pending',
            'numero_ecf' => 'E310000000001',
            'rnc_emisor' => '101012345',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('electronic-invoice.received.approve', $received));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('ecf_received', [
            'id' => $received->id,
            'status' => 'accepted',
        ]);
    }

    public function test_approve_fails_when_not_pending(): void
    {
        $received = EcfReceived::factory()->create([
            'business_id' => $this->business->id,
            'status' => 'accepted',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('electronic-invoice.received.approve', $received));

        $response->assertSessionHas('error');
    }

    public function test_approve_not_found_for_other_business(): void
    {
        $otherBusiness = Business::factory()->create();
        $otherReceived = EcfReceived::factory()->create([
            'business_id' => $otherBusiness->id,
            'status' => 'pending',
        ]);

        // BelongsToBusiness global scope causes 404 before abort_if
        $response = $this->actingAs($this->admin)
            ->post(route('electronic-invoice.received.approve', $otherReceived));

        $response->assertNotFound();
    }

    public function test_reject_validates_codigo_rechazo(): void
    {
        $received = EcfReceived::factory()->create([
            'business_id' => $this->business->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('electronic-invoice.received.reject', $received), []);

        $response->assertSessionHasErrors(['codigo_rechazo']);
    }

    public function test_reject_fails_when_not_pending(): void
    {
        $received = EcfReceived::factory()->create([
            'business_id' => $this->business->id,
            'status' => 'rejected',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('electronic-invoice.received.reject', $received), [
                'codigo_rechazo' => 1,
            ]);

        $response->assertSessionHas('error');
    }

    public function test_reject_not_found_for_other_business(): void
    {
        $otherBusiness = Business::factory()->create();
        $otherReceived = EcfReceived::factory()->create([
            'business_id' => $otherBusiness->id,
            'status' => 'pending',
        ]);

        // BelongsToBusiness global scope causes 404 before abort_if
        $response = $this->actingAs($this->admin)
            ->post(route('electronic-invoice.received.reject', $otherReceived), [
                'codigo_rechazo' => 1,
            ]);

        $response->assertNotFound();
    }

    public function test_received_enforces_multi_tenant_isolation(): void
    {
        $otherBusiness = Business::factory()->create();
        $otherAdmin = User::factory()->create([
            'business_id' => $otherBusiness->id,
            'role' => 'business_admin',
            'email_verified_at' => now(),
        ]);

        EcfReceived::factory()->count(3)->create(['business_id' => $this->business->id]);

        $response = $this->actingAs($otherAdmin)
            ->get(route('electronic-invoice.received.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('received.total', 0)
        );
    }
}
