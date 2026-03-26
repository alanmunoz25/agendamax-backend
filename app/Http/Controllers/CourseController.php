<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreCourseRequest;
use App\Http\Requests\UpdateCourseRequest;
use App\Models\Course;
use App\Services\CourseService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly CourseService $courseService
    ) {}

    /**
     * Display a listing of courses.
     */
    public function index(): Response
    {
        $this->authorize('viewAny', Course::class);

        $courses = Course::query()
            ->when(request('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('instructor_name', 'like', "%{$search}%");
                });
            })
            ->when(request('is_active') !== null && request('is_active') !== '', function ($query) {
                $query->where('is_active', request('is_active') === '1');
            })
            ->when(request('modality'), function ($query, $modality) {
                $query->where('modality', $modality);
            })
            ->withCount('enrollments')
            ->orderBy(request('sort', 'created_at'), request('direction', 'desc'))
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Courses/Index', [
            'courses' => $courses,
            'filters' => request()->only(['search', 'is_active', 'modality', 'sort', 'direction']),
            'can' => [
                'create' => auth()->user()->can('create', Course::class),
            ],
        ]);
    }

    /**
     * Show the form for creating a new course.
     */
    public function create(): Response
    {
        $this->authorize('create', Course::class);

        return Inertia::render('Courses/Create');
    }

    /**
     * Store a newly created course in storage.
     */
    public function store(StoreCourseRequest $request): RedirectResponse
    {
        $course = $this->courseService->create([
            ...$request->validated(),
            'business_id' => auth()->user()->business_id,
        ]);

        return redirect()->route('courses.show', $course)
            ->with('success', 'Curso creado exitosamente.');
    }

    /**
     * Display the specified course.
     */
    public function show(Course $course): Response
    {
        $this->authorize('view', $course);

        $course->loadCount('enrollments');

        $enrollments = $course->enrollments()
            ->latest()
            ->limit(5)
            ->get();

        return Inertia::render('Courses/Show', [
            'course' => $course,
            'recentEnrollments' => $enrollments,
            'can' => [
                'update' => auth()->user()->can('update', $course),
                'delete' => auth()->user()->can('delete', $course),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified course.
     */
    public function edit(Course $course): Response
    {
        $this->authorize('update', $course);

        return Inertia::render('Courses/Edit', [
            'course' => $course,
        ]);
    }

    /**
     * Update the specified course in storage.
     */
    public function update(UpdateCourseRequest $request, Course $course): RedirectResponse
    {
        $this->courseService->update($course, $request->validated());

        return redirect()->route('courses.show', $course)
            ->with('success', 'Curso actualizado exitosamente.');
    }

    /**
     * Remove the specified course from storage.
     */
    public function destroy(Course $course): RedirectResponse
    {
        $this->authorize('delete', $course);

        try {
            $this->courseService->delete($course);
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('courses.index')
            ->with('success', 'Curso eliminado exitosamente.');
    }
}
