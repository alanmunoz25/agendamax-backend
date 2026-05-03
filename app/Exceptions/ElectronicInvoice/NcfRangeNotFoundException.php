<?php

declare(strict_types=1);

namespace App\Exceptions\ElectronicInvoice;

use RuntimeException;

class NcfRangeNotFoundException extends RuntimeException
{
    public function __construct(int $businessId, int $tipoEcf)
    {
        parent::__construct(
            "No se encontró un rango NCF activo para el tipo {$tipoEcf} del negocio {$businessId}."
        );
    }
}
