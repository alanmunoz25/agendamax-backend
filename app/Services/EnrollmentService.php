<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\EnrollmentCreated;
use App\Events\EnrollmentPaid;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class EnrollmentService
{
    /**
     * Enroll a customer in a course.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws RuntimeException
     */
    public function enroll(Course $course, array $data): Enrollment
    {
        return DB::transaction(function () use ($course, $data) {
            // Lock the course row to prevent race conditions on capacity
            $course = Course::lockForUpdate()->find($course->id);

            if (! $course) {
                throw new RuntimeException('Course not found.');
            }

            // Check capacity
            if ($course->capacity !== null && $course->remaining_capacity < 1) {
                throw new RuntimeException('This course has reached its maximum capacity.');
            }

            // Check for duplicate enrollment (same email + course, not cancelled)
            $existingEnrollment = Enrollment::withoutGlobalScopes()
                ->where('course_id', $course->id)
                ->where('customer_email', $data['email'])
                ->where('status', '!=', 'cancelled')
                ->exists();

            if ($existingEnrollment) {
                throw new RuntimeException('This email is already enrolled in this course.');
            }

            // Find or create user as lead
            $user = User::where('email', $data['email'])
                ->where('primary_business_id', $course->business_id)
                ->first();

            if (! $user) {
                $user = new User;
                $user->fill([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'] ?? null,
                    'password' => Str::random(32),
                ]);
                $user->forceFill([
                    'role' => 'lead',
                    'business_id' => $course->business_id,
                ])->save();
            }

            $isFree = (float) $course->price <= 0;

            $enrollment = Enrollment::create([
                'business_id' => $course->business_id,
                'course_id' => $course->id,
                'user_id' => $user->id,
                'customer_name' => $data['name'],
                'customer_email' => $data['email'],
                'customer_phone' => $data['phone'] ?? null,
                'status' => $isFree ? 'confirmed' : 'lead',
                'payment_status' => $isFree ? 'free' : 'pending',
                'enrolled_at' => $isFree ? now() : null,
                'notes' => $data['notes'] ?? null,
            ]);

            $enrollment->load(['course', 'user', 'business']);

            EnrollmentCreated::dispatch($enrollment);

            return $enrollment;
        });
    }

    /**
     * Confirm an enrollment.
     */
    public function confirm(Enrollment $enrollment): void
    {
        $enrollment->update([
            'status' => 'confirmed',
            'enrolled_at' => $enrollment->enrolled_at ?? now(),
        ]);
    }

    /**
     * Cancel an enrollment.
     */
    public function cancel(Enrollment $enrollment): void
    {
        $enrollment->update([
            'status' => 'cancelled',
        ]);
    }

    /**
     * Mark an enrollment as paid.
     *
     * @param  array<string, mixed>  $paymentData
     */
    public function markAsPaid(Enrollment $enrollment, array $paymentData): void
    {
        $enrollment->update([
            'payment_status' => 'paid',
            'payment_provider' => $paymentData['provider'] ?? null,
            'payment_reference' => $paymentData['reference'] ?? null,
            'payment_metadata' => $paymentData['metadata'] ?? null,
            'amount_paid' => $paymentData['amount'] ?? $enrollment->course->price,
            'status' => 'confirmed',
            'enrolled_at' => $enrollment->enrolled_at ?? now(),
        ]);

        $enrollment->load(['course', 'user', 'business']);

        EnrollmentPaid::dispatch($enrollment);
    }
}
