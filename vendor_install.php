<?php
// DELETE THIS FILE FROM SERVER IMMEDIATELY AFTER USE — it is a security risk

$secret = 'nafix2026'; // Change this before uploading

if (!isset($_GET['token']) || $_GET['token'] !== $secret) {
    http_response_code(403);
    die('Forbidden. Use ?token=YOUR_SECRET');
}

$action = $_GET['action'] ?? 'install';

header('Content-Type: text/plain; charset=utf-8');

$base_dir = __DIR__;
echo "Working directory: $base_dir\n\n";

// Find composer
$composer_paths = [
    'composer',
    '/usr/bin/composer',
    '/usr/local/bin/composer',
    $base_dir . '/composer.phar',
];

$composer_bin = null;
foreach ($composer_paths as $path) {
    $test = shell_exec("which $path 2>/dev/null") ?? shell_exec("$path --version 2>&1");
    if ($test) {
        $composer_bin = $path;
        break;
    }
}

if (!$composer_bin) {
    // Try to download composer.phar
    echo "Composer not found. Downloading composer.phar...\n";
    $phar = file_get_contents('https://getcomposer.org/composer-stable.phar');
    if ($phar) {
        file_put_contents($base_dir . '/composer.phar', $phar);
        $composer_bin = 'php ' . $base_dir . '/composer.phar';
        echo "Downloaded composer.phar\n\n";
    } else {
        die("ERROR: Could not find or download Composer.\n");
    }
}

echo "Using composer: $composer_bin\n\n";

$env = 'COMPOSER_HOME=/tmp HOME=/tmp';

$commands = [
    'install' => "$env $composer_bin install --no-dev --optimize-autoloader --no-interaction 2>&1",
    'dump'    => "$env $composer_bin dump-autoload --optimize 2>&1",
    'update'  => "$env $composer_bin update --no-dev --optimize-autoloader --no-interaction 2>&1",
];

if (!isset($commands[$action])) {
    die("Unknown action. Use ?action=install or ?action=dump or ?action=update\n");
}

echo "Running: $action\n";
echo str_repeat('-', 50) . "\n";

chdir($base_dir);
$output = shell_exec($commands[$action]);
echo $output ?: "No output returned.\n";

echo str_repeat('-', 50) . "\n";
echo "\nDONE. Delete this file from the server now!\n";
