<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// List all databases
echo "=== DATABASES ===\n";
$dbs = DB::select('SHOW DATABASES');
foreach ($dbs as $d) {
    $name = array_values((array)$d)[0];
    if (!in_array($name, ['information_schema','performance_schema','mysql','sys'])) {
        echo $name . "\n";
    }
}

// Check generals table in demo for company list
echo "\n=== COMPANIES (generals) ===\n";
try {
    $companies = DB::select("SELECT cvalue FROM generals WHERE code IN ('SHOPNM','DBNAME','SHOPDB') LIMIT 20");
    foreach ($companies as $c) echo $c->cvalue . "\n";
} catch (Exception $e) {
    echo "No generals: " . $e->getMessage() . "\n";
}

// Check if there's a company list table
echo "\n=== COMPANY TABLE ===\n";
try {
    $rows = DB::select("SELECT * FROM companylist LIMIT 10");
    foreach ($rows as $r) print_r((array)$r);
} catch (Exception $e) {
    echo "No companylist: " . $e->getMessage() . "\n";
}
