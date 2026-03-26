<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Course;
use Inertia\Inertia;
use Inertia\Response;

class PublicCourseController extends Controller
{
    /**
     * Display the public course catalog for a business.
     */
    public function index(Business $business): Response
    {
        $courses = Course::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->enrollable()
            ->withCount('enrollments')
            ->orderBy('is_featured', 'desc')
            ->orderBy('start_date')
            ->paginate(12);

        return Inertia::render('Public/Courses/Index', [
            'business' => $business,
            'courses' => $courses,
        ]);
    }

    /**
     * Display a public course detail page.
     */
    public function show(Business $business, string $courseSlug): Response
    {
        $course = Course::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->where('slug', $courseSlug)
            ->where('is_active', true)
            ->withCount('enrollments')
            ->firstOrFail();

        return Inertia::render('Public/Courses/Show', [
            'business' => $business,
            'course' => $course,
        ]);
    }
}
