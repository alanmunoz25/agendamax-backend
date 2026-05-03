<?php

declare(strict_types=1);

namespace App\Http\Requests\Payroll;

use App\Models\PayrollAdjustment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddPayrollAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [PayrollAdjustment::class, $this->route('period')]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $period = $this->route('period');

        return [
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id')->where('business_id', $period->business_id),
            ],
            'type' => ['required', 'string', Rule::in(['credit', 'debit'])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'min:3'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'employee_id.required' => 'El empleado es obligatorio.',
            'employee_id.exists' => 'El empleado no pertenece a este negocio.',
            'type.required' => 'El tipo de ajuste es obligatorio.',
            'type.in' => 'El tipo debe ser crédito o débito.',
            'amount.required' => 'El monto es obligatorio.',
            'amount.gt' => 'El monto debe ser mayor a cero.',
            'reason.required' => 'La razón del ajuste es obligatoria.',
        ];
    }
}
