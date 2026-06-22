<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$errors = [];
$message = '';
$previewRows = [];
$summary = [];

function rr_clean(mixed $value): string
{
    return trim(str_replace(["\r", "\n", "\t"], ' ', (string) $value));
}

function rr_has_table(string $table): bool
{
    try {
        return Schema::hasTable($table);
    } catch (Throwable) {
        return false;
    }
}

function rr_has_col(string $table, string $column): bool
{
    try {
        return rr_has_table($table) && Schema::hasColumn($table, $column);
    } catch (Throwable) {
        return false;
    }
}

function rr_switch_database(string $database): void
{
    $database = preg_replace('/[^A-Za-z0-9_]/', '', $database) ?? '';
    if ($database === '') {
        return;
    }

    Config::set('database.connections.mysql.database', $database);
    DB::purge('mysql');
    DB::reconnect('mysql');
}

function rr_available_databases(): array
{
    $databases = [];
    foreach ([
        rr_clean(env('DB_DATABASE', '')),
        rr_clean(env('DB_SECONDARY_DATABASE', '')),
        rr_clean(Config::get('database.connections.mysql.database', '')),
    ] as $configuredDb) {
        if ($configuredDb !== '') {
            $databases[$configuredDb] = $configuredDb;
        }
    }

    $current = rr_clean(Config::get('database.connections.mysql.database', ''));
    if ($current !== '') {
        $databases[$current] = $current;
    }

    $path = storage_path('app/company-select.json');
    if (is_file($path)) {
        $decoded = json_decode((string) file_get_contents($path), true);
        if (is_array($decoded)) {
            foreach ($decoded as $row) {
                $db = rr_clean($row['database'] ?? '');
                if ($db !== '') {
                    $databases[$db] = $db;
                }
            }
        }
    }

    if (rr_is_local_request()) {
        try {
            foreach (DB::select('SHOW DATABASES') as $row) {
                $values = array_values((array) $row);
                $db = rr_clean($values[0] ?? '');
                if ($db === '' || in_array(strtolower($db), ['information_schema', 'mysql', 'performance_schema', 'phpmyadmin', 'sys'], true)) {
                    continue;
                }
                $databases[$db] = $db;
            }
        } catch (Throwable) {
            // Keep env/registry database list when SHOW DATABASES is not allowed.
        }
    }

    return array_values($databases);
}

