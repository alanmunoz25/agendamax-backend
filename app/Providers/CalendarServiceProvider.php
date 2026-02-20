<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\CalendarProviderInterface;
use App\Services\GoogleCalendarMockService;
use App\Services\GoogleCalendarService;
use Google\Client as GoogleClient;
use Illuminate\Support\ServiceProvider;

class CalendarServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GoogleClient::class, function ($app) {
            $client = new GoogleClient();
            $client->setClientId(config('services.google.client_id'));
            $client->setClientSecret(config('services.google.client_secret'));
            $client->setRedirectUri(config('services.google.redirect'));
            $client->setAccessType(config('services.google.access_type', 'offline'));
            $client->setPrompt(config('services.google.approval_prompt', 'consent select_account'));
            $client->setScopes(config('services.google.scopes', []));
            $client->setIncludeGrantedScopes(true);

            return $client;
        });

        $this->app->singleton(CalendarProviderInterface::class, function ($app) {
            if ($app->environment('local')) {
                return $app->make(GoogleCalendarMockService::class);
            }

            return $app->make(GoogleCalendarService::class);
        });
    }
}
