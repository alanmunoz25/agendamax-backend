<?php

declare(strict_types=1);

namespace App\Exceptions\Payroll;

/**
 * AgendaMax Payroll Phase 3 — Thrown when required metadata is missing for a payroll state transition
 * (e.g., void_reason is empty when voiding a record).
 */
class MissingTransitionMetadataException extends PayrollException {}