function rr_is_local_request(): bool
{
    $host = strtolower(rr_clean($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
    $host = explode(':', $host)[0] ?? $host;

    return in_array($host, ['localhost', '127.0.0.1', '::1'], true)
        || str_ends_with($host, '.test')
        || str_ends_with($host, '.local');
}

function rr_database_has_table(string $database, string $table): bool
{
    $database = preg_replace('/[^A-Za-z0-9_]/', '', $database) ?? '';
    $table = preg_replace('/[^A-Za-z0-9_]/', '', $table) ?? '';
    if ($database === '' || $table === '') {
        return false;
    }

    try {
        return (int) DB::table('information_schema.tables')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->count() > 0;
    } catch (Throwable) {
        return false;
    }
}

function rr_financial_year_start(): string
{
    $year = (int) date('Y');
    if ((int) date('n') < 4) {
        $year--;
    }

    return sprintf('%04d-04-01', $year);
}

function rr_billing_database_status(string $database): array
{
    return [
        'salesm' => rr_database_has_table($database, 'salesm'),
        'salestype' => rr_database_has_table($database, 'salestype'),
    ];
}

function rr_first_billing_database(array $databases): string
{
    foreach ($databases as $database) {
        $status = rr_billing_database_status($database);
        if ($status['salesm'] || $status['salestype']) {
            return $database;
        }
    }

    return '';
}

function rr_general_s(string $code, string $default = ''): string
{
    if (!rr_has_table('generals')) {
        return $default;
    }

    $value = DB::table('generals')->whereRaw('UPPER(TRIM(code)) = ?', [strtoupper($code)])->value('cvalue');
    $value = rr_clean($value ?? '');
    return $value === '' ? $default : $value;
}

function rr_general_i(string $code, int $default = 0): int
{
    if (!rr_has_table('generali')) {
        return $default;
    }

    $value = DB::table('generali')->whereRaw('UPPER(TRIM(code)) = ?', [strtoupper($code)])->value('cvalue');
    return is_numeric($value) ? (int) $value : $default;
}

function rr_set_general_i(string $code, int $value): void
{
    if (!rr_has_table('generali')) {
        return;
    }

    DB::table('generali')->updateOrInsert(['code' => $code], ['cvalue' => (string) max(0, $value)]);
}

function rr_doc_no(string $prefix, int $number, int $length): string
{
    return $prefix . str_pad((string) $number, max(1, $length), '0', STR_PAD_LEFT);
}

function rr_number_from_docno(string $docNo): int
{
    $docNo = rr_clean($docNo);
    if ($docNo === '') {
        return 0;
    }

    if (preg_match('/(\d+)\D*$/', $docNo, $m) === 1) {
        return (int) $m[1];
    }

    return is_numeric($docNo) ? (int) $docNo : 0;
}

function rr_last_no_before(string $table, string $billColumn, string $fromDate, int $control, string $counterCode): int
{
    if (!rr_has_col($table, $billColumn)) {
        return rr_general_i($counterCode, 0);
    }

    $query = DB::table($table)->whereDate('tdate', '<', $fromDate);
    if (rr_has_col($table, 'control')) {
        $query->where('control', $control);
    }

    $lastDoc = rr_clean($query->max($billColumn) ?? '');
    if ($lastDoc === '') {
        return rr_general_i($counterCode, 0);
    }

    return rr_number_from_docno($lastDoc);
}

function rr_sales_types(): array
{
    $rows = [];

    if (rr_has_table('salestype')) {
        $rows = DB::table('salestype')
            ->select(array_values(array_filter(['code', rr_has_col('salestype', 'name') ? 'name' : null, rr_has_col('salestype', 'prefix') ? 'prefix' : null])))
            ->orderBy('code')
            ->get()
            ->map(fn ($row) => [
                'code' => rr_clean($row->code ?? ''),
                'name' => rr_clean($row->name ?? ''),
                'prefix' => rr_clean($row->prefix ?? ''),
                'source' => 'salestype',
            ])
            ->filter(fn ($row) => $row['code'] !== '')
            ->keyBy(fn ($row) => strtoupper($row['code']))
            ->all();
    }

    if (rr_has_col('salesm', 'billtype')) {
        $usedTypes = DB::table('salesm')
            ->selectRaw('DISTINCT UPPER(TRIM(COALESCE(billtype, ""))) as code')
            ->whereRaw('TRIM(COALESCE(billtype, "")) <> ""')
            ->orderBy('code')
            ->pluck('code')
            ->map(fn ($code) => rr_clean($code))
            ->filter(fn ($code) => $code !== '')
            ->values()
            ->all();

        foreach ($usedTypes as $code) {
            if (!isset($rows[$code])) {
                $rows[$code] = [
                    'code' => $code,
                    'name' => '',
                    'prefix' => '',
                    'source' => 'salesm',
                ];
            }
        }
    }

    ksort($rows);
    return array_values($rows);
}

function rr_type_count(string $module, string $fromDate, string $toDate, string $billType, bool $includeEstimates): int
{
    $table = $module === 'sales_return' ? 'salesrm' : 'salesm';
    if (!rr_has_table($table)) {
        return 0;
    }

    $query = DB::table($table);
    if (rr_has_col($table, 'tdate')) {
        $query->whereDate('tdate', '>=', $fromDate)->whereDate('tdate', '<=', $toDate);
    }
    if (rr_has_col($table, 'control')) {
        $query->whereIn('control', $includeEstimates ? [1, 2] : [1]);
    }
    if ($module === 'sales_return' && rr_has_col($table, 'sr')) {
        $query->where('sr', 'R');
    }
    if ($module === 'sales' && rr_has_col($table, 'billno')) {
        $query->whereRaw("LEFT(TRIM(COALESCE(billno, '')), 2) <> 'SO'");
    }
    if ($billType !== '' && rr_has_col($table, 'billtype')) {
        $query->whereRaw('UPPER(TRIM(billtype)) = ?', [strtoupper($billType)]);
    }

    return (int) $query->count();
}

function rr_prefix_for_billtype(string $billType, string $fallback): string
{
    if ($billType === '' || !rr_has_table('salestype') || !rr_has_col('salestype', 'prefix')) {
        return $fallback;
    }

    $prefix = rr_clean(DB::table('salestype')->whereRaw('UPPER(TRIM(code)) = ?', [strtoupper($billType)])->value('prefix') ?? '');
    return $prefix === '' ? $fallback : $prefix;
}

function rr_build_rows(array $input): array
{
    $mode = $input['module'];
    $table = $mode === 'sales_return' ? 'salesrm' : 'salesm';
    if (!rr_has_table($table)) {
        throw new RuntimeException($table . ' table not found.');
    }

    $fromDate = $input['from_date'];
    $toDate = $input['to_date'];
    $includeEstimates = !empty($input['include_estimates']);
    $billTypes = array_values(array_unique(array_filter(array_map(
        fn ($value) => strtoupper(rr_clean($value)),
        is_array($input['billtypes'] ?? []) ? $input['billtypes'] : []
    ))));
    $prefixOverrides = [];
    if (is_array($input['prefixes'] ?? null)) {
        foreach ($input['prefixes'] as $code => $prefix) {
            $code = strtoupper(rr_clean($code));
            $prefix = rr_clean($prefix);
            if ($code !== '' && $prefix !== '') {
                $prefixOverrides[$code] = $prefix;
            }
        }
    }
    $startOverrides = [];
    if (is_array($input['start_numbers'] ?? null)) {
        foreach ($input['start_numbers'] as $code => $startNo) {
            $code = strtoupper(rr_clean($code));
            $startNo = (int) $startNo;
            if ($code !== '' && $startNo > 0) {
                $startOverrides[$code] = $startNo;
            }
        }
    }
    $billNo = $mode === 'sales_return' ? 'billno' : 'billno';
    $billLast = rr_last_no_before($table, $billNo, $fromDate, 1, $mode === 'sales_return' ? 'SRETURNB' : 'SALESB');
    $estLast = rr_last_no_before($table, $billNo, $fromDate, 2, $mode === 'sales_return' ? 'SRETURNE' : 'SALESE');

    $salesPrefix = rr_general_s('SBPREF', 'SLB/');
    $salesLen = (int) rr_general_s('SBLEN', '5');
    $estimatePrefix = rr_general_s('SEPREF', 'SLE/');
    $estimateLen = (int) rr_general_s('SELEN', '5');
    $billTypeWise = strtoupper(rr_general_s('BillTypewiseBillNo', 'N')) === 'Y';

    $query = DB::table($table)->select([$table . '.slno', $table . '.billno', $table . '.tdate']);
    foreach (['custname', 'billtype', 'control', 'status', 'sr'] as $col) {
        if (rr_has_col($table, $col)) {
            $query->addSelect($table . '.' . $col);
        }
    }

    if (rr_has_col($table, 'tdate')) {
        $query->whereDate($table . '.tdate', '>=', $fromDate)->whereDate($table . '.tdate', '<=', $toDate);
    }
    if (rr_has_col($table, 'control')) {
        $query->whereIn($table . '.control', $includeEstimates ? [1, 2] : [1]);
    }
    if ($mode === 'sales_return' && rr_has_col($table, 'sr')) {
        $query->where($table . '.sr', 'R');
    }
    if ($mode === 'sales' && rr_has_col($table, 'billno')) {
        $query->whereRaw("LEFT(TRIM(COALESCE({$table}.billno, '')), 2) <> 'SO'");
    }
    if ($billTypes !== [] && rr_has_col($table, 'billtype')) {
        $query->where(function ($typeQuery) use ($table, $billTypes) {
            foreach ($billTypes as $idx => $billType) {
                $method = $idx === 0 ? 'whereRaw' : 'orWhereRaw';
                $typeQuery->{$method}('UPPER(TRIM(' . $table . '.billtype)) = ?', [$billType]);
            }
        });
    }

    $rows = $query->orderBy($table . '.tdate')->orderBy($table . '.slno')->get();
    $out = [];
    $typeWiseCounters = [];

    foreach ($rows as $row) {
        $control = (int) ($row->control ?? 1);
        $party = rr_clean($row->custname ?? '');
        $oldBillNo = rr_clean($row->billno ?? '');
        $rowBillType = rr_clean($row->billtype ?? '');
        $prefix = '';
        $counterCode = '';
        $newBillNo = '';
        $particular = '';

        if ($control === 1) {
            if ($mode === 'sales_return') {
                $billLast++;
                $newBillNo = rr_doc_no('SRB/', $billLast, 5);
                $counterCode = 'SRETURNB';
                $particular = 'By Sales Return (' . $newBillNo . ')';
            } elseif ($billTypeWise || isset($prefixOverrides[strtoupper($rowBillType)])) {
                $prefix = $prefixOverrides[strtoupper($rowBillType)] ?? rr_prefix_for_billtype($rowBillType, $salesPrefix);
                $counterCode = 'SALES' . $prefix;
                if (!array_key_exists($counterCode, $typeWiseCounters)) {
                    $startNo = $startOverrides[strtoupper($rowBillType)] ?? 0;
                    $typeWiseCounters[$counterCode] = $startNo > 0 ? $startNo - 1 : rr_general_i($counterCode, 0);
                }
                $typeWiseCounters[$counterCode]++;
                $next = $typeWiseCounters[$counterCode];
                $newBillNo = rr_doc_no($prefix, $next, 5);
                $particular = 'By Sales (' . $newBillNo . ')';
            } else {
                $billLast++;
                $counterCode = 'SALESB';
                $newBillNo = rr_doc_no($salesPrefix, $billLast, $salesLen);
                $particular = 'By Sales (' . $newBillNo . ')';
            }

            if ($mode === 'sales' && $party !== '') {
                $particular .= ' To ' . $party;
            }
        } elseif ($control === 2 && $includeEstimates) {
            $estLast++;
            if ($mode === 'sales_return') {
                $newBillNo = rr_doc_no('SRE/', $estLast, 5);
                $counterCode = 'SRETURNE';
                $particular = 'By Sales Return (' . $newBillNo . ')';
            } else {
                $newBillNo = rr_doc_no($estimatePrefix, $estLast, $estimateLen);
                $counterCode = 'SALESE';
                $particular = 'By Sales (' . $newBillNo . ')';
            }
        }

        if ($newBillNo === '') {
            continue;
        }

        $out[] = [
            'slno' => (int) $row->slno,
            'tdate' => rr_clean($row->tdate ?? ''),
            'control' => $control,
            'billtype' => $rowBillType,
            'custname' => $party,
            'old_billno' => $oldBillNo,
            'new_billno' => $newBillNo,
            'particular' => mb_substr($particular, 0, 40),
            'counter_code' => $counterCode,
            'counter_value' => rr_number_from_docno($newBillNo),
        ];
    }

    return $out;
}

function rr_apply_rows(array $rows): array
{
    if ($rows === []) {
        return ['updated' => 0];
    }

    $slnos = array_values(array_unique(array_map(fn ($row) => (int) $row['slno'], $rows)));
    $newNos = array_values(array_unique(array_map(fn ($row) => (string) $row['new_billno'], $rows)));
    if (count($newNos) !== count($rows)) {
        throw new RuntimeException('Duplicate new bill numbers are present in preview.');
    }

    foreach (['salesm', 'salesrm'] as $table) {
        if (!rr_has_col($table, 'billno')) {
            continue;
        }
        $conflicts = DB::table($table)
            ->whereIn('billno', $newNos)
            ->whereNotIn('slno', $slnos)
            ->pluck('billno')
            ->map(fn ($v) => rr_clean($v))
            ->unique()
            ->values()
            ->all();
        if ($conflicts !== []) {
            throw new RuntimeException('New bill number already exists in ' . $table . ': ' . implode(', ', array_slice($conflicts, 0, 5)));
        }
    }

    $summary = ['processed' => 0, 'salesm' => 0, 'purchasem_docno' => 0, 'salesrm' => 0, 'daybookpart' => 0, 'generali' => 0];

    DB::transaction(function () use ($rows, &$summary): void {
        $counterValues = [];

        foreach ($rows as $row) {
            $slno = (int) $row['slno'];
            $newBillNo = (string) $row['new_billno'];
            $summary['processed']++;

            if (rr_has_col('salesm', 'billno')) {
                $summary['salesm'] += DB::table('salesm')->where('slno', $slno)->update(['billno' => $newBillNo]);
            }
            if (rr_has_col('purchasem', 'docno')) {
                $summary['purchasem_docno'] += DB::table('purchasem')->where('slno', $slno)->update(['docno' => $newBillNo]);
            }
            if (rr_has_col('salesrm', 'billno')) {
                $summary['salesrm'] += DB::table('salesrm')->where('slno', $slno)->update(['billno' => $newBillNo]);
            }
            if (rr_has_col('daybookpart', 'particular')) {
                $summary['daybookpart'] += DB::table('daybookpart')->where('slno', $slno)->update(['particular' => $row['particular']]);
            }

            $code = rr_clean($row['counter_code'] ?? '');
            if ($code !== '') {
                $counterValues[$code] = max((int) ($counterValues[$code] ?? 0), (int) $row['counter_value']);
            }
        }

        foreach ($counterValues as $code => $value) {
            rr_set_general_i($code, $value);
            $summary['generali']++;
        }
    });

    return array_filter($summary, fn ($value) => (int) $value > 0);
}

$selectedDatabase = rr_clean($_POST['database'] ?? $_GET['database'] ?? $_COOKIE['selected_company_database'] ?? Config::get('database.connections.mysql.database', ''));
if ($selectedDatabase !== '') {
    rr_switch_database($selectedDatabase);
}

$databases = rr_available_databases();
$currentStatus = rr_billing_database_status(rr_clean(Config::get('database.connections.mysql.database', '')));
if (!$currentStatus['salesm'] && !$currentStatus['salestype']) {
    $fallbackDatabase = rr_first_billing_database($databases);
    if ($fallbackDatabase !== '') {
        rr_switch_database($fallbackDatabase);
    }
}
$databaseStatuses = [];
foreach ($databases as $databaseName) {
    $databaseStatuses[$databaseName] = rr_billing_database_status($databaseName);
}
$salesTypes = rr_sales_types();
$today = date('Y-m-d');
$financialYearStart = rr_financial_year_start();
$action = rr_clean($_POST['action'] ?? '');
$postedBillTypes = is_array($_POST['billtypes'] ?? null) ? $_POST['billtypes'] : [];
$selectedBillTypes = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? array_values(array_unique(array_filter(array_map(fn ($value) => strtoupper(rr_clean($value)), $postedBillTypes))))
    : array_values(array_unique(array_map(fn ($row) => strtoupper($row['code']), $salesTypes)));
$input = [
    'database' => rr_clean(Config::get('database.connections.mysql.database', '')),
    'module' => rr_clean($_POST['module'] ?? 'sales'),
    'from_date' => rr_clean($_POST['from_date'] ?? $financialYearStart),
    'to_date' => rr_clean($_POST['to_date'] ?? $today),
    'include_estimates' => false,
    'billtypes' => $selectedBillTypes,
    'prefixes' => is_array($_POST['prefixes'] ?? null) ? $_POST['prefixes'] : [],
    'start_numbers' => is_array($_POST['start_numbers'] ?? null) ? $_POST['start_numbers'] : [],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!in_array($input['module'], ['sales', 'sales_return'], true)) {
            throw new RuntimeException('Invalid module selected.');
        }
        if ($salesTypes !== [] && $input['billtypes'] === []) {
            throw new RuntimeException('Tick at least one bill type to rearrange.');
        }
        foreach (['from_date', 'to_date'] as $key) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $input[$key])) {
                throw new RuntimeException('Date must be yyyy-mm-dd.');
            }
        }

        $previewRows = rr_build_rows($input);
        if ($action === 'apply') {
            $summary = rr_apply_rows($previewRows);
            $message = 'Rearrange completed in database ' . $input['database'] . '. Processed ' . count($previewRows) . ' bill(s).';
        } else {
            $message = 'Preview ready from database ' . $input['database'] . '.';
        }
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Rearange Sales Bill No</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f4f6f8;color:#172033;font:14px/1.45 "Segoe UI",Arial,sans-serif}.top{background:#fff;border-bottom:1px solid #d9e1ea;padding:16px 22px}.top h1{margin:0;font-size:22px}.top p{margin:4px 0 0;color:#64748b}.wrap{max-width:1220px;margin:0 auto;padding:22px}.panel{background:#fff;border:1px solid #d9e1ea;border-radius:8px;padding:18px;box-shadow:0 10px 24px rgba(15,23,42,.04)}.layout{display:grid;grid-template-columns:1fr 190px;gap:18px;align-items:start}.grid{display:grid;grid-template-columns:1.4fr 2.2fr 1.2fr 1.2fr 1fr;gap:14px}.field label{display:block;font-weight:700;margin-bottom:6px}.field input,.field select{width:100%;height:38px;border:1px solid #cbd5e1;border-radius:6px;padding:0 10px;background:#fff}.module-select{min-width:280px}.inline-field{display:flex;gap:8px}.inline-field input{flex:1}.inline-field button{height:38px;white-space:nowrap}.actions{display:flex;flex-direction:column;gap:10px;position:sticky;top:14px}.btn{border:0;border-radius:6px;padding:12px 14px;font-weight:800;cursor:pointer;width:100%}.btn.primary{background:#173a63;color:#fff}.btn.danger{background:#b42318;color:#fff}.btn.light{background:#e2e8f0;color:#172033}.msg{margin:16px 0;padding:12px 14px;border-radius:6px}.ok{background:#ecfdf3;border:1px solid #abefc6;color:#067647}.err{background:#fef3f2;border:1px solid #fecdca;color:#b42318}.summary{display:flex;gap:8px;flex-wrap:wrap;margin:14px 0}.pill{background:#eef4ff;color:#175cd3;border-radius:999px;padding:6px 10px;font-weight:700}table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #d9e1ea}th,td{padding:8px 10px;border-bottom:1px solid #e5e7eb;text-align:left;white-space:nowrap}th{background:#f8fafc;font-size:12px;text-transform:uppercase;color:#475569}.old{color:#b42318}.new{color:#067647;font-weight:800}.typebox{margin-top:16px;border:1px solid #d9e1ea;border-radius:8px;overflow:hidden;background:#fff}.typehead{display:flex;justify-content:space-between;gap:12px;align-items:center;background:#f8fafc;padding:10px 12px;border-bottom:1px solid #d9e1ea;font-weight:800}.typeactions{display:flex;gap:8px}.mini{border:1px solid #cbd5e1;background:#fff;border-radius:6px;padding:5px 8px;font-size:12px;font-weight:800;cursor:pointer}.type-table{width:100%;border:0}.type-table th,.type-table td{border-bottom:1px solid #eef2f7}.type-table input[type=checkbox]{width:16px;height:16px}.prefix-input{width:150px;height:32px;border:1px solid #cbd5e1;border-radius:6px;padding:0 8px}.start-input{width:90px;height:32px;border:1px solid #cbd5e1;border-radius:6px;padding:0 8px;text-align:right}.typecode{font-weight:800}.source{font-size:11px;color:#64748b}.empty{padding:16px;color:#64748b}.sidebox{background:#f8fafc;border:1px solid #d9e1ea;border-radius:8px;padding:12px}.sidebox .field{margin-bottom:10px}.db-current{margin-bottom:10px;padding:9px 10px;border:1px solid #d9e1ea;border-radius:6px;background:#fff;color:#64748b;font-size:12px}.db-current strong{display:block;color:#172033;font-size:13px;overflow:hidden;text-overflow:ellipsis}@media(max-width:1100px){.grid{grid-template-columns:1fr 1fr 1fr}.module-select{min-width:0}}@media(max-width:980px){.layout{grid-template-columns:1fr}.actions{position:static;flex-direction:row;flex-wrap:wrap}.btn{width:auto}}@media(max-width:560px){.grid{grid-template-columns:1fr}th,td{white-space:normal}.actions{flex-direction:column}.btn{width:100%}.prefix-input,.start-input{width:100%}}
</style>
</head>
<body>
<div class="top">
    <h1>Rearange Sales Bill No</h1>
    <p>Legacy flow: find last bill before From Date, then renumber sales register rows by date and slno.</p>
