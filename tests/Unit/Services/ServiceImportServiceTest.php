<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Business;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Services\ServiceImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ServiceImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private ServiceImportService $service;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ServiceImportService;
        $this->business = Business::factory()->create();
    }

    public function test_returns_correct_statistics(): void
    {
        $data = $this->getValidImportData();

        $stats = $this->service->import($this->business->id, $data);

        $this->assertEquals(4, $stats['categories_created']);
        $this->assertEquals(0, $stats['categories_updated']);
        $this->assertEquals(5, $stats['services_created']);
        $this->assertEquals(0, $stats['services_updated']);
    }

    public function test_creates_correct_hierarchy(): void
    {
        $data = $this->getValidImportData();

        $this->service->import($this->business->id, $data);

        // Root category "Cabello"
        $cabello = ServiceCategory::withoutGlobalScopes()
            ->where('business_id', $this->business->id)
            ->where('name', 'Cabello')
            ->whereNull('parent_id')
            ->first();

        $this->assertNotNull($cabello);

        // Subcategory "Cortes" under "Cabello"
        $cortes = ServiceCategory::withoutGlobalScopes()
            ->where('business_id', $this->business->id)
            ->where('name', 'Cortes')
            ->where('parent_id', $cabello->id)
            ->first();

        $this->assertNotNull($cortes);

        // Service "Corte Mujer" under "Cortes"
        $this->assertDatabaseHas('services', [
            'business_id' => $this->business->id,
            'name' => 'Corte Mujer',
            'service_category_id' => $cortes->id,
        ]);
    }

    public function test_idempotent_double_import_does_not_duplicate(): void
    {
        $data = $this->getValidImportData();

        $firstStats = $this->service->import($this->business->id, $data);
        $secondStats = $this->service->import($this->business->id, $data);

        // Second import should only update, not create
        $this->assertEquals(0, $secondStats['categories_created']);
        $this->assertEquals(4, $secondStats['categories_updated']);
        $this->assertEquals(0, $secondStats['services_created']);
        $this->assertEquals(5, $secondStats['services_updated']);

        // Total count should not change
        $this->assertEquals(4, ServiceCategory::withoutGlobalScopes()->where('business_id', $this->business->id)->count());
        $this->assertEquals(5, Service::withoutGlobalScopes()->where('business_id', $this->business->id)->count());
    }

    public function test_validation_rejects_invalid_data(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->import($this->business->id, [
            'categories' => [
                [
                    'name' => 'Valid',
                    'services' => [
                        ['name' => 'Missing required fields'],
                    ],
                ],
            ],
        ]);
    }

    public function test_validation_rejects_empty_categories(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->import($this->business->id, ['categories' => []]);
    }

    public function test_validation_rejects_missing_categories_key(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->import($this->business->id, ['data' => 'wrong']);
    }

    public function test_legacy_category_field_populated_with_root_name(): void
    {
        $data = $this->getValidImportData();

        $this->service->import($this->business->id, $data);

        // Services under "Cabello > Cortes" should have category "Cabello"
        $this->assertDatabaseHas('services', [
            'business_id' => $this->business->id,
            'name' => 'Corte Mujer',
            'category' => 'Cabello',
        ]);

        $this->assertDatabaseHas('services', [
            'business_id' => $this->business->id,
            'name' => 'Tinte Completo',
            'category' => 'Cabello',
        ]);

        // Services directly under "Unas" should have category "Unas"
        $this->assertDatabaseHas('services', [
            'business_id' => $this->business->id,
            'name' => 'Manicure',
            'category' => 'Unas',
        ]);
    }

    public function test_services_directly_under_root_category(): void
    {
        $data = $this->getValidImportData();

        $this->service->import($this->business->id, $data);

        $unas = ServiceCategory::withoutGlobalScopes()
            ->where('business_id', $this->business->id)
            ->where('name', 'Unas')
            ->whereNull('parent_id')
            ->first();

        $this->assertNotNull($unas);

        // Manicure should be directly under Unas (no subcategory)
        $this->assertDatabaseHas('services', [
            'business_id' => $this->business->id,
            'name' => 'Manicure',
            'service_category_id' => $unas->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function getValidImportData(): array
    {
        return json_decode(
            file_get_contents(base_path('tests/Fixtures/valid-import.json')),
            true
        );
    }
}
