<?php

declare(strict_types=1);

namespace App\Models\Traits;

use App\Models\Business;
use App\Models\Scopes\BelongsToBusinessScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToBusiness
{
    /**
     * Boot the trait and apply the global scope.
     */
    protected static function bootBelongsToBusiness(): void
    {
        static::addGlobalScope(new BelongsToBusinessScope);
    }

    /**
     * Get the business that owns the model.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
