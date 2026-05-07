<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class HealthController extends Controller
{
    /**
     * Liveness probe — confirms the application is running.
     * Returns 200 immediately without checking dependencies.
     */
    public function liveness(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'version' => config('app.version', '1.0.0'),
            'uptime' => $this->uptimeSeconds(),
        ]);
    }

    /**
     * Readiness probe — verifies all critical dependencies.
     * Returns 200 when all are healthy, 503 if any fail.
     */
    public function readiness(): JsonResponse
    {
        $checks = [
            'db' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
        ];

        $allHealthy = ! in_array('fail', $checks, true);

        return response()->json($checks, $allHealthy ? 200 : 503);
    }

    private function checkDatabase(): string
    {
        try {
            DB::connection()->getPdo();

            return 'ok';
        } catch (\Throwable) {
            return 'fail';
        }
    }

    private function checkCache(): string
    {
        try {
            $key = '_health_check_'.getmypid();
            Cache::put($key, 1, 5);
            $value = Cache::get($key);
            Cache::forget($key);

            return $value === 1 ? 'ok' : 'fail';
        } catch (\Throwable) {
            return 'fail';
        }
    }

    private function checkQueue(): string
    {
        try {
            // For sync driver (testing), always ok.
            // For real queue drivers, verify the connection is reachable.
            $connection = config('queue.default');

            if ($connection === 'sync') {
                return 'ok';
            }

            Queue::connection($connection)->size();

            return 'ok';
        } catch (\Throwable) {
            return 'fail';
        }
    }

    private function uptimeSeconds(): int
    {
        if (defined('LARAVEL_START')) {
            return (int) round(microtime(true) - LARAVEL_START);
        }

        return 0;
    }
}