</div>
<div class="wrap">
    <form method="post" class="panel">
        <div class="layout">
            <div>
                <div class="grid">
                    <div class="field">
                        <label>Select Database</label>
                        <select name="database" onchange="window.location.href='rearange.php?database=' + encodeURIComponent(this.value)">
                            <?php foreach ($databases as $db): ?>
                                <?php
                                    $dbStatus = $databaseStatuses[$db] ?? ['salesm' => false, 'salestype' => false];
                                    $dbNote = $dbStatus['salesm'] || $dbStatus['salestype']
                                        ? ' - bills ok'
                                        : ' - no sales tables';
                                ?>
                                <option value="<?= htmlspecialchars($db) ?>" <?= strcasecmp($db, $input['database']) === 0 ? 'selected' : '' ?>><?= htmlspecialchars($db . $dbNote) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Module</label>
                        <select name="module" class="module-select">
                            <option value="sales" <?= $input['module'] === 'sales' ? 'selected' : '' ?>>Sales Bill Rearrange</option>
                            <option value="sales_return" <?= $input['module'] === 'sales_return' ? 'selected' : '' ?>>Sales Return Bill Rearrange</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>From Date</label>
                        <input type="date" name="from_date" value="<?= htmlspecialchars($input['from_date']) ?>">
                    </div>
                    <div class="field">
                        <label>To Date</label>
                        <input type="date" name="to_date" value="<?= htmlspecialchars($input['to_date']) ?>">
                    </div>
                    <div class="field">
                        <label>Starting Number</label>
                        <div class="inline-field">
                            <input id="globalStartNo" type="number" min="1" value="1">
                            <button type="button" class="mini" onclick="document.querySelectorAll('.start-input').forEach(i=>i.value=document.getElementById('globalStartNo').value || 1)">Set All</button>
                        </div>
                    </div>
                </div>
                <div class="typebox">
                    <div class="typehead">
                        <span>Bill Types / Series Prefix</span>
                        <span class="typeactions">
                            <button type="button" class="mini" onclick="document.querySelectorAll('.billtype-check').forEach(c=>c.checked=true)">Select All</button>
                            <button type="button" class="mini" onclick="document.querySelectorAll('.billtype-check').forEach(c=>c.checked=false)">Clear</button>
                        </span>
                    </div>
                    <?php if ($salesTypes === []): ?>
                        <div class="empty">No bill types found in salestype or salesm.</div>
                    <?php else: ?>
                        <table class="type-table">
                            <thead>
                                <tr>
                                    <th>Select</th>
                                    <th>Series Prefix</th>
                                    <th>Bill Type</th>
                                    <th>Code</th>
                                    <th>Start No</th>
                                    <th>Auto No</th>
                                    <th>Rows</th>
                                    <th>Source</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($salesTypes as $type): ?>
                                    <?php
                                        $code = strtoupper($type['code']);
                                        $prefix = rr_clean($input['prefixes'][$code] ?? '') !== ''
                                            ? rr_clean($input['prefixes'][$code])
                                            : ($type['prefix'] !== '' ? $type['prefix'] : rr_general_s('SBPREF', 'SLB/'));
                                        $defaultStartNo = rr_general_i('SALES' . $prefix, 0) + 1;
                                        $startNo = (int) ($input['start_numbers'][$code] ?? $defaultStartNo);
                                        if ($startNo <= 0) {
                                            $startNo = $defaultStartNo;
                                        }
                                        $autoNo = rr_doc_no($prefix, $startNo, 5);
                                        $rowCount = rr_type_count($input['module'], $input['from_date'], $input['to_date'], $code, $input['include_estimates']);
                                        $checked = in_array($code, $input['billtypes'], true);
                                    ?>
                                    <tr>
                                        <td><input class="billtype-check" type="checkbox" name="billtypes[]" value="<?= htmlspecialchars($code) ?>" <?= $checked ? 'checked' : '' ?>></td>
                                        <td><input class="prefix-input" name="prefixes[<?= htmlspecialchars($code) ?>]" value="<?= htmlspecialchars($prefix) ?>"></td>
                                        <td><?= htmlspecialchars($type['name']) ?></td>
                                        <td class="typecode"><?= htmlspecialchars($code) ?></td>
                                        <td><input class="start-input" type="number" min="1" name="start_numbers[<?= htmlspecialchars($code) ?>]" value="<?= htmlspecialchars((string) $startNo) ?>"></td>
                                        <td><?= htmlspecialchars($autoNo) ?></td>
                                        <td><?= htmlspecialchars((string) $rowCount) ?></td>
                                        <td><span class="source"><?= htmlspecialchars($type['source'] ?? '') ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            <div class="sidebox">
                <div class="db-current">Database<br><strong><?= htmlspecialchars($input['database']) ?></strong></div>
                <button class="btn primary" type="submit" name="action" value="preview">Preview</button>
                <button class="btn danger" type="submit" name="action" value="apply" onclick="return confirm('You are going to rearrange the billnos. Continue?')">Rearrange</button>
                <button class="btn light" type="button" onclick="location.href='rearange.php'">Reset</button>
            </div>
        </div>
    </form>

    <?php foreach ($errors as $error): ?>
        <div class="msg err"><?= htmlspecialchars($error) ?></div>
    <?php endforeach; ?>
    <?php if ($message !== ''): ?>
        <div class="msg ok"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($summary !== []): ?>
        <div class="summary">
            <?php foreach ($summary as $key => $value): ?>
                <span class="pill"><?= htmlspecialchars((string) $key) ?>: <?= htmlspecialchars((string) $value) ?></span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($previewRows !== []): ?>
        <table>
            <thead>
                <tr>
                    <th>Slno</th><th>Date</th><th>Ctrl</th><th>Type</th><th>Customer</th><th>Old Bill No</th><th>New Bill No</th><th>Particular</th><th>Counter</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($previewRows as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars((string) $row['slno']) ?></td>
                        <td><?= htmlspecialchars($row['tdate']) ?></td>
                        <td><?= htmlspecialchars((string) $row['control']) ?></td>
                        <td><?= htmlspecialchars($row['billtype']) ?></td>
                        <td><?= htmlspecialchars($row['custname']) ?></td>
                        <td class="old"><?= htmlspecialchars($row['old_billno']) ?></td>
                        <td class="new"><?= htmlspecialchars($row['new_billno']) ?></td>
                        <td><?= htmlspecialchars($row['particular']) ?></td>
                        <td><?= htmlspecialchars($row['counter_code']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>
