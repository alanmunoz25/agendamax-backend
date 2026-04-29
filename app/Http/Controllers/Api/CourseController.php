<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseDetailResource;
use App\Http\Resources\CourseResource;
use App\Models\Business;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CourseController extends Controller
{
    /**
     * List all active, enrollable courses for a business.
     */
    public function index(Request $request, int $businessId): AnonymousResourceCollection
    {
        $business = Business::findOrFail($businessId);

        $query = Course::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->enrollable()
            ->withCount('enrollments');

        if ($request->filled('search')) {
            $query->where('title', 'like', '%'.$request->input('search').'%');
        }

        $perPage = min((int) $request->input('per_page', 15), 50);

        return CourseResource::collection(
            $query->orderBy('is_featured', 'desc')
                ->orderBy('start_date')
                ->paginate($perPage)
        );
    }

    /**
     * Show a single course by slug.
     */
    public function show(int $businessId, string $slug): CourseDetailResource
    {
        $business = Business::findOrFail($businessId);

        $course = Course::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->withCount('enrollments')
            ->firstOrFail();

        return new CourseDetailResource($course);
    }
}
