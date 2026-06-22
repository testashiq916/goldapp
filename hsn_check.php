<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// Check all company DBs
$allDbs = DB::select('SHOW DATABASES');
$skipDbs = ['information_schema','performance_schema','mysql','sys','phpmyadmin','bakeryerp','hrmbk','hrms','hrms_db','insurenerp_test','masjid_erp','nafix_hrms','officeit_erp','food_production_erp','rbldpnmy_hrmks','taskmanager','cybor432_erpnew','eperfume_erp','perfu_export'];

foreach ($allDbs as $d) {
    $db = array_values((array)$d)[0];
    if (in_array($db, $skipDbs)) continue;

    try {
        // Check if this is a goldapp DB
        $generals = DB::select("SELECT cvalue FROM `{$db}`.generals WHERE code='SHOPNM' LIMIT 1");
        if (!$generals) continue;
        $shopName = $generals[0]->cvalue;

        // Get item types and saccode status
        $summary = DB::select("SELECT itype, COUNT(*) as cnt, SUM(saccode='' OR saccode IS NULL) as missing FROM `{$db}`.items GROUP BY itype ORDER BY itype");

        echo "\n=== DB: $db | Shop: $shopName ===\n";
        foreach ($summary as $row) {
            echo "  itype={$row->itype} | total={$row->cnt} | missing saccode={$row->missing}\n";
        }

        // Show distinct saccode values already set
        $existing = DB::select("SELECT DISTINCT saccode, COUNT(*) as cnt FROM `{$db}`.items WHERE saccode!='' AND saccode IS NOT NULL GROUP BY saccode");
        if ($existing) {
            echo "  Existing saccodes: ";
            foreach ($existing as $e) echo "{$e->saccode}({$e->cnt}) ";
            echo "\n";
        }

        // Show item groups
        $grps = DB::select("SELECT TRIM(code) as code, TRIM(name) as name FROM `{$db}`.itemgrp ORDER BY code LIMIT 20");
        echo "  Groups: ";
        foreach ($grps as $g) echo "{$g->code}={$g->name}  ";
        echo "\n";

    } catch (Exception $e) { /* skip non-gold DBs */ }
}
