<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EcfAuditLog extends Model
{
    use BelongsToBusiness, HasFactory;

    /** @var string */
    protected $table = 'ecf_audit_logs';

    /** @var array<int, string> */
    protected $fillable = [
        'business_id',
        'ecf_id',
        'action',
        'payload',
        'response',
        'status_code',
        'error',
        'duration_ms',
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
            'response' => 'array',
            'status_code' => 'integer',
            'duration_ms' => 'integer',
        ];
    }

    /**
     * Get the ECF associated with this log entry.
     */
    public function ecf(): BelongsTo
    {
        return $this->belongsTo(Ecf::class);
    }

    /**
     * Get the business that owns this log entry.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
