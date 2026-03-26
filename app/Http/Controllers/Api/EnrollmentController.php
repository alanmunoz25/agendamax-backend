<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreEnrollmentRequest;
use App\Http\Resources\EnrollmentResource;
use App\Models\Course;
use App\Services\EnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class EnrollmentController extends Controller
{
    public function __construct(
        private readonly EnrollmentService $enrollmentService
    ) {}

    /**
     * Enroll in a course (public endpoint).
     */
    public function store(StoreEnrollmentRequest $request, int $courseId): JsonResponse
    {
        $course = Course::withoutGlobalScopes()
            ->where('is_active', true)
            ->findOrFail($courseId);

        try {
            $enrollment = $this->enrollmentService->enroll($course, $request->validated());

            $isFree = (float) $course->price <= 0;

            return response()->json([
                'enrollment' => new EnrollmentResource($enrollment),
                'message' => $isFree
                    ? 'Inscripcion confirmada exitosamente.'
                    : 'Inscripcion registrada. Te contactaremos para completar el pago.',
                'payment_required' => ! $isFree,
            ], 201);
        } catch (\RuntimeException $e) {
            Log::warning('Enrollment failed', [
                'course_id' => $courseId,
                'email' => $request->validated('email'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
