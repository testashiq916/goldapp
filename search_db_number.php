<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$search = '9037964994';
$results = [];

$tables = DB::select('SHOW TABLES');
$dbName = 'Tables_in_' . env('DB_DATABASE');

foreach ($tables as $table) {
    $tableName = $table->$dbName;
    $columns = Schema::getColumnListing($tableName);
    
    foreach ($columns as $column) {
        $count = DB::table($tableName)->where($column, 'LIKE', "%$search%")->count();
        if ($count > 0) {
            $results[] = [
                'table' => $tableName,
                'column' => $column,
                'count' => $count
            ];
        }
    }
}

echo json_encode($results, JSON_PRETTY_PRINT);
