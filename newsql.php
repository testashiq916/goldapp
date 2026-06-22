<?php
/**
 * newsql.php
 * Run this file once to manually apply the userhist IP / useragent columns
 * if you cannot use php artisan migrate.
 *
 * Usage:  php newsql.php
 *   or open in browser: http://localhost/goldapp/newsql.php
 */

// ── Bootstrap Laravel ──────────────────────────────────────────────────────
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$messages = [];

// ── 1. Add `ip` column to userhist ────────────────────────────────────────
if (Schema::hasTable('userhist')) {
    if (!Schema::hasColumn('userhist', 'ip')) {
        DB::statement('ALTER TABLE `userhist` ADD COLUMN `ip` VARCHAR(45) NULL AFTER `time2`');
        $messages[] = "✔ Added column `ip` to `userhist`.";
    } else {
        $messages[] = "– Column `ip` already exists in `userhist`. Skipped.";
    }

    if (!Schema::hasColumn('userhist', 'useragent')) {
        DB::statement('ALTER TABLE `userhist` ADD COLUMN `useragent` VARCHAR(512) NULL AFTER `ip`');
        $messages[] = "✔ Added column `useragent` to `userhist`.";
    } else {
        $messages[] = "– Column `useragent` already exists in `userhist`. Skipped.";
    }
} else {
    $messages[] = "✘ Table `userhist` not found. No changes made.";
}

// ── Output ─────────────────────────────────────────────────────────────────
if (PHP_SAPI === 'cli') {
    foreach ($messages as $m) {
        echo $m . PHP_EOL;
    }
} else {
    echo '<pre style="font-family:monospace;font-size:14px;padding:20px">';
    echo implode("\n", array_map('htmlspecialchars', $messages));
    echo '</pre>';
}
