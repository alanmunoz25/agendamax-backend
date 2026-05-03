<?php

declare(strict_types=1);

namespace App\Exceptions\Payroll;

/**
 * AgendaMax Payroll Phase 3 — Thrown when a new payroll period overlaps with an existing one for the same business.
 */
class PeriodOverlapException extends PayrollException {}
