<?php

declare(strict_types=1);

namespace App\Exceptions\Payroll;

/**
 * AgendaMax Payroll Phase 3 — Base domain exception for all payroll-related errors.
 * Extend this class for specific payroll failure scenarios.
 */
class PayrollException extends \DomainException {}
