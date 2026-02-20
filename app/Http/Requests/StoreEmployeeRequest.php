<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreEmployeeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('create', Employee::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'photo_url' => ['nullable', 'url', 'max:500'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', 'exists:services,id'],
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
            'user_id.required' => 'Please select a user for this employee profile.',
            'user_id.exists' => 'The selected user does not exist.',
            'photo_url.url' => 'The photo URL must be a valid URL.',
            'photo_url.max' => 'Photo URL cannot exceed 500 characters.',
            'bio.max' => 'Bio cannot exceed 1000 characters.',
            'service_ids.array' => 'Services must be provided as an array.',
            'service_ids.*.exists' => 'One or more selected services do not exist.',
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
            'user_id' => 'user',
            'photo_url' => 'photo URL',
            'is_active' => 'active status',
            'service_ids' => 'services',
        ];
    }
}
