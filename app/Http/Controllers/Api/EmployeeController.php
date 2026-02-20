<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;

class EmployeeController extends Controller
{
    /**
     * Get employee details with services and schedules.
     */
    public function show(int $employeeId): EmployeeResource
    {
        $employee = Employee::with([
            'user:id,name,email',
            'business:id,name,address,phone',
            'services:id,name,price,duration,category',
            'schedules',
        ])
            ->where('is_active', true)
            ->findOrFail($employeeId);

        return new EmployeeResource($employee);
    }
}
