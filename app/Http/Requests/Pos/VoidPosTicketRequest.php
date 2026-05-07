<?php

declare(strict_types=1);

namespace App\Http\Requests\Pos;

use App\Models\PosTicket;
use Illuminate\Foundation\Http\FormRequest;

class VoidPosTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        /** @var PosTicket|null $ticket */
        $ticket = $this->route('ticket');

        if ($ticket === null) {
            return false;
        }

        if ($user->isBusinessAdmin()) {
            return $user->business_id === $ticket->business_id;
        }

        if ($user->isEmployee()) {
            return $user->id === $ticket->cashier_id;
        }

        return false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }
}
