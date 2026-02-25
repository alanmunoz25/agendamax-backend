<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ServiceCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreServiceCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('create', ServiceCategory::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'parent_id' => ['nullable', 'integer', 'exists:service_categories,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
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
            'name.required' => 'Please provide a name for the category.',
            'name.max' => 'Category name cannot exceed 255 characters.',
            'description.max' => 'Category description cannot exceed 1000 characters.',
            'parent_id.exists' => 'The selected parent category does not exist.',
            'sort_order.min' => 'Sort order must be a positive number.',
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
            'name' => 'category name',
            'description' => 'category description',
            'parent_id' => 'parent category',
            'sort_order' => 'sort order',
            'is_active' => 'active status',
        ];
    }
}
