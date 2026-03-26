<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Enrollment;
use Illuminate\Http\Request;

/**
 * Contract for interacting with payment providers.
 */
interface PaymentProviderInterface
{
    /**
     * Create a checkout session for an enrollment.
     *
     * @return array{url: string, reference: string}
     */
    public function createCheckout(Enrollment $enrollment): array;

    /**
     * Verify a payment by its reference.
     */
    public function verifyPayment(string $reference): bool;

    /**
     * Handle an incoming webhook from the payment provider.
     */
    public function handleWebhook(Request $request): void;
}
