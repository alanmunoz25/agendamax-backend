<?php

declare(strict_types=1);

namespace App\Http\Requests\Appointments;

use App\Models\Appointment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppointmentServiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Business admins and super admins can always add services.
     * Employees may only add services to appointments assigned to them.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        if ($user->isSuperAdmin() || $user->isBusinessAdmin()) {
            return true;
        }

        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Appointment $appointment */
        $appointment = $this->route('appointment');

        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'service_id' => [
                'required',
                'integer',
                'exists:services,id',
                Rule::unique('appointment_services', 'service_id')
                    ->where('appointment_id', $appointment->id),
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
            'service_id.unique' => 'Este servicio ya está en la cita.',
            'employee_id.required' => 'Debes seleccionar un colaborador.',
            'employee_id.exists' => 'El colaborador seleccionado no existe.',
            'service_id.required' => 'Debes seleccionar un servicio.',
            'service_id.exists' => 'El servicio seleccionado no existe.',
        ];
    }
}
