<?php

namespace App\Http\Middleware;

use App\Enums\Permission;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class CheckMenuAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = trim($request->path(), '/');
        if ($path === '' || $this->isPublicPath($path)) {
            return $next($request);
        }

        $userCode = strtoupper(trim((string) $request->session()->get('user_code', '')));
        if ($userCode === '') {
            return $next($request);
        }

        // Superusers always have full access bypass.
        if (in_array($userCode, ['ADMIN', 'MGR', 'DEVELOPER'], true)) {
            return $next($request);
        }

        if (!$request->session()->has('userd_exists')) {
            try {
                $request->session()->put('userd_exists', Schema::hasTable('userd'));
            } catch (Throwable $e) {
                $request->session()->put('userd_exists', false);
            }
        }
        if (!$request->session()->get('userd_exists')) {
            return $next($request);
        }

        $requiredPermission = Permission::matchPermissionForRequest($request);
        if ($requiredPermission === null) {
            return $next($request);
        }

        try {
            $userPermissions = DB::table('userd')
                ->whereRaw('UPPER(TRIM(code)) = ?', [$userCode])
                ->pluck('menuitem')
                ->map(fn ($item) => strtoupper(trim((string) $item)))
                ->values()
                ->all();
        } catch (Throwable $e) {
            return $next($request);
        }

        if ($this->hasPermission($requiredPermission, $userPermissions)) {
            return $next($request);
        }

        if ($request->is('/') || $request->is('dashboard') || $request->is('dashboard/*')) {
            // Dashboard shell is always allowed so the user can navigate to other modules.
            // NativeDashboardController blocks the internal content if access is denied.
            return $next($request);
        }

        abort(403, 'Access Denied');
    }

    private function hasPermission(string $requiredPermission, array $userPermissions): bool
    {
        if (in_array($requiredPermission, $userPermissions, true)) {
            return true;
        }

        $aliases = [
            'MDI_COMPANY_SELECT' => ['COMPANYSELECT', 'COMPANY_SELECT', 'MDI_COMPANYSELECT'],
        ];

        foreach ($aliases[$requiredPermission] ?? [] as $alias) {
            if (in_array($alias, $userPermissions, true)) {
                return true;
            }
        }

        return false;
    }

    private function isPublicPath(string $path): bool
    {
        foreach (['login', 'logout', 'up'] as $publicPath) {
            if ($path === $publicPath || str_starts_with($path, $publicPath . '/')) {
                return true;
            }
        }

        return false;
    }
}
