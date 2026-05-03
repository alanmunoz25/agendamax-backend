<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Ambiente por defecto
    |--------------------------------------------------------------------------
    | TestECF = Pruebas DGII
    | CertECF = Certificación DGII
    | ECF     = Producción DGII
    */
    'default_ambiente' => env('FE_DEFAULT_AMBIENTE', 'TestECF'),

    /*
    |--------------------------------------------------------------------------
    | Timeouts HTTP
    |--------------------------------------------------------------------------
    */
    'dgii_timeout' => (int) env('FE_DGII_TIMEOUT', 30),
    'dgii_max_retries' => (int) env('FE_DGII_MAX_RETRIES', 3),

    /*
    |--------------------------------------------------------------------------
    | Storage paths (relativo a storage/app/)
    |--------------------------------------------------------------------------
    */
    'storage' => [
        'certificates_path' => 'certificates',
        'ecf_enviados_path' => 'ecf_enviados',
        'ecf_recibidos_path' => 'ecf_recibidos',
        'cache_path' => 'cache',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tipos de e-CF soportados
    |--------------------------------------------------------------------------
    */
    'tipos_ecf' => [
        '31' => 'Factura de Crédito Fiscal Electrónica',
        '32' => 'Factura de Consumo Electrónica',
        '33' => 'Nota de Débito Electrónica',
        '34' => 'Nota de Crédito Electrónica',
        '41' => 'Comprobante de Compras',
        '43' => 'Gastos Menores',
        '44' => 'Regímenes Especiales',
        '45' => 'Gubernamental',
        '46' => 'Exportaciones',
        '47' => 'Pagos al Exterior',
    ],

    /*
    |--------------------------------------------------------------------------
    | Prefijos por tipo de e-CF
    |--------------------------------------------------------------------------
    */
    'prefijos_ecf' => [
        '31' => 'B01',
        '32' => 'B02',
        '33' => 'B03',
        '34' => 'B04',
        '41' => 'B11',
        '43' => 'B13',
        '44' => 'B14',
        '45' => 'B15',
        '46' => 'B16',
        '47' => 'B17',
    ],

    /*
    |--------------------------------------------------------------------------
    | Job de polling: intervalos de retry (segundos)
    |--------------------------------------------------------------------------
    */
    'poll_backoff' => [300, 900, 3600, 14400],

    /*
    |--------------------------------------------------------------------------
    | Cache key prefix para tokens DGII
    |--------------------------------------------------------------------------
    */
    'token_cache_prefix' => 'fe:token:business_',
    'token_cache_ttl' => 3600,
];
