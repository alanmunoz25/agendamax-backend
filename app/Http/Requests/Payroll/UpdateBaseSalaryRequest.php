<?php

declare(strict_types=1);

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBaseSalaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $user->isSuperAdmin() || $user->isBusinessAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'base_salary' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
        ];
    }
}
