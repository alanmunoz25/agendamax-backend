<?php

declare(strict_types=1);

namespace App\Models\Pivots;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * AgendaMax Payroll Phase 1 — dedicated pivot model for appointment_services.
 * Allows commission records to reference individual service lines via FK.
 */
class AppointmentService extends Pivot
{
    /** @var bool Enable auto-incrementing PK on this pivot */
    public $incrementing = true;

    /** @var string */
    protected $table = 'appointment_services';
}
