<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EcfReceived extends Model
{
    use BelongsToBusiness, HasFactory;

    /** @var string */
    protected $table = 'ecf_received';

    /** @var array<int, string> */
    protected $fillable = [
        'business_id',
        'rnc_emisor',
        'razon_social_emisor',
        'nombre_comercial_emisor',
        'correo_emisor',
        'numero_ecf',
        'tipo',
        'fecha_emision',
        'monto_total',
        'itbis_total',
        'xml_path',
        'xml_arecf_path',
        'status',
        'codigo_motivo',
        'arecf_sent_at',
    ];

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
            'arecf_sent_at' => 'datetime',
        ];
    }

    /**
     * Get the business that owns this received ECF.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
