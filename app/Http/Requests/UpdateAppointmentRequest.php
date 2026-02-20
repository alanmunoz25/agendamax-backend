<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateAppointmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $appointment = $this->route('appointment');

        return Gate::allows('update', $appointment);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'service_id' => ['sometimes', 'integer', 'exists:services,id'],
            'employee_id' => ['sometimes', 'integer', 'exists:employees,id'],
            'scheduled_at' => ['sometimes', 'date', 'after:now'],
            'status' => ['sometimes', 'string', 'in:pending,confirmed,in_progress,completed,cancelled'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'service_id.exists' => 'The selected service does not exist.',
            'employee_id.exists' => 'The selected employee does not exist.',
            'scheduled_at.after' => 'The appointment must be scheduled for a future date and time.',
            'status.in' => 'The appointment status must be one of: pending, confirmed, in_progress, completed, or cancelled.',
            'notes.max' => 'Notes cannot exceed 500 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'service_id' => 'service',
            'employee_id' => 'employee',
            'scheduled_at' => 'appointment date and time',
        ];
    }
}
