<?php

namespace App\Support;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class SecondaryDatabaseSync
{
    public const PERMISSION = 'ALLOWSECONDARYDBSYNC';
    private const SOFTWARE_SECTION = 'Software';
    private const SECONDARY_PARTY_SERIES_SETTING = 'SecondaryPartySeriesSeperate';
    private const SECONDARY_TRANSACTION_SERIES_SETTING = 'SecondaryTransactionSeriesSeperate';
    private const MODULE_PREFIX_KEYS = [
        'sales'    => 'SecSalesSeriesPrefix',
        'purchase' => 'SecPurchaseSeriesPrefix',
        'receipt'  => 'SecReceiptSeriesPrefix',
        'payment'  => 'SecPaymentSeriesPrefix',
        'order'    => 'SecOrderSeriesPrefix',
    ];

    private string $connectionName = 'secondary_sync';

    private string $sourceDatabase = '';

    private string $targetDatabase = '';

    private ?string $targetDatabaseOverride = null;

    private ?array $syncMapCache = null;

    private array $sourcePartyTypeCache = [];

    public static function userCanUse(?string $userCode): bool
    {
        return self::secondarySaveEnabled();
    }

    public static function secondarySaveEnabled(): bool
    {
        $iniPath = storage_path('app/software-settings.ini');
        if (!File::exists($iniPath)) {
            return true;
        }

        $parsed = parse_ini_string((string) File::get($iniPath), true, INI_SCANNER_RAW);
        if (!is_array($parsed)) {
            return true;
        }

        $value = $parsed[self::SOFTWARE_SECTION][self::PERMISSION] ?? 'Y';
        return strtoupper(trim((string) $value)) !== 'N';
    }

    private static function softwareFlagEnabled(string $key, string $default = 'N'): bool
    {
        $iniPath = storage_path('app/software-settings.ini');
        if (!File::exists($iniPath)) {
            return strtoupper(trim($default)) === 'Y';
        }

        $parsed = parse_ini_string((string) File::get($iniPath), true, INI_SCANNER_RAW);
        if (!is_array($parsed)) {
            return strtoupper(trim($default)) === 'Y';
        }

        $value = $parsed[self::SOFTWARE_SECTION][$key] ?? $default;
        return strtoupper(trim((string) $value)) === 'Y';
    }

    private static function softwareString(string $key, string $default = ''): string
    {
        $iniPath = storage_path('app/software-settings.ini');
        if (!File::exists($iniPath)) {
            return $default;
        }

        $parsed = parse_ini_string((string) File::get($iniPath), true, INI_SCANNER_RAW);
        if (!is_array($parsed)) {
            return $default;
        }

        return trim((string) ($parsed[self::SOFTWARE_SECTION][$key] ?? $default));
    }

    public function sync(string $module, int $slno, array $context = []): array
    {
        $module = strtolower(trim($module));
        if ($slno <= 0) {
            throw new RuntimeException('Secondary sync SLNO is invalid.');
        }

        $this->bootTargetConnection();
        $this->guardPrimaryToSecondaryTransactionSync();

        $copied = match ($module) {
            'sales' => $this->syncSalesBundle([$slno]),
            'purchase' => $this->syncPurchaseBundle([$slno]),
            'order' => $this->syncOrderBundle([$slno]),
            'order_sale' => $this->syncOrderSaleBundle([$slno], trim((string) ($context['order_no'] ?? ''))),
            'journal' => $this->syncVoucherBundle([$slno], 'JL'),
            'receipt' => $this->syncVoucherBundle([$slno], 'VR'),
            'payment' => $this->syncVoucherBundle([$slno], 'VP'),
            default => throw new RuntimeException('Secondary sync module is not supported.'),
        };

        return [
            'database' => $this->targetDatabase,
            'copied' => $copied,
            'module' => $module,
        ];
    }

    public function syncMany(string $module, array $slnos, array $context = []): array
    {
        $module = strtolower(trim($module));
        $slnos = collect($slnos)
            ->map(fn ($value) => (int) $value)
            ->filter(fn ($value) => $value > 0)
            ->unique()
            ->values()
            ->all();

        if ($slnos === []) {
            return [
                'database' => $this->targetDatabaseName(),
                'copied' => 0,
                'module' => $module,
            ];
        }

        $copied = 0;
        foreach ($slnos as $slno) {
            $result = $this->sync($module, $slno, $context);
            $copied += (int) ($result['copied'] ?? 0);
        }

        return [
            'database' => $this->targetDatabase,
            'copied' => $copied,
            'module' => $module,
        ];
    }

    public function targetDatabaseName(): string
    {
        return $this->readConfiguredTargetDatabase();
    }

    public function useTargetDatabase(string $database): static
    {
        $database = trim($database);
        $this->targetDatabaseOverride = $database !== '' ? $database : null;
        $this->targetDatabase = '';
        $this->syncMapCache = null;

        return $this;
    }

    public function copyWholeTable(string $table, array $candidateKeys): int
    {
        $this->bootTargetConnection();

        return $this->upsertRowsToTarget($table, $this->fetchLocalAllRows($table), $candidateKeys);
    }

    public function syncParty(string $code, string $type = ''): array
    {
        $code = strtoupper(trim($code));
        $type = strtoupper(trim($type));
        if ($code === '') {
            throw new RuntimeException('Secondary sync code is invalid.');
        }

        $this->bootTargetConnection();
        $this->guardPrimaryToSecondaryPartySync();
        $targetCode = $this->ensureTargetPartyCode($code, $type);
        $copied = $targetCode !== '' ? 1 : 0;

        return [
            'database' => $this->targetDatabase,
            'copied' => $copied,
            'module' => 'party',
            'code' => $targetCode !== '' ? $targetCode : $code,
            'source_code' => $code,
            'type' => $type,
        ];
    }

    public function deleteParty(string $code, string $type = ''): array
    {
        $code = strtoupper(trim($code));
        $type = strtoupper(trim($type));
        if ($code === '') {
            throw new RuntimeException('Secondary sync code is invalid.');
        }

        $this->bootTargetConnection();
        $this->guardPrimaryToSecondaryPartySync();
        $bucket = $type !== '' ? $type : 'UNK';
        $targetCode = trim((string) $this->getSyncMapValue('parties', $bucket, $code));
        if ($targetCode === '') {
            return [
                'database' => $this->targetDatabase,
                'copied' => 0,
                'module' => 'party_delete',
                'code' => $code,
                'source_code' => $code,
                'type' => $type,
            ];
        }

        foreach (['clients', 'clients_advanced', 'clientspict', 'clientsgs', 'clients_kuridet'] as $table) {
            $this->cleanupTargetRowsByCodes($table, ['code'], [$targetCode]);
        }
        $this->cleanupTargetRowsByCodes('accountm', ['accode'], [$targetCode]);

        return [
            'database' => $this->targetDatabase,
            'copied' => 1,
            'module' => 'party_delete',
            'code' => $targetCode,
            'source_code' => $code,
            'type' => $type,
        ];
    }

