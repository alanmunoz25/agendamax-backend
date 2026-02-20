<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
                'business_name' => $request->user()?->business?->name,
            ],
            'permissions' => $request->user() ? [
                'is_super_admin' => $request->user()->isSuperAdmin(),
                'is_business_admin' => $request->user()->isBusinessAdmin(),
                'is_employee' => $request->user()->isEmployee(),
                'is_client' => $request->user()->isClient(),
                'can_manage_businesses' => $request->user()->isSuperAdmin(),
                'can_manage_users' => $request->user()->isSuperAdmin() || $request->user()->isBusinessAdmin(),
            ] : [],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
