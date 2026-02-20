<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class StoreEmployeeScheduleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $employee = $this->route('employee');

        return Gate::allows('update', $employee);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'schedules' => ['required', 'array', 'min:1', 'max:7'],
            'schedules.*.day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            'schedules.*.start_time' => ['required', 'date_format:H:i'],
            'schedules.*.end_time' => ['required', 'date_format:H:i', 'after:schedules.*.start_time'],
            'schedules.*.is_available' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Check for duplicate day_of_week entries
            $schedules = $this->input('schedules', []);
            $days = collect($schedules)->pluck('day_of_week');

            if ($days->count() !== $days->unique()->count()) {
                $validator->errors()->add(
                    'schedules',
                    'Each day can only have one schedule entry.'
                );
            }

            // Validate that end_time is after start_time for each schedule
            foreach ($schedules as $index => $schedule) {
                if (isset($schedule['start_time'], $schedule['end_time'])) {
                    $startTime = \Carbon\Carbon::createFromFormat('H:i', $schedule['start_time']);
                    $endTime = \Carbon\Carbon::createFromFormat('H:i', $schedule['end_time']);

                    if ($endTime->lte($startTime)) {
                        $validator->errors()->add(
                            "schedules.{$index}.end_time",
                            'End time must be after start time.'
                        );
                    }
                }
            }
        });
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'schedules.required' => 'Please provide at least one schedule entry.',
            'schedules.min' => 'Please provide at least one schedule entry.',
            'schedules.max' => 'You can only provide schedules for up to 7 days.',
            'schedules.*.day_of_week.required' => 'Day of week is required for each schedule.',
            'schedules.*.day_of_week.integer' => 'Day of week must be a number between 0 (Sunday) and 6 (Saturday).',
            'schedules.*.day_of_week.min' => 'Day of week must be between 0 (Sunday) and 6 (Saturday).',
            'schedules.*.day_of_week.max' => 'Day of week must be between 0 (Sunday) and 6 (Saturday).',
            'schedules.*.start_time.required' => 'Start time is required for each schedule.',
            'schedules.*.start_time.date_format' => 'Start time must be in HH:MM format (e.g., 09:00).',
            'schedules.*.end_time.required' => 'End time is required for each schedule.',
            'schedules.*.end_time.date_format' => 'End time must be in HH:MM format (e.g., 17:00).',
            'schedules.*.end_time.after' => 'End time must be after start time.',
            'schedules.*.is_available.boolean' => 'Availability status must be true or false.',
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
            'schedules' => 'employee schedules',
            'schedules.*.day_of_week' => 'day of week',
            'schedules.*.start_time' => 'start time',
            'schedules.*.end_time' => 'end time',
            'schedules.*.is_available' => 'availability',
        ];
    }
}
