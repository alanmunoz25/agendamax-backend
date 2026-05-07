<?php

declare(strict_types=1);

namespace App\Http\Requests\Business;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class BlockClientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var Business $business */
        $business = $this->route('business');

        /** @var User $target */
        $target = $this->route('user');

        return Gate::check('block-client', [$target, $business]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:500'],
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
            'reason.required' => 'A reason for blocking the client is required.',
            'reason.min' => 'The reason must be at least 10 characters.',
            'reason.max' => 'The reason cannot exceed 500 characters.',
        ];
    }
}
