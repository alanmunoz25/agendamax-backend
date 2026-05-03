<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * AgendaMax Payroll Phase 1 — one payroll record per employee per period.
 * Aggregates commissions, tips, and adjustments with full audit trail.
 * Status flow: draft -> approved -> paid -> voided.
 */
class PayrollRecord extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<\Database\Factories\PayrollRecordFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     * Audit/transition fields (status, *_at, *_by, payment_*) are intentionally excluded —
     * all state transitions are controlled exclusively by PayrollService via forceFill().
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'business_id',
        'payroll_period_id',
        'employee_id',
        'base_salary_snapshot',
        'commissions_total',
        'tips_total',
        'adjustments_total',
        'gross_total',
        'snapshot_payload',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_salary_snapshot' => 'decimal:2',
            'commissions_total' => 'decimal:2',
            'tips_total' => 'decimal:2',
            'adjustments_total' => 'decimal:2',
            'gross_total' => 'decimal:2',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
            'snapshot_payload' => 'array',
        ];
    }

    /**
     * Get the payroll period this record belongs to.
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    /**
     * Get the employee this payroll record is for.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the admin who approved this record.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the admin who marked this record as paid.
     */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /**
     * Get the admin who voided this record.
     */
    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /**
     * Get the commission records for this employee in this period.
     * Filtered by employee_id for isolation. Eager loading is supported because
     * the constraint uses a static value from the model instance.
     * NOTE: use the controller eager-load constraint to pass employee_id for collections.
     */
    public function commissionRecords(): HasMany
    {
        return $this->hasMany(CommissionRecord::class, 'payroll_period_id', 'payroll_period_id');
    }

    /**
     * Get the tips for this employee in this period.
     */
    public function tips(): HasMany
    {
        return $this->hasMany(Tip::class, 'payroll_period_id', 'payroll_period_id');
    }

    /**
     * Get the manual adjustments for this employee in this period.
     */
    public function adjustments(): HasMany
    {
        return $this->hasMany(PayrollAdjustment::class, 'payroll_period_id', 'payroll_period_id');
    }
}
