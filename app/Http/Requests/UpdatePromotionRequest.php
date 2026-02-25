<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdatePromotionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $promotion = $this->route('promotion');

        return Gate::allows('update', $promotion);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'url' => ['nullable', 'url', 'max:2048'],
            'expires_at' => ['nullable', 'date'],
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
            'title.max' => 'Promotion title cannot exceed 255 characters.',
            'image.image' => 'The file must be an image.',
            'image.mimes' => 'The image must be a JPG or PNG file.',
            'image.max' => 'The image must not exceed 2MB.',
            'url.url' => 'Please provide a valid URL.',
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
            'title' => 'promotion title',
            'image' => 'flyer image',
            'url' => 'promotion URL',
            'expires_at' => 'expiration date',
            'is_active' => 'active status',
        ];
    }
}
