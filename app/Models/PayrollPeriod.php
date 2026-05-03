<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * AgendaMax Payroll Phase 1 — a payroll period defines the date range for grouping commissions.
 * Once closed, it is never reopened; retroactive commissions fall into the next open period.
 */
class PayrollPeriod extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<\Database\Factories\PayrollPeriodFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     * closed_at and closed_by are excluded — closure is controlled exclusively by PayrollService via forceFill().
     * status is retained because createPeriod() inserts the initial 'open' value on creation.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'business_id',
        'starts_on',
        'ends_on',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * Get the user who closed this period.
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Get the payroll records within this period.
     */
    public function payrollRecords(): HasMany
    {
        return $this->hasMany(PayrollRecord::class);
    }

    /**
     * Get the commission records assigned to this period.
     */
    public function commissionRecords(): HasMany
    {
        return $this->hasMany(CommissionRecord::class);
    }

    /**
     * Get the tips assigned to this period.
     */
    public function tips(): HasMany
    {
        return $this->hasMany(Tip::class);
    }

    /**
     * Get the manual adjustments for this period.
     */
    public function adjustments(): HasMany
    {
        return $this->hasMany(PayrollAdjustment::class);
    }

    /**
     * Scope to only open periods.
     *
     * @param  Builder<PayrollPeriod>  $query
     * @return Builder<PayrollPeriod>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }
}