    public function syncBarcode(int $bcode): array
    {
        if ($bcode <= 0) {
            throw new RuntimeException('Secondary sync barcode is invalid.');
        }

        $this->bootTargetConnection();

        $barcodeRows = $this->fetchLocalRowsByCodes('barcode', ['bcode', 'barcode', 'barcodeno'], [(string) $bcode]);
        if ($barcodeRows === []) {
            return $this->deleteBarcode($bcode);
        }

        $docNos = collect($barcodeRows)
            ->pluck('docno')
            ->filter(fn ($value) => trim((string) $value) !== '')
            ->map(fn ($value) => trim((string) $value))
            ->unique()
            ->values()
            ->all();

        $this->cleanupTargetRowsByCodes('barcode_dmddet', ['bcode'], [(string) $bcode]);
        $this->cleanupTargetRowsByCodes('barcodedmd', ['bcode'], [(string) $bcode]);
        $this->cleanupTargetRowsByCodes('barcode', ['bcode', 'barcode', 'barcodeno'], [(string) $bcode]);

        $copied = 0;
        $copied += $this->upsertRowsToTarget('barcode', $barcodeRows, [['bcode'], ['barcode'], ['barcodeno']]);
        $copied += $this->upsertRowsToTarget('barcode_dmddet', $this->fetchLocalRowsByCodes('barcode_dmddet', ['bcode'], [(string) $bcode]), [['bcode', 'sno'], ['bcode']]);
        $copied += $this->upsertRowsToTarget('barcodedmd', $this->fetchLocalRowsByCodes('barcodedmd', ['bcode'], [(string) $bcode]), [['bcode']]);

        if ($docNos !== []) {
            $copied += $this->upsertRowsToTarget('barcodedoc', $this->fetchLocalRowsByColumn('barcodedoc', 'docno', $docNos), [['docno']]);
        }

        return [
            'database' => $this->targetDatabase,
            'copied' => $copied,
            'module' => 'barcode',
            'bcode' => $bcode,
        ];
    }

    public function deleteBarcode(int $bcode, string $docno = ''): array
    {
        if ($bcode <= 0) {
            throw new RuntimeException('Secondary sync barcode is invalid.');
        }

        $this->bootTargetConnection();

        if ($docno === '' && $this->targetTableExists('barcode')) {
            $docno = trim((string) (DB::connection($this->connectionName)->table('barcode')->where('bcode', $bcode)->value('docno') ?? ''));
        }

        $this->cleanupTargetRowsByCodes('barcode_dmddet', ['bcode'], [(string) $bcode]);
        $this->cleanupTargetRowsByCodes('barcodedmd', ['bcode'], [(string) $bcode]);
        $this->cleanupTargetRowsByCodes('barcode', ['bcode', 'barcode', 'barcodeno'], [(string) $bcode]);

        if ($docno !== '' && $this->targetTableHasColumn('barcode', 'docno') && $this->targetTableExists('barcodedoc')) {
            $stillExists = DB::connection($this->connectionName)
                ->table('barcode')
                ->where('docno', $docno)
                ->exists();
            if (!$stillExists) {
                DB::connection($this->connectionName)
                    ->table('barcodedoc')
                    ->where('docno', $docno)
                    ->delete();
            }
        }

        return [
            'database' => $this->targetDatabase,
            'copied' => 1,
            'module' => 'barcode_delete',
            'bcode' => $bcode,
            'docno' => $docno,
        ];
    }

    private function bootTargetConnection(): void
    {
        $targetDatabase = $this->readConfiguredTargetDatabase();
        if (!$this->isSafeDatabaseName($targetDatabase)) {
            throw new RuntimeException('Secondary database is not configured.');
        }

        $currentDatabase = trim((string) Config::get('database.connections.mysql.database', ''));
        if (!$this->isSafeDatabaseName($currentDatabase)) {
            throw new RuntimeException('Current company database is not configured.');
        }

        if (strcasecmp($currentDatabase, $targetDatabase) === 0) {
            throw new RuntimeException('Secondary database cannot be the same as the active company database.');
        }

        $this->sourceDatabase = $currentDatabase;

        $config = Config::get('database.connections.mysql');
        if (!is_array($config) || $config === []) {
            throw new RuntimeException('MySQL connection is not available.');
        }

        $config['database'] = $targetDatabase;
        Config::set('database.connections.' . $this->connectionName, $config);
        DB::purge($this->connectionName);

        $this->targetDatabase = $targetDatabase;
    }

    private function guardPrimaryToSecondaryTransactionSync(): void
    {
        $primaryDatabase = strtolower(trim((string) env('DB_DATABASE', '')));
        $sourceDatabase = strtolower(trim($this->sourceDatabase));
        $targetDatabase = strtolower(trim($this->targetDatabase));

        if ($primaryDatabase === '' || $sourceDatabase === '' || $targetDatabase === '') {
            return;
        }

        if ($sourceDatabase !== $primaryDatabase || $targetDatabase === $primaryDatabase) {
            throw new RuntimeException('Sales, purchase, and order sync is allowed only from primary company to secondary company.');
        }
    }

    private function guardPrimaryToSecondaryPartySync(): void
    {
        $primaryDatabase = strtolower(trim((string) env('DB_DATABASE', '')));
        $sourceDatabase = strtolower(trim($this->sourceDatabase));
        $targetDatabase = strtolower(trim($this->targetDatabase));

        if ($primaryDatabase === '' || $sourceDatabase === '' || $targetDatabase === '') {
            return;
        }

        if ($sourceDatabase !== $primaryDatabase || $targetDatabase === $primaryDatabase) {
            throw new RuntimeException('Customer and supplier sync is allowed only from primary company to secondary company.');
        }
    }

    private function readConfiguredTargetDatabase(): string
    {
        if ($this->targetDatabaseOverride !== null && $this->targetDatabaseOverride !== '') {
            return $this->targetDatabaseOverride;
        }

        $path = storage_path('app/company-dbstrf.json');
        if (!File::exists($path)) {
            return '';
        }

        $decoded = json_decode((string) File::get($path), true);
        return is_array($decoded) ? trim((string) ($decoded['dbstrf'] ?? '')) : '';
    }

    private function syncMapPath(): string
    {
        return storage_path('app/secondary-sync-map.json');
    }

    private function loadSyncMap(): array
    {
        if ($this->syncMapCache !== null) {
            return $this->syncMapCache;
        }

        $path = $this->syncMapPath();
        if (!File::exists($path)) {
            return $this->syncMapCache = [];
        }

        $decoded = json_decode((string) File::get($path), true);
        return $this->syncMapCache = is_array($decoded) ? $decoded : [];
    }

