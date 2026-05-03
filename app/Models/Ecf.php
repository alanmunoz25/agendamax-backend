<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ecf extends Model
{
    use BelongsToBusiness, HasFactory;

    /** @var string */
    protected $table = 'ecfs';

    /** @var array<int, string> */
    protected $fillable = [
        'business_id',
        'appointment_id',
        'pos_ticket_id',
        'numero_ecf',
        'tipo',
        'rnc_comprador',
        'razon_social_comprador',
        'nombre_comprador',
        'fecha_emision',
        'monto_total',
        'itbis_total',
        'monto_gravado',
        'xml_path',
        'last_polled_at',
    ];

    /**
     * Fields intentionally excluded from $fillable to prevent mass-assignment.
     * Must be set via forceFill() from trusted service / job layer:
     *   - status             (set by EmitEcfJob, ElectronicInvoiceService)
     *   - track_id           (set by EmitEcfJob after DGII submission)
     *   - error_message      (set by EmitEcfJob on failure)
     *   - dgii_response      (set by EmitEcfJob / PollEcfStatusJob)
     *   - manual_void_ncf    (set by IssuedController::registerManualVoid)
     *   - manual_void_reason (set by IssuedController::registerManualVoid)
     *   - voided_at          (set by IssuedController::registerManualVoid)
     *   - voided_by          (set by IssuedController::registerManualVoid)
     */

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_emision' => 'date',
            'monto_total' => 'decimal:2',
            'itbis_total' => 'decimal:2',
            'monto_gravado' => 'decimal:2',
            'last_polled_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * Get the appointment associated with this ECF (nullable).
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * Get the business that owns this ECF.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the audit logs for this ECF.
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(EcfAuditLog::class);
    }

    /**
     * Whether the ECF is in a terminal (non-retryable) state.
     */
    public function isTerminalStatus(): bool
    {
        return in_array($this->status, ['accepted', 'rejected', 'voided_manual'], true);
    }

    /**
     * Whether the ECF has been manually voided (NC emitted through DGII portal).
     */
    public function isManuallyVoided(): bool
    {
        return $this->status === 'voided_manual';
    }

    /**
     * Whether the ECF can be polled for a status update.
     */
    public function canBePoll(): bool
    {
        return in_array($this->status, ['sent'], true)
            && ! empty($this->track_id);
    }
}
