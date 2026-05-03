<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Pivots\AppointmentService;
use App\Models\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Appointment extends Model
{
    use BelongsToBusiness, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'business_id',
        'service_id',
        'employee_id',
        'client_id',
        'scheduled_at',
        'scheduled_until',
        'status',
        'notes',
        'cancellation_reason',
        'google_event_id',
        'google_synced_at',
        'completed_at',
        'final_price',
        'ticket_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'scheduled_until' => 'datetime',
            'google_synced_at' => 'datetime',
            'completed_at' => 'datetime',
            'final_price' => 'decimal:2',
        ];
    }

    /**
     * Get the service for this appointment.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Get the employee for this appointment.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the client (user) for this appointment.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * Get the services for this appointment (multi-service pivot).
     * Uses dedicated AppointmentService pivot model so commission records can FK to individual lines.
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'appointment_services')
            ->using(AppointmentService::class)
            ->withPivot('id', 'employee_id')
            ->withTimestamps();
    }

    /**
     * Get the visit associated with this appointment.
     */
    public function visit(): HasOne
    {
        return $this->hasOne(Visit::class);
    }

    /**
     * Get the commission records generated for this appointment.
     */
    public function commissionRecords(): HasMany
    {
        return $this->hasMany(CommissionRecord::class);
    }

    /**
     * Get the tips received at this appointment.
     */
    public function tips(): HasMany
    {
        return $this->hasMany(Tip::class);
    }

    /**
     * Get the electronic invoice (e-CF) emitted for this appointment.
     */
    public function ecf(): HasOne
    {
        return $this->hasOne(Ecf::class);
    }

    /**
     * Get the POS ticket that was generated when this appointment was checked out.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(PosTicket::class, 'ticket_id');
    }
}
