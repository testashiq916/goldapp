<?php

namespace App\Http\Middleware;

use App\Enums\Permission;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        if (!$request->session()->has('userd_exists')) {
            $request->session()->put('userd_exists', Schema::hasTable('userd'));
        }
        if (!$request->session()->get('userd_exists')) {
            return $next($request);
        }

        $requiredPermission = Permission::matchPermissionForRequest($request);
        if ($requiredPermission === null) {
            return $next($request);
        }

        $userPermissions = DB::table('userd')
            ->whereRaw('UPPER(TRIM(code)) = ?', [$userCode])
            ->pluck('menuitem')
            ->map(fn ($item) => strtoupper(trim((string) $item)))
            ->values()
            ->all();

        $hasAnyMdiPermission = collect($userPermissions)->contains(
            fn (string $permission) => str_starts_with($permission, 'MDI_')
        );

        // Legacy users without MDI_* rows keep current unrestricted behavior.
        if (!$hasAnyMdiPermission) {
            return $next($request);
        }

        // Dashboard is the post-login shell. If a user has any MDI_* access,
        // allow opening dashboard even when the explicit MDI_DASHBOARD row is
        // missing or out of sync.
        if ($path === 'dashboard') {
            return $next($request);
        }

        if (in_array($requiredPermission, $userPermissions, true)) {
            return $next($request);
        }

        abort(403, 'Access Denied');
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
