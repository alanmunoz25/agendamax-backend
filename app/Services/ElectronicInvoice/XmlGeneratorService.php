<?php

declare(strict_types=1);

namespace App\Services\ElectronicInvoice;

use App\Models\Business;
use App\Models\BusinessFeConfig;
use App\Models\Ecf;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\Log;

class XmlGeneratorService
{
    /** ITBIS standard rate */
    private const ITBIS_RATE = '18';

    /** Types that require buyer RNC (crédito fiscal) */
    private const TIPOS_CREDITO_FISCAL = ['31', '33', '34'];

    public function __construct(
        private readonly Business $business,
        private readonly BusinessFeConfig $feConfig
    ) {}

    /**
     * Generates the DGII-compliant XML for an e-CF.
     *
     * @param  Ecf  $ecf  Persisted Ecf model (must belong to this business)
     * @param  array<int, array{name: string, quantity: float, unit_price: float, discount?: float, indicator?: string}>  $items
     * @param  array{tipo_pago?: string, tipo_ingresos?: string, indicador_monto_gravado?: string, fecha_vencimiento?: string, numero_orden?: string}  $options
     *
     * @throws \InvalidArgumentException when business_id mismatch or unsupported tipo
     * @throws \RuntimeException when XML generation fails
     */
    public function generate(Ecf $ecf, array $items, array $options = []): string
    {
        if ($ecf->business_id !== $this->business->id) {
            throw new \InvalidArgumentException(
                "Ecf business_id ({$ecf->business_id}) does not match service business_id ({$this->business->id})."
            );
        }

        Log::info('[XmlGenerator] Generating XML', [
            'business_id' => $this->business->id,
            'ecf_id' => $ecf->id,
            'numero_ecf' => $ecf->numero_ecf,
            'tipo' => $ecf->tipo,
        ]);

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;

        $root = $dom->createElement('ECF');
        $dom->appendChild($root);

        $this->appendEncabezado($dom, $root, $ecf, $options);
        $this->appendDetallesItems($dom, $root, $items, $ecf->tipo);
        $this->appendFechaHoraFirma($dom, $root);

        $xml = $dom->saveXML();

        if ($xml === false) {
            throw new \RuntimeException('XmlGeneratorService: DOMDocument::saveXML() returned false.');
        }

        Log::info('[XmlGenerator] XML generated', [
            'business_id' => $this->business->id,
            'ecf_id' => $ecf->id,
            'xml_length' => strlen($xml),
        ]);

        return $xml;
    }

    private function appendEncabezado(DOMDocument $dom, DOMElement $root, Ecf $ecf, array $options): void
    {
        $enc = $dom->createElement('Encabezado');
        $root->appendChild($enc);

        $this->el($dom, $enc, 'Version', '1.0');

        $this->appendIdDoc($dom, $enc, $ecf, $options);
        $this->appendEmisor($dom, $enc, $ecf);
        $this->appendComprador($dom, $enc, $ecf);
        $this->appendTotales($dom, $enc, $ecf);
    }

    private function appendIdDoc(DOMDocument $dom, DOMElement $enc, Ecf $ecf, array $options): void
    {
        $idDoc = $dom->createElement('IdDoc');
        $enc->appendChild($idDoc);

        $this->el($dom, $idDoc, 'TipoeCF', $ecf->tipo);
        $this->el($dom, $idDoc, 'eNCF', $ecf->numero_ecf);

        if (! empty($options['fecha_vencimiento'])) {
            $this->el($dom, $idDoc, 'FechaVencimientoSecuencia', $options['fecha_vencimiento']);
        }

        $this->el($dom, $idDoc, 'IndicadorEnvioDiferido', '0');
        $this->el($dom, $idDoc, 'IndicadorMontoGravado', $options['indicador_monto_gravado'] ?? '1');
        $this->el($dom, $idDoc, 'TipoIngresos', $options['tipo_ingresos'] ?? '01');
        $this->el($dom, $idDoc, 'TipoPago', $options['tipo_pago'] ?? '1');

        if (! empty($options['numero_orden'])) {
            $this->el($dom, $idDoc, 'NumeroOrdenCompra', $options['numero_orden']);
        }
    }

    private function appendEmisor(DOMDocument $dom, DOMElement $enc, Ecf $ecf): void
    {
        $emisor = $dom->createElement('Emisor');
        $enc->appendChild($emisor);

        $this->el($dom, $emisor, 'RNCEmisor', $this->feConfig->rnc_emisor);
        $this->el($dom, $emisor, 'RazonSocialEmisor', $this->feConfig->razon_social);

        if (! empty($this->feConfig->nombre_comercial)) {
            $this->el($dom, $emisor, 'NombreComercial', $this->feConfig->nombre_comercial);
        }

        $this->el($dom, $emisor, 'Sucursal', '001');

        if (! empty($this->feConfig->direccion)) {
            $this->el($dom, $emisor, 'DireccionEmisor', $this->feConfig->direccion);
        }

        if (! empty($this->feConfig->municipio)) {
            $this->el($dom, $emisor, 'Municipio', $this->feConfig->municipio);
        }

        if (! empty($this->feConfig->provincia)) {
            $this->el($dom, $emisor, 'Provincia', $this->feConfig->provincia);
        }

        if (! empty($this->feConfig->email)) {
            $this->el($dom, $emisor, 'CorreoEmisor', $this->feConfig->email);
        }

        if (! empty($this->feConfig->actividad_economica)) {
            $this->el($dom, $emisor, 'ActividadEconomica', $this->feConfig->actividad_economica);
        }

        $this->el($dom, $emisor, 'NumeroFacturaInterna', (string) $ecf->id);
        $this->el($dom, $emisor, 'FechaEmision', $ecf->fecha_emision->format('d-m-Y'));
    }

