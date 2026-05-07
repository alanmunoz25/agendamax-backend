<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Pivots\UserBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class Business extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'email',
        'phone',
        'address',
        'logo_url',
        'banner_url',
        'cover_image_url',
        'invitation_code',
        'loyalty_stamps_required',
        'loyalty_reward_description',
        'status',
        'timezone',
        'pos_commissions_enabled',
        'sector',
        'province',
        'country',
        'latitude',
        'longitude',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'loyalty_stamps_required' => 'integer',
            'status' => 'string',
            'pos_commissions_enabled' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /**
     * Resolve a storage-relative path to a full public URL, or return null/external URLs as-is.
     */
    private function resolveStorageUrl(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        return Storage::disk('public')->url($value);
    }

    /**
     * Get the logo as a full public URL.
     */
    public function getLogoUrlAttribute(?string $value): ?string
    {
        return $this->resolveStorageUrl($value);
    }

    /**
     * Get the banner as a full public URL.
     */
    public function getBannerUrlAttribute(?string $value): ?string
    {
        return $this->resolveStorageUrl($value);
    }

    /**
     * Get the cover image as a full public URL.
     */
    public function getCoverImageUrlAttribute(?string $value): ?string
    {
        return $this->resolveStorageUrl($value);
    }

    /**
     * Get the geographic coordinates as a simple lat/lng array, or null if not set.
     *
     * @return array{lat: float, lng: float}|null
     */
    public function locationPoint(): ?array
    {
        if ($this->latitude === null || $this->longitude === null) {
            return null;
        }

        return ['lat' => $this->latitude, 'lng' => $this->longitude];
    }

    /**
     * Get the users for the business (via the legacy primary_business_id FK on users).
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'primary_business_id');
    }

    /**
     * Get the employees for the business.
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * Get the services for the business.
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    /**
     * Get the service categories for the business.
     */
    public function serviceCategories(): HasMany
    {
        return $this->hasMany(ServiceCategory::class);
    }

    /**
     * Get the courses for the business.
     */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    /**
     * Get the enrollments for the business.
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Get the electronic invoice configuration for this business.
     */
    public function feConfig(): HasOne
    {
        return $this->hasOne(BusinessFeConfig::class);
    }

    /**
     * Get the e-CFs emitted by this business.
     */
    public function ecfs(): HasMany
    {
        return $this->hasMany(Ecf::class);
    }

    /**
     * Get all users enrolled in this business via the pivot table.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_business')
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
}
