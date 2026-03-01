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
            'service_id' => ['required_without:services', 'integer', 'exists:services,id'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'services' => ['required_without:service_id', 'array', 'min:1'],
            'services.*.service_id' => ['required', 'integer', 'exists:services,id'],
            'services.*.employee_id' => ['nullable', 'integer', 'exists:employees,id'],
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
            'service_id.required_without' => 'Please provide either a service_id or a services array.',
            'service_id.exists' => 'The selected service does not exist.',
            'employee_id.exists' => 'The selected employee does not exist.',
            'services.required_without' => 'Please provide either a services array or a service_id.',
            'services.min' => 'At least one service must be selected.',
            'services.*.service_id.required' => 'Each service entry must include a service_id.',
            'services.*.service_id.exists' => 'One of the selected services does not exist.',
            'services.*.employee_id.exists' => 'One of the selected employees does not exist.',
            'scheduled_at.required' => 'Please provide a date and time for the appointment.',
            'scheduled_at.after' => 'The appointment must be scheduled for a future date and time.',
            'notes.max' => 'Notes cannot exceed 500 characters.',
        ];
    }
}
