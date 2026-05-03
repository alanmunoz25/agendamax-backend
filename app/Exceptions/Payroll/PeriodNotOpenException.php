<?php

declare(strict_types=1);

namespace App\Exceptions\Payroll;

/**
 * AgendaMax Payroll Phase 3 — Thrown when an operation requires an open period but the period is not open (e.g., closed or non-existent).
 */
class PeriodNotOpenException extends PayrollException {}
