<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'api/customer/*',
            'api/accounts/master/*',
            'api/states-adding',
        ]);
        $middleware->web(append: [
            \App\Http\Middleware\ApplySelectedDatabase::class,
            \App\Http\Middleware\CheckMenuAccess::class,
            \App\Http\Middleware\InjectResponsiveCss::class,
            \App\Http\Middleware\RunBackupAutomation::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
