<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateServiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $service = $this->route('service');

        return Gate::allows('update', $service);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'duration' => ['sometimes', 'integer', 'min:15', 'max:480'],
            'price' => ['sometimes', 'numeric', 'min:0', 'max:999999.99'],
            'category' => ['nullable', 'string', 'max:100'],
            'service_category_id' => ['nullable', 'integer', 'exists:service_categories,id'],
            'is_active' => ['sometimes', 'boolean'],
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
            'name.max' => 'Service name cannot exceed 255 characters.',
            'description.max' => 'Service description cannot exceed 1000 characters.',
            'duration.min' => 'Service duration must be at least 15 minutes.',
            'duration.max' => 'Service duration cannot exceed 8 hours (480 minutes).',
            'price.min' => 'Service price cannot be negative.',
            'price.max' => 'Service price cannot exceed 999,999.99.',
            'category.max' => 'Category name cannot exceed 100 characters.',
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
            'name' => 'service name',
            'description' => 'service description',
            'duration' => 'service duration',
            'price' => 'service price',
            'is_active' => 'active status',
        ];
    }
}
