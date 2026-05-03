<?php

declare(strict_types=1);

namespace App\Exceptions\Payroll;

/**
 * AgendaMax Payroll Phase 3 — Thrown when generateRecords() is called on a period that already has records.
 * Prevents double-generation even in concurrent scenarios.
 */
class RecordsAlreadyGeneratedException extends PayrollException {}
