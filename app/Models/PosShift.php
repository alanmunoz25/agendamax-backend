<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosShift extends Model
{
    use BelongsToBusiness;
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'business_id',
        'cashier_id',
        'shift_date',
        'opened_at',
        'opening_cash',
        'closing_cash_counted',
        'difference_reason',
        'pdf_path',
    ];

    /**
     * Fields excluded from $fillable — calculated by PosService::calculateShiftSummary
     * and written exclusively via forceFill() from PosShiftController::store.
     * Prevents a cashier from submitting fabricated totals:
     *   - closed_at, closing_cash_expected, cash_difference
     *   - tickets_count, total_sales, total_tips
     *   - cash_sales, card_sales, transfer_sales
     */

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shift_date' => 'date',
            'opening_cash' => 'decimal:2',
            'closing_cash_counted' => 'decimal:2',
            'closing_cash_expected' => 'decimal:2',
            'cash_difference' => 'decimal:2',
            'total_sales' => 'decimal:2',
            'total_tips' => 'decimal:2',
            'cash_sales' => 'decimal:2',
            'card_sales' => 'decimal:2',
            'transfer_sales' => 'decimal:2',
            'tickets_count' => 'integer',
        ];
    }

    /**
     * Get the cashier (user) who owns this shift.
     */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }
}
