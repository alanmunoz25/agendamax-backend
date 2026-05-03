<?php

declare(strict_types=1);

namespace App\Exceptions\ElectronicInvoice;

use RuntimeException;

class NcfRangeExhaustedException extends RuntimeException
{
    public function __construct(int $businessId, int $tipoEcf)
    {
        parent::__construct(
            "El rango NCF tipo {$tipoEcf} del negocio {$businessId} está agotado."
        );
    }
}
