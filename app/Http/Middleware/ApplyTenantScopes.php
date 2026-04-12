<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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

        // Super Admins are global and do not belong to any company; skip all tenant checks.
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $company = $user->company;

        if (! $company || ! $company->isActive()) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('filament.company.auth.login')
                ->with('error', __('app.suspended_msg'));
        }

        if (! $company->isSubscriptionValid()) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('filament.company.auth.login')
                ->with('error', __('app.expired_msg'));
        }

        // Set the team ID for Spatie permissions so hasRole()/hasPermissionTo() are scoped correctly.
        setPermissionsTeamId($company->id);

        return $next($request);
    }
}
