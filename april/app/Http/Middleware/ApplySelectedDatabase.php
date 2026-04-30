<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ApplySelectedDatabase
{
    public function handle(Request $request, Closure $next): Response
    {
        $database = trim((string) (
            $request->session()->get('selected_database')
            ?? $request->cookie('selected_company_database')
            ?? ''
        ));

        $currentDatabase = (string) Config::get('database.connections.mysql.database', '');

        if ($database !== '' && strcasecmp($database, $currentDatabase) !== 0) {
            Config::set('database.connections.mysql.database', $database);
            DB::purge('mysql');
            DB::reconnect('mysql');
        }

        if ($database !== '' && $request->session()->get('selected_database') !== $database) {
            $request->session()->put('selected_database', $database);
        }

        // Preserve the user's existing level context instead of forcing primary mode.
        if (!$request->session()->has('gilevel')) {
            $request->session()->put('gilevel', (int) $request->input('gilevel', 1));
        }

        if (!$request->session()->has('user_level')) {
            $request->session()->put('user_level', (int) $request->input('user_level', (int) $request->session()->get('gilevel', 1)));
        }

        if (!$request->session()->has('rlevel')) {
            $request->session()->put('rlevel', (int) $request->input('rlevel', (int) $request->session()->get('gilevel', 1)));
        }

        if (!$request->session()->has('control')) {
            $request->session()->put('control', (int) $request->input('control', (int) $request->session()->get('gilevel', 1)));
        }

        if (!$request->session()->has('dbcontrol')) {
            $request->session()->put('dbcontrol', (int) $request->input('dbcontrol', (int) $request->session()->get('gilevel', 1)));
        }

        return $next($request);
    }
}
