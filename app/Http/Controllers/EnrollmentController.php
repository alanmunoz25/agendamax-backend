<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Services\EnrollmentService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class EnrollmentController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly EnrollmentService $enrollmentService
    ) {}

    /**
     * Display enrollments for a course.
     */
    public function index(Course $course): InertiaResponse
    {
        $this->authorize('view', $course);

        $enrollments = $course->enrollments()
            ->when(request('status'), function ($query, $status) {
                $query->where('status', $status);
            })
            ->when(request('payment_status'), function ($query, $paymentStatus) {
                $query->where('payment_status', $paymentStatus);
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'total' => $course->enrollments()->count(),
            'confirmed' => $course->enrollments()->where('status', 'confirmed')->count(),
            'leads' => $course->enrollments()->where('status', 'lead')->count(),
            'revenue' => (float) $course->enrollments()->where('payment_status', 'paid')->sum('amount_paid'),
        ];

        return Inertia::render('Courses/Enrollments/Index', [
            'course' => $course,
            'enrollments' => $enrollments,
            'stats' => $stats,
            'filters' => request()->only(['status', 'payment_status']),
        ]);
    }

    /**
     * Update the status of an enrollment.
     */
    public function updateStatus(Request $request, Enrollment $enrollment): RedirectResponse
    {
        $this->authorize('update', $enrollment->course);

        $request->validate([
            'status' => ['required', 'in:confirmed,cancelled'],
        ]);

        $status = $request->input('status');

        if ($status === 'confirmed') {
            $this->enrollmentService->confirm($enrollment);
        } elseif ($status === 'cancelled') {
            $this->enrollmentService->cancel($enrollment);
        }

        return redirect()->back()->with('success', 'Estado actualizado exitosamente.');
    }

    /**
     * Remove the specified enrollment.
     */
    public function destroy(Enrollment $enrollment): RedirectResponse
    {
        $this->authorize('delete', $enrollment->course);

        $enrollment->delete();

        return redirect()->back()->with('success', 'Inscripcion eliminada exitosamente.');
    }

    /**
     * Export enrollments as CSV.
     */
    public function export(Course $course): Response
    {
        $this->authorize('view', $course);

        $enrollments = $course->enrollments()->get();

        $csv = "Nombre,Email,Telefono,Estado,Estado Pago,Monto Pagado,Fecha Inscripcion\n";

        foreach ($enrollments as $enrollment) {
            $csv .= implode(',', [
                '"'.str_replace('"', '""', $enrollment->customer_name).'"',
                $enrollment->customer_email,
                $enrollment->customer_phone ?? '',
                $enrollment->status,
                $enrollment->payment_status,
                $enrollment->amount_paid ?? '0',
                $enrollment->enrolled_at?->format('Y-m-d H:i') ?? '',
            ])."\n";
        }

        $filename = 'inscripciones-'.str()->slug($course->title).'-'.now()->format('Y-m-d').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
