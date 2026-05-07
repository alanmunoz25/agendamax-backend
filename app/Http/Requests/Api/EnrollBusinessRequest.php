<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Models\Business;
use Illuminate\Foundation\Http\FormRequest;

class EnrollBusinessRequest extends FormRequest
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
            'invitation_code' => ['nullable', 'string', 'max:50'],
            'business_slug' => ['nullable', 'string', 'max:100', 'exists:businesses,slug'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            $hasInvitationCode = $this->filled('invitation_code');
            $hasBusinessSlug = $this->filled('business_slug');

            if ($hasInvitationCode && $hasBusinessSlug) {
                $validator->errors()->add(
                    'invitation_code',
                    'Provide either invitation_code or business_slug, not both.'
                );

                return;
            }

            if (! $hasInvitationCode && ! $hasBusinessSlug) {
                $validator->errors()->add(
                    'invitation_code',
                    'Either invitation_code or business_slug is required.'
                );

                return;
            }

            if ($hasInvitationCode) {
                $business = Business::where('invitation_code', $this->input('invitation_code'))->first();

                if (! $business) {
                    $validator->errors()->add(
                        'invitation_code',
                        'The invitation code is invalid.'
                    );

                    return;
                }

                if ($business->status !== 'active') {
                    $validator->errors()->add(
                        'invitation_code',
                        'The business associated with this invitation code is not active.'
                    );
                }
            }

            if ($hasBusinessSlug) {
                $business = Business::where('slug', $this->input('business_slug'))->first();

                if ($business && $business->status !== 'active') {
                    $validator->errors()->add(
                        'business_slug',
                        'The business is not active.'
                    );
                }
            }
        });
    }

    /**
     * Resolve the Business model from the request inputs.
     */
    public function resolveBusiness(): ?Business
    {
        if ($this->filled('invitation_code')) {
            return Business::where('invitation_code', $this->input('invitation_code'))
                ->where('status', 'active')
                ->first();
        }

        if ($this->filled('business_slug')) {
            return Business::where('slug', $this->input('business_slug'))
                ->where('status', 'active')
                ->first();
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
            'business_slug.exists' => 'The business was not found.',
        ];
    }
}
