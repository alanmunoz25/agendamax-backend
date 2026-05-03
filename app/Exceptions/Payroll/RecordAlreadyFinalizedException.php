<?php

declare(strict_types=1);

namespace App\Exceptions\Payroll;

/**
 * AgendaMax Payroll Phase 3.1 — Thrown when addAdjustment() is called on a PayrollRecord
 * that is already in a terminal state (approved, paid, or voided).
 *
 * To modify a finalized record, void it first and use the compensation flow,
 * or create an adjustment in a new open period.
 */
class RecordAlreadyFinalizedException extends PayrollException
{
    public function __construct(int $recordId, string $status)
    {
        parent::__construct(
            "Cannot adjust finalized payroll record (id: {$recordId}, status: {$status}). Void it or use compensation flow."
        );
    }
}
