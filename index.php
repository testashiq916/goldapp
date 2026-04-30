<?php

/**
 * Proxy front controller: serves Laravel from the project root.
 */

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$app->handleRequest(Illuminate\Http\Request::capture());
