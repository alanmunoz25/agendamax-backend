<?php

declare(strict_types=1);

namespace App\Models\Pivots;

use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot model for the user_business many-to-many relationship.
 *
 * Tracks a user's membership in a business including their role,
 * enrollment status, and block information if applicable.
 */
class UserBusiness extends Pivot
{
    /** @var bool Enable auto-incrementing PK on this pivot */
    public $incrementing = true;

    /** @var string */
    protected $table = 'user_business';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'role_in_business',
        'status',
        'joined_at',
        'blocked_at',
        'blocked_by_user_id',
        'blocked_reason',
        'last_active_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'blocked_at' => 'datetime',
            'last_active_at' => 'datetime',
        ];
    }

    /**
     * Get the user associated with this pivot.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the business associated with this pivot.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the user who blocked this member.
     */
    public function blockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by_user_id');
    }

    /**
     * Determine whether this membership is currently active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Determine whether this membership is currently blocked.
     */
    public function isBlocked(): bool
    {
        return $this->status === 'blocked';
    }

    /**
     * Determine whether the user has left the business.
     */
    public function isLeft(): bool
    {
        return $this->status === 'left';
    }
}
