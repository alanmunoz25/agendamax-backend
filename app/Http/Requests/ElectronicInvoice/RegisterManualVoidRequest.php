<?php

declare(strict_types=1);

namespace App\Http\Requests\ElectronicInvoice;

use Illuminate\Foundation\Http\FormRequest;

class RegisterManualVoidRequest extends FormRequest
{
    /**
     * Only business admins and super admins may register a manual void.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->isBusinessAdmin() || $user->isSuperAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'manual_void_ncf' => [
                'required',
                'string',
                'regex:/^E34\d{10}$/',
            ],
            'reason' => [
                'required',
                'string',
                'min:10',
                'max:500',
            ],
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
            'manual_void_ncf.required' => 'El NCF de la Nota de Crédito es obligatorio.',
            'manual_void_ncf.regex' => 'El NCF debe tener el formato E34 seguido de 10 dígitos (ej: E34000000001).',
            'reason.required' => 'La razón de anulación es obligatoria.',
            'reason.min' => 'La razón debe tener al menos 10 caracteres.',
            'reason.max' => 'La razón no puede superar los 500 caracteres.',
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
            'manual_void_ncf' => 'NCF de Nota de Crédito',
            'reason' => 'razón de anulación',
        ];
    }
}
