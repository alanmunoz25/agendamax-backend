<?php

declare(strict_types=1);

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCommissionRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scope_type' => ['required', 'in:global,per_service,per_employee,specific'],
            'employee_id' => [
                'nullable',
                'required_if:scope_type,per_employee',
                'required_if:scope_type,specific',
                'exists:employees,id',
            ],
            'service_id' => [
                'nullable',
                'required_if:scope_type,per_service',
                'required_if:scope_type,specific',
                'exists:services,id',
            ],
            'type' => ['required', 'in:percentage,fixed'],
            'value' => ['required', 'numeric', 'min:0.01'],
            'effective_from' => ['required', 'date'],
            'effective_until' => ['nullable', 'date', 'after:effective_from'],
        ];
    }
}
