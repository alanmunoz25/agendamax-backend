<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBusinessRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->isSuperAdmin() || $this->user()->business_id !== null;
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
            'email' => ['sometimes', 'email', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'logo_url' => ['nullable', 'url', 'max:500'],
            'logo' => ['sometimes', 'file', 'image', 'max:2048', 'mimes:jpeg,jpg,png,webp'],
            'banner' => ['sometimes', 'file', 'image', 'max:5120', 'mimes:jpeg,jpg,png,webp'],
            'cover' => ['sometimes', 'file', 'image', 'max:5120', 'mimes:jpeg,jpg,png,webp'],
            'loyalty_stamps_required' => ['nullable', 'integer', 'min:1', 'max:50'],
            'loyalty_reward_description' => ['nullable', 'string', 'max:500'],
            'status' => ['sometimes', 'string', 'in:active,inactive,suspended'],
            'timezone' => ['sometimes', 'string', 'timezone:all'],
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
            'name.max' => 'Business name cannot exceed 255 characters.',
            'email.email' => 'Please provide a valid email address.',
            'email.max' => 'Email address cannot exceed 255 characters.',
            'phone.max' => 'Phone number cannot exceed 20 characters.',
            'address.max' => 'Address cannot exceed 500 characters.',
            'logo_url.url' => 'Logo URL must be a valid URL.',
            'logo_url.max' => 'Logo URL cannot exceed 500 characters.',
            'loyalty_stamps_required.min' => 'Loyalty stamps required must be at least 1.',
            'loyalty_stamps_required.max' => 'Loyalty stamps required cannot exceed 50.',
            'loyalty_reward_description.max' => 'Loyalty reward description cannot exceed 500 characters.',
            'status.in' => 'Status must be one of: active, inactive, or suspended.',
            'timezone.timezone' => 'Please provide a valid timezone.',
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
            'name' => 'business name',
            'logo_url' => 'logo URL',
            'loyalty_stamps_required' => 'stamps required for reward',
            'loyalty_reward_description' => 'reward description',
        ];
    }
}
