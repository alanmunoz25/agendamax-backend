<?php

declare(strict_types=1);

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class VoidPayrollRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('void', $this->route('record'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reason.required' => 'La razón de anulación es obligatoria.',
            'reason.min' => 'La razón debe tener al menos 10 caracteres.',
        ];
    }
}
