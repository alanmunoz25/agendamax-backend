<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\PaymentProviderInterface;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use RuntimeException;

class StubPaymentProvider implements PaymentProviderInterface
{
    /**
     * @throws RuntimeException
     */
    public function createCheckout(Enrollment $enrollment): array
    {
        throw new RuntimeException('Payment provider not configured.');
    }

    /**
     * @throws RuntimeException
     */
    public function verifyPayment(string $reference): bool
    {
        throw new RuntimeException('Payment provider not configured.');
    }

    /**
     * @throws RuntimeException
     */
    public function handleWebhook(Request $request): void
    {
        throw new RuntimeException('Payment provider not configured.');
    }
}