    private function saveSyncMap(): void
    {
        $path = $this->syncMapPath();
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($this->syncMapCache ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function getSyncMapValue(string $bucket, string $module, string $sourceKey): mixed
    {
        $map = $this->loadSyncMap();
        return $map[$this->sourceDatabase][$this->targetDatabase][$bucket][$module][$sourceKey] ?? null;
    }

    private function findSourceKeyByMappedValue(string $bucket, string $module, string $field, mixed $value): ?string
    {
        $map = $this->loadSyncMap();
        $entries = $map[$this->sourceDatabase][$this->targetDatabase][$bucket][$module] ?? [];
        if (!is_array($entries)) {
            return null;
        }

        foreach ($entries as $sourceKey => $entry) {
            if (is_array($entry) && array_key_exists($field, $entry) && (string) $entry[$field] === (string) $value) {
                return (string) $sourceKey;
            }
            if (!is_array($entry) && $field === 'value' && (string) $entry === (string) $value) {
                return (string) $sourceKey;
            }
        }

        return null;
    }

    private function putSyncMapValue(string $bucket, string $module, string $sourceKey, mixed $value): void
    {
        $map = $this->loadSyncMap();
        $map[$this->sourceDatabase][$this->targetDatabase][$bucket][$module][$sourceKey] = $value;
        $this->syncMapCache = $map;
        $this->saveSyncMap();
    }

    private function resolveTransactionMap(string $module, int $sourceSlno, string $sourceNumber, string $targetTable, string $targetColumn): array
    {
        $sourceKey = (string) $sourceSlno;
        $useSeparateSeries = $this->shouldUseSeparateTransactionSeries($module);
        $mapped = $this->getSyncMapValue('transactions', $module, $sourceKey);
        if (is_array($mapped)) {
            $collision = false;
            $otherBySlno = $this->findSourceKeyByMappedValue('transactions', $module, 'target_slno', (string) ($mapped['target_slno'] ?? ''));
            if ($otherBySlno !== null && $otherBySlno !== $sourceKey) {
                $collision = true;
            }
            $targetNumber = trim((string) ($mapped['target_number'] ?? ''));
            if ($useSeparateSeries && !$collision && $targetNumber !== '') {
                $otherByNumber = $this->findSourceKeyByMappedValue('transactions', $module, 'target_number', $targetNumber);
                if ($otherByNumber !== null && $otherByNumber !== $sourceKey) {
                    $collision = true;
                }
            }
            if (!$useSeparateSeries && !$collision) {
                $mapped['target_number'] = $sourceNumber;
                $this->putSyncMapValue('transactions', $module, $sourceKey, $mapped);
                return $mapped;
            }
            if (!$collision) {
                return $mapped;
            }
        }

        if (is_array($mapped)) {
            // stale duplicate mapping, allocate a fresh target
            unset($this->syncMapCache[$this->sourceDatabase][$this->targetDatabase]['transactions'][$module][$sourceKey]);
            $this->saveSyncMap();
        }

        do {
            $targetSlno = $this->nextTargetGlobalSlno();
        } while (($takenBy = $this->findSourceKeyByMappedValue('transactions', $module, 'target_slno', (string) $targetSlno)) !== null && $takenBy !== $sourceKey);

        if ($useSeparateSeries) {
            $configuredPrefix = $this->getModuleSeriesPrefix($module);
            if ($configuredPrefix !== '' && $sourceNumber !== '') {
                $targetNumber = $this->nextTargetNumberWithPrefix($targetTable, $targetColumn, $configuredPrefix);
                // Increment serial manually on collision to avoid re-querying DB in a loop
                while ($targetNumber !== '') {
                    $takenBy = $this->findSourceKeyByMappedValue('transactions', $module, 'target_number', $targetNumber);
                    if ($takenBy === null || $takenBy === $sourceKey) {
                        break;
                    }
                    $serial = substr($targetNumber, strlen($configuredPrefix));
                    $targetNumber = $configuredPrefix . ((ctype_digit($serial) ? (int) $serial : 0) + 1);
                }
            } else {
                $targetNumber = $sourceNumber !== '' ? $this->nextTargetSequenceValue($targetTable, $targetColumn, $sourceNumber) : '';
                while ($targetNumber !== '') {
                    $takenBy = $this->findSourceKeyByMappedValue('transactions', $module, 'target_number', $targetNumber);
                    if ($takenBy === null || $takenBy === $sourceKey) {
                        break;
                    }
                    $targetNumber = $this->nextTargetSequenceValue($targetTable, $targetColumn, $targetNumber);
                }
            }
        } else {
            $targetNumber = $sourceNumber;
        }

        $mapped = [
            'target_slno' => $targetSlno,
            'target_number' => $targetNumber,
        ];
        $this->putSyncMapValue('transactions', $module, $sourceKey, $mapped);

        return $mapped;
    }

    private function isMappedPartyCodeTaken(string $type, string $targetCode, string $sourceCode): bool
    {
        $takenBy = $this->findSourceKeyByMappedValue('parties', $type !== '' ? $type : 'UNK', 'value', $targetCode);
        return $takenBy !== null && $takenBy !== $sourceCode;
    }

    private function nextTargetGlobalSlno(): int
    {
        $max = 0;
        foreach (['daybook', 'daybookpart', 'salesm', 'purchasem', 'orderm'] as $table) {
            if (!$this->targetTableHasColumn($table, 'slno')) {
                continue;
            }
            $tableMax = (int) (DB::connection($this->connectionName)->table($table)->max('slno') ?? 0);
            if ($tableMax > $max) {
                $max = $tableMax;
            }
        }

        return max(1, $max + 1);
    }

    private function nextTargetSequenceValue(string $table, string $column, string $sourceValue): string
    {
        $sourceValue = trim($sourceValue);
        if ($sourceValue === '' || !$this->targetTableHasColumn($table, $column)) {
            return $sourceValue;
        }

        if (!preg_match('/^(.*?)(\d+)$/', $sourceValue, $matches)) {
            return $sourceValue;
        }

        $prefix = (string) ($matches[1] ?? '');
        $sourceDigits = (string) ($matches[2] ?? '1');
        $width = max(strlen($sourceDigits), 1);
        $maxSerial = 0;

        $rows = DB::connection($this->connectionName)
            ->table($table)
            ->select($column)
            ->where($column, 'like', $prefix . '%')
            ->get();

        foreach ($rows as $row) {
            $value = trim((string) ($row->{$column} ?? ''));
            if (!str_starts_with($value, $prefix)) {
                continue;
            }
            $suffix = substr($value, strlen($prefix));
            if (!ctype_digit($suffix)) {
                continue;
            }
            $serial = (int) $suffix;
            if ($serial > $maxSerial) {
                $maxSerial = $serial;
            }
        }

        return $prefix . str_pad((string) ($maxSerial + 1), $width, '0', STR_PAD_LEFT);
    }

    private function getModuleSeriesPrefix(string $module): string
    {
        $module = strtolower(trim($module));
        $defaults = [
            'sales'    => 'S/',
            'purchase' => 'P/',
            'receipt'  => 'R/',
            'payment'  => 'PY/',
            'order'    => 'ORD/',
        ];
        $key = self::MODULE_PREFIX_KEYS[$module] ?? null;
        if ($key === null) {
            return '';
        }

        $fromIni = self::softwareString($key, '');
        return $fromIni !== '' ? $fromIni : ($defaults[$module] ?? '');
    }

    private function nextTargetNumberWithPrefix(string $table, string $column, string $prefix): string
    {
        if (!$this->targetTableHasColumn($table, $column)) {
            return $prefix . '1';
        }

        $rows = DB::connection($this->connectionName)
            ->table($table)
            ->select($column)
            ->where($column, 'like', $prefix . '%')
            ->get();

        $maxSerial = 0;
        foreach ($rows as $row) {
            $value = trim((string) ($row->{$column} ?? ''));
            if (strcasecmp(substr($value, 0, strlen($prefix)), $prefix) !== 0) {
                continue;
            }
            $suffix = substr($value, strlen($prefix));
            if (!ctype_digit($suffix)) {
                continue;
            }
            $serial = (int) $suffix;
            if ($serial > $maxSerial) {
                $maxSerial = $serial;
            }
        }

        return $prefix . ($maxSerial + 1);
    }

    private function sourcePartyType(string $code): string
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return '';
        }
        if (array_key_exists($code, $this->sourcePartyTypeCache)) {
            return $this->sourcePartyTypeCache[$code];
        }
        if (!Schema::hasTable('clients')) {
            return $this->sourcePartyTypeCache[$code] = '';
        }

        $type = strtoupper(trim((string) (DB::table('clients')->where('code', $code)->value('ctype') ?? '')));
        return $this->sourcePartyTypeCache[$code] = $type;
    }

