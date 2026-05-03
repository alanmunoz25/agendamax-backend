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
 * AgendaMax Payroll Phase 1 — commission rules define how commissions are calculated.
 * Rules can be global (business-wide), per-employee, per-service, or per-employee+service.
 * Higher priority wins when multiple rules match.
 */
class CommissionRule extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<\Database\Factories\CommissionRuleFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'business_id',
        'employee_id',
        'service_id',
        'type',
        'value',
        'priority',
        'is_active',
        'effective_from',
        'effective_until',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'is_active' => 'boolean',
            'effective_from' => 'date',
            'effective_until' => 'date',
            'priority' => 'integer',
        ];
    }

    /**
     * Get the employee this rule applies to (null = all employees).
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the service this rule applies to (null = all services).
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Get the commission records generated using this rule.
     */
    public function commissionRecords(): HasMany
    {
        return $this->hasMany(CommissionRecord::class);
    }

    /**
     * Scope to only active rules.
     *
     * @param  Builder<CommissionRule>  $query
     * @return Builder<CommissionRule>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
