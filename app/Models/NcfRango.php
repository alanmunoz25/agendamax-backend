<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\ElectronicInvoice\NcfRangeExhaustedException;
use App\Exceptions\ElectronicInvoice\NcfRangeExpiredException;
use App\Models\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class NcfRango extends Model
{
    use BelongsToBusiness, HasFactory;

    /** @var string */
    protected $table = 'ncf_rangos';

    /** @var array<int, string> */
    protected $fillable = [
        'business_id',
        'tipo_ecf',
        'numero_solicitud',
        'numero_autorizacion',
        'secuencia_desde',
        'secuencia_hasta',
        'proximo_secuencial',
        'fecha_vencimiento',
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
            'tipo_ecf' => 'integer',
            'secuencia_desde' => 'integer',
            'secuencia_hasta' => 'integer',
            'proximo_secuencial' => 'integer',
            'fecha_vencimiento' => 'date',
        ];
    }

    /**
     * Scope to only active ranges.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to a specific business.
     */
    public function scopeForBusiness(Builder $query, int $businessId): Builder
    {
        return $query->where('business_id', $businessId);
    }

    /**
     * Scope to a specific e-CF type.
     */
    public function scopeForTipo(Builder $query, int $tipo): Builder
    {
        return $query->where('tipo_ecf', $tipo);
    }

    /**
     * Atomically assigns the next sequential eNCF number.
     * Uses lockForUpdate() to prevent race conditions.
     *
     * @throws NcfRangeExpiredException when the authorization date has passed
     * @throws NcfRangeExhaustedException when all sequential numbers have been used
     */
    public function assignNextSecuencial(): string
    {
        $encf = DB::transaction(function (): string {
            /** @var NcfRango $locked */
            $locked = static::withoutGlobalScopes()
                ->where('id', $this->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->isExpired()) {
                $locked->status = 'expired';
                $locked->save();

                throw new NcfRangeExpiredException($locked->business_id, $locked->tipo_ecf);
            }

            if ($locked->isExhausted()) {
                $locked->status = 'exhausted';
                $locked->save();

                throw new NcfRangeExhaustedException($locked->business_id, $locked->tipo_ecf);
            }

            $secuencial = $locked->proximo_secuencial;
            $encf = $locked->formatEcf($secuencial);

            $locked->proximo_secuencial = $secuencial + 1;

            if ($locked->proximo_secuencial > $locked->secuencia_hasta) {
                $locked->status = 'exhausted';
            }

            $locked->save();

            return $encf;
        });

        $this->refresh();

        return $encf;
    }

    /**
     * Formats an eNCF string from the tipo_ecf and a sequential number.
     * Produces exactly 13 characters: 'E' + 2-digit type + 10-digit sequential.
     * Example: tipo=31, secuencial=3 → 'E310000000003'
     */
    public function formatEcf(int $secuencial): string
    {
        return 'E'
            .str_pad((string) $this->tipo_ecf, 2, '0', STR_PAD_LEFT)
            .str_pad((string) $secuencial, 10, '0', STR_PAD_LEFT);
    }

    /**
     * Returns the number of sequential numbers remaining in this range.
     */
    public function remainingCount(): int
    {
        return $this->secuencia_hasta - $this->proximo_secuencial + 1;
    }

    /**
     * Checks whether this range has passed its authorization expiry date.
     */
    public function isExpired(): bool
    {
        if ($this->fecha_vencimiento === null) {
            return false;
        }

        return Carbon::today()->greaterThanOrEqualTo($this->fecha_vencimiento);
    }

    /**
     * Checks whether all sequential numbers in this range have been consumed.
     */
    public function isExhausted(): bool
    {
        return $this->proximo_secuencial > $this->secuencia_hasta;
    }
}
