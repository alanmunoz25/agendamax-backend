<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Models\Business;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
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
        if (config('agendamax.client_multi_business')) {
            // In multi-business mode, users are global — email must be globally unique.
            $emailUnique = Rule::unique('users', 'email');
        } else {
            $businessId = $this->resolveBusinessId();
            $emailUnique = $businessId
                ? Rule::unique('users', 'email')->where('primary_business_id', $businessId)
                : Rule::unique('users', 'email')->whereNull('primary_business_id');
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', $emailUnique],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:20'],
            'invitation_code' => ['nullable', 'string'],
            'business_id' => ['nullable', 'integer', 'exists:businesses,id'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            if ($this->filled('invitation_code') && $this->filled('business_id')) {
                $validator->errors()->add(
                    'invitation_code',
                    'You cannot provide both invitation_code and business_id.'
                );
            }

            if ($this->filled('invitation_code')) {
                $business = Business::where('invitation_code', $this->input('invitation_code'))->first();

                if (! $business) {
                    $validator->errors()->add(
                        'invitation_code',
                        'The invitation code is invalid.'
                    );
                } elseif ($business->status !== 'active') {
                    $validator->errors()->add(
                        'invitation_code',
                        'The business associated with this invitation code is not active.'
                    );
                }
            }
        });
    }

    /**
     * Resolve the business ID from invitation_code or business_id.
     */
    public function resolveBusinessId(): ?int
    {
        if ($this->filled('invitation_code')) {
            $business = Business::where('invitation_code', $this->input('invitation_code'))
                ->where('status', 'active')
                ->first();

            return $business?->id;
        }

        if ($this->filled('business_id')) {
            return (int) $this->input('business_id');
        }

        return null;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Please provide your name.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email is already registered for this business.',
            'password.required' => 'Password is required.',
            'password.confirmed' => 'Password confirmation does not match.',
        ];
    }
}
