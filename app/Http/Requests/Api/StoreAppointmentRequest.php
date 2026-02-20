<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreAppointmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'scheduled_at' => ['required', 'date', 'after:now'],
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
            'service_id.required' => 'Please select a service for the appointment.',
            'service_id.exists' => 'The selected service does not exist.',
            'employee_id.required' => 'Please select an employee for the appointment.',
            'employee_id.exists' => 'The selected employee does not exist.',
            'scheduled_at.required' => 'Please provide a date and time for the appointment.',
            'scheduled_at.after' => 'The appointment must be scheduled for a future date and time.',
            'notes.max' => 'Notes cannot exceed 500 characters.',
        ];
    }
}
