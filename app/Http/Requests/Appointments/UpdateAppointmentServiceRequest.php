<?php

declare(strict_types=1);

namespace App\Http\Requests\Appointments;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAppointmentServiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Only business admins and super admins can reassign employees on service lines.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->isSuperAdmin() || $user->isBusinessAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
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
            'employee_id.required' => 'Debes seleccionar un colaborador.',
            'employee_id.exists' => 'El colaborador seleccionado no existe.',
        ];
    }
}
