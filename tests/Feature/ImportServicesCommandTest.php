<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportServicesCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $validFixture;

    private string $invalidFixture;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validFixture = base_path('tests/Fixtures/valid-import.json');
        $this->invalidFixture = base_path('tests/Fixtures/invalid-import.json');
        $this->business = Business::factory()->create();
    }

    public function test_imports_categories_and_services_correctly(): void
    {
        $this->artisan('services:import', [
            'file' => $this->validFixture,
            '--business-id' => $this->business->id,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Import completed successfully!');

        // Root categories
        $this->assertDatabaseCount('service_categories', 4); // Cabello, Cortes, Color, Unas
        $this->assertDatabaseHas('service_categories', [
            'business_id' => $this->business->id,
            'parent_id' => null,
            'name' => 'Cabello',
        ]);
        $this->assertDatabaseHas('service_categories', [
            'business_id' => $this->business->id,
            'parent_id' => null,
            'name' => 'Unas',
        ]);

        // Subcategories
        $cabello = ServiceCategory::withoutGlobalScopes()
            ->where('business_id', $this->business->id)
            ->where('name', 'Cabello')
            ->first();

        $this->assertDatabaseHas('service_categories', [
            'business_id' => $this->business->id,
            'parent_id' => $cabello->id,
            'name' => 'Cortes',
        ]);

        // Services
        $this->assertDatabaseCount('services', 5);
        $this->assertDatabaseHas('services', [
            'business_id' => $this->business->id,
            'name' => 'Corte Mujer',
            'duration' => 45,
            'price' => 65.00,
            'category' => 'Cabello',
        ]);
        $this->assertDatabaseHas('services', [
            'business_id' => $this->business->id,
            'name' => 'Manicure',
            'category' => 'Unas',
        ]);
    }

    public function test_update_or_create_does_not_duplicate(): void
    {
        // Run import twice
        $this->artisan('services:import', [
            'file' => $this->validFixture,
            '--business-id' => $this->business->id,
        ])->assertSuccessful();

        $this->artisan('services:import', [
            'file' => $this->validFixture,
            '--business-id' => $this->business->id,
        ])->assertSuccessful();

        // Should still have same counts (not doubled)
        $this->assertDatabaseCount('service_categories', 4);
        $this->assertDatabaseCount('services', 5);
    }

    public function test_multi_tenant_isolation(): void
    {
        $otherBusiness = Business::factory()->create();

        // Import for business 1
        $this->artisan('services:import', [
            'file' => $this->validFixture,
            '--business-id' => $this->business->id,
        ])->assertSuccessful();

        // Import for business 2
        $this->artisan('services:import', [
            'file' => $this->validFixture,
            '--business-id' => $otherBusiness->id,
        ])->assertSuccessful();

        // Each business should have its own categories and services
        $this->assertEquals(
            4,
            ServiceCategory::withoutGlobalScopes()->where('business_id', $this->business->id)->count()
        );
        $this->assertEquals(
            4,
            ServiceCategory::withoutGlobalScopes()->where('business_id', $otherBusiness->id)->count()
        );
        $this->assertEquals(
            5,
            Service::withoutGlobalScopes()->where('business_id', $this->business->id)->count()
        );
        $this->assertEquals(
            5,
            Service::withoutGlobalScopes()->where('business_id', $otherBusiness->id)->count()
        );
    }

    public function test_fails_with_nonexistent_file(): void
    {
        $this->artisan('services:import', [
            'file' => '/nonexistent/path.json',
            '--business-id' => $this->business->id,
        ])
            ->assertFailed()
            ->expectsOutputToContain('File not found');
    }

    public function test_fails_with_invalid_json(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'import_');
        file_put_contents($tempFile, '{invalid json}');

        $this->artisan('services:import', [
            'file' => $tempFile,
            '--business-id' => $this->business->id,
        ])
            ->assertFailed()
            ->expectsOutputToContain('Invalid JSON');

        unlink($tempFile);
    }

    public function test_fails_with_missing_required_fields(): void
    {
        $this->artisan('services:import', [
            'file' => $this->invalidFixture,
            '--business-id' => $this->business->id,
        ])
            ->assertFailed()
            ->expectsOutputToContain('Validation failed');
    }

    public function test_fails_without_business_id(): void
    {
        $this->artisan('services:import', [
            'file' => $this->validFixture,
        ])
            ->assertFailed()
            ->expectsOutputToContain('--business-id option is required');
    }

    public function test_fails_with_nonexistent_business(): void
    {
        $this->artisan('services:import', [
            'file' => $this->validFixture,
            '--business-id' => 99999,
        ])
            ->assertFailed()
            ->expectsOutputToContain('not found');
    }

    public function test_displays_summary_table(): void
    {
        $this->artisan('services:import', [
            'file' => $this->validFixture,
            '--business-id' => $this->business->id,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Categories Created')
            ->expectsOutputToContain('Services Created');
    }

    public function test_second_import_shows_updated_counts(): void
    {
        // First import
        $this->artisan('services:import', [
            'file' => $this->validFixture,
            '--business-id' => $this->business->id,
        ])->assertSuccessful();

        // Second import should show updates
        $this->artisan('services:import', [
            'file' => $this->validFixture,
            '--business-id' => $this->business->id,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Categories Updated')
            ->expectsOutputToContain('Services Updated');
    }
}
