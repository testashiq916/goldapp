<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// Find item/HSN tables in demo DB
echo "=== Tables in demo with hsn/item/stock/group columns ===\n";
$tables = DB::select("SHOW TABLES FROM demo");
foreach ($tables as $t) {
    $name = array_values((array)$t)[0];
    echo $name . "\n";
}

echo "\n=== Check for HSN column in stockm/itemm/itemmaster ===\n";
foreach (['stockm','itemm','itemmaster','stockmaster','items','products','stock'] as $tbl) {
    try {
        $cols = DB::select("DESCRIBE demo.{$tbl}");
        $colNames = array_column(array_map('get_object_vars', $cols), 'Field');
        $hsnCols = array_filter($colNames, fn($c) => stripos($c,'hsn')!==false || stripos($c,'sac')!==false);
        if ($hsnCols) {
            echo "Table: $tbl | HSN cols: " . implode(', ', $hsnCols) . "\n";
            // Show sample
            $sample = DB::select("SELECT * FROM demo.{$tbl} LIMIT 3");
            foreach ($sample as $row) { print_r((array)$row); break; }
        }
    } catch (Exception $e) { /* skip */ }
}
