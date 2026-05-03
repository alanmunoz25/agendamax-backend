<?php

declare(strict_types=1);

namespace Tests\Feature\ElectronicInvoice;

use App\Models\Business;
use App\Models\BusinessFeConfig;
use App\Models\Ecf;
use App\Services\ElectronicInvoice\XmlGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class XmlGeneratorServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, array{name: string, quantity: float, unit_price: float}> */
    private array $sampleItems = [
        ['name' => 'Corte de cabello', 'quantity' => 1.0, 'unit_price' => 500.0],
        ['name' => 'Tinte completo', 'quantity' => 1.0, 'unit_price' => 1500.0],
    ];

    private function makeServices(): array
    {
        $business = Business::factory()->create();
        $config = BusinessFeConfig::factory()->create([
            'business_id' => $business->id,
            'rnc_emisor' => '132036352',
            'razon_social' => 'SALON DE PRUEBA SRL',
            'ambiente' => 'TestECF',
        ]);

        return [$business, $config];
    }

    public function test_generates_valid_xml_for_tipo_32(): void
    {
        [$business, $config] = $this->makeServices();

        $ecf = Ecf::factory()->tipo32()->create([
            'business_id' => $business->id,
            'numero_ecf' => 'B020000000001',
            'monto_gravado' => '2000.00',
            'itbis_total' => '360.00',
            'monto_total' => '2360.00',
        ]);

        $generator = new XmlGeneratorService($business, $config);
        $xml = $generator->generate($ecf, $this->sampleItems);

        $this->assertNotEmpty($xml);

        $dom = simplexml_load_string($xml);
        $this->assertNotFalse($dom, 'Generated XML must be valid');

        $this->assertEquals('B020000000001', (string) $dom->Encabezado->IdDoc->eNCF);
        $this->assertEquals('32', (string) $dom->Encabezado->IdDoc->TipoeCF);
        $this->assertEquals('132036352', (string) $dom->Encabezado->Emisor->RNCEmisor);
        $this->assertEquals('SALON DE PRUEBA SRL', (string) $dom->Encabezado->Emisor->RazonSocialEmisor);
        $this->assertEquals('2000.00', (string) $dom->Encabezado->Totales->MontoGravadoTotal);
        $this->assertEquals('360.00', (string) $dom->Encabezado->Totales->TotalITBIS);
        $this->assertEquals('2360.00', (string) $dom->Encabezado->Totales->MontoTotal);
    }

    public function test_generates_valid_xml_for_tipo_31_with_rnc_comprador(): void
    {
        [$business, $config] = $this->makeServices();

        $ecf = Ecf::factory()->create([
            'business_id' => $business->id,
            'numero_ecf' => 'B010000000001',
            'tipo' => '31',
            'rnc_comprador' => '131880681',
            'razon_social_comprador' => 'CLIENTE EMPRESA SRL',
            'monto_gravado' => '10000.00',
            'itbis_total' => '1800.00',
            'monto_total' => '11800.00',
        ]);

        $generator = new XmlGeneratorService($business, $config);
        $xml = $generator->generate($ecf, $this->sampleItems);

        $dom = simplexml_load_string($xml);
        $this->assertNotFalse($dom);

        $this->assertEquals('31', (string) $dom->Encabezado->IdDoc->TipoeCF);
        $this->assertEquals('131880681', (string) $dom->Encabezado->Comprador->RNCComprador);
        $this->assertEquals('CLIENTE EMPRESA SRL', (string) $dom->Encabezado->Comprador->RazonSocialComprador);
    }

    public function test_xml_contains_detail_items(): void
    {
        [$business, $config] = $this->makeServices();
        $ecf = Ecf::factory()->tipo32()->create(['business_id' => $business->id]);

        $generator = new XmlGeneratorService($business, $config);
        $xml = $generator->generate($ecf, $this->sampleItems);

        $dom = simplexml_load_string($xml);
        $this->assertNotFalse($dom);

        $items = $dom->DetallesItems->Item;
        $this->assertCount(2, $items);
        $this->assertEquals('Corte de cabello', (string) $items[0]->NombreItem);
        $this->assertEquals('Tinte completo', (string) $items[1]->NombreItem);
    }

    public function test_xml_contains_fecha_hora_firma(): void
    {
        [$business, $config] = $this->makeServices();
        $ecf = Ecf::factory()->tipo32()->create(['business_id' => $business->id]);

        $generator = new XmlGeneratorService($business, $config);
        $xml = $generator->generate($ecf, $this->sampleItems);

        $dom = simplexml_load_string($xml);
        $this->assertNotFalse($dom);
        $this->assertNotEmpty((string) $dom->FechaHoraFirma);
    }

    public function test_throws_exception_on_business_mismatch(): void
    {
        [$business, $config] = $this->makeServices();
        $otherBusiness = Business::factory()->create();
        $ecf = Ecf::factory()->create(['business_id' => $otherBusiness->id]);

        $this->expectException(\InvalidArgumentException::class);

        $generator = new XmlGeneratorService($business, $config);
        $generator->generate($ecf, $this->sampleItems);
    }

    public function test_xml_root_element_is_ecf(): void
    {
        [$business, $config] = $this->makeServices();
        $ecf = Ecf::factory()->tipo32()->create(['business_id' => $business->id]);

        $generator = new XmlGeneratorService($business, $config);
        $xml = $generator->generate($ecf, $this->sampleItems);

        $dom = simplexml_load_string($xml);
        $this->assertEquals('ECF', $dom->getName());
    }
}
