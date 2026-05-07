<?php

declare(strict_types=1);

namespace App\Http\Requests\Business;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UnblockClientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var Business $business */
        $business = $this->route('business');

        /** @var User $target */
        $target = $this->route('user');

        return Gate::check('unblock-client', [$target, $business]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
