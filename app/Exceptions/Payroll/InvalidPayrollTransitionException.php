<?php

declare(strict_types=1);

namespace App\Exceptions\Payroll;

/**
 * AgendaMax Payroll Phase 3 — Thrown when a state machine transition is invalid (e.g., approving a non-draft record).
 */
class InvalidPayrollTransitionException extends PayrollException {}
