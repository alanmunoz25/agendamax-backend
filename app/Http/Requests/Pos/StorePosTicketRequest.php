<?php

declare(strict_types=1);

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePosTicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Cashiers (employees), business admins, and super admins may create tickets.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && ($user->isBusinessAdmin() || $user->isSuperAdmin() || $user->isEmployee());
    }

    /**
     * Resolve the effective business_id for validation scope.
     * super_admin must pass it in the request payload; others use their own.
     */
    private function effectiveBusinessId(): ?int
    {
        $user = $this->user();

        if ($user?->isSuperAdmin()) {
            return $this->input('business_id') ? (int) $this->input('business_id') : null;
        }

        return $user?->business_id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->user();
        $businessId = $this->effectiveBusinessId();

        return [
            'business_id' => [
                $user?->isSuperAdmin() ? 'required' : 'prohibited',
                'integer',
                Rule::exists('businesses', 'id'),
            ],
            'appointment_id' => [
                'nullable',
                'integer',
                Rule::exists('appointments', 'id')->where(function ($query) use ($businessId): void {
                    if ($businessId) {
                        $query->where('business_id', $businessId);
                    }
                }),
            ],
            'client_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(function ($query) use ($businessId): void {
                    if ($businessId) {
                        $query->where('business_id', $businessId);
                    }
                }),
            ],
            'client_name' => ['nullable', 'string', 'max:150'],
            'client_rnc' => ['nullable', 'string', 'max:20'],
            'employee_id' => [
                'nullable',
                'integer',
                Rule::exists('employees', 'id')->where(function ($query) use ($businessId): void {
                    if ($businessId) {
                        $query->where('business_id', $businessId);
                    }
                }),
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.type' => ['required', 'string', 'in:service,product'],
            'items.*.item_id' => ['required', 'integer'],
            'items.*.name' => ['required', 'string', 'max:200'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.employee_id' => [
                'nullable',
                'integer',
                Rule::exists('employees', 'id')->where(function ($query) use ($businessId): void {
                    if ($businessId) {
                        $query->where('business_id', $businessId);
                    }
                }),
            ],
            'items.*.appointment_service_id' => ['nullable', 'integer'],
            'discount_amount' => ['required', 'numeric', 'min:0'],
            'itbis_pct' => ['required', 'numeric', 'in:0,16,18'],
            'tip_amount' => ['required', 'numeric', 'min:0'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'string', 'in:cash,card,transfer'],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'payments.*.reference' => ['nullable', 'string', 'max:100'],
            'payments.*.cash_tendered' => ['nullable', 'numeric'],
            'ecf_requested' => ['required', 'boolean'],
            'ecf_type' => ['nullable', 'string', 'in:consumidor_final,credito_fiscal', 'required_if:ecf_requested,true'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
