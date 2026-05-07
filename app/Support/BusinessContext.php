<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Business context resolver for the current request lifecycle.
 *
 * Resolves and holds the active business_id for a given request, enabling
 * BelongsToBusinessScope to filter queries without relying on users.business_id.
 *
 * Usage:
 *   BusinessContext::set(4);   // Set context (called by middleware)
 *   BusinessContext::current(); // Get current business_id (null if not set)
 *   BusinessContext::clear();   // Reset for testing
 */
class BusinessContext
{
    private static ?int $businessId = null;

    /**
     * Set the active business context for this request.
     */
    public static function set(int $businessId): void
    {
        static::$businessId = $businessId;
    }

    /**
     * Get the currently active business_id, or null if no context has been set.
     */
    public static function current(): ?int
    {
        return static::$businessId;
    }

    /**
     * Clear the business context. Used between requests and in tests.
     */
    public static function clear(): void
    {
        static::$businessId = null;
    }
}
