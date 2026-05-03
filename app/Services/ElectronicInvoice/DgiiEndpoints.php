<?php

declare(strict_types=1);

namespace App\Services\ElectronicInvoice;

/**
 * Centralises all DGII endpoint URLs per environment.
 *
 * Environments:
 *   TestECF  → Development / testing
 *   CertECF  → Pre-production certification
 *   ECF      → Production
 *
 * IMPORTANT: DGII uses specific casing in URL paths:
 *   TestECF  → /TesteCF/
 *   CertECF  → /CerteCF/
 *   ECF      → /eCF/
 */
class DgiiEndpoints
{
    /**
     * Base URL for authentication (without /Semilla or /ValidarSemilla).
     */
    public static function getAutenticacionBaseUrl(string $ambiente = 'TestECF'): string
    {
        return match (strtolower($ambiente)) {
            'testecf' => 'https://ecf.dgii.gov.do/TesteCF/Autenticacion/api/Autenticacion',
            'certecf' => 'https://ecf.dgii.gov.do/CerteCF/Autenticacion/api/Autenticacion',
            'ecf', 'produccion' => 'https://ecf.dgii.gov.do/eCF/Autenticacion/api/Autenticacion',
            default => 'https://ecf.dgii.gov.do/TesteCF/Autenticacion/api/Autenticacion',
        };
    }

    /**
     * Endpoint for sending electronic invoices (types 31-47, except type 32 < RD$250k).
     */
    public static function getRecepcionEndpoint(string $ambiente = 'TestECF'): string
    {
        return match (strtolower($ambiente)) {
            'testecf' => 'https://ecf.dgii.gov.do/testecf/recepcion/api/FacturasElectronicas',
            'certecf' => 'https://ecf.dgii.gov.do/certecf/recepcion/api/FacturasElectronicas',
            'ecf', 'produccion' => 'https://ecf.dgii.gov.do/ecf/recepcion/api/FacturasElectronicas',
            default => 'https://ecf.dgii.gov.do/testecf/recepcion/api/FacturasElectronicas',
        };
    }

    /**
     * Endpoint for receiving type-32 summary invoices under RD$250,000.
     * Uses fc.dgii.gov.do domain (different from ecf.dgii.gov.do).
     */
    public static function getRecepcionResumenEndpoint(string $ambiente = 'TestECF'): string
    {
        return match (strtolower($ambiente)) {
            'testecf' => 'https://fc.dgii.gov.do/testecf/recepcionfc/api/recepcion/ecf',
            'certecf' => 'https://fc.dgii.gov.do/certecf/recepcionfc/api/recepcion/ecf',
            'ecf', 'produccion' => 'https://fc.dgii.gov.do/ecf/recepcionfc/api/recepcion/ecf',
            default => 'https://fc.dgii.gov.do/testecf/recepcionfc/api/recepcion/ecf',
        };
    }

    /**
     * Endpoint for querying the status of a sent ECF by TrackID.
     */
    public static function getConsultaEstadoEndpoint(string $ambiente = 'TestECF'): string
    {
        return match (strtolower($ambiente)) {
            'testecf' => 'https://ecf.dgii.gov.do/testecf/consultaresultado/api/Consultas/Estado',
            'certecf' => 'https://ecf.dgii.gov.do/certecf/consultaresultado/api/Consultas/Estado',
            'ecf', 'produccion' => 'https://ecf.dgii.gov.do/ecf/consultaresultado/api/Consultas/Estado',
            default => 'https://ecf.dgii.gov.do/testecf/consultaresultado/api/Consultas/Estado',
        };
    }

    /**
     * Endpoint for querying the status of a summary (RFCE) invoice by TrackID.
     */
    public static function getConsultaEstadoResumenEndpoint(string $ambiente = 'TestECF'): string
    {
        return match (strtolower($ambiente)) {
            'testecf' => 'https://fc.dgii.gov.do/testecf/consulta/api/consulta/estado',
            'certecf' => 'https://fc.dgii.gov.do/certecf/consulta/api/consulta/estado',
            'ecf', 'produccion' => 'https://fc.dgii.gov.do/ecf/consulta/api/consulta/estado',
            default => 'https://fc.dgii.gov.do/testecf/consulta/api/consulta/estado',
        };
    }

    /**
     * Endpoint for consulting TrackIDs by RNC and eNCF.
     */
    public static function getConsultaTrackIdEndpoint(string $ambiente = 'TestECF'): string
    {
        return match (strtolower($ambiente)) {
            'testecf' => 'https://ecf.dgii.gov.do/testecf/consultatrackids/api/TrackIds/Consulta',
            'certecf' => 'https://ecf.dgii.gov.do/certecf/consultatrackids/api/TrackIds/Consulta',
            'ecf', 'produccion' => 'https://ecf.dgii.gov.do/ecf/consultatrackids/api/TrackIds/Consulta',
            default => 'https://ecf.dgii.gov.do/testecf/consultatrackids/api/TrackIds/Consulta',
        };
    }

    /**
     * Endpoint for commercial approval (ACECF).
     */
    public static function getAprobacionComercialEndpoint(string $ambiente = 'TestECF'): string
    {
        return match (strtolower($ambiente)) {
            'testecf' => 'https://ecf.dgii.gov.do/testecf/aprobacioncomercial/api/AprobacionComercial',
            'certecf' => 'https://ecf.dgii.gov.do/certecf/aprobacioncomercial/api/AprobacionComercial',
            'ecf', 'produccion' => 'https://ecf.dgii.gov.do/ecf/aprobacioncomercial/api/AprobacionComercial',
            default => 'https://ecf.dgii.gov.do/testecf/aprobacioncomercial/api/AprobacionComercial',
        };
    }

    /**
     * Human-readable environment name.
     */
    public static function getAmbienteNombre(string $ambiente): string
    {
        return match ($ambiente) {
            'TestECF' => 'Ambiente de Pruebas',
            'CertECF' => 'Ambiente de Certificación',
            'ECF', 'produccion' => 'Producción',
            default => 'Desconocido',
        };
    }

    /**
     * Validates whether the given ambiente string is a recognised DGII environment.
     */
    public static function isValidAmbiente(string $ambiente): bool
    {
        return in_array($ambiente, ['TestECF', 'CertECF', 'ECF', 'produccion'], true);
    }
}
