<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Pivots\AppointmentService;
use App\Models\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AgendaMax Payroll Phase 1 — one record per appointment service line per employee.
 * Snapshots the rule values at generation time to preserve historical accuracy.
 * Status flow: pending -> locked (period approved) -> paid (period paid) -> voided.
 */
class CommissionRecord extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<\Database\Factories\CommissionRecordFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     * Transition fields (status, payroll_period_id, locked_at, paid_at) are intentionally excluded —
     * all state transitions are controlled exclusively by PayrollService via query()->update() or forceFill().
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'business_id',
        'appointment_id',
        'appointment_service_id',
        'employee_id',
        'service_id',
        'commission_rule_id',
        'service_price_snapshot',
        'rule_type_snapshot',
        'rule_value_snapshot',
        'commission_amount',
        'generated_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'service_price_snapshot' => 'decimal:2',
            'rule_value_snapshot' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'generated_at' => 'datetime',
            'locked_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * Get the appointment this commission belongs to.
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * Get the specific service line (pivot row) that generated this commission.
     */
    public function appointmentService(): BelongsTo
    {
        return $this->belongsTo(AppointmentService::class, 'appointment_service_id');
    }

    /**
     * Get the employee who earned this commission.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the service this commission was calculated for.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Get the commission rule that was applied (nullable — rule may have been deleted).
     */
    public function commissionRule(): BelongsTo
    {
        return $this->belongsTo(CommissionRule::class);
    }

    /**
     * Get the payroll period this commission is assigned to.
     */
    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    /**
     * Scope to records that are still pending assignment to a period.
     *
     * @param  Builder<CommissionRecord>  $query
     * @return Builder<CommissionRecord>
     */
    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->where('status', 'pending')->whereNull('payroll_period_id');
    }
}