    private function maybeMapPartyCode(string $code): string
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return '';
        }

        $type = $this->sourcePartyType($code);
        if ($type === '') {
            return $code;
        }

        return $this->ensureTargetPartyCode($code, $type);
    }

    private function ensureTargetPartyCode(string $sourceCode, string $type): string
    {
        $sourceCode = strtoupper(trim($sourceCode));
        $type = strtoupper(trim($type));
        if ($sourceCode === '') {
            return '';
        }

        $clientRows = $this->fetchLocalRowsByColumn('clients', 'code', [$sourceCode]);
        if ($clientRows === []) {
            return $sourceCode;
        }

        if ($type === '') {
            $type = strtoupper(trim((string) ($clientRows[0]['ctype'] ?? '')));
        }

        $bucket = $type !== '' ? $type : 'UNK';
        $previousTargetCode = strtoupper(trim((string) $this->getSyncMapValue('parties', $bucket, $sourceCode)));
        $targetCode = $sourceCode;
        if ($this->shouldUseSeparatePartySeries($type)) {
            $targetCode = $previousTargetCode !== '' ? $previousTargetCode : $this->nextTargetPartyCode($type);
        } elseif ($previousTargetCode !== '' && $previousTargetCode !== $sourceCode) {
            foreach (['clients', 'clients_advanced', 'clientspict', 'clients_kuridet'] as $table) {
                $this->cleanupTargetRowsByCodes($table, ['code'], [$previousTargetCode]);
            }
            $this->cleanupTargetRowsByCodes('accountm', ['accode'], [$previousTargetCode]);
            $this->cleanupTargetRowsByCodes('clientsgs', ['code'], [$previousTargetCode]);
        }

        foreach (['clients', 'clients_advanced', 'clientspict', 'clients_kuridet'] as $table) {
            $this->cleanupTargetRowsByCodes($table, ['code'], [$targetCode]);
        }
        $this->cleanupTargetRowsByCodes('accountm', ['accode'], [$targetCode]);
        $this->cleanupTargetRowsByCodes('clientsgs', ['code'], [$targetCode]);

        $clientRows = $this->remapPartyRows($clientRows, $sourceCode, $targetCode, $type, 'clients');
        $accountRows = $this->remapPartyRows($this->fetchLocalRowsByColumn('accountm', 'accode', [$sourceCode]), $sourceCode, $targetCode, $type, 'accountm');
        $advancedRows = $this->remapPartyRows($this->fetchLocalRowsByColumn('clients_advanced', 'code', [$sourceCode]), $sourceCode, $targetCode, $type, 'clients_advanced');
        $photoRows = $this->remapPartyRows($this->fetchLocalRowsByColumn('clientspict', 'code', [$sourceCode]), $sourceCode, $targetCode, $type, 'clientspict');
        $gsRows = $this->remapPartyRows($this->fetchLocalRowsByColumn('clientsgs', 'code', [$sourceCode]), $sourceCode, $targetCode, $type, 'clientsgs');
        $kuriRows = $this->remapPartyRows($this->fetchLocalRowsByColumn('clients_kuridet', 'code', [$sourceCode]), $sourceCode, $targetCode, $type, 'clients_kuridet');

        $this->upsertRowsToTarget('clients', $clientRows, ['code']);
        $this->upsertRowsToTarget('accountm', $accountRows, ['accode']);
        $this->upsertRowsToTarget('clients_advanced', $advancedRows, ['code']);
        $this->upsertRowsToTarget('clientspict', $photoRows, ['code']);
        if ($kuriRows !== []) {
            $this->upsertRowsToTarget('clients_kuridet', $kuriRows, [['code', 'startdate'], ['code']]);
        }
        if ($gsRows !== []) {
            $this->upsertRowsToTarget('clientsgs', $gsRows, ['code']);
        }

        $this->putSyncMapValue('parties', $bucket, $sourceCode, $targetCode);

        return $targetCode;
    }

    private function shouldUseSeparatePartySeries(string $type): bool
    {
        $type = strtoupper(trim($type));
        if (!in_array($type, ['C', 'S'], true)) {
            return false;
        }

        return self::softwareFlagEnabled(self::SECONDARY_PARTY_SERIES_SETTING, 'N');
    }

    private function shouldUseSeparateTransactionSeries(string $module): bool
    {
        $module = strtolower(trim($module));
        if (!in_array($module, ['sales', 'purchase', 'order', 'receipt', 'payment', 'journal'], true)) {
            return false;
        }

        return self::softwareFlagEnabled(self::SECONDARY_TRANSACTION_SERIES_SETTING, 'N');
    }

    private function nextTargetPartyCode(string $type): string
    {
        $type = strtoupper(trim($type));
        $counterCode = $type === 'C' ? 'CLASTNO' : 'SLASTNO';
        $prefixCode = $type === 'C' ? 'CPREFIX' : 'SPREFIX';
        $fallbackPrefix = $type === 'C' ? 'C' : ($type !== '' ? $type : 'S');

        $current = (int) (DB::connection($this->connectionName)->table('generali')->where('code', $counterCode)->value('cvalue') ?? 0);
        $prefix = trim((string) (DB::connection($this->connectionName)->table('generals')->where('code', $prefixCode)->value('cvalue') ?? $fallbackPrefix));
        if ($prefix === '') {
            $prefix = $fallbackPrefix;
        }

        $rows = DB::connection($this->connectionName)
            ->table('clients')
            ->select('code')
            ->where('code', 'like', $prefix . '%')
            ->get();

        $maxSerial = $current;
        foreach ($rows as $row) {
            $value = trim((string) ($row->code ?? ''));
            if (!str_starts_with($value, $prefix)) {
                continue;
            }
            $suffix = substr($value, strlen($prefix));
            if (!ctype_digit($suffix)) {
                continue;
            }
            $serial = (int) $suffix;
            if ($serial > $maxSerial) {
                $maxSerial = $serial;
            }
        }

        $next = $maxSerial + 1;
        if ($this->targetTableExists('generali')) {
            DB::connection($this->connectionName)->table('generali')->updateOrInsert(['code' => $counterCode], ['cvalue' => $next]);
        }

        return strtoupper($prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT));
    }

    private function remapPartyRows(array $rows, string $sourceCode, string $targetCode, string $type, string $table): array
    {
        return collect($rows)->map(function (array $row) use ($sourceCode, $targetCode, $type, $table) {
            if (array_key_exists('code', $row) && strtoupper(trim((string) $row['code'])) === $sourceCode) {
                $row['code'] = $targetCode;
            }
            if (array_key_exists('accode', $row) && strtoupper(trim((string) $row['accode'])) === $sourceCode) {
                $row['accode'] = $targetCode;
            }
            if ($table === 'clients_kuridet' && array_key_exists('custlinkac', $row)) {
                $custLinkCode = strtoupper(trim((string) $row['custlinkac']));
                if ($custLinkCode === $sourceCode) {
                    $row['custlinkac'] = $targetCode;
                } elseif ($custLinkCode !== '') {
                    $row['custlinkac'] = $this->maybeMapPartyCode($custLinkCode);
                }
            }
            if ($table === 'clients' && array_key_exists('ctype', $row) && $type !== '') {
                $row['ctype'] = $type;
            }
            if ($table === 'accountm' && array_key_exists('actype2', $row) && $type !== '') {
                $row['actype2'] = $type;
            }

            return $row;
        })->all();
    }

    private function remapTransactionRows(array $rows, array $replacements): array
    {
        return collect($rows)->map(function (array $row) use ($replacements) {
            foreach ($row as $column => $value) {
                $columnKey = strtolower((string) $column);
                if ($columnKey === 'slno' && isset($replacements['slno'])) {
                    $row[$column] = $replacements['slno'];
                    continue;
                }
                if (in_array($columnKey, ['billno', 'docno', 'vchno', 'ordno'], true) && isset($replacements[$columnKey])) {
                    $current = trim((string) $value);
                    $source = trim((string) ($replacements['source_' . $columnKey] ?? ''));
                    if ($current !== '' && $source !== '' && strcasecmp($current, $source) === 0) {
                        $row[$column] = $replacements[$columnKey];
                    }
                }
                if (in_array($columnKey, ['custcode', 'suppcode', 'accode', 'opaccode', 'smithcode'], true)) {
                    $row[$column] = $this->maybeMapPartyCode((string) $value);
                }
                if ($columnKey === 'orderno') {
                    $row[$column] = $this->maybeMapOrderNo((string) $value);
                }
            }

            return $row;
        })->all();
    }

    private function maybeMapOrderNo(string $orderNo): string
    {
        $orderNo = trim($orderNo);
        if ($orderNo === '' || !Schema::hasTable('orderm') || !Schema::hasColumn('orderm', 'ordno')) {
            return $orderNo;
        }

        $sourceSlno = (int) (DB::table('orderm')->where('ordno', $orderNo)->value('slno') ?? 0);
        if ($sourceSlno <= 0) {
            return $orderNo;
        }

        $mapped = $this->getSyncMapValue('transactions', 'order', (string) $sourceSlno);
        if (is_array($mapped) && trim((string) ($mapped['target_number'] ?? '')) !== '') {
            return trim((string) $mapped['target_number']);
        }

        return $orderNo;
    }

    private function syncSalesBundle(array $slnos): int
    {
        $sourceSlno = (int) ($slnos[0] ?? 0);
        $salesmRows = $this->fetchLocalRowsBySlno('salesm', $slnos);
        $sourceBillNo = trim((string) ($salesmRows[0]['billno'] ?? ''));
        $mapped = $this->resolveTransactionMap('sales', $sourceSlno, $sourceBillNo, 'salesm', 'billno');
        $targetSlno = (int) ($mapped['target_slno'] ?? 0);
        $targetBillNo = trim((string) ($mapped['target_number'] ?? $sourceBillNo));
        $replace = [
            'slno' => $targetSlno,
            'billno' => $targetBillNo,
            'docno' => $targetBillNo,
            'source_billno' => $sourceBillNo,
            'source_docno' => $sourceBillNo,
        ];

        $barcodeCodes = collect($this->fetchLocalRowsBySlno('salesd', $slnos))
            ->pluck('bcode')
            ->merge(collect($this->fetchLocalRowsBySlno('salesrd', $slnos))->pluck('bcode'))
            ->filter(fn ($value) => trim((string) $value) !== '')
            ->map(fn ($value) => trim((string) $value))
            ->unique()
            ->values()
            ->all();

        foreach ([
            'daybookpart',
            'daybookratewgt',
            'spdmddet',
            'purchased_dmddet',
            'salesd',
            'purchased',
            'salesrd',
            'oglist',
            'kuricolln',
            'pdclist',
            'stkandprofit',
            'daybook',
            'purchasem',
            'salesrm',
            'salesm',
        ] as $table) {
            $this->cleanupTargetRowsBySlno($table, [$targetSlno]);
        }

        if ($barcodeCodes !== []) {
            $this->cleanupTargetRowsByCodes('barcode', ['bcode', 'barcode', 'barcodeno'], $barcodeCodes);
        }

        $copied = 0;
        $copied += $this->upsertRowsToTarget('salesm', $this->remapTransactionRows($salesmRows, $replace), ['slno']);
        $copied += $this->upsertRowsToTarget('salesd', $this->remapTransactionRows($this->fetchLocalRowsBySlno('salesd', $slnos), $replace), [['slno', 'itemslno'], ['slno', 'sl'], ['slno', 'code'], ['slno']]);
        $copied += $this->upsertRowsToTarget('purchasem', $this->remapTransactionRows($this->fetchLocalRowsBySlno('purchasem', $slnos), $replace), ['slno']);
        $copied += $this->upsertRowsToTarget('purchased', $this->remapTransactionRows($this->fetchLocalRowsBySlno('purchased', $slnos), $replace), [['slno', 'itemslno'], ['slno', 'sl'], ['slno', 'code'], ['slno']]);
        $copied += $this->upsertRowsToTarget('purchased_dmddet', $this->remapTransactionRows($this->fetchLocalRowsBySlno('purchased_dmddet', $slnos), $replace), [['slno', 'sno'], ['slno']]);
        $copied += $this->upsertRowsToTarget('salesrm', $this->remapTransactionRows($this->fetchLocalRowsBySlno('salesrm', $slnos), $replace), ['slno']);
        $copied += $this->upsertRowsToTarget('salesrd', $this->remapTransactionRows($this->fetchLocalRowsBySlno('salesrd', $slnos), $replace), [['slno', 'itemslno'], ['slno', 'sl'], ['slno', 'code'], ['slno']]);
        $copied += $this->upsertRowsToTarget('daybook', $this->remapTransactionRows($this->fetchLocalRowsBySlno('daybook', $slnos), $replace), [['slno', 'sno'], ['slno', 'accode', 'amount'], ['slno']]);
        $copied += $this->upsertRowsToTarget('daybookpart', $this->remapTransactionRows($this->fetchLocalRowsBySlno('daybookpart', $slnos), $replace), [['slno', 'accode', 'vchno'], ['slno', 'accode'], ['slno']]);
        $copied += $this->upsertRowsToTarget('daybookratewgt', $this->remapTransactionRows($this->fetchLocalRowsBySlno('daybookratewgt', $slnos), $replace), [['slno', 'accode'], ['slno', 'docno'], ['slno']]);
        $copied += $this->upsertRowsToTarget('spdmddet', $this->remapTransactionRows($this->fetchLocalRowsBySlno('spdmddet', $slnos), $replace), [['slno', 'sno'], ['slno']]);
        $copied += $this->upsertRowsToTarget('oglist', $this->remapTransactionRows($this->fetchLocalRowsBySlno('oglist', $slnos), $replace), [['slno', 'docno'], ['slno', 'accode'], ['slno']]);
        $copied += $this->upsertRowsToTarget('kuricolln', $this->remapTransactionRows($this->fetchLocalRowsBySlno('kuricolln', $slnos), $replace), [['slno', 'docno'], ['slno']]);
        $copied += $this->upsertRowsToTarget('pdclist', $this->remapTransactionRows($this->fetchLocalRowsBySlno('pdclist', $slnos), $replace), [['slno', 'docno'], ['slno']]);
        $copied += $this->upsertRowsToTarget('stkandprofit', $this->remapTransactionRows($this->fetchLocalRowsBySlno('stkandprofit', $slnos), $replace), [['slno', 'docno'], ['slno', 'bcode'], ['slno']]);

        if ($barcodeCodes !== []) {
            $copied += $this->upsertRowsToTarget('barcode', $this->fetchLocalRowsByCodes('barcode', ['bcode', 'barcode', 'barcodeno'], $barcodeCodes), [['bcode'], ['barcode'], ['barcodeno'], ['slno'], ['docno']]);
        }

        return $copied;
    }

    private function syncPurchaseBundle(array $slnos): int
    {
        $sourceSlno = (int) ($slnos[0] ?? 0);
        $purchasemRows = $this->fetchLocalRowsBySlno('purchasem', $slnos);
        $sourceDocNo = trim((string) ($purchasemRows[0]['docno'] ?? ''));
        $mapped = $this->resolveTransactionMap('purchase', $sourceSlno, $sourceDocNo, 'purchasem', 'docno');
        $targetSlno = (int) ($mapped['target_slno'] ?? 0);
        $targetDocNo = trim((string) ($mapped['target_number'] ?? $sourceDocNo));
        $replace = [
            'slno' => $targetSlno,
            'docno' => $targetDocNo,
            'source_docno' => $sourceDocNo,
        ];

        foreach (['daybook', 'daybookpart', 'pdclist', 'purchaserd', 'purchaserm', 'purchased', 'purchasem'] as $table) {
            $this->cleanupTargetRowsBySlno($table, [$targetSlno]);
        }

        $copied = 0;
        $copied += $this->upsertRowsToTarget('purchasem', $this->remapTransactionRows($purchasemRows, $replace), ['slno']);
        $copied += $this->upsertRowsToTarget('purchased', $this->remapTransactionRows($this->fetchLocalRowsBySlno('purchased', $slnos), $replace), [['slno', 'itemslno'], ['slno', 'sl'], ['slno', 'code'], ['slno']]);
        $copied += $this->upsertRowsToTarget('purchaserm', $this->remapTransactionRows($this->fetchLocalRowsBySlno('purchaserm', $slnos), $replace), ['slno']);
        $copied += $this->upsertRowsToTarget('purchaserd', $this->remapTransactionRows($this->fetchLocalRowsBySlno('purchaserd', $slnos), $replace), [['slno', 'itemslno'], ['slno', 'sl'], ['slno', 'code'], ['slno']]);
        $copied += $this->upsertRowsToTarget('daybook', $this->remapTransactionRows($this->fetchLocalRowsBySlno('daybook', $slnos), $replace), [['slno', 'sno'], ['slno', 'accode', 'amount'], ['slno']]);
        $copied += $this->upsertRowsToTarget('daybookpart', $this->remapTransactionRows($this->fetchLocalRowsBySlno('daybookpart', $slnos), $replace), [['slno', 'accode', 'vchno'], ['slno', 'accode'], ['slno']]);
        $copied += $this->upsertRowsToTarget('pdclist', $this->remapTransactionRows($this->fetchLocalRowsBySlno('pdclist', $slnos), $replace), [['slno', 'docno'], ['slno']]);

        return $copied;
    }

    private function syncOrderBundle(array $slnos): int
    {
        $sourceSlno = (int) ($slnos[0] ?? 0);
        $ordermRows = $this->fetchLocalRowsBySlno('orderm', $slnos);
        $sourceOrderNo = trim((string) ($ordermRows[0]['ordno'] ?? ''));
        $mapped = $this->resolveTransactionMap('order', $sourceSlno, $sourceOrderNo, 'orderm', 'ordno');
        $targetSlno = (int) ($mapped['target_slno'] ?? 0);
        $targetOrderNo = trim((string) ($mapped['target_number'] ?? $sourceOrderNo));
        $replace = [
            'slno' => $targetSlno,
            'ordno' => $targetOrderNo,
            'docno' => $targetOrderNo,
            'source_ordno' => $sourceOrderNo,
            'source_docno' => $sourceOrderNo,
        ];

        foreach (['ordermodel', 'orderdga', 'salesrd', 'salesrm', 'purchased', 'purchasem', 'orderd', 'stkandprofit', 'oglist', 'pdclist', 'daybook', 'daybookpart', 'orderm'] as $table) {
            $this->cleanupTargetRowsBySlno($table, [$targetSlno]);
        }

        $copied = 0;
        $copied += $this->upsertRowsToTarget('orderm', $this->remapTransactionRows($ordermRows, $replace), ['slno']);
        $copied += $this->upsertRowsToTarget('orderd', $this->remapTransactionRows($this->fetchLocalRowsBySlno('orderd', $slnos), $replace), [['slno', 'sno'], ['slno', 'code'], ['slno']]);
        $copied += $this->upsertRowsToTarget('purchasem', $this->remapTransactionRows($this->fetchLocalRowsBySlno('purchasem', $slnos), $replace), ['slno']);
        $copied += $this->upsertRowsToTarget('purchased', $this->remapTransactionRows($this->fetchLocalRowsBySlno('purchased', $slnos), $replace), [['slno', 'sno'], ['slno', 'code'], ['slno']]);
        $copied += $this->upsertRowsToTarget('salesrm', $this->remapTransactionRows($this->fetchLocalRowsBySlno('salesrm', $slnos), $replace), ['slno']);
        $copied += $this->upsertRowsToTarget('salesrd', $this->remapTransactionRows($this->fetchLocalRowsBySlno('salesrd', $slnos), $replace), [['slno', 'sno'], ['slno', 'code'], ['slno']]);
        $copied += $this->upsertRowsToTarget('orderdga', $this->remapTransactionRows($this->fetchLocalRowsBySlno('orderdga', $slnos), $replace), [['slno', 'sno'], ['slno', 'code'], ['slno']]);
        $copied += $this->upsertRowsToTarget('ordermodel', $this->remapTransactionRows($this->fetchLocalRowsBySlno('ordermodel', $slnos), $replace), [['slno', 'sno'], ['slno', 'code'], ['slno']]);
        $copied += $this->upsertRowsToTarget('stkandprofit', $this->remapTransactionRows($this->fetchLocalRowsBySlno('stkandprofit', $slnos), $replace), [['slno', 'docno'], ['slno', 'bcode'], ['slno']]);
        $copied += $this->upsertRowsToTarget('oglist', $this->remapTransactionRows($this->fetchLocalRowsBySlno('oglist', $slnos), $replace), [['slno', 'docno'], ['slno', 'accode'], ['slno']]);
        $copied += $this->upsertRowsToTarget('pdclist', $this->remapTransactionRows($this->fetchLocalRowsBySlno('pdclist', $slnos), $replace), [['slno', 'docno'], ['slno']]);
        $copied += $this->upsertRowsToTarget('daybook', $this->remapTransactionRows($this->fetchLocalRowsBySlno('daybook', $slnos), $replace), [['slno', 'sno'], ['slno', 'accode', 'amount'], ['slno']]);
        $copied += $this->upsertRowsToTarget('daybookpart', $this->remapTransactionRows($this->fetchLocalRowsBySlno('daybookpart', $slnos), $replace), [['slno', 'accode', 'vchno'], ['slno', 'accode'], ['slno']]);

        return $copied;
    }

    private function syncOrderSaleBundle(array $slnos, string $orderNo = ''): int
    {
        $copied = 0;

        $orderNo = trim($orderNo);
        if ($orderNo === '') {
            $salesRow = collect($this->fetchLocalRowsBySlno('salesm', $slnos))->first();
            $orderNo = trim((string) ($salesRow['orderno'] ?? ''));
        }

        if ($orderNo !== '') {
            $orderRows = $this->fetchLocalRowsByColumn('orderm', 'ordno', [$orderNo]);
            if ($orderRows !== []) {
                $orderSlnos = collect($orderRows)->pluck('slno')->filter()->map(fn ($value) => (int) $value)->values()->all();
                $sourceOrderSlno = (int) ($orderSlnos[0] ?? 0);
                $mapped = $this->resolveTransactionMap('order', $sourceOrderSlno, $orderNo, 'orderm', 'ordno');
                $targetOrderSlno = (int) ($mapped['target_slno'] ?? 0);
                $targetOrderNo = trim((string) ($mapped['target_number'] ?? $orderNo));
                $replace = [
                    'slno' => $targetOrderSlno,
                    'ordno' => $targetOrderNo,
                    'docno' => $targetOrderNo,
                    'source_ordno' => $orderNo,
                    'source_docno' => $orderNo,
                ];
                foreach (['orderdga', 'orderd', 'orderm'] as $table) {
                    $this->cleanupTargetRowsBySlno($table, [$targetOrderSlno]);
                }
                $copied += $this->upsertRowsToTarget('orderm', $this->remapTransactionRows($orderRows, $replace), ['slno', 'ordno']);
                if ($orderSlnos !== []) {
                    $copied += $this->upsertRowsToTarget('orderd', $this->remapTransactionRows($this->fetchLocalRowsBySlno('orderd', $orderSlnos), $replace), [['slno', 'sno'], ['slno', 'code'], ['slno']]);
                    $copied += $this->upsertRowsToTarget('orderdga', $this->remapTransactionRows($this->fetchLocalRowsBySlno('orderdga', $orderSlnos), $replace), [['slno', 'sno'], ['slno', 'code'], ['slno']]);
                }
            }
        }

        return $copied + $this->syncSalesBundle($slnos);
    }

    private function syncVoucherBundle(array $slnos, string $prefix): int
    {
        $headerRows = $this->fetchLocalRowsBySlno('daybookpart', $slnos);
        if ($headerRows !== []) {
            $matchedSlnos = collect($headerRows)
                ->filter(fn (array $row) => str_starts_with(strtoupper(trim((string) ($row['vchno'] ?? ''))), strtoupper($prefix)))
                ->pluck('slno')
                ->filter()
                ->map(fn ($value) => (int) $value)
                ->values()
                ->all();
            if ($matchedSlnos !== []) {
                $slnos = $matchedSlnos;
            }
        }

        $sourceSlno = (int) ($slnos[0] ?? 0);
        $sourceVchNo = trim((string) ($headerRows[0]['vchno'] ?? ''));
        $module = strtoupper($prefix) === 'VR' ? 'receipt' : 'payment';
        $mapped = $this->resolveTransactionMap($module, $sourceSlno, $sourceVchNo, 'daybookpart', 'vchno');
        $targetSlno = (int) ($mapped['target_slno'] ?? 0);
        $targetVchNo = trim((string) ($mapped['target_number'] ?? $sourceVchNo));
        $replace = [
            'slno' => $targetSlno,
            'vchno' => $targetVchNo,
            'docno' => $targetVchNo,
            'source_vchno' => $sourceVchNo,
            'source_docno' => $sourceVchNo,
        ];

        foreach (['daybook', 'daybookpart', 'pdclist'] as $table) {
            $this->cleanupTargetRowsBySlno($table, [$targetSlno]);
        }

        $copied = 0;
        $copied += $this->upsertRowsToTarget('daybook', $this->remapTransactionRows($this->fetchLocalRowsBySlno('daybook', $slnos), $replace), [['slno', 'sno'], ['slno', 'accode', 'amount'], ['slno']]);
        $copied += $this->upsertRowsToTarget('daybookpart', $this->remapTransactionRows($this->fetchLocalRowsBySlno('daybookpart', $slnos), $replace), [['slno', 'accode', 'vchno'], ['slno', 'accode'], ['slno']]);
        $copied += $this->upsertRowsToTarget('pdclist', $this->remapTransactionRows($this->fetchLocalRowsBySlno('pdclist', $slnos), $replace), [['slno', 'docno'], ['slno']]);

        return $copied;
    }

    private function fetchLocalRowsBySlno(string $table, array $slnos): array
    {
        if ($slnos === [] || !Schema::hasTable($table) || !Schema::hasColumn($table, 'slno')) {
            return [];
        }

        return DB::table($table)
            ->whereIn('slno', $slnos)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    private function fetchLocalAllRows(string $table): array
    {
        if (!Schema::hasTable($table)) {
            return [];
        }

        return DB::table($table)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    private function fetchLocalRowsByCodes(string $table, array $columns, array $codes): array
    {
        if ($codes === [] || !Schema::hasTable($table)) {
            return [];
        }

        $validColumns = collect($columns)
            ->filter(fn ($column) => Schema::hasColumn($table, $column))
            ->values()
            ->all();

        if ($validColumns === []) {
            return [];
        }

        return DB::table($table)
            ->where(function ($query) use ($validColumns, $codes) {
                foreach ($validColumns as $index => $column) {
                    if ($index === 0) {
                        $query->whereIn($column, $codes);
                    } else {
                        $query->orWhereIn($column, $codes);
                    }
                }
            })
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    private function fetchLocalRowsByColumn(string $table, string $column, array $values): array
    {
        if ($values === [] || !Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return [];
        }

        return DB::table($table)
            ->whereIn($column, $values)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    private function cleanupTargetRowsBySlno(string $table, array $slnos): void
    {
        if ($slnos === [] || !$this->targetTableHasColumn($table, 'slno')) {
            return;
        }

        DB::connection($this->connectionName)
            ->table($table)
            ->whereIn('slno', $slnos)
            ->delete();
    }

    private function cleanupTargetRowsByCodes(string $table, array $columns, array $codes): void
    {
        if ($codes === [] || !$this->targetTableExists($table)) {
            return;
        }

        $validColumns = collect($columns)
            ->filter(fn ($column) => $this->targetTableHasColumn($table, $column))
            ->values()
            ->all();

        if ($validColumns === []) {
            return;
        }

        DB::connection($this->connectionName)
            ->table($table)
            ->where(function ($query) use ($validColumns, $codes) {
                foreach ($validColumns as $index => $column) {
                    if ($index === 0) {
                        $query->whereIn($column, $codes);
                    } else {
                        $query->orWhereIn($column, $codes);
                    }
                }
            })
            ->delete();
    }

    private function upsertRowsToTarget(string $table, array $rows, array $candidateKeys): int
    {
        if ($rows === [] || !$this->targetTableExists($table)) {
            return 0;
        }

        $targetColumns = collect(Schema::connection($this->connectionName)->getColumnListing($table))
            ->map(fn ($column) => strtolower((string) $column))
            ->values()
            ->all();

        $keyGroups = collect($candidateKeys)
            ->map(function ($group) {
                $items = is_array($group) ? $group : [$group];
                return collect($items)
                    ->map(fn ($key) => strtolower((string) $key))
                    ->filter()
                    ->values()
                    ->all();
            })
            ->filter(fn ($group) => $group !== [])
            ->values()
            ->all();

        if ($keyGroups === []) {
            return 0;
        }

        $preparedRows = [];
        foreach ($rows as $row) {
            $filtered = [];
            foreach ($row as $column => $value) {
                $column = strtolower((string) $column);
                if (in_array($column, $targetColumns, true)) {
                    $filtered[$column] = $value;
                }
            }

            if ($filtered === []) {
                continue;
            }

            $preparedRows[] = $filtered;
        }

        if ($preparedRows === []) {
            return 0;
        }

        $sharedSlnos = collect($preparedRows)
            ->pluck('slno')
            ->filter(fn ($value) => $value !== null && (!is_string($value) || trim($value) !== ''))
            ->unique()
            ->values()
            ->all();

        if (count($preparedRows) > 1 && count($sharedSlnos) === 1 && $this->targetTableHasColumn($table, 'slno')) {
            DB::connection($this->connectionName)
                ->table($table)
                ->where('slno', $sharedSlnos[0])
                ->delete();

            foreach (array_chunk($preparedRows, 200) as $chunk) {
                DB::connection($this->connectionName)->table($table)->insert($chunk);
            }

            return count($preparedRows);
        }

        $copied = 0;
        foreach ($preparedRows as $filtered) {

            $where = [];
            foreach ($keyGroups as $group) {
                $candidate = [];
                foreach ($group as $key) {
                    if (!array_key_exists($key, $filtered)) {
                        $candidate = [];
                        break;
                    }
                    $value = $filtered[$key];
                    if ($value === null || (is_string($value) && trim($value) === '')) {
                        $candidate = [];
                        break;
                    }
                    $candidate[$key] = $value;
                }
                if ($candidate !== []) {
                    $where = $candidate;
                    break;
                }
            }

            if ($where === []) {
                continue;
            }

            DB::connection($this->connectionName)->table($table)->updateOrInsert($where, $filtered);
            $copied++;
        }

        return $copied;
    }

    private function targetTableExists(string $table): bool
    {
        return Schema::connection($this->connectionName)->hasTable($table);
    }

    private function targetTableHasColumn(string $table, string $column): bool
    {
        return $this->targetTableExists($table) && Schema::connection($this->connectionName)->hasColumn($table, $column);
    }

    private function isSafeDatabaseName(string $database): bool
    {
        return preg_match('/^[A-Za-z0-9_]+$/', $database) === 1;
    }
}
