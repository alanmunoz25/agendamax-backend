<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AgendaMax Payroll Phase 1 — manual credit/debit adjustments for a payroll period.
 * Used for corrections to already-paid commissions (new credit/debit in next open period).
 */
class PayrollAdjustment extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<\Database\Factories\PayrollAdjustmentFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'business_id',
        'payroll_period_id',
        'employee_id',
        'related_commission_record_id',
        'related_appointment_id',
        'type',
        'amount',
        'reason',
        'description',
        'created_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    /**
     * Get the payroll period this adjustment belongs to.
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    /**
     * Get the employee this adjustment is for.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the commission record this adjustment corrects (if applicable).
     */
    public function relatedCommissionRecord(): BelongsTo
    {
        return $this->belongsTo(CommissionRecord::class, 'related_commission_record_id');
    }

    /**
     * Get the appointment this adjustment relates to (if applicable).
     */
    public function relatedAppointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'related_appointment_id');
    }

    /**
     * Get the user who created this adjustment.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Returns the signed amount as a string to preserve decimal precision.
     * Debit adjustments are returned as negative values.
     */
    public function signedAmount(): string
    {
        return $this->type === 'debit'
            ? '-'.$this->amount
            : (string) $this->amount;
    }
}
