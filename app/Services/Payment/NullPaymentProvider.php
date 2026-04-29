<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\PaymentProviderInterface;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NullPaymentProvider implements PaymentProviderInterface
{
    /**
     * Create a fake checkout for free courses.
     *
     * @return array{url: string, reference: string}
     */
    public function createCheckout(Enrollment $enrollment): array
    {
        return [
            'url' => '#',
            'reference' => 'free-'.Str::uuid()->toString(),
        ];
    }

    /**
     * Always returns true for free courses.
     */
    public function verifyPayment(string $reference): bool
    {
        return true;
    }

    /**
     * No-op for free courses.
     */
    public function handleWebhook(Request $request): void
    {
        // No-op
    }
}
