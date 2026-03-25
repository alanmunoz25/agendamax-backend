<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;  // kept for source validation

class StoreLeadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'business_id' => ['required', 'integer', 'exists:businesses,id'],
            'interested_service_id' => ['nullable', 'integer', 'exists:services,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'source' => ['nullable', 'string', Rule::in([
                'appointment_form',
                'event_quote',
                'evaluation_form',
                'contact_form',
            ])],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Please provide a name.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'business_id.required' => 'A business ID is required.',
            'business_id.exists' => 'The selected business does not exist.',
            'interested_service_id.exists' => 'The selected service does not exist.',
            'source.in' => 'The source must be one of: appointment_form, event_quote, evaluation_form, contact_form.',
        ];
    }
}
