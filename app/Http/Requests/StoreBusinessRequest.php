<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBusinessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isSuperAdmin();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:businesses,slug'],
            'description' => ['nullable', 'string', 'max:1000'],
            'email' => ['required', 'email', 'max:255', 'unique:businesses,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'logo_url' => ['nullable', 'url', 'max:500'],
            'invitation_code' => ['nullable', 'string', 'max:20', 'unique:businesses,invitation_code'],
            'status' => ['sometimes', 'string', 'in:active,inactive,suspended'],
            'timezone' => ['sometimes', 'string', 'timezone:all'],
            'loyalty_stamps_required' => ['nullable', 'integer', 'min:1', 'max:50'],
            'loyalty_reward_description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Business name is required.',
            'slug.required' => 'A URL slug is required.',
            'slug.unique' => 'This slug is already taken.',
            'email.required' => 'Business email is required.',
            'email.unique' => 'This email is already associated with another business.',
        ];
    }
}
