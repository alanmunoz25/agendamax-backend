<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QrCode extends Model
{
    use BelongsToBusiness, HasFactory;

    protected $fillable = [
        'business_id',
        'code',
        'type',
        'reward_description',
        'stamps_required',
        'is_active',
        'image_path',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'stamps_required' => 'integer',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function scans(): HasMany
    {
        return $this->hasMany(QrScan::class);
    }
}
