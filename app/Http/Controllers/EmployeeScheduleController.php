<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateEmployeeScheduleRequest;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeScheduleController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display the employee's weekly schedule.
     */
    public function index(Employee $employee): Response
    {
        $this->authorize('view', $employee);

        $schedules = $employee->schedules()
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        return Inertia::render('Employees/Schedule/Index', [
            'employee' => $employee->load('user'),
            'schedules' => $schedules,
        ]);
    }

    /**
     * Show the form for editing the employee's schedule.
     */
    public function edit(Employee $employee): Response
    {
        $this->authorize('update', $employee);

        $schedules = $employee->schedules()
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        return Inertia::render('Employees/Schedule/Edit', [
            'employee' => $employee->load('user'),
            'schedules' => $schedules,
        ]);
    }

    /**
     * Update the employee's weekly schedule.
     *
     * This method accepts an array of schedule slots and replaces
     * the employee's entire weekly schedule.
     */
    public function update(UpdateEmployeeScheduleRequest $request, Employee $employee): RedirectResponse
    {
        $this->authorize('update', $employee);

        // Delete existing schedules
        $employee->schedules()->delete();

        // Create new schedules from validated data
        $schedules = collect($request->validated('schedules'))->map(function ($schedule) use ($employee) {
            return [
                'employee_id' => $employee->id,
                'day_of_week' => $schedule['day_of_week'],
                'start_time' => $schedule['start_time'],
                'end_time' => $schedule['end_time'],
                'is_available' => $schedule['is_available'] ?? true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        });

        EmployeeSchedule::insert($schedules->toArray());

        return redirect()->route('employees.schedules.index', $employee)
            ->with('success', 'Employee schedule updated successfully.');
    }

    /**
     * Remove all schedules for the employee.
     */
    public function destroy(Employee $employee): RedirectResponse
    {
        $this->authorize('update', $employee);

        $employee->schedules()->delete();

        return redirect()->route('employees.schedules.index', $employee)
            ->with('success', 'Employee schedule cleared successfully.');
    }
}
