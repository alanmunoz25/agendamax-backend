<?php

declare(strict_types=1);

namespace App\Exceptions\Payroll;

use App\Models\CommissionRecord;

/**
 * AgendaMax Payroll Phase 3.1 — T-3.1.6 (DN-06)
 *
 * Thrown when generateRecords() detects a CommissionRecord with a negative commission_amount.
 * This is a data integrity error that must be investigated before generating payroll.
 *
 * Double defense: DB CHECK constraint (MySQL/MariaDB) + this service-layer guard (all drivers).
 */
class NegativeCommissionDetectedException extends PayrollException
{
    private readonly CommissionRecord $commissionRecord;

    public function __construct(CommissionRecord $commission)
    {
        $this->commissionRecord = $commission;

        parent::__construct(
            'Negative commission amount detected during payroll generation '
            ."(commission_id: {$commission->id}, amount: {$commission->commission_amount}, "
            ."business_id: {$commission->business_id}). "
            .'Investigate before generating payroll.'
        );
    }

    /**
     * The CommissionRecord with the negative amount that triggered this exception.
     */
    public function commission(): CommissionRecord
    {
        return $this->commissionRecord;
    }
}
