<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    use BelongsToBusiness, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'business_id',
        'title',
        'slug',
        'description',
        'syllabus',
        'cover_image',
        'instructor_name',
        'instructor_bio',
        'duration_text',
        'start_date',
        'end_date',
        'enrollment_deadline',
        'schedule_text',
        'price',
        'currency',
        'capacity',
        'modality',
        'is_active',
        'is_featured',
        'meta',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'enrollment_deadline' => 'date',
            'price' => 'decimal:2',
            'capacity' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'meta' => 'array',
        ];
    }

    /**
     * Get the enrollments for this course.
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Scope to courses that are currently enrollable.
     */
    public function scopeEnrollable(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function (Builder $q) {
                $q->whereNull('enrollment_deadline')
                    ->orWhere('enrollment_deadline', '>=', now()->toDateString());
            });
    }

    /**
     * Scope to courses for a specific business (bypassing global scope).
     */
    public function scopeForBusiness(Builder $query, int $businessId): Builder
    {
        return $query->where('business_id', $businessId);
    }

    /**
     * Get the remaining capacity for this course.
     */
    protected function remainingCapacity(): Attribute
    {
        return Attribute::get(function (): ?int {
            if ($this->capacity === null) {
                return null;
            }

            return max(0, $this->capacity - $this->enrollments()->count());
        });
    }
}
