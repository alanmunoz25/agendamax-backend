<?php

declare(strict_types=1);

namespace App\Exceptions\Payroll;

use App\Models\PayrollRecord;

/**
 * AgendaMax Payroll Phase 3.1 — Thrown when void() is called on a paid PayrollRecord
 * but no open period exists after the voided record's period end date.
 *
 * The admin must open a new payroll period for the business before voiding a paid record,
 * because the compensation debit must land in a real open period.
 */
class NoOpenPeriodForCompensationException extends PayrollException
{
    private readonly PayrollRecord $payrollRecord;

    private readonly string $nextPeriodAfter;

    public function __construct(PayrollRecord $record, string $endsOn)
    {
        $this->payrollRecord = $record;
        $this->nextPeriodAfter = $endsOn;

        parent::__construct(
            "Cannot void paid payroll record (id: {$record->id}): no open period found after {$endsOn} "
            ."for business {$record->business_id} employee {$record->employee_id}. "
            .'Open a new period before voiding.'
        );
    }

    /**
     * The PayrollRecord that could not be voided due to missing open period.
     */
    public function record(): PayrollRecord
    {
        return $this->payrollRecord;
    }

    /**
     * The period end date after which an open period was sought.
     */
    public function nextPeriodAfter(): string
    {
        return $this->nextPeriodAfter;
    }
}
