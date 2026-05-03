<?php

declare(strict_types=1);

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;

class StorePosShiftRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Cashiers (employees), business admins, and super admins may open/close shifts.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && ($user->isBusinessAdmin() || $user->isSuperAdmin() || $user->isEmployee());
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            // cashier_id is overridden in prepareForValidation() — any client-submitted value
            // is discarded and replaced with the authenticated user's ID before validation runs.
            'cashier_id' => ['required', 'integer', 'exists:users,id'],
            'shift_date' => ['required', 'date'],
            'opened_at' => ['nullable', 'date_format:H:i'],
            'closed_at' => ['nullable', 'date_format:H:i'],
            'opening_cash' => ['required', 'numeric', 'min:0'],
            'closing_cash_counted' => ['required', 'numeric', 'min:0'],
            'difference_reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Force cashier_id to the authenticated user — any value from the client payload is
     * discarded before validation, preventing impersonation of another cashier.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'cashier_id' => $this->user()?->id,
        ]);
    }
}
