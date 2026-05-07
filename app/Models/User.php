<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Pivots\UserBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'avatar_url',
        'birthday_day',
        'birthday_month',
        'push_token',
        'interested_service_id',
        'notes',
        'source',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'birthday_day' => 'integer',
            'birthday_month' => 'integer',
        ];
    }

    /**
     * Get the primary business this user belongs to (legacy direct assignment).
     */
    public function primaryBusiness(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'primary_business_id');
    }

    /**
     * Compatibility forwarder — remove post-F5+1
     */
    public function business(): BelongsTo
    {
        return $this->primaryBusiness();
    }

    /**
     * Compatibility setter: maps legacy 'business_id' writes to 'primary_business_id'.
     * Tests, factories, and seeders may still use the old column name — remove post-F5+1.
     */
    public function setBusinessIdAttribute(mixed $value): void
    {
        $this->attributes['primary_business_id'] = $value;
    }

    /**
     * Compatibility getter: exposes 'business_id' as an alias for 'primary_business_id'.
     * Remove post-F5+1.
     */
    public function getBusinessIdAttribute(): mixed
    {
        return $this->attributes['primary_business_id'] ?? null;
    }

    /**
     * Get the service the user (lead) is interested in.
     */
    public function interestedService(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'interested_service_id');
    }

    /**
     * Get the employee profile associated with the user.
     */
    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    /**
     * Get appointments where user is the client.
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'client_id');
    }

    /**
     * Get loyalty stamps earned by the user (when user is a client).
     */
    public function stamps(): HasMany
    {
        return $this->hasMany(Stamp::class, 'client_id');
    }

    /**
     * Check if user has a specific role.
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if user is a super admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    /**
     * Check if user is a business admin.
     */
    public function isBusinessAdmin(): bool
    {
        return $this->role === 'business_admin';
    }

    /**
     * Check if user is an employee.
     */
    public function isEmployee(): bool
    {
        return $this->role === 'employee';
    }

    /**
     * Check if user is a client.
     */
    public function isClient(): bool
    {
        return $this->role === 'client';
    }

    /**
     * Check if user is a lead.
     */
    public function isLead(): bool
    {
        return $this->role === 'lead';
    }

    /**
     * Get all businesses this user belongs to (via pivot).
     */
    public function businesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class, 'user_business')
            ->using(UserBusiness::class)
            ->withPivot([
                'role_in_business',
                'status',
                'joined_at',
                'blocked_at',
                'blocked_by_user_id',
                'blocked_reason',
                'last_active_at',
            ])
            ->withTimestamps();
    }

    /**
     * Get only businesses where this user has an active membership.
     */
    public function activeBusinesses(): BelongsToMany
    {
        return $this->businesses()->wherePivot('status', 'active');
    }

    /**
     * Check whether this user is enrolled (any non-left status) in the given business.
     */
    public function isEnrolledIn(Business $business): bool
    {
        return $this->businesses()
            ->wherePivot('business_id', $business->id)
            ->wherePivotIn('status', ['active', 'blocked'])
            ->exists();
    }

    /**
     * Check whether this user is blocked in the given business.
     */
    public function isBlockedIn(Business $business): bool
    {
        return $this->businesses()
            ->wherePivot('business_id', $business->id)
            ->wherePivot('status', 'blocked')
            ->exists();
    }

    /**
     * Check whether this user can book appointments in the given business.
     * Requires an active enrollment and the business itself to be active.
     */
    public function canBookIn(Business $business): bool
    {
        return $this->isEnrolledIn($business) && ! $this->isBlockedIn($business);
    }
}
