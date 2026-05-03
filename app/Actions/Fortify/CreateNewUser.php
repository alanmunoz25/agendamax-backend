<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $businessId = config('app.default_business_id');

        $emailRule = $businessId
            ? Rule::unique('users', 'email')->where('business_id', $businessId)
            : Rule::unique(User::class);

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                $emailRule,
            ],
            'password' => $this->passwordRules(),
        ])->validate();

        // role and business_id are excluded from $fillable to prevent mass-assignment.
        // This trusted Action uses forceFill to set them explicitly.
        $user = new User;
        $user->fill([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);
        $user->forceFill([
            'business_id' => $businessId,
            'role' => 'client',
        ])->save();

        return $user;
    }
}
