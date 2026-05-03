<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TD-036: Immutable audit trail for payroll state transitions.
 * Append-only — update() and delete() are explicitly blocked at the model level.
 */
class PayrollAuditLog extends Model
{
    use BelongsToBusiness;

    /** @var bool Disable updated_at — this table is append-only and has no updated_at column. */
    public $timestamps = false;

    /** @var bool Use only created_at managed manually. */
    const CREATED_AT = 'created_at';

    /** @var array<int, string> */
    protected $fillable = [
        'business_id',
        'payroll_record_id',
        'payroll_period_id',
        'user_id',
        'action',
        'previous_status',
        'new_status',
        'payload',
        'ip_address',
        'user_agent',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Prevent updates — this model is append-only.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     *
     * @throws \LogicException
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw new \LogicException('PayrollAuditLog is append-only.');
    }

    /**
     * Prevent deletes — this model is append-only.
     *
     * @throws \LogicException
     */
    public function delete(): bool
    {
        throw new \LogicException('PayrollAuditLog is append-only.');
    }

    /**
     * Get the payroll record this log entry references.
     */
    public function payrollRecord(): BelongsTo
    {
        return $this->belongsTo(PayrollRecord::class);
    }

    /**
     * Get the payroll period this log entry references.
     */
    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    /**
     * Get the user who performed the action.
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
