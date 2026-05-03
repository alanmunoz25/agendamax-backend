<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'invitation_code',
        'loyalty_stamps_required',
        'loyalty_reward_description',
        'status',
        'timezone',
        'pos_commissions_enabled',
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
        ];
    }

    /**
     * Get the users for the business.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
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
}
