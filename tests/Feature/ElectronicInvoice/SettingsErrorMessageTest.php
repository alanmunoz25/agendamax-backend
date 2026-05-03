<?php

declare(strict_types=1);

namespace Tests\Feature\ElectronicInvoice;

use App\Models\Business;
use App\Models\BusinessFeConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsErrorMessageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create();

        $this->admin = User::factory()->create([
            'role' => 'business_admin',
            'business_id' => $this->business->id,
        ]);

        BusinessFeConfig::factory()->create(['business_id' => $this->business->id]);

        Storage::fake('local');
    }

    /**
     * BLOCK-010: An invalid cert password must return a generic user-facing error,
     * not expose the raw OpenSSL/exception message.
     */
    public function test_invalid_cert_password_returns_generic_error_message(): void
    {
        // Upload a fake file that is not a real .p12 cert — OpenSSL will fail
        $fakeFile = UploadedFile::fake()->create('cert.p12', 10, 'application/x-pkcs12');

        $response = $this->actingAs($this->admin)
            ->post(route('electronic-invoice.settings.upload-certificate'), [
                'certificate' => $fakeFile,
                'password' => 'wrong-password',
            ]);

        // Must redirect back with validation error (not raw exception)
        $response->assertRedirect();
        $response->assertSessionHasErrors('certificate');

        // The error message must be generic — no internal paths or OpenSSL details
        $errors = session('errors');
        if ($errors) {
            $certError = $errors->get('certificate')[0] ?? '';
            $this->assertStringNotContainsString('/var/', $certError);
            $this->assertStringNotContainsString('openssl', strtolower($certError));
            $this->assertStringNotContainsString('exception', strtolower($certError));
        }
    }

    /**
     * BLOCK-010: The internal technical error must be logged, not exposed to the user.
     */
    public function test_internal_openssl_error_logged_not_exposed(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return str_contains($message, 'Certificate upload/conversion failed')
                    && isset($context['business_id'])
                    && isset($context['error']);
            });

        $fakeFile = UploadedFile::fake()->create('cert.p12', 10, 'application/x-pkcs12');

        $this->actingAs($this->admin)
            ->post(route('electronic-invoice.settings.upload-certificate'), [
                'certificate' => $fakeFile,
                'password' => 'bad-pass',
            ]);
    }
}
