<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the authenticated user belongs to an active company.
 *
 * If the company is suspended or the subscription has expired,
 * the user is logged out and redirected to the login page.
 */
class ApplyTenantScopes
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->hasUser()) {
            return $next($request);
        }

        $user = auth()->user();
        $company = $user->company;

        if (! $company || ! $company->isActive()) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('filament.company.auth.login')
                ->with('error', __('Your company account has been suspended. Please contact support.'));
        }

        if (! $company->isSubscriptionValid()) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('filament.company.auth.login')
                ->with('error', __('Your subscription has expired. Please renew to continue.'));
        }

        // Inform Spatie exactly which "team" (company) permissions context we are currently in
        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);

        return $next($request);
    }

}
