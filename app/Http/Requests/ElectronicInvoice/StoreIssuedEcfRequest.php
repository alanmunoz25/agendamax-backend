<?php

declare(strict_types=1);

namespace App\Http\Requests\ElectronicInvoice;

use Illuminate\Foundation\Http\FormRequest;

class StoreIssuedEcfRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Only business admins and super admins may emit e-CFs.
     * The business FE configuration must also be active.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        if (! $user->isBusinessAdmin() && ! $user->isSuperAdmin()) {
            return false;
        }

        $config = $user->business?->feConfig;

        return $config !== null && $config->activo === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'tipo_ecf' => ['required', 'string', 'in:31,32,33,34'],
            'tipo_pago' => ['required', 'string', 'in:contado,credito'],
            'client_rnc' => ['nullable', 'string', 'max:20'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'client_direccion' => ['nullable', 'string', 'max:500'],
            'indicador_monto_gravado' => ['required', 'integer', 'in:0,1'],
            'ecf_referencia' => ['nullable', 'string', 'max:30'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.qty' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
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
            'tipo_ecf.required' => 'Selecciona el tipo de comprobante.',
            'tipo_ecf.in' => 'Tipo de comprobante no válido.',
            'tipo_pago.required' => 'Selecciona el tipo de pago.',
            'items.required' => 'Debes añadir al menos un ítem.',
            'items.min' => 'Debes añadir al menos un ítem.',
            'items.*.description.required' => 'La descripción del ítem es obligatoria.',
            'items.*.qty.required' => 'La cantidad es obligatoria.',
            'items.*.qty.min' => 'La cantidad debe ser mayor a 0.',
            'items.*.unit_price.required' => 'El precio unitario es obligatorio.',
            'items.*.unit_price.min' => 'El precio unitario no puede ser negativo.',
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
            'tipo_ecf' => 'tipo de comprobante',
            'tipo_pago' => 'tipo de pago',
            'client_rnc' => 'RNC/Cédula del cliente',
            'client_name' => 'nombre del cliente',
            'indicador_monto_gravado' => 'indicador de monto gravado',
        ];
    }
}
