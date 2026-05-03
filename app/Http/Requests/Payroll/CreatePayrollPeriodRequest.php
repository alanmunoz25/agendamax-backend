<?php

declare(strict_types=1);

namespace App\Http\Requests\Payroll;

use App\Models\PayrollPeriod;
use Illuminate\Foundation\Http\FormRequest;

class CreatePayrollPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', PayrollPeriod::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'start' => ['required', 'date', 'date_format:Y-m-d'],
            'end' => ['required', 'date', 'date_format:Y-m-d', 'after:start'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'start.required' => 'La fecha de inicio es obligatoria.',
            'start.date' => 'La fecha de inicio no es válida.',
            'end.required' => 'La fecha de fin es obligatoria.',
            'end.date' => 'La fecha de fin no es válida.',
            'end.after' => 'La fecha de fin debe ser posterior a la fecha de inicio.',
        ];
    }
}
