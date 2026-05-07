<?php

declare(strict_types=1);

namespace App\Logging;

use Illuminate\Support\Facades\Auth;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Monolog processor that injects structured context into every log record:
 * request_id, user_id, business_id, route, and ip.
 */
class ContextProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        /** @var array<string, mixed> $extra */
        $extra = $record->extra;

        $extra['request_id'] = app()->has('request_id') ? app('request_id') : null;
        $extra['ip'] = request()?->ip();
        $extra['route'] = request()?->route()?->getName() ?? request()?->path();

        try {
            $user = Auth::user();
            $extra['user_id'] = $user?->id;
            $extra['business_id'] = $user?->business_id;
        } catch (\Throwable) {
            $extra['user_id'] = null;
            $extra['business_id'] = null;
        }

        return $record->with(extra: $extra);
    }
}
