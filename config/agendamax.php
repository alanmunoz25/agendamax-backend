<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Business Context Resolver (F2)
    |--------------------------------------------------------------------------
    |
    | When enabled, BelongsToBusinessScope reads its filter from
    | BusinessContext::current() instead of auth()->user()->business_id.
    | The ResolveBusinessContext middleware populates this context from the
    | X-Business-Id request header or legacy fallback.
    |
    | Default: false — preserves identical legacy behaviour.
    | Flip to true only after BackwardCompatibilityTest suite is green.
    |
    */
    'use_business_context' => env('AGENDAMAX_USE_BUSINESS_CONTEXT', false),

    /*
    |--------------------------------------------------------------------------
    | Client Multi-Business (F3)
    |--------------------------------------------------------------------------
    |
    | When enabled, clients can enroll in multiple businesses and the
    | enrollment / block / switcher endpoints become operational.
    |
    | Default: false — new endpoints return 503 Service Unavailable.
    |
    */
    'client_multi_business' => env('AGENDAMAX_CLIENT_MULTI_BUSINESS', true),

];