    private function appendComprador(DOMDocument $dom, DOMElement $enc, Ecf $ecf): void
    {
        $comprador = $dom->createElement('Comprador');
        $enc->appendChild($comprador);

        if (in_array($ecf->tipo, self::TIPOS_CREDITO_FISCAL, true) && ! empty($ecf->rnc_comprador)) {
            $this->el($dom, $comprador, 'RNCComprador', $ecf->rnc_comprador);
        }

        if (! empty($ecf->razon_social_comprador)) {
            $this->el($dom, $comprador, 'RazonSocialComprador', $ecf->razon_social_comprador);
        } elseif (! empty($ecf->nombre_comprador)) {
            $this->el($dom, $comprador, 'RazonSocialComprador', $ecf->nombre_comprador);
        }
    }

    private function appendTotales(DOMDocument $dom, DOMElement $enc, Ecf $ecf): void
    {
        $totales = $dom->createElement('Totales');
        $enc->appendChild($totales);

        $montoGravado = number_format((float) $ecf->monto_gravado, 2, '.', '');
        $itbisTotal = number_format((float) $ecf->itbis_total, 2, '.', '');
        $montoTotal = number_format((float) $ecf->monto_total, 2, '.', '');

        $this->el($dom, $totales, 'MontoGravadoTotal', $montoGravado);
        $this->el($dom, $totales, 'MontoGravadoI1', $montoGravado);
        $this->el($dom, $totales, 'ITBIS1', self::ITBIS_RATE);
        $this->el($dom, $totales, 'TotalITBIS', $itbisTotal);
        $this->el($dom, $totales, 'TotalITBIS1', $itbisTotal);
        $this->el($dom, $totales, 'MontoTotal', $montoTotal);
        $this->el($dom, $totales, 'MontoPeriodo', $montoTotal);
        $this->el($dom, $totales, 'ValorPagar', $montoTotal);
    }

    /**
     * @param  array<int, array{name: string, quantity: float, unit_price: float, discount?: float, indicator?: string}>  $items
     */
    private function appendDetallesItems(DOMDocument $dom, DOMElement $root, array $items, string $tipo): void
    {
        $detalles = $dom->createElement('DetallesItems');
        $root->appendChild($detalles);

        foreach ($items as $index => $item) {
            $itemEl = $dom->createElement('Item');
            $detalles->appendChild($itemEl);

            $lineNumber = $index + 1;
            $quantity = (float) ($item['quantity'] ?? 1.0);
            $unitPrice = (float) ($item['unit_price'] ?? 0.0);
            $discount = (float) ($item['discount'] ?? 0.0);
            $montoItem = bcmul((string) $quantity, (string) $unitPrice, 2);

            if ($discount > 0) {
                $montoItem = bcsub($montoItem, number_format($discount, 2, '.', ''), 2);
            }

            $this->el($dom, $itemEl, 'NumeroLinea', (string) $lineNumber);
            $this->el($dom, $itemEl, 'IndicadorFacturacion', '1');
            $this->el($dom, $itemEl, 'NombreItem', $item['name']);
            $this->el($dom, $itemEl, 'IndicadorBienoServicio', $item['indicator'] ?? '2');
            $this->el($dom, $itemEl, 'CantidadItem', number_format($quantity, 2, '.', ''));
            $this->el($dom, $itemEl, 'UnidadMedida', '43');
            $this->el($dom, $itemEl, 'PrecioUnitarioItem', number_format($unitPrice, 4, '.', ''));
            $this->el($dom, $itemEl, 'MontoItem', $montoItem);

            if ($discount > 0) {
                $this->el($dom, $itemEl, 'MontoDescuento', number_format($discount, 2, '.', ''));
            }
        }
    }

    private function appendFechaHoraFirma(DOMDocument $dom, DOMElement $root): void
    {
        $this->el($dom, $root, 'FechaHoraFirma', now()->format('d-m-Y H:i:s'));
    }

    /**
     * Appends a text element to a parent node.
     */
    private function el(DOMDocument $dom, DOMElement $parent, string $name, string $value): void
    {
        $node = $dom->createElement($name);
        $node->appendChild($dom->createTextNode($value));
        $parent->appendChild($node);
    }
}
