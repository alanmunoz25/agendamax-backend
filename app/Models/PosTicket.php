<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosTicket extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    /**
     * @var array<int, string>
     *
     * Note: the following fields are intentionally excluded from $fillable to prevent
     * mass assignment attacks. They must be set via forceFill() from trusted service layer:
     *   - status         (transition managed by PosService)
     *   - ecf_ncf        (assigned by ElectronicInvoiceService after DGII response)
     *   - voided_by      (set by PosService::void, tied to authenticated user)
     *   - voided_at      (set by PosService::void)
     *   - void_reason    (set by PosService::void from validated input)
     */
    protected $fillable = [
        'business_id',
        'ticket_number',
        'appointment_id',
        'client_id',
        'client_name',
        'client_rnc',
        'employee_id',
        'cashier_id',
        'subtotal',
        'discount_amount',
        'discount_pct',
        'itbis_amount',
        'itbis_pct',
        'tip_amount',
        'total',
        'ecf_requested',
        'ecf_type',
        'ecf_status',
        'ecf_error_message',
        'ecf_emitted_at',
        'nc_ticket_id',
        'is_offline',
        'offline_created_at',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'discount_pct' => 'decimal:2',
            'itbis_amount' => 'decimal:2',
            'itbis_pct' => 'decimal:2',
            'tip_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'voided_at' => 'datetime',
            'ecf_emitted_at' => 'datetime',
            'offline_created_at' => 'datetime',
            'ecf_requested' => 'boolean',
            'is_offline' => 'boolean',
        ];
    }

    /**
     * Get the appointment this ticket was generated from.
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * Get the client (registered user) for this ticket.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * Get the primary employee associated with this ticket.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the cashier (user) who created this ticket.
     */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    /**
     * Get the line items for this ticket.
     */
    public function items(): HasMany
    {
        return $this->hasMany(PosTicketItem::class);
    }

    /**
     * Get the payment records for this ticket.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(PosPayment::class);
    }

    /**
     * Get the credit note ticket (Nota de Crédito) that this ticket originates from.
     */
    public function ncTicket(): BelongsTo
    {
        return $this->belongsTo(self::class, 'nc_ticket_id');
    }

    /**
     * Scope to paid tickets.
     *
     * @param  Builder<PosTicket>  $query
     * @return Builder<PosTicket>
     */
    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', 'paid');
    }

    /**
     * Scope to voided tickets.
     *
     * @param  Builder<PosTicket>  $query
     * @return Builder<PosTicket>
     */
    public function scopeVoided(Builder $query): Builder
    {
        return $query->where('status', 'voided');
    }

    /**
     * Scope to open tickets.
     *
     * @param  Builder<PosTicket>  $query
     * @return Builder<PosTicket>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    /**
     * Generate the human-readable ticket number from the ticket's ID and year.
     */
    public static function generateTicketNumber(int $year, int $id): string
    {
        return 'TKT-'.$year.'-'.str_pad((string) $id, 4, '0', STR_PAD_LEFT);
    }
}
