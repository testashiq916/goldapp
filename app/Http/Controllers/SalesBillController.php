<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\LogsDelpartAudit;
use App\Models\Item;
use App\Models\SalesBill;
use App\Support\SecondaryDatabaseSync;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class SalesBillController extends Controller
{
    use LogsDelpartAudit;

    /** @var array<string, array<string, int|null>> */
    private array $legacyColumnLengthCache = [];

    private function isSecondaryCompanyDatabase(): bool
    {
        $currentDatabase = trim((string) config('database.connections.mysql.database', ''));
        $primaryDatabase = trim((string) env('DB_DATABASE', $currentDatabase));

        return $currentDatabase !== ''
            && $primaryDatabase !== ''
            && strcasecmp($currentDatabase, $primaryDatabase) !== 0;
    }

    private function currentUserPermissionSet(Request $request): array
    {
        $userCode = strtoupper(trim((string) $request->session()->get('user_code', '')));
        if ($userCode === '' || !$this->hasTable('userd')) {
            return [];
        }

        return DB::table('userd')
            ->whereRaw('UPPER(TRIM(code)) = ?', [$userCode])
            ->pluck('menuitem')
            ->map(fn ($item) => strtoupper(trim((string) $item)))
            ->filter()
            ->values()
            ->all();
    }

    private function userHasPermission(Request $request, string $permission): bool
    {
        $permission = strtoupper(trim($permission));
        if ($permission === '') {
            return false;
        }

        return in_array($permission, $this->currentUserPermissionSet($request), true);
    }

    private function hasSalesBillsTable(): bool
    {
        return $this->hasTable('sales_bills');
    }

    private function billNoExistsInPrimaryStore(string $billNo): bool
    {
        $billNo = trim($billNo);
        if ($billNo === '') {
            return false;
        }

        if ($this->hasTable('salesm')) {
            return DB::table('salesm')->where('billno', $billNo)->exists();
        }

        if ($this->hasSalesBillsTable()) {
            return DB::table('sales_bills')->where('bill_no', $billNo)->exists();
        }

        return false;
    }

    private function legacyColumnLength(string $table, string $column): ?int
    {
        $cacheKey = strtolower($table) . '.' . strtolower($column);
        if (array_key_exists($cacheKey, $this->legacyColumnLengthCache)) {
            return $this->legacyColumnLengthCache[$cacheKey];
        }

        $row = DB::selectOne(
            "SELECT CHARACTER_MAXIMUM_LENGTH AS max_len
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
             LIMIT 1",
            [$table, $column]
        );

        $length = isset($row->max_len) ? (int) $row->max_len : null;
        $this->legacyColumnLengthCache[$cacheKey] = $length > 0 ? $length : null;

        return $this->legacyColumnLengthCache[$cacheKey];
    }

    private function ensureLegacyBillNoCapacity(string $table, string $billNo, int $minimumLength = 20): void
    {
        if ($billNo === '' || !$this->hasTable($table) || !Schema::hasColumn($table, 'billno')) {
            return;
        }

        $requiredLength = mb_strlen($billNo);
        $currentLength = $this->legacyColumnLength($table, 'billno');
        if ($currentLength !== null && $currentLength >= $requiredLength) {
            return;
        }

        $targetLength = max($minimumLength, $requiredLength);
        DB::statement(sprintf('ALTER TABLE `%s` MODIFY `billno` VARCHAR(%d) NULL', $table, $targetLength));
        unset($this->legacyColumnLengthCache[strtolower($table) . '.billno']);
    }

    private function fitLegacyRowToSchema(string $table, array $row, array $preserve = []): array
    {
        if (!$this->hasTable($table)) {
            return $row;
        }

        $columns = array_fill_keys(array_map('strtolower', Schema::getColumnListing($table)), true);
        $row = array_filter(
            $row,
            fn ($column) => isset($columns[strtolower((string) $column)]),
            ARRAY_FILTER_USE_KEY
        );

        $preserve = array_map('strtolower', $preserve);

        foreach ($row as $column => $value) {
            if (!is_string($value)) {
                continue;
            }

            $maxLength = $this->legacyColumnLength($table, (string) $column);
            if ($maxLength === null || mb_strlen($value) <= $maxLength) {
                continue;
            }

            if (in_array(strtolower((string) $column), $preserve, true)) {
                continue;
            }

            $row[$column] = mb_substr($value, 0, $maxLength);
        }

        return $row;
    }

    public function editPicker(Request $request): View
    {
        $quotationMode = $request->boolean('qtn') || $request->boolean('quotation');

        return view('sales-bill.edit-picker', [
            'title' => (string) $request->query('title', $quotationMode ? 'Quotation Edit' : 'Sales Edit'),
            'moduleId' => (string) $request->query('module', $quotationMode ? 'sales-bill-quotation-edit' : 'sales-bill-edit'),
            'actionMode' => 'edit',
            'showViewOption' => false,
            'showActionChoice' => false,
            'quotationMode' => $quotationMode,
        ]);
    }

    public function reprintPicker(Request $request): View
    {
        $quotationMode = $request->boolean('qtn') || $request->boolean('quotation');

        return view('sales-bill.edit-picker', [
            'title' => (string) $request->query('title', $quotationMode ? 'Quotation Reprint' : 'Sales Reprint'),
            'moduleId' => (string) $request->query('module', 'sales-bill-reprint'),
            'actionMode' => 'reprint',
            'showViewOption' => true,
            'showActionChoice' => false,
            'quotationMode' => $quotationMode,
        ]);
    }

    public function cancelPicker(Request $request): View
    {
        $quotationMode = $request->boolean('qtn') || $request->boolean('quotation');

        return view('sales-bill.edit-picker', [
            'title' => (string) $request->query('title', $quotationMode ? 'Quotation Cancellation' : 'Sales Cancellation'),
            'moduleId' => (string) $request->query('module', $quotationMode ? 'sales-bill-quotation-cancel' : 'sales-bill-cancel'),
            'actionMode' => 'cancel',
            'showViewOption' => false,
            'showActionChoice' => false,
            'quotationMode' => $quotationMode,
        ]);
    }

    public function index(Request $request, ?string $mode = null): View
    {
        $mode = strtolower((string) ($mode ?: $request->query('mode', 'bill')));
        if (!in_array($mode, ['bill', 'edit', 'cancel', 'reprint', 'confirmation'], true)) {
            $mode = 'bill';
        }
        $quotationMode = $request->boolean('qtn') || $request->boolean('quotation');

        $titleMap = [
            'bill' => 'Enter Sales Bill Details',
            'edit' => 'Edit Sales Bill',
            'cancel' => 'Cancel Sales Bill',
            'reprint' => 'Reprint Sales Bill',
            'confirmation' => 'Bill Confirmation',
        ];
        if ($mode === 'bill' && $quotationMode) {
            $titleMap['bill'] = 'Enter Sales Quotation Details';
            $titleMap['edit'] = 'Edit Sales Quotation';
            $titleMap['cancel'] = 'Cancel Sales Quotation';
            $titleMap['reprint'] = 'Reprint Sales Quotation';
        }
        $allowPrevNextButtons = $this->userHasPermission($request, 'ALLOWPREVNEXTBUTTONSINSALES');

        $settingsPayload = $this->loadSettingsPayload();
        $software = (array) ($settingsPayload['Software'] ?? []);
        $company = (array) ($settingsPayload['Company'] ?? []);
        $ratesCfg = (array) ($settingsPayload['Rates'] ?? []);
        $sw = static fn (array $arr, string $key, string $default = ''): string => (string) ($arr[$key] ?? $default);
        $useNwRates = strtoupper(trim($sw($software, 'NW', 'N'))) === 'Y';

        // Load exchange rates from generald/settings
        $rateCodes = ['GRATE', 'SRATE', 'OGRATE', 'OSRATE', 'BULRATE', 'BULTOUCH', 'THRATE', 'G18RATE'];
        $ratesRaw = [];
        if ($this->hasTable('generald')) {
            $ratesRaw = DB::table('generald')
                ->whereIn('code', $rateCodes)
                ->pluck('cvalue', 'code')
                ->toArray();
        }
        $rates = [];
        foreach ($rateCodes as $c) {
            $dbRate = round((float) ($ratesRaw[$c] ?? 0), 2);
            if ($useNwRates) {
                $rates[$c] = $dbRate;
                continue;
            }
            // Keep Rate Update screen and Sales Bill in sync:
            // when DB rate is present, prefer it over INI fallback.
            if ($dbRate > 0) {
                $rates[$c] = $dbRate;
                continue;
            }
            $iniRate = round($this->toNum((string) ($ratesCfg[$c] ?? '0')), 2);
            if ($iniRate > 0) {
                $rates[$c] = $iniRate;
                continue;
            }
            $settingRate = round($this->toNum($sw($software, $c, (string) $dbRate)), 2);
            $rates[$c] = $settingRate > 0 ? $settingRate : $dbRate;
        }

        $itemGroups = [];
        if ($this->hasTable('itemgrp')) {
            $itemGroups = DB::table('itemgrp')
                ->whereNotNull('code')
                ->where('code', '!=', '')
                ->orderBy('code')
                ->get(['code', 'name'])
                ->map(fn ($r) => [
                    'code' => strtoupper(trim((string) ($r->code ?? ''))),
                    'name' => trim((string) ($r->name ?? '')),
                ])
                ->values()
                ->all();
        }

        // Load items for exchange dropdown and sales item group filtering (code, name, itype)
        $exchItems = [];
        if ($this->hasTable('items')) {
            $itemCols = $this->getColumns('items');
            $select = ['code', 'name', 'itype'];
            foreach (['touch', 'cost', 'disabled', 'defstktype', 'defquality', 'stkinnos', 'ornament', 'grpcode', 'display'] as $col) {
                if (in_array($col, $itemCols, true)) {
                    $select[] = $col;
                }
            }
            $exchItems = DB::table('items')
                ->whereNotNull('code')
                ->where('code', '!=', '')
                ->orderBy('code')
                ->get($select)
                ->map(fn ($r) => [
                    'code' => trim($r->code),
                    'name' => trim($r->name ?? ''),
                    'itype' => strtoupper(trim($r->itype ?? 'G')),
                    'touch' => (float) ($r->touch ?? 0),
                    'cost' => (float) ($r->cost ?? 0),
                    'disabled' => (int) ($r->disabled ?? 0),
                    'defstktype' => trim((string) ($r->defstktype ?? '')),
                    'defquality' => trim((string) ($r->defquality ?? '')),
                    'stkinnos' => strtoupper(trim((string) ($r->stkinnos ?? 'N'))),
                    'ornament' => strtoupper(trim((string) ($r->ornament ?? 'N'))),
                    'grpcode' => strtoupper(trim((string) ($r->grpcode ?? ''))),
                    'display_category' => strtoupper(trim((string) ($r->display ?? ''))),
                    'is_diamond' => str_contains(strtoupper(trim((string) ($r->display ?? ''))), 'DIAM')
                        || str_contains(strtoupper(trim((string) ($r->name ?? ''))), 'DIAM')
                        || strtoupper(trim((string) ($r->grpcode ?? ''))) === 'DD',
                ])
                ->values()
                ->all();
        }

        // Load counters from counter table
        $counters = [];
        if ($this->hasTable('counter')) {
            $counters = DB::table('counter')
                ->whereNotNull('code')->where('code', '!=', '')
                ->orderBy('code')
                ->get(['code', 'name'])
                ->map(fn ($r) => ['code' => trim($r->code), 'name' => trim($r->name ?? '')])
                ->values()->all();
        }

        // Load salesmen from sman table
        $salesmen = [];
        if ($this->hasTable('sman')) {
            $salesmen = DB::table('sman')->orderBy('name')
                ->get(['code', 'name'])
                ->map(fn ($r) => ['code' => trim($r->code), 'name' => trim($r->name ?? '')])
                ->values()->all();
        }

        // Load agents from clients where agent='Y'
        $agents = [];
        if ($this->hasTable('clients')) {
            $clientCols = $this->getColumns('clients');
            if (in_array('agent', $clientCols, true)) {
                $agents = DB::table('clients')
                    ->where('agent', 'Y')
                    ->whereNotNull('code')->where('code', '!=', '')
                    ->orderBy('code')
                    ->get(['code', 'name'])
                    ->map(fn ($r) => ['code' => trim($r->code), 'name' => trim($r->name ?? '')])
                    ->values()->all();
            }
        }

        // Load cash/bank accounts from accountm
        $cashBanks = [];
        if ($this->hasTable('accountm')) {
            $cashBanks = DB::table('accountm')
                ->whereIn('actype2', ['H', 'B'])
                ->orderByRaw("CASE WHEN actype2='H' THEN 0 ELSE 1 END, accode")
                ->get(['accode as code', 'name', 'actype2'])
                ->map(fn ($r) => ['code' => trim($r->code), 'name' => trim($r->name ?? ''), 'type' => $r->actype2])
                ->values()->all();
        }

        // Load approved-by users from userm
        $approvedBy = [];
        if ($this->hasTable('userm')) {
            $approvedBy = DB::table('userm')
                ->whereNotNull('code')->where('code', '!=', '')
                ->orderBy('code')
                ->get(['code', 'name'])
                ->map(fn ($r) => ['code' => trim($r->code), 'name' => trim($r->name ?? '')])
                ->values()->all();
        }

        // Load states (try statestate first, then state)
        $states = [];
        $stateTable = $this->hasTable('statestate') ? 'statestate' : ($this->hasTable('state') ? 'state' : null);
        if ($stateTable) {
            $stCols = $this->getColumns($stateTable);
            $codeCol = in_array('code', $stCols, true) ? 'code' : (in_array('statecode', $stCols, true) ? 'statecode' : null);
            $nameCol = in_array('name', $stCols, true) ? 'name' : (in_array('statename', $stCols, true) ? 'statename' : null);
            if ($codeCol && $nameCol) {
                $states = DB::table($stateTable)
                    ->orderBy($codeCol)
                    ->get([$codeCol . ' as code', $nameCol . ' as name'])
                    ->map(fn ($r) => ['code' => trim($r->code), 'name' => trim($r->name ?? '')])
                    ->values()->all();
            }
        }

        $mcRows = [];
        if ($this->hasTable('mctable')) {
            $cols = $this->getColumns('mctable');
            $select = [];
            foreach (['code', 'iqtype', 'weight1', 'weight2', 'mc', 'mcpergm', 'mcperqty', 'vaperc'] as $c) {
                if (in_array($c, $cols, true)) {
                    $select[] = $c;
                }
            }
            if ($select !== []) {
                $mcRows = DB::table('mctable')->get($select)->map(function ($r) {
                    return [
                        'code' => strtoupper(trim((string) ($r->code ?? ''))),
                        'iqtype' => strtoupper(trim((string) ($r->iqtype ?? ''))),
                        'weight1' => (float) ($r->weight1 ?? 0),
                        'weight2' => (float) ($r->weight2 ?? 0),
                        'mc' => (float) ($r->mc ?? 0),
                        'mcpergm' => (float) ($r->mcpergm ?? 0),
                        'mcperqty' => (float) ($r->mcperqty ?? 0),
                        'vaperc' => (float) ($r->vaperc ?? 0),
                    ];
                })->values()->all();
            }
        }

        $wstgRows = [];
        if ($this->hasTable('wstgtable')) {
            $cols = $this->getColumns('wstgtable');
            $select = [];
            foreach (['code', 'iqtype', 'weight1', 'weight2', 'wastage', 'perc'] as $c) {
                if (in_array($c, $cols, true)) {
                    $select[] = $c;
                }
            }
            if ($select !== []) {
                $wstgRows = DB::table('wstgtable')->get($select)->map(function ($r) {
                    return [
                        'code' => strtoupper(trim((string) ($r->code ?? ''))),
                        'iqtype' => strtoupper(trim((string) ($r->iqtype ?? ''))),
                        'weight1' => (float) ($r->weight1 ?? 0),
                        'weight2' => (float) ($r->weight2 ?? 0),
                        'wastage' => (float) ($r->wastage ?? 0),
                        'perc' => (float) ($r->perc ?? 0),
                    ];
                })->values()->all();
            }
        }

        $billTypes = [];
        if ($this->hasTable('salestype')) {
            $stCols = $this->getColumns('salestype');
            $select = ['code'];
            if (in_array('name', $stCols, true)) {
                $select[] = 'name';
            }
            if (in_array('taxperc', $stCols, true)) {
                $select[] = 'taxperc';
            }
            if (in_array('prefix', $stCols, true)) {
                $select[] = 'prefix';
            }
            $billTypes = DB::table('salestype')
                ->whereNotNull('code')
                ->where('code', '!=', '')
                ->orderBy('code')
                ->get($select)
                ->map(fn ($r) => [
                    'code' => strtoupper(trim((string) ($r->code ?? ''))),
                    'name' => strtoupper(trim((string) ($r->name ?? $r->code ?? ''))),
                    'taxperc' => (float) ($r->taxperc ?? 0),
                    'prefix' => strtoupper(trim((string) ($r->prefix ?? ''))),
                ])
                ->values()
                ->all();
        }
        if ($billTypes === []) {
            $billTypes = [
                ['code' => 'G', 'name' => 'GOLD', 'taxperc' => 0, 'prefix' => ''],
                ['code' => 'S', 'name' => 'SILVER', 'taxperc' => 0, 'prefix' => ''],
            ];
        }

        return view('sales-bill.index', [
            'mode' => $mode,
            'title' => $titleMap[$mode],
            'quotationMode' => $quotationMode,
            'rates' => $rates,
            'exchItems' => $exchItems,
            'itemGroups' => $itemGroups,
            'counters' => $counters,
            'salesmen' => $salesmen,
            'agents' => $agents,
            'cashBanks' => $cashBanks,
            'approvedBy' => $approvedBy,
            'states' => $states,
            'mcRows' => $mcRows,
            'wstgRows' => $wstgRows,
            'billTypes' => $billTypes,
            'software' => [
                'NW' => $sw($software, 'NW', 'N'),
                'WSM' => $sw($software, 'WSM', 'N'),
                'ExchForm' => $sw($software, 'ExchForm', ''),
                'DefExchItem' => $sw($software, 'DefExchItem', 'OG'),
                'StopOnExchRate' => $sw($software, 'StopOnExchRate', 'N'),
                'OGLessStrictly' => $sw($software, 'OGLessStrictly', 'N'),
                'UseTouchToLessWgtCalculationInPurchase' => $sw($software, 'UseTouchToLessWgtCalculationInPurchase', 'Y'),
                'PurchConvTouch' => $sw($software, 'PurchConvTouch', '0'),
                'DevideNetwgtByQty' => $sw($software, 'DevideNetwgtByQty', 'N'),
                'AllowInsufficientStockSales' => $sw($software, 'AllowInsufficientStockSales', 'N'),
                'ExchangeDefStkType' => $sw($software, 'ExchangeDefStkType', ''),
                'ExchangeDefStkType2' => $sw($software, 'ExchangeDefStkType2', ''),
                'SReturnDefStkType' => $sw($software, 'SReturnDefStkType', ''),
                'SReturnDefStkType2' => $sw($software, 'SReturnDefStkType2', ''),
                'RoundOffAllAmt' => $sw($software, 'RoundOffAllAmt', 'N'),
                'RoundDec' => $sw($software, 'RoundDec', '2'),
                'AllowSalesBillManual' => $sw($software, 'AllowSalesBillManual', 'Y'),
                'BNO' => $sw($software, 'BNO', 'N'),
                'FocusOnPartyCode' => $sw($software, 'FocusOnPartyCode', 'N'),
                'DefRate' => $sw($software, 'DefRate', 'RTR'),
                'SRateBasedOnBullionRate' => $sw($software, 'SRateBasedOnBullionRate', 'N'),
                'SRateBeforeTax' => $sw($software, 'SRateBeforeTax', 'N'),
                'SalesDefCredit' => $sw($software, 'SalesDefCredit', 'N'),
                'ShortPrintOnly' => $sw($software, 'ShortPrintOnly', 'N'),
                'BillTypewiseBillNo' => $sw($software, 'BillTypewiseBillNo', 'N'),
                'TaxBillTypeWise' => $sw($software, 'TaxBillTypeWise', 'N'),
                'DefBillType' => $sw($software, 'DefBillType', ''),
                'DefCounter' => $sw($software, 'DefCounter', ''),
                'BulTouch' => $sw($software, 'BulTouch', '99.5'),
                'ShowTaxInEstimate' => $sw($software, 'ShowTaxInEstimate', 'N'),
                'ShowAdvanceInSalesEstimate' => $sw($software, 'ShowAdvanceInSalesEstimate', 'N'),
                'AlwaysShowSMList' => $sw($software, 'AlwaysShowSMList', 'N'),
                'PrinterType' => $sw($software, 'PrinterType', 'DotMatrix'),
                'PanLabel' => $sw($software, 'PanLabel', 'PAN/Adh'),
                'StateLabel' => $sw($software, 'StateLabel', 'State'),
                'TaxNoLabel' => $sw($software, 'TaxNoLabel', 'GST No'),
                'QtnV' => $sw($software, 'QtnV', 'N'),
                'MobileNoBasedSearch' => $sw($software, 'MobileNoBasedSearch', 'N'),
                'ShowEstNumbers' => $sw($software, 'ShowEstNumbers', 'N'),
                'SalesEntryEditNo' => $sw($software, 'SalesEntryEditNo', 'N'),
                'AskEInvoiceAboveAmount' => $sw($software, 'AskEInvoiceAboveAmount', 'Y'),
                'EInvoiceThresholdAmount' => $sw($software, 'EInvoiceThresholdAmount', '1000000'),
                'EWayBillThresholdAmount' => $sw($software, 'EWayBillThresholdAmount', '1000000'),
                'DefStateCode' => (string) ($company['DefStateCode'] ?? ''),
            ],
            'access' => [
                'allowPrevNextButtons' => $allowPrevNextButtons,
            ],
        ]);
    }

    public function searchEditBills(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $date = $this->parseDate((string) $request->query('tdate', ''), true);
        $quotationMode = $request->boolean('quotation') || $request->boolean('qtn');

        if (!$this->hasTable('salesm')) {
            return response()->json(['ok' => true, 'rows' => []]);
        }

        $query = DB::table('salesm')
            ->select(['slno', 'billno', 'tdate', 'custname', 'status'])
            ->where(function ($sub) use ($q) {
                if ($q !== '') {
                    $sub->where('billno', 'like', $q . '%')
                        ->orWhere('custname', 'like', '%' . $q . '%');
                } else {
                    $sub->whereNotNull('billno');
                }
            });

        if ($quotationMode) {
            $query->whereRaw('COALESCE(control, 1) <> 1');
        } else {
            $query->whereRaw('COALESCE(control, 1) = 1')
                ->where(function ($orderBill) {
                    $orderBill->whereNull('orderno')
                        ->orWhereRaw('LENGTH(TRIM(orderno)) = 0');
                });
        }

        if ($date) {
            $query->whereDate('tdate', $date);
        }

        $rawRows = $query
            ->orderByDesc('tdate')
            ->orderByDesc('slno')
            ->get();

        $slnos = $rawRows
            ->pluck('slno')
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->values()
            ->all();

        $salesWeights = collect();
        if ($slnos !== [] && $this->hasTable('salesd') && Schema::hasColumn('salesd', 'weight')) {
            $salesWeights = DB::table('salesd')
                ->select('slno', DB::raw('SUM(COALESCE(weight, 0)) as total_weight'))
                ->whereIn('slno', $slnos)
                ->groupBy('slno')
                ->pluck('total_weight', 'slno');
        }

        $exchangeWeights = collect();
        if ($slnos !== [] && $this->hasTable('purchased') && Schema::hasColumn('purchased', 'weight')) {
            $exchangeWeights = DB::table('purchased')
                ->select('slno', DB::raw('SUM(COALESCE(weight, 0)) as total_weight'))
                ->whereIn('slno', $slnos)
                ->groupBy('slno')
                ->pluck('total_weight', 'slno');
        }

        $rows = $rawRows
            ->map(fn ($r) => [
                'slno' => (int) ($r->slno ?? 0),
                'billno' => trim((string) ($r->billno ?? '')),
                'tdate' => $r->tdate ? Carbon::parse($r->tdate)->format('d/m/Y') : '',
                'custname' => trim((string) ($r->custname ?? '')),
                'sales_weight' => round($this->toNum($salesWeights[(int) ($r->slno ?? 0)] ?? 0), 3),
                'exchange_weight' => round($this->toNum($exchangeWeights[(int) ($r->slno ?? 0)] ?? 0), 3),
                'status' => ((int) $this->toNum($r->status ?? 1)) === 0 ? 'cancelled' : 'saved',
            ])
            ->values();

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    public function resolveEditBill(Request $request): JsonResponse
    {
        $billNo = strtoupper(trim((string) $request->input('bill_no', '')));
        $date = $this->normDate((string) $request->input('tdate', ''));
        $quotationMode = $request->boolean('quotation') || $request->boolean('qtn');

        if ($billNo === '' || !$date) {
            return response()->json(['ok' => false, 'message' => 'Bill no and date are required.'], 422);
        }

        if (!$this->hasTable('salesm')) {
            return response()->json(['ok' => false, 'message' => 'Sales table not found.'], 404);
        }

        $row = DB::table('salesm')
            ->select(['slno', 'billno', 'tdate', 'control', 'sr', 'orderno'])
            ->whereRaw('UPPER(TRIM(billno)) = ?', [$billNo])
            ->whereDate('tdate', $date)
            ->first();

        if (!$row) {
            return response()->json(['ok' => false, 'message' => 'This bill number does not exist...'], 404);
        }

        $isQuotation = (int) $this->toNum($row->control ?? 1) !== 1;
        if ($quotationMode !== $isQuotation) {
            return response()->json(['ok' => false, 'message' => $quotationMode ? 'This quotation number does not exist...' : 'This bill number does not exist...'], 404);
        }

        if (!$quotationMode && trim((string) ($row->orderno ?? '')) !== '') {
            return response()->json(['ok' => false, 'message' => 'This is an order sale bill. Please use Order Sale > Edit Sale.'], 404);
        }

        return response()->json([
            'ok' => true,
            'bill_no' => trim((string) ($row->billno ?? '')),
            'tdate' => $row->tdate ? Carbon::parse($row->tdate)->format('d/m/Y') : '',
            'url' => url('/sales-bill/edit?' . http_build_query([
                'bill_no' => trim((string) ($row->billno ?? '')),
                'qtn' => $quotationMode ? 1 : null,
            ])),
        ]);
    }

    public function resolveBillAction(Request $request): JsonResponse
    {
        $billNo = strtoupper(trim((string) $request->input('bill_no', '')));
        $date = $this->normDate((string) $request->input('tdate', ''));
        $action = strtolower(trim((string) $request->input('action', 'edit')));
        $viewOnly = filter_var($request->input('view_only', false), FILTER_VALIDATE_BOOLEAN);
        $quotationMode = $request->boolean('quotation') || $request->boolean('qtn');

        if (!in_array($action, ['edit', 'reprint', 'cancel'], true)) {
            $action = 'edit';
        }

        if ($billNo === '' || !$date) {
            return response()->json(['ok' => false, 'message' => 'Bill no and date are required.'], 422);
        }

        if (!$this->hasTable('salesm')) {
            return response()->json(['ok' => false, 'message' => 'Sales table not found.'], 404);
        }

        $row = DB::table('salesm')
            ->select(['slno', 'billno', 'tdate', 'status', 'control', 'orderno'])
            ->whereRaw('UPPER(TRIM(billno)) = ?', [$billNo])
            ->whereDate('tdate', $date)
            ->first();

        if (!$row) {
            return response()->json(['ok' => false, 'message' => 'This bill number does not exist...'], 404);
        }

        $isQuotation = (int) $this->toNum($row->control ?? 1) !== 1;
        if ($quotationMode !== $isQuotation) {
            return response()->json(['ok' => false, 'message' => $quotationMode ? 'This quotation number does not exist...' : 'This bill number does not exist...'], 404);
        }

        if (!$quotationMode && trim((string) ($row->orderno ?? '')) !== '') {
            return response()->json(['ok' => false, 'message' => 'This is an order sale bill. Please use Order Sale > Edit Sale.'], 404);
        }

        if ((int) ($row->status ?? 1) === 0 && $action !== 'cancel') {
            return response()->json(['ok' => false, 'message' => 'This bill has been cancelled.'], 422);
        }

        $resolvedBillNo = trim((string) ($row->billno ?? ''));
        if ($action === 'reprint') {
            $printQuery = ['slno' => (int) ($row->slno ?? 0)];
            if ($quotationMode) {
                $printQuery['qtn'] = 1;
            }

            return response()->json([
                'ok' => true,
                'bill_no' => $resolvedBillNo,
                'tdate' => $row->tdate ? Carbon::parse($row->tdate)->format('d/m/Y') : '',
                'url' => url('/sales-bill-print?' . http_build_query($printQuery)),
            ]);
        }

        $query = ['bill_no' => $resolvedBillNo];
        if ($quotationMode) {
            $query['qtn'] = 1;
        }

        return response()->json([
            'ok' => true,
            'bill_no' => $resolvedBillNo,
            'tdate' => $row->tdate ? Carbon::parse($row->tdate)->format('d/m/Y') : '',
            'url' => url('/sales-bill/' . $action . '?' . http_build_query($query)),
        ]);
    }

    public function nextBillNo(Request $request): JsonResponse
    {
        $seb = strtoupper(trim((string) $request->query('seb', 'B')));
        if (!in_array($seb, ['B', 'E'], true)) {
            $seb = 'B';
        }
        $billType = trim((string) $request->query('bill_type', 'Gold'));
        $quotationMode = $request->boolean('quotation') || $request->boolean('qtn');
        [$billNo, $taxPerc] = $this->buildNextBillNoPayload($billType, $seb, $quotationMode);

        return response()->json([
            'ok' => true,
            'bill_no' => $billNo,
            'tax_perc' => $taxPerc,
        ]);
    }

    public function checkBillNo(Request $request): JsonResponse
    {
        $billNo = trim((string) $request->query('bill_no', ''));
        $current = trim((string) $request->query('current_bill_no', ''));

        if ($billNo === '') {
            return response()->json(['ok' => false, 'message' => 'Bill number required.'], 422);
        }
        if ($current !== '' && strcasecmp($billNo, $current) === 0) {
            return response()->json(['ok' => true, 'duplicate' => false]);
        }

        $exists = false;
        if ($this->hasTable('salesm')) {
            $exists = DB::table('salesm')->where('billno', $billNo)->exists();
        } elseif ($this->hasSalesBillsTable()) {
            $exists = DB::table('sales_bills')->where('bill_no', $billNo)->exists();
        }

        return response()->json([
            'ok' => true,
            'duplicate' => $exists,
            'message' => $exists ? 'This Bill Number already exist...' : '',
        ]);
    }

    public function get(Request $request): JsonResponse
    {
        $billNo = trim((string) $request->query('bill_no', ''));
        if ($billNo === '') {
            return response()->json(['ok' => false, 'message' => 'Bill number required.'], 422);
        }

        // Quotation numbers are bare integers and can collide with sales bill numbers,
        // so when a quotation/sales context is given, load the matching record.
        $hasQtnParam = $request->has('qtn') || $request->has('quotation');
        $quotationMode = $request->boolean('qtn') || $request->boolean('quotation');

        if ($this->hasSalesBillsTable()) {
            $bills = SalesBill::query()->where('bill_no', $billNo)->get();
            if ($bills->count() > 0) {
                $bill = $bills->first();
                if ($hasQtnParam && $bills->count() > 1) {
                    $match = $bills->first(function ($b) use ($quotationMode) {
                        $extra = $this->toArray($b->extra_json);
                        return !empty($extra['is_quotation']) === $quotationMode;
                    });
                    if ($match) {
                        $bill = $match;
                    }
                }

                return response()->json([
                    'ok' => true,
                    'data' => $this->mapBill($bill),
                ]);
            }
        }

        if ($this->hasTable('salesm')) {
            // Legacy PB fallback: load from salesm/salesd when sales_bills row is missing.
            $legacyQuery = DB::table('salesm')->where('billno', $billNo);
            if ($hasQtnParam) {
                if ($quotationMode) {
                    $legacyQuery->where('control', '!=', 1);
                } else {
                    $legacyQuery->where(function ($w) {
                        $w->where('control', 1)->orWhereNull('control');
                    });
                }
            }
            $legacy = $legacyQuery->first();
            if ($legacy) {
                return response()->json([
                    'ok' => true,
                    'data' => $this->mapLegacySalesBill($legacy),
                ]);
            }
        }

        return response()->json(['ok' => false, 'message' => 'Bill not found.'], 404);
    }

    public function customerSearch(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $preload = filter_var($request->query('preload', false), FILTER_VALIDATE_BOOLEAN);
        if ($q === '' && !$preload) {
            return response()->json(['ok' => true, 'rows' => []]);
        }
        if (!$this->hasTable('clients')) {
            return response()->json(['ok' => true, 'rows' => []]);
        }

        $clientCols = $this->getColumns('clients');
        $type = strtoupper(trim((string) $request->query('type', 'C')));
        $coPartyOnly = filter_var($request->query('coparty_only', false), FILTER_VALIDATE_BOOLEAN)
            || strtoupper(trim((string) $request->query('coparty_only', 'N'))) === 'Y';

        $query = DB::table('clients');
        if (in_array('ctype', $clientCols, true) && $type !== '' && $type !== 'ALL') {
            $query->where('ctype', $type);
        }
        if (in_array('removed', $clientCols, true)) {
            $query->where(function ($w) {
                $w->whereNull('removed')->orWhere('removed', '!=', 1);
            });
        }
        if ($coPartyOnly && in_array('coparty', $clientCols, true)) {
            $query->where('coparty', 'Y');
        }
        if ($q !== '') {
            $query->where(function ($w) use ($q, $clientCols) {
                $w->where('code', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%");
                if (in_array('mobile', $clientCols, true)) {
                    $w->orWhere('mobile', 'like', "%{$q}%");
                }
            });
        }

        $rows = $query->orderBy('code')
            ->limit($preload ? 300 : 20)
            ->get();

        $result = $rows->map(function ($r) use ($clientCols) {
            $mobile = in_array('mobile', $clientCols, true) ? trim((string) ($r->mobile ?? '')) : '';
            if ($mobile === '' && in_array('telephone', $clientCols, true)) {
                $mobile = trim((string) ($r->telephone ?? ''));
            }
            return [
                'code'      => trim($r->code ?? ''),
                'name'      => trim($r->name ?? ''),
                'addr1'     => trim($r->addr1 ?? ''),
                'mobile'    => $mobile,
                'tin'       => in_array('tin', $clientCols, true) ? trim($r->tin ?? '') : '',
                'panadhar'  => in_array('panadhar', $clientCols, true) ? trim($r->panadhar ?? '') : '',
                'state'     => in_array('state', $clientCols, true) ? trim($r->state ?? '') : '',
                'opbalance' => in_array('opbalance', $clientCols, true) ? round((float) ($r->opbalance ?? 0), 2) : 0,
                'smcode'    => in_array('smcode', $clientCols, true) ? trim($r->smcode ?? '') : '',
            ];
        })->values()->all();

        return response()->json(['ok' => true, 'rows' => $result]);
    }

    public function customerDetails(Request $request): JsonResponse
    {
        $code = strtoupper(trim((string) $request->query('code', '')));
        if ($code === '') {
            return response()->json([
                'ok' => false,
                'blocked' => false,
                'invalid' => true,
                'message' => 'Customer code is required.',
            ], 422);
        }

        // PB-like blocked account check.
        if ($this->hasTable('accountm')) {
            $accCols = $this->getColumns('accountm');
            $q = DB::table('accountm')->whereRaw('UPPER(TRIM(accode)) = ?', [$code]);
            if (in_array('blocked', $accCols, true)) {
                $q->where('blocked', 'Y');
            }
            $isBlocked = $q->exists();
            if ($isBlocked) {
                return response()->json([
                    'ok' => false,
                    'blocked' => true,
                    'invalid' => false,
                    'message' => 'Customer is blocked...Sorry....',
                ]);
            }
        }

        if (!$this->hasTable('clients')) {
            return response()->json([
                'ok' => false,
                'blocked' => false,
                'invalid' => true,
                'message' => 'Clients table not found.',
            ], 404);
        }

        $client = DB::table('clients')->whereRaw('UPPER(TRIM(code)) = ?', [$code])->first();
        if (!$client) {
            return response()->json([
                'ok' => false,
                'blocked' => false,
                'invalid' => true,
                'message' => 'Invalid Customer',
            ]);
        }

        $cols = $this->getColumns('clients');
        $cocode = trim((string) ($client->cocode ?? ''));
        $openingBalance = $this->customerOpeningBalance($code, $client, $cols);

        // Optional PB enrichment by clients_kuridet.custlinkac.
        if ($cocode === '' && $this->hasTable('clients_kuridet')) {
            $kcode = DB::table('clients_kuridet')
                ->where('custlinkac', $code)
                ->value('code');
            $cocode = trim((string) ($kcode ?? ''));
        }

        return response()->json([
            'ok' => true,
            'blocked' => false,
            'invalid' => false,
            'message' => '',
            'data' => [
                'code' => $code,
                'name' => trim((string) ($client->name ?? '')),
                'addr1' => trim((string) ($client->addr1 ?? '')),
                'addr2' => trim((string) ($client->addr2 ?? '')),
                'mobile' => $this->pickClientMobile($client, $cols),
                'opbalance' => $openingBalance,
                'panadhar' => in_array('panadhar', $cols, true) ? trim((string) ($client->panadhar ?? '')) : '',
                'tin' => in_array('tin', $cols, true) ? trim((string) ($client->tin ?? '')) : '',
                'state' => in_array('state', $cols, true) ? trim((string) ($client->state ?? '')) : '',
                'distance' => in_array('distance', $cols, true) ? (int) $this->toNum($client->distance ?? 0) : 0,
                'cocode' => $cocode,
                'ctype' => in_array('ctype', $cols, true) ? trim((string) ($client->ctype ?? '')) : '',
            ],
        ]);
    }

    private function customerOpeningBalance(string $code, object $client, array $clientCols): float
    {
        $code = strtoupper(trim($code));
        $clientOb = in_array('opbalance', $clientCols, true)
            ? round($this->toNum($client->opbalance ?? 0), 2)
            : 0.0;
        $accountOb = 0.0;
        $daybookBalance = 0.0;

        if ($code !== '' && $this->hasTable('accountm')) {
            $account = DB::table('accountm')
                ->whereRaw('UPPER(TRIM(accode)) = ?', [$code])
                ->first();
            if ($account) {
                $accountOb = round($this->toNum($account->opbal ?? 0), 2);
            }
        }

        if ($code !== '' && $this->hasTable('daybook')) {
            $daybookBalance = round((float) DB::table('daybook')
                ->whereRaw('UPPER(TRIM(accode)) = ?', [$code])
                ->sum('amount'), 2);
        }

        if ($clientOb != 0.0) {
            return $clientOb;
        }
        if ($accountOb != 0.0) {
            return $accountOb;
        }

        return $daybookBalance;
    }

    public function customerByMobile(Request $request): JsonResponse
    {
        $mob = trim((string) $request->query('mobile', ''));
        if ($mob === '' || !$this->hasTable('clients')) {
            return response()->json(['ok' => true, 'found' => false]);
        }

        $normalizedMob = preg_replace('/\D+/', '', $mob) ?? '';
        $clientCols = $this->getColumns('clients');
        $q = DB::table('clients');
        $q->where(function ($w) use ($mob, $normalizedMob, $clientCols) {
            if (in_array('mobile', $clientCols, true)) {
                $w->orWhere('mobile', $mob);
                if ($normalizedMob !== '') {
                    $w->orWhereRaw("REGEXP_REPLACE(COALESCE(mobile, ''), '[^0-9]', '') = ?", [$normalizedMob]);
                }
            }
            if (in_array('telephone', $clientCols, true)) {
                $w->orWhere('telephone', $mob);
                if ($normalizedMob !== '') {
                    $w->orWhereRaw("REGEXP_REPLACE(COALESCE(telephone, ''), '[^0-9]', '') = ?", [$normalizedMob]);
                }
            }
        });
        if (in_array('removed', $clientCols, true)) {
            $q->where(function ($w) {
                $w->whereNull('removed')->orWhere('removed', '!=', 1);
            });
        }
        $client = $q->first();

        $code = trim((string) ($client->code ?? ''));
        $name = trim((string) ($client->name ?? ''));
        $addr = trim((string) ($client->addr1 ?? ''));
        if ($client && isset($client->addr2) && trim((string) $client->addr2) !== '') {
            $addr2 = trim((string) $client->addr2);
            if ($addr !== '') {
                $addr .= ', ' . $addr2;
            } else {
                $addr = $addr2;
            }
        }

        // PB-like: if mobile exists in salesm, use the last bill only when it carries a valid customer code.
        if ($this->hasTable('salesm')) {
            $smCols = $this->getColumns('salesm');
            if (in_array('mobno', $smCols, true)) {
                $lastSlno = (int) (DB::table('salesm')
                    ->where(function ($w) use ($mob, $normalizedMob) {
                        $w->where('mobno', $mob);
                        if ($normalizedMob !== '') {
                            $w->orWhereRaw("REGEXP_REPLACE(COALESCE(mobno, ''), '[^0-9]', '') = ?", [$normalizedMob]);
                        }
                    })
                    ->max('slno') ?? 0);
                if ($lastSlno > 0) {
                    $last = DB::table('salesm')->where('slno', $lastSlno)->first();
                    if ($last) {
                        $lastCode = strtoupper(trim((string) ($last->custcode ?? '')));
                        if ($lastCode !== '') {
                            $lastClient = DB::table('clients')
                                ->whereRaw('UPPER(TRIM(code)) = ?', [$lastCode])
                                ->first();

                            if ($lastClient) {
                                $code = trim((string) ($lastClient->code ?? $lastCode));
                                $name = trim((string) ($lastClient->name ?? $name));

                                $addr1 = trim((string) ($lastClient->addr1 ?? ''));
                                $addr2 = trim((string) ($lastClient->addr2 ?? ''));
                                $mergedAddr = $addr1;
                                if ($addr2 !== '') {
                                    $mergedAddr = $mergedAddr !== '' ? ($mergedAddr . ', ' . $addr2) : $addr2;
                                }
                                if ($mergedAddr !== '') {
                                    $addr = $mergedAddr;
                                }
                            }
                        }
                    }
                }
            }
        }

        return response()->json([
            'ok' => true,
            'found' => $code !== '',
            'code' => $code,
            'name' => $name,
            'addr' => $addr,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if ($this->hasTable('salesm')) {
            $rows = DB::table('salesm')
                ->where('status', '!=', 0)
                ->when($q !== '', function ($query) use ($q) {
                    $query->where(function ($w) use ($q) {
                        $w->where('billno', 'like', "%{$q}%")
                            ->orWhere('custname', 'like', "%{$q}%");
                    });
                })
                ->orderByDesc('slno')
                ->limit(25)
                ->get(['billno', 'tdate', 'custname', 'netamt', 'status'])
                ->map(fn ($r) => [
                    'bill_no' => trim((string) ($r->billno ?? '')),
                    'bill_date' => !empty($r->tdate) ? Carbon::parse((string) $r->tdate)->format('Y-m-d') : null,
                    'customer_name' => trim((string) ($r->custname ?? '')),
                    'net_total' => round($this->toNum($r->netamt ?? 0), 2),
                    'status' => ((int) $this->toNum($r->status ?? 1)) === 0 ? 'cancelled' : 'saved',
                ])
                ->values();
        } else {
            $rows = SalesBill::query()
                ->when($q !== '', function ($query) use ($q) {
                    $query->where(function ($w) use ($q) {
                        $w->where('bill_no', 'like', "%{$q}%")
                            ->orWhere('customer_name', 'like', "%{$q}%");
                    });
                })
                ->orderByDesc('id')
                ->limit(25)
                ->get(['bill_no', 'bill_date', 'customer_name', 'net_total', 'status']);
        }

        return response()->json([
            'ok' => true,
            'rows' => $rows,
        ]);
    }

    public function quotationList(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $usedQuotationBillNos = $this->usedQuotationBillNos();

        if (!$this->hasTable('salesm')) {
            return response()->json(['ok' => true, 'rows' => []]);
        }

        $rows = DB::table('salesm')
            ->where('control', 2)
            ->where('status', '!=', 0)
            ->when($usedQuotationBillNos !== [], fn ($query) => $query->whereNotIn('billno', $usedQuotationBillNos))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('billno', 'like', "%{$q}%")
                        ->orWhere('custname', 'like', "%{$q}%")
                        ->orWhere('mobno', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('slno')
            ->limit(100)
            ->get(['billno', 'tdate', 'custname', 'mobno', 'netamt'])
            ->map(fn ($r) => [
                'bill_no' => trim((string) ($r->billno ?? '')),
                'bill_date' => !empty($r->tdate) ? Carbon::parse((string) $r->tdate)->format('d/m/Y') : '',
                'customer_name' => trim((string) ($r->custname ?? '')),
                'mobile' => trim((string) ($r->mobno ?? '')),
                'net_total' => round($this->toNum($r->netamt ?? 0), 2),
            ])
            ->values();

        return response()->json([
            'ok' => true,
            'rows' => $rows,
        ]);
    }

    private function usedQuotationBillNos(): array
    {
        if (!$this->hasSalesBillsTable()) {
            return [];
        }

        return DB::table('sales_bills')
            ->where('status', '!=', 'cancelled')
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(extra_json, '$.source_quotation_bill_no')) IS NOT NULL")
            ->pluck(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(extra_json, '$.source_quotation_bill_no'))"))
            ->map(fn ($billNo) => trim((string) $billNo))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function prevBill(Request $request): JsonResponse
    {
        if (!$this->userHasPermission($request, 'ALLOWPREVNEXTBUTTONSINSALES')) {
            return response()->json(['ok' => false, 'message' => 'You do not have permission to use Previous / Next navigation.'], 403);
        }

        $billNo = trim((string) $request->query('bill_no', ''));
        $gisemi = $this->resolveGisemi($request);

        // PB primary path: salesm by slno/control/orderno
        if ($this->hasTable('salesm')) {
            $current = DB::table('salesm')
                ->select('slno', 'sr')
                ->where('billno', $billNo)
                ->where('control', '<=', $gisemi)
                ->first();

            if ($current) {
                $targetSlno = (int) DB::table('salesm')
                    ->where('slno', '<', (int) $current->slno)
                    ->where('control', '<=', $gisemi)
                    ->where('status', '!=', 0)
                    ->where(function ($query) {
                        $query->whereNull('orderno')->orWhere('orderno', '');
                    })
                    ->max('slno');
            } else {
                $targetSlno = (int) DB::table('salesm')
                    ->where('control', '<=', $gisemi)
                    ->where('status', '!=', 0)
                    ->where(function ($query) {
                        $query->whereNull('orderno')->orWhere('orderno', '');
                    })
                    ->max('slno');
            }

            if ($targetSlno > 0) {
                $targetBillNo = trim((string) (DB::table('salesm')->where('slno', $targetSlno)->value('billno') ?? ''));
                if ($targetBillNo !== '') {
                    return response()->json(['ok' => true, 'bill_no' => $targetBillNo, 'mode' => 'edit']);
                }
            }
            return response()->json(['ok' => true, 'bill_no' => null, 'mode' => 'edit']);
        }

        // Fallback: sales_bills by created id
        if (!$this->hasSalesBillsTable()) {
            return response()->json(['ok' => true, 'bill_no' => null, 'mode' => 'edit']);
        }
        $current = SalesBill::query()->where('bill_no', $billNo)->first();
        if ($current) {
            $target = SalesBill::query()
                ->where('id', '<', $current->id)
                ->orderByDesc('id')
                ->first();
        } else {
            $target = SalesBill::query()->orderByDesc('id')->first();
        }

        return response()->json([
            'ok' => true,
            'bill_no' => $target?->bill_no,
            'mode' => 'edit',
        ]);
    }

    public function nextBill(Request $request): JsonResponse
    {
        if (!$this->userHasPermission($request, 'ALLOWPREVNEXTBUTTONSINSALES')) {
            return response()->json(['ok' => false, 'message' => 'You do not have permission to use Previous / Next navigation.'], 403);
        }

        $billNo = trim((string) $request->query('bill_no', ''));
        $gisemi = $this->resolveGisemi($request);

        // PB primary path: salesm by slno/control/orderno
        if ($this->hasTable('salesm')) {
            $current = DB::table('salesm')
                ->select('slno', 'sr')
                ->where('billno', $billNo)
                ->where('control', '<=', $gisemi)
                ->first();

            $targetSlno = 0;
            if ($current) {
                $targetSlno = (int) (DB::table('salesm')
                    ->where('slno', '>', (int) $current->slno)
                    ->where('control', '<=', $gisemi)
                    ->where('status', '!=', 0)
                    ->where(function ($query) {
                        $query->whereNull('orderno')->orWhere('orderno', '');
                    })
                    ->min('slno') ?? 0);
            }

            // PB: if no next -> reset to Add mode
            if ($targetSlno <= 0 || ($current && $targetSlno === (int) $current->slno)) {
                return response()->json(['ok' => true, 'bill_no' => null, 'mode' => 'add']);
            }

            $targetBillNo = trim((string) (DB::table('salesm')->where('slno', $targetSlno)->value('billno') ?? ''));
            if ($targetBillNo === '') {
                return response()->json(['ok' => true, 'bill_no' => null, 'mode' => 'add']);
            }
            return response()->json(['ok' => true, 'bill_no' => $targetBillNo, 'mode' => 'edit']);
        }

        // Fallback: sales_bills by created id
        if (!$this->hasSalesBillsTable()) {
            return response()->json(['ok' => true, 'bill_no' => null, 'mode' => 'add']);
        }
        $current = SalesBill::query()->where('bill_no', $billNo)->first();
        if (!$current) {
            return response()->json(['ok' => true, 'bill_no' => null, 'mode' => 'add']);
        }
        $target = SalesBill::query()
            ->where('id', '>', $current->id)
            ->orderBy('id')
            ->first();

        if (!$target) {
            return response()->json(['ok' => true, 'bill_no' => null, 'mode' => 'add']);
        }

        return response()->json([
            'ok' => true,
            'bill_no' => $target->bill_no,
            'mode' => 'edit',
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'bill_no' => 'required|string|max:40',
            'slno' => 'nullable|integer',
            'form_mode' => 'nullable|string|max:20',
            'manual_bill_no' => 'nullable|boolean',
            'bill_date' => 'nullable|string|max:20',
            'bill_time' => 'nullable|string|max:20',
            'bill_type' => 'nullable|string|max:30',
            'is_quotation' => 'nullable|boolean',
            'source_quotation_bill_no' => 'nullable|string|max:40',
            'customer_name' => 'required|string|max:120',
            'customer_code' => 'required|string|max:20',
            'address' => 'nullable|string|max:255',
            'mobile' => 'nullable|string|max:30',
            'gst_no' => 'nullable|string|max:40',
            'pan_no' => 'nullable|string|max:30',
            'co_party_code' => 'nullable|string|max:20',
            'state_code' => 'nullable|string|max:20',
            'due_date' => 'nullable|string|max:20',
            'vehicle_no' => 'nullable|string|max:20',
            'supply_place' => 'nullable|string|max:80',
            'rate_per_gm' => 'nullable',
            'counter_name' => 'nullable|string|max:80',
            'counter_code' => 'nullable|string|max:20',
            'salesman_name' => 'nullable|string|max:120',
            'salesman_code' => 'nullable|string|max:20',
            'agent_code' => 'nullable|string|max:20',
            'approved_by' => 'nullable|string|max:20',
            'cashbank_code' => 'nullable|string|max:20',
            'distance' => 'nullable',
            'items' => 'nullable|array',
            'exchange' => 'nullable|array',
            'sales_return' => 'nullable|array',
            'sr_tax_perc' => 'nullable|numeric',
            'sr_tax_amt' => 'nullable|numeric',
            'sr_cess_perc' => 'nullable|numeric',
            'sr_cess_amt' => 'nullable|numeric',
            'sr_discount_amt' => 'nullable|numeric',
            'extra' => 'nullable|array',
            'secondary_sync' => 'nullable|boolean',
        ]);

        $custCode = trim((string) ($payload['customer_code'] ?? ''));
        $custName = trim((string) ($payload['customer_name'] ?? ''));
        if ($custCode === '' || $custName === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Customer code and customer name are required.',
            ], 422);
        }

        $shouldSecondarySync = (bool) ($payload['secondary_sync'] ?? false);
        if (!empty($payload['is_quotation'])) {
            $shouldSecondarySync = false;
        }
        if ($shouldSecondarySync && !SecondaryDatabaseSync::userCanUse($request->session()->get('user_code'))) {
            return response()->json(['ok' => false, 'message' => 'You do not have permission for secondary database sync.'], 403);
        }

        $items = collect($payload['items'] ?? [])
            ->filter(fn ($row) => trim((string) ($row['item_code'] ?? '')) !== '')
            ->values()
            ->map(function ($row) {
            $qty = $this->toNum($row['qty'] ?? 0);
            $weight = $this->toNum($row['weight'] ?? 0);
            $stoneWgt = $this->toNum($row['stone_wgt'] ?? 0);
            $stonePrice = $this->toNum($row['stone_price'] ?? 0);
            $mc = $this->toNum($row['making_charge'] ?? 0);
            $vaDiscPerc = $this->toNum($row['va_disc_perc'] ?? 0);
            $vaDiscAmt = $this->toNum($row['va_disc_amt'] ?? 0);
            $rate = $this->toNum($row['rate'] ?? 0);
            $vaDiscBase = max($weight - $stoneWgt, 0) * $rate;
            if ($vaDiscAmt == 0.0 && $vaDiscPerc > 0 && $vaDiscBase > 0) {
                $vaDiscAmt = round(($vaDiscBase * $vaDiscPerc) / 100, 2);
            } elseif ($vaDiscPerc == 0.0 && $vaDiscAmt > 0 && $vaDiscBase > 0) {
                $vaDiscPerc = round(($vaDiscAmt * 100) / $vaDiscBase, 3);
            }
            $amount = $this->toNum($row['amount'] ?? 0);
            if ($amount == 0.0) {
                $netWgt = max($weight - $stoneWgt, 0);
                $amount = (($netWgt * $rate) + $stonePrice + $mc) - $vaDiscAmt;
            }

            $row['qty'] = $qty;
            $row['weight'] = $weight;
            $row['stone_wgt'] = $stoneWgt;
            $row['net_wgt'] = max($weight - $stoneWgt, 0);
            $row['stone_price'] = $stonePrice;
            $row['making_charge'] = $mc;
            $row['va_disc_perc'] = $vaDiscPerc;
            $row['va_disc_amt'] = round($vaDiscAmt, 2);
            $row['rate'] = $rate;
            $row['amount'] = round($amount, 2);
            return $row;
        })->values()->all();

        $exchange = collect($payload['exchange'] ?? [])
            ->filter(fn ($row) => trim((string) ($row['item_code'] ?? '')) !== '')
            ->values()
            ->map(function ($row) {
            $row['amount'] = round($this->toNum($row['amount'] ?? 0), 2);
            return $row;
        })->values()->all();

        $salesReturn = collect($payload['sales_return'] ?? [])
            ->filter(fn ($row) => trim((string) ($row['item_code'] ?? '')) !== '')
            ->values()
            ->map(function ($row) {
            $row['amount'] = round($this->toNum($row['amount'] ?? 0), 2);
            return $row;
        })
            ->unique(function ($row) {
                $bcode = trim((string) ($row['bcode'] ?? ''));
                if ($bcode !== '' && $bcode !== '0') {
                    return 'B:' . $bcode;
                }
                return implode(':', [
                    'I',
                    strtoupper(trim((string) ($row['item_code'] ?? ''))),
                    strtoupper(trim((string) ($row['model'] ?? ''))),
                    number_format($this->toNum($row['weight'] ?? 0), 3, '.', ''),
                    number_format($this->toNum($row['amount'] ?? 0), 2, '.', ''),
                ]);
            })
            ->values()
            ->all();

        $payload['items'] = $items;
        $payload['exchange'] = $exchange;
        $payload['sales_return'] = $salesReturn;
        $calc = $this->calcTotals($payload);
        $billDate = $this->parseDate((string) ($payload['bill_date'] ?? ''));
        $settingsPayload = $this->loadSettingsPayload();
        $software = (array) ($settingsPayload['Software'] ?? []);
        $flagY = static fn (mixed $v, string $def = 'N'): bool => strtoupper(trim((string) ($v ?? $def))) === 'Y';
        $stktypeStrict = $flagY($software['StktypeStrict'] ?? 'N');
        $strictCheck = $flagY($software['StrictCheck'] ?? 'N');
        $allowInsufficientStockSales = $flagY($software['AllowInsufficientStockSales'] ?? 'N');
        $billControl = !empty($payload['is_quotation']) ? 2 : 1;
        $formMode = strtolower(trim((string) ($payload['form_mode'] ?? 'bill')));
        $manualBillNo = (bool) ($payload['manual_bill_no'] ?? false);
        $isAddMode = $formMode === 'bill';
        $editSlno = (int) ($payload['slno'] ?? 0);
        $billNo = trim((string) ($payload['bill_no'] ?? ''));

        if (!$isAddMode) {
            if ($editSlno <= 0) {
                return response()->json(['ok' => false, 'message' => 'Loaded bill reference missing. Please reopen the bill and save again.'], 422);
            }
            $loadedLegacy = $this->hasTable('salesm')
                ? DB::table('salesm')->where('slno', $editSlno)->first(['slno', 'billno'])
                : null;
            if (!$loadedLegacy) {
                return response()->json(['ok' => false, 'message' => 'Loaded bill not found. Please reopen the bill and save again.'], 422);
            }
            $loadedBillNo = trim((string) ($loadedLegacy->billno ?? ''));
            if (strcasecmp($loadedBillNo, $billNo) !== 0) {
                return response()->json(['ok' => false, 'message' => 'Bill number cannot be changed while editing. Please reopen the correct bill.'], 422);
            }
        }

        // Manual bill numbers must stay unique. Automatic bill numbers are reserved below on the server
        // so a stale preview from another terminal can move to the next number instead of failing here.
        if ($isAddMode && $manualBillNo && $billNo !== '' && $this->hasTable('salesm')) {
            $existingForBillNo = DB::table('salesm')->where('billno', $billNo)->first(['slno']);
            if ($existingForBillNo) {
                return response()->json([
                    'ok' => false,
                    'code' => 'BILLNO_TAKEN',
                    'message' => "Bill number $billNo was just used by another user. Click New / Refresh to get the next number, then save again.",
                ], 409);
            }
        }

        if ($items === [] && $exchange === [] && $salesReturn === []) {
            return response()->json(['ok' => false, 'message' => "There is no entries. You can't proceed..."], 422);
        }

        $balance = $this->toNum(($payload['extra']['balance'] ?? $calc['extra']['balance'] ?? 0));
        if ($balance != 0.0 && trim((string) ($payload['customer_code'] ?? '')) === '') {
            return response()->json(['ok' => false, 'message' => 'Enter customer for credit sale'], 422);
        }

        $salesmanCode = trim((string) ($payload['salesman_code'] ?? ''));
        if ($salesmanCode !== '' && $this->hasTable('sman')) {
            $smRow = DB::table('sman')
                ->where(function ($q) use ($salesmanCode) {
                    $q->whereRaw('UPPER(TRIM(code)) = ?', [strtoupper($salesmanCode)])
                        ->orWhereRaw('UPPER(TRIM(name)) = ?', [strtoupper($salesmanCode)]);
                })
                ->first(['code', 'name']);
            if ($smRow) {
                $payload['salesman_code'] = trim((string) ($smRow->code ?? $salesmanCode));
                if (trim((string) ($payload['salesman_name'] ?? '')) === '') {
                    $payload['salesman_name'] = trim((string) ($smRow->name ?? ''));
                }
            }
        }

        $isEditForValidation = !$isAddMode;
        if ($items !== [] && !$this->hasTable('items')) {
            return response()->json(['ok' => false, 'message' => 'Item master table not found. You can\'t save this bill.'], 422);
        }

        if ($this->hasTable('items')) {
            $itemRows = DB::table('items')
                ->whereIn('code', collect($items)->pluck('item_code')->filter()->all())
                ->get()
                ->keyBy(fn ($row) => strtoupper(trim((string) ($row->code ?? ''))));
            foreach ($items as $idx => $it) {
                $code = trim((string) ($it['item_code'] ?? ''));
                $weight = $this->toNum($it['weight'] ?? 0);
                $rate = $this->toNum($it['rate'] ?? 0);
                $qty = $this->toNum($it['qty'] ?? 0);
                $stoneWgt = $this->toNum($it['stone_wgt'] ?? 0);
                $stonePrice = $this->toNum($it['stone_price'] ?? 0);
                $stkinnos = strtoupper(trim((string) ($it['stkinnos'] ?? 'N')));
                $stktype = trim((string) ($it['stktype'] ?? ''));
                $groupCode = strtoupper(trim((string) ($it['group_code'] ?? '')));
                $bcode = (int) $this->toNum($it['bcode'] ?? 0);
                $dbItem = $itemRows->get(strtoupper($code));
                if (!$dbItem) {
                    return response()->json(['ok' => false, 'message' => "Invalid Item Code ({$code}). You can't save..."], 422);
                }
                $dbGroupCode = strtoupper(trim((string) ($dbItem->grpcode ?? '')));
                if ($groupCode !== '' && $dbGroupCode !== '' && strcasecmp($groupCode, $dbGroupCode) !== 0) {
                    return response()->json(['ok' => false, 'message' => "Check the item group ({$code}). You can't proceed..."], 422);
                }

                $stkinnos = strtoupper(trim((string) ($dbItem->stkinnos ?? $stkinnos)));
                $items[$idx]['stkinnos'] = $stkinnos;

                if ($weight <= 0 && $stkinnos !== 'Y') {
                    return response()->json(['ok' => false, 'message' => "Check Weight ({$code}). You can't proceed..."], 422);
                }
                if ($rate <= 0) {
                    return response()->json(['ok' => false, 'message' => "Check Rate ({$code}). You can't proceed..."], 422);
                }
                if ($stktypeStrict && $stktype === '') {
                    return response()->json(['ok' => false, 'message' => "Check Stock Type ({$code}). You can't save..."], 422);
                }
                if ($qty <= 0 && $strictCheck) {
                    return response()->json(['ok' => false, 'message' => "Check Qty ({$code}). You can't proceed..."], 422);
                }
                if ($stoneWgt > 0 && $stonePrice <= 0 && $strictCheck) {
                    return response()->json(['ok' => false, 'message' => "Check Stone Price ({$code})."], 422);
                }

                $bcComp = strtoupper(trim((string) ($dbItem->bccompulsory ?? 'N')));
                if ($bcode <= 0 && $bcComp === 'Y') {
                    return response()->json(['ok' => false, 'message' => "Barcode Compulsory for item {$code}. You can't continue..."], 422);
                }
                $stoneMust = strtoupper(trim((string) ($dbItem->stonemust ?? 'N')));
                if ($stoneWgt <= 0 && $stoneMust === 'Y') {
                    return response()->json(['ok' => false, 'message' => "Stone Compulsory for item {$code}. You can't continue..."], 422);
                }
                if ($billControl === 1 && !$allowInsufficientStockSales && !$isEditForValidation) {
                    $stockQty = $this->toNum($dbItem->qty ?? 0);
                    $stockWgt = $this->toNum($dbItem->weight ?? 0);
                    if ($qty > $stockQty || $weight > $stockWgt) {
                        return response()->json(['ok' => false, 'message' => "Insufficient stock for item {$code}. You can't continue..."], 422);
                    }
                }
            }
        }

        if ($isAddMode && !$manualBillNo) {
            [$reservedBillNo] = $this->reserveNextBillNoPayload(
                (string) ($payload['bill_type'] ?? 'Gold'),
                $billControl === 1 ? 'B' : 'E',
                $billControl !== 1
            );
            $payload['bill_no'] = $reservedBillNo;
        }

        if ($isAddMode && $manualBillNo && $this->billNoExistsInPrimaryStore(trim((string) $payload['bill_no']))) {
            return response()->json(['ok' => false, 'message' => 'This Bill Number already exist...'], 422);
        }

        $legacyExisting = $this->hasTable('salesm')
            ? DB::table('salesm')->where('billno', trim((string) $payload['bill_no']))->exists()
            : false;
        $bill = null;
        $isEdit = $legacyExisting;
        if ($this->hasSalesBillsTable()) {
            $bill = SalesBill::query()->firstOrNew(['bill_no' => trim((string) $payload['bill_no'])]);
            if ($bill->exists && $bill->status === 'cancelled') {
                return response()->json(['ok' => false, 'message' => 'Cancelled bill cannot be edited.'], 422);
            }
            $isEdit = $bill->exists;
        }

        try {
            if ($bill) {
                $bill->fill([
                    'bill_date' => $billDate,
                    'bill_time' => trim((string) ($payload['bill_time'] ?? '')) ?: now()->format('h:i A'),
                    'bill_type' => trim((string) ($payload['bill_type'] ?? 'Gold')) ?: 'Gold',
                    'customer_name' => trim((string) ($payload['customer_name'] ?? '')),
                    'customer_code' => trim((string) ($payload['customer_code'] ?? '')) ?: null,
                    'address' => trim((string) ($payload['address'] ?? '')) ?: null,
                    'mobile' => trim((string) ($payload['mobile'] ?? '')) ?: null,
                    'gst_no' => trim((string) ($payload['gst_no'] ?? '')) ?: null,
                    'pan_no' => trim((string) ($payload['pan_no'] ?? '')) ?: null,
                    'state_code' => trim((string) ($payload['state_code'] ?? '')) ?: null,
                    'rate_per_gm' => round($this->toNum($payload['rate_per_gm'] ?? 0), 2),
                    'counter_name' => trim((string) ($payload['counter_name'] ?? '')),
                    'counter_code' => trim((string) ($payload['counter_code'] ?? '')) ?: null,
                    'salesman_name' => trim((string) ($payload['salesman_name'] ?? '')),
                    'salesman_code' => trim((string) ($payload['salesman_code'] ?? '')) ?: null,
                    'agent_code' => trim((string) ($payload['agent_code'] ?? '')) ?: null,
                    'approved_by' => trim((string) ($payload['approved_by'] ?? '')) ?: null,
                    'cashbank_code' => trim((string) ($payload['cashbank_code'] ?? '')) ?: null,
                    'bill_total' => $calc['bill_total'],
                    'exchange_amount' => $calc['exchange_amount'],
                    'return_amount' => $calc['return_amount'],
                    'net_total' => $calc['net_total'],
                    'status' => $bill->status ?: 'saved',
                    'items_json' => json_encode($items),
                    'exchange_json' => json_encode($exchange),
                    'return_json' => json_encode($salesReturn),
                    'extra_json' => json_encode(array_merge($calc['extra'], [
                        'is_quotation' => $billControl !== 1,
                        'source_quotation_bill_no' => trim((string) ($payload['source_quotation_bill_no'] ?? '')),
                    ])),
                ]);
                $isNew = !$bill->exists;
                $bill->save();
                if ($isNew || $bill->wasRecentlyCreated) {
                    $this->syncBillNoCounter((string) $bill->bill_no);
                }
            }
            $legacySlno = $this->syncLegacySalesTables($payload, $calc, $items, $billDate, $billControl);

            // Sync bill number counter for legacy-only path (when sales_bills table doesn't exist).
            if (!$bill) {
                $this->syncBillNoCounter(trim((string) ($payload['bill_no'] ?? '')));
            }

            $sourceQuotationBillNo = trim((string) ($payload['source_quotation_bill_no'] ?? ''));
            if ($billControl === 1 && $sourceQuotationBillNo !== '' && strcasecmp($sourceQuotationBillNo, trim((string) ($payload['bill_no'] ?? ''))) !== 0) {
                DB::transaction(function () use ($sourceQuotationBillNo) {
                    $sourceLegacy = $this->hasTable('salesm')
                        ? DB::table('salesm')->where('billno', $sourceQuotationBillNo)->first(['slno', 'control'])
                        : null;

                    if ($sourceLegacy && (int) ($sourceLegacy->control ?? 1) === 2) {
                        $this->deleteLegacySalesBundle((int) ($sourceLegacy->slno ?? 0));
                    }

                    if ($this->hasSalesBillsTable()) {
                        SalesBill::query()
                            ->where('bill_no', $sourceQuotationBillNo)
                            ->delete();
                    }
                });
            }
        } catch (\Throwable $e) {
            if ($isAddMode && !$manualBillNo) {
                $this->rewindBillNoCounterToSavedMax(trim((string) ($payload['bill_no'] ?? '')));
            }
            throw $e;
        }

        $responseData = null;
        if ($bill) {
            $responseData = $this->mapBill($bill->fresh());
        } elseif ($legacySlno && $this->hasTable('salesm')) {
            $legacy = DB::table('salesm')->where('slno', $legacySlno)->first();
            if ($legacy) {
                $responseData = $this->mapLegacySalesBill($legacy);
            }
        }

        $savedBillNo = trim((string) ($responseData['bill_no'] ?? ($payload['bill_no'] ?? '')));
        $this->logDelpart($request, 'Sales Bill(' . $savedBillNo . ') Saved', ['utype' => !empty($payload['slno']) ? 'E' : 'A', 'ttype' => 'T', 'slno' => $legacySlno]);
        [$nextBillNo, $nextTaxPerc] = $this->buildNextBillNoPayload(
            (string) ($payload['bill_type'] ?? 'Gold'),
            $billControl === 1 ? 'B' : 'E',
            $billControl !== 1
        );
        $message = $billControl === 1 ? 'Sales bill saved.' : 'Quotation saved.';
        $secondarySync = null;
        if ($shouldSecondarySync && $legacySlno > 0) {
            try {
                $secondarySync = (new SecondaryDatabaseSync())->sync('sales', (int) $legacySlno);
                $message .= ' Secondary sync completed to ' . ($secondarySync['database'] ?? '') . '.';
            } catch (\Throwable $e) {
                $message .= ' Primary save completed, but secondary sync failed: ' . $e->getMessage();
            }
        }

        return response()->json([
            'ok' => true,
            'message' => $message,
            'bill_no' => $savedBillNo,
            'legacy_slno' => $legacySlno,
            'data' => $responseData,
            'secondary_sync' => $secondarySync,
            'next_bill_no' => $nextBillNo,
            'next_tax_perc' => $nextTaxPerc,
        ]);
    }

    private function buildNextBillNoPayload(string $billType = 'Gold', string $seb = 'B', bool $quotationMode = false): array
    {
        $settingsPayload = $this->loadSettingsPayload();
        $software = (array) ($settingsPayload['Software'] ?? []);
        $sw = static fn (array $arr, string $key, string $default = ''): string => (string) ($arr[$key] ?? $default);

        $seb = strtoupper(trim($seb));
        if (!in_array($seb, ['B', 'E'], true)) {
            $seb = 'B';
        }
        $billType = trim($billType) !== '' ? trim($billType) : 'Gold';

        $shortPrintOnly = strtoupper(trim($sw($software, 'ShortPrintOnly', 'N'))) === 'Y';
        $billTypewiseBillNo = strtoupper(trim($sw($software, 'BillTypewiseBillNo', 'N'))) === 'Y';
        $taxBillTypeWise = strtoupper(trim($sw($software, 'TaxBillTypeWise', 'N'))) === 'Y';
        $isSecondaryDatabase = $this->isSecondaryCompanyDatabase();
        $secondaryTxnSeriesSeparate = strtoupper(trim($sw($software, 'SecondaryTransactionSeriesSeperate', 'N'))) === 'Y';
        $secondarySalesPrefix = trim($sw($software, 'SecSalesSeriesPrefix', '')) ?: 'S/';
        $useSecondarySalesPrefix = $isSecondaryDatabase && $secondaryTxnSeriesSeparate && $seb === 'B';

        $stype = ($shortPrintOnly && $seb === 'E') ? 'T' : 'S';
        $genCode = ($stype === 'T') ? ('TSALES' . $seb) : ('SALES' . $seb);

        $lbillno = $this->readGeneraliCounter($genCode);
        $taxPerc = 0.0;

        if ($quotationMode && $seb === 'E') {
            $billNo = (string) ($lbillno + 1);
            while ($this->billNoExistsInPrimaryStore($billNo)) {
                $lbillno++;
                $billNo = (string) ($lbillno + 1);
            }

            return [$billNo, $taxPerc];
        }

        if ($useSecondarySalesPrefix) {
            $salesLen = $this->salesBillNumberLength();
            $billNo = $secondarySalesPrefix . str_pad((string) ($lbillno + 1), $salesLen, '0', STR_PAD_LEFT);
            while ($this->billNoExistsInPrimaryStore($billNo)) {
                $lbillno++;
                $billNo = $secondarySalesPrefix . str_pad((string) ($lbillno + 1), $salesLen, '0', STR_PAD_LEFT);
            }
            [, $taxPerc] = $this->fetchSalesTypePrefixAndTax($billType);
            return [$billNo, $taxPerc];
        }

        if ($billTypewiseBillNo && $seb === 'B') {
            [$prefixBt, $taxPercBt] = $this->fetchSalesTypePrefixAndTax($billType);
            if ($prefixBt !== '') {
                $lbillno = $this->readGeneraliCounter('SALES' . $prefixBt);
                $salesLen = $this->salesBillNumberLength();
                $btBillNo = $prefixBt . str_pad((string) ($lbillno + 1), $salesLen, '0', STR_PAD_LEFT);
                while ($this->billNoExistsInPrimaryStore($btBillNo)) {
                    $lbillno++;
                    $btBillNo = $prefixBt . str_pad((string) ($lbillno + 1), $salesLen, '0', STR_PAD_LEFT);
                }
                return [$btBillNo, $taxPercBt];
            }
        }

        if ($stype === 'T') {
            $salesLen = $this->salesBillNumberLength();
            $billNo = $seb === 'B'
                ? ('TSB/' . str_pad((string) ($lbillno + 1), $salesLen, '0', STR_PAD_LEFT))
                : ('TSE/' . str_pad((string) ($lbillno + 1), $salesLen, '0', STR_PAD_LEFT));
        } else {
            if ($isSecondaryDatabase && $seb === 'B' && !$billTypewiseBillNo) {
                [$prefCode, $lenCode, $defPref] = ['SBPREF', 'SBLEN', 'S/'];
            } else {
                [$prefCode, $lenCode, $defPref] = $seb === 'B'
                    ? ['SBPREF', 'SBLEN', 'SL/']
                    : ['SEPREF', 'SELEN', 'SLE/'];
            }
            $prefix = $this->readGeneralsValue($prefCode, $defPref);
            $len = (int) $this->toNum($this->readGeneralsValue($lenCode, '5'));
            if ($len <= 0) {
                $len = $this->salesBillNumberLength();
            }
            $billNo = $prefix . str_pad((string) ($lbillno + 1), $len, '0', STR_PAD_LEFT);
            while ($this->billNoExistsInPrimaryStore($billNo)) {
                $lbillno++;
                $billNo = $prefix . str_pad((string) ($lbillno + 1), $len, '0', STR_PAD_LEFT);
            }
        }

        if (($seb === 'B' || $taxBillTypeWise) && $taxPerc <= 0) {
            [, $taxPerc] = $this->fetchSalesTypePrefixAndTax($billType);
        }

        return [$billNo, $taxPerc];
    }

    private function reserveNextBillNoPayload(string $billType = 'Gold', string $seb = 'B', bool $quotationMode = false): array
    {
        $settingsPayload = $this->loadSettingsPayload();
        $software = (array) ($settingsPayload['Software'] ?? []);
        $sw = static fn (array $arr, string $key, string $default = ''): string => (string) ($arr[$key] ?? $default);

        $seb = strtoupper(trim($seb));
        if (!in_array($seb, ['B', 'E'], true)) {
            $seb = 'B';
        }
        $billType = trim($billType) !== '' ? trim($billType) : 'Gold';

        $shortPrintOnly = strtoupper(trim($sw($software, 'ShortPrintOnly', 'N'))) === 'Y';
        $billTypewiseBillNo = strtoupper(trim($sw($software, 'BillTypewiseBillNo', 'N'))) === 'Y';
        $taxBillTypeWise = strtoupper(trim($sw($software, 'TaxBillTypeWise', 'N'))) === 'Y';
        $isSecondaryDatabase = $this->isSecondaryCompanyDatabase();
        $secondaryTxnSeriesSeparate = strtoupper(trim($sw($software, 'SecondaryTransactionSeriesSeperate', 'N'))) === 'Y';
        $secondarySalesPrefix = trim($sw($software, 'SecSalesSeriesPrefix', '')) ?: 'S/';
        $useSecondarySalesPrefix = $isSecondaryDatabase && $secondaryTxnSeriesSeparate && $seb === 'B';

        $stype = ($shortPrintOnly && $seb === 'E') ? 'T' : 'S';
        $genCode = ($stype === 'T') ? ('TSALES' . $seb) : ('SALES' . $seb);
        $taxPerc = 0.0;

        if ($quotationMode && $seb === 'E') {
            $next = $this->reserveGeneraliCounterValue($genCode);
            $billNo = (string) $next;
            while ($this->billNoExistsInPrimaryStore($billNo)) {
                $next = $this->reserveGeneraliCounterValue($genCode);
                $billNo = (string) $next;
            }

            return [$billNo, $taxPerc];
        }

        if ($useSecondarySalesPrefix) {
            $salesLen = $this->salesBillNumberLength();
            $next = $this->reserveGeneraliCounterValue($genCode);
            $billNo = $secondarySalesPrefix . str_pad((string) $next, $salesLen, '0', STR_PAD_LEFT);
            while ($this->billNoExistsInPrimaryStore($billNo)) {
                $next = $this->reserveGeneraliCounterValue($genCode);
                $billNo = $secondarySalesPrefix . str_pad((string) $next, $salesLen, '0', STR_PAD_LEFT);
            }
            [, $taxPerc] = $this->fetchSalesTypePrefixAndTax($billType);
            return [$billNo, $taxPerc];
        }

        if ($billTypewiseBillNo && $seb === 'B') {
            [$prefixBt, $taxPercBt] = $this->fetchSalesTypePrefixAndTax($billType);
            if ($prefixBt !== '') {
                $salesLen = $this->salesBillNumberLength();
                $counterCode = 'SALES' . $prefixBt;
                $next = $this->reserveGeneraliCounterValue($counterCode);
                $btBillNo = $prefixBt . str_pad((string) $next, $salesLen, '0', STR_PAD_LEFT);
                while ($this->billNoExistsInPrimaryStore($btBillNo)) {
                    $next = $this->reserveGeneraliCounterValue($counterCode);
                    $btBillNo = $prefixBt . str_pad((string) $next, $salesLen, '0', STR_PAD_LEFT);
                }
                return [$btBillNo, $taxPercBt];
            }
        }

        if ($stype === 'T') {
            $salesLen = $this->salesBillNumberLength();
            $next = $this->reserveGeneraliCounterValue($genCode);
            $billNo = $seb === 'B'
                ? ('TSB/' . str_pad((string) $next, $salesLen, '0', STR_PAD_LEFT))
                : ('TSE/' . str_pad((string) $next, $salesLen, '0', STR_PAD_LEFT));
            while ($this->billNoExistsInPrimaryStore($billNo)) {
                $next = $this->reserveGeneraliCounterValue($genCode);
                $billNo = $seb === 'B'
                    ? ('TSB/' . str_pad((string) $next, $salesLen, '0', STR_PAD_LEFT))
                    : ('TSE/' . str_pad((string) $next, $salesLen, '0', STR_PAD_LEFT));
            }
        } else {
            if ($isSecondaryDatabase && $seb === 'B' && !$billTypewiseBillNo) {
                [$prefCode, $lenCode, $defPref] = ['SBPREF', 'SBLEN', 'S/'];
            } else {
                [$prefCode, $lenCode, $defPref] = $seb === 'B'
                    ? ['SBPREF', 'SBLEN', 'SL/']
                    : ['SEPREF', 'SELEN', 'SLE/'];
            }
            $prefix = $this->readGeneralsValue($prefCode, $defPref);
            $len = (int) $this->toNum($this->readGeneralsValue($lenCode, '5'));
            if ($len <= 0) {
                $len = $this->salesBillNumberLength();
            }
            $next = $this->reserveGeneraliCounterValue($genCode);
            $billNo = $prefix . str_pad((string) $next, $len, '0', STR_PAD_LEFT);
            while ($this->billNoExistsInPrimaryStore($billNo)) {
                $next = $this->reserveGeneraliCounterValue($genCode);
                $billNo = $prefix . str_pad((string) $next, $len, '0', STR_PAD_LEFT);
            }
        }

        if (($seb === 'B' || $taxBillTypeWise) && $taxPerc <= 0) {
            [, $taxPerc] = $this->fetchSalesTypePrefixAndTax($billType);
        }

        return [$billNo, $taxPerc];
    }

    private function syncLegacySalesTables(array $payload, array $calc, array $items, ?string $billDate, int $billControl = 1): ?int
    {
        if (!$this->hasTable('salesm') || !$this->hasTable('salesd')) {
            return null;
        }

        $billNo = trim((string) ($payload['bill_no'] ?? ''));
        if ($billNo === '') {
            return null;
        }

        $this->ensureLegacyBillNoCapacity('salesm', $billNo);

        $extra = (array) ($calc['extra'] ?? []);
        $existing = DB::table('salesm')->where('billno', $billNo)->first();
        $slno = $existing ? (int) ($existing->slno ?? 0) : $this->reserveGlobalSerialNo();
        if ($slno <= 0) {
            $slno = 1;
        }

        $taxPerc = $this->toNum($payload['extra']['tax_perc'] ?? $payload['extra']['taxperc'] ?? 0);
        $isCredit = $this->toBool($extra['credit'] ?? false);
        $isCst = $this->toBool($payload['extra']['is_cst'] ?? false);
        $taxSplit = $this->salesTaxSplitFromAmount(round($this->toNum($extra['tax'] ?? 0), 2), $isCst);
        $addBc = $this->toBool($extra['add_bank_charge'] ?? false);
        $sqlTime = $this->toSqlTime((string) ($payload['bill_time'] ?? ''));
        $cbcode = trim((string) ($payload['cashbank_code'] ?? ''));
        if ($cbcode === '' || strtoupper($cbcode) === 'CASH IN HAND') {
            $cbcode = 'CASH';
        }
        $row = [
            'slno' => $slno,
            'billno' => $billNo,
            'tdate' => $billDate ?: now()->toDateString(),
            'ttime' => $sqlTime,
            'custcode' => mb_substr(trim((string) ($payload['customer_code'] ?? '')), 0, 8),
            'custname' => mb_substr(trim((string) ($payload['customer_name'] ?? '')), 0, 30),
            'billamt' => round($this->toNum($calc['bill_total'] ?? 0), 2),
            'eamt' => round($this->toNum($calc['exchange_amount'] ?? 0), 2),
            'sretamt' => round($this->toNum($calc['return_amount'] ?? 0), 2),
            'staxperc' => round($taxPerc, 3),
            'staxamt' => $taxSplit['total'],
            'sgst' => $taxSplit['sgst'],
            'cgst' => $taxSplit['cgst'],
            'igst' => $taxSplit['igst'],
            'discount' => round($this->toNum($extra['discount'] ?? 0), 2),
            'discperc' => round($this->toNum($extra['discount_perc'] ?? 0), 3),
            'ramt' => round($this->toNum($extra['received'] ?? 0), 2),
            'grate' => round($this->toNum($payload['rate_per_gm'] ?? 0), 2),
            'sr' => 'S',
            'status' => 1,
            'control' => $billControl,
            'smcode' => trim((string) ($payload['salesman_code'] ?? '')),
            'ob' => round($this->toNum($extra['opening_balance'] ?? 0), 2),
            'netamt' => round($this->toNum($calc['net_total'] ?? 0), 2),
            'advance' => round($this->toNum($extra['advance'] ?? 0), 2),
            'astamt' => round($this->toNum($extra['ast'] ?? 0), 2),
            'astperc' => round($this->toNum($payload['extra']['ast_perc'] ?? 0), 3),
            'duedate' => $this->parseDate((string) ($payload['due_date'] ?? ''), nullable: true),
            'billtype' => mb_substr(trim((string) ($payload['bill_type'] ?? '')), 0, 5),
            'counter' => mb_substr(trim((string) ($payload['counter_code'] ?? '')), 0, 5),
            'cocode' => mb_substr(trim((string) ($payload['co_party_code'] ?? '')), 0, 8),
            'cbcode' => mb_substr($cbcode, 0, 10),
            'addr' => mb_substr(trim((string) ($payload['address'] ?? '')), 0, 60),
            'note' => mb_substr(trim((string) ($extra['note'] ?? '')), 0, 40),
            'bcharge' => round($this->toNum($extra['bank_charge'] ?? 0), 2),
            'addbcharge' => $addBc ? 'Y' : 'N',
            'ccamt' => round($this->toNum($extra['cc_amt'] ?? 0), 2),
            'hmc' => round($this->toNum($extra['hallmark_charge'] ?? 0), 2),
            'rcamt' => round($this->toNum($extra['repair_charge'] ?? 0), 2),
            'loan' => $isCredit ? 'Y' : 'N',
            'cst' => $isCst ? 'Y' : 'N',
            'mobno' => mb_substr(trim((string) ($payload['mobile'] ?? '')), 0, 25),
            'agcode' => mb_substr(trim((string) ($payload['agent_code'] ?? '')), 0, 10),
            'chqno' => mb_substr(trim((string) ($extra['chq_no'] ?? '')), 0, 20),
            'chqdate' => $this->parseDate((string) ($extra['chq_date'] ?? ''), nullable: true),
            'chqamt' => round($this->toNum($extra['chq_amt'] ?? 0), 2),
            'chqbank' => mb_substr(trim((string) ($extra['chq_bank'] ?? '')), 0, 10),
            'chqpdc' => $this->toBool($extra['chq_pdc'] ?? false) ? 'Y' : 'N',
            'placeos' => mb_substr(trim((string) ($payload['supply_place'] ?? '')), 0, 60),
            'fancyamt' => round($this->toNum($extra['fancy_amt'] ?? 0), 2),
            'schmamt' => round($this->toNum($extra['scheme_amt'] ?? 0), 2),
            'pan' => mb_substr(trim((string) ($payload['pan_no'] ?? '')), 0, 20),
            'tin' => mb_substr(trim((string) ($payload['gst_no'] ?? '')), 0, 20),
            'statecode' => mb_substr(trim((string) ($payload['state_code'] ?? '')), 0, 10),
            'redmpoints' => round($this->toNum($extra['redeem_points'] ?? 0), 2),
            'bcperc' => round($this->toNum($extra['bc_perc'] ?? 0), 3),
            'approvedby' => mb_substr(trim((string) ($payload['approved_by'] ?? '')), 0, 10),
            'ccpdc' => $this->toBool($extra['cc_pdc'] ?? false) ? 'Y' : 'N',
            'tcsperc' => round($this->toNum($extra['tcs_perc'] ?? 0), 3),
            'tcsamt' => round($this->toNum($extra['tcs_amt'] ?? 0), 2),
            'vehno' => mb_substr(trim((string) ($payload['vehicle_no'] ?? '')), 0, 15),
            'distance' => (int) $this->toNum($payload['distance'] ?? 0),
        ];
        $row = $this->fitLegacyRowToSchema('salesm', $row, ['billno']);

        DB::transaction(function () use ($existing, $row, $slno, $items, $payload, $calc, $sqlTime, $billControl) {
            $prevControl = (int) ($existing->control ?? 1);
            $billNo = trim((string) ($payload['bill_no'] ?? ''));
            if ($existing) {
                $this->reverseLegacyInventoryEffects($slno, $prevControl);
            }

            if ($existing) {
                DB::table('salesm')->where('slno', $slno)->update($row);
            } else {
                DB::table('salesm')->insert($row);
            }

            DB::table('salesd')->where('slno', $slno)->delete();

            $insRows = [];
            $sno = 1;
            $salesdCols = $this->getColumns('salesd');
            foreach ($items as $it) {
                $code = trim((string) ($it['item_code'] ?? ''));
                if ($code === '') {
                    continue;
                }
                $salesdRow = [
                    'slno' => $slno,
                    'sno' => $sno++,
                    'code' => $code,
                    'name' => trim((string) ($it['item_name'] ?? '')),
                    'qty' => $this->toNum($it['qty'] ?? 0),
                    'weight' => $this->toNum($it['weight'] ?? 0),
                    'stonewgt' => $this->toNum($it['stone_wgt'] ?? 0),
                    'stoneprice' => $this->toNum($it['stone_price'] ?? 0),
                    'rate' => $this->toNum($it['rate'] ?? 0),
                    'mcharge' => $this->toNum($it['making_charge'] ?? 0),
                    'wastage' => $this->toNum($it['wastage'] ?? 0),
                    'amount' => round($this->toNum($it['amount'] ?? 0), 2),
                    'model' => trim((string) ($it['model'] ?? '')),
                    'note' => trim((string) ($it['note'] ?? '')),
                    'huid' => trim((string) ($it['huid'] ?? '')),
                    'iqtype' => trim((string) ($it['qtype'] ?? $it['iqtype'] ?? '')),
                    'stktype' => trim((string) ($it['stktype'] ?? '')),
                    'stktouch' => $this->toNum($it['stktouch'] ?? 0),
                    'vaperc' => $this->toNum($it['vaperc'] ?? 0),
                    'bcode' => (int) $this->toNum($it['bcode'] ?? 0),
                ];
                if (in_array('va_disc_perc', $salesdCols, true)) {
                    $salesdRow['va_disc_perc'] = $this->toNum($it['va_disc_perc'] ?? 0);
                }
                if (in_array('va_disc_amt', $salesdCols, true)) {
                    $salesdRow['va_disc_amt'] = $this->toNum($it['va_disc_amt'] ?? 0);
                }
                if (in_array('vadiscperc', $salesdCols, true)) {
                    $salesdRow['vadiscperc'] = $this->toNum($it['va_disc_perc'] ?? 0);
                }
                if (in_array('vadiscamt', $salesdCols, true)) {
                    $salesdRow['vadiscamt'] = $this->toNum($it['va_disc_amt'] ?? 0);
                }
                $insRows[] = $salesdRow;
            }
            if (!empty($insRows)) {
                DB::table('salesd')->insert($insRows);
            }

            // Clean any older linked exchange / return rows for the same bill number.
            // This prevents duplicate GO/PB history lines when a prior save created rows under a different slno.
            if ($billNo !== '') {
                $this->cleanupLegacySalesLinkedRowsByBillNo($billNo, $slno);
            }

            // keep dependent legacy tables in sync for same slno
            foreach (['purchasem', 'purchased', 'salesrm', 'salesrd', 'daybook', 'daybookpart', 'daybookratewgt', 'spdmddet', 'stkandprofit', 'oglist', 'kuricolln', 'pdclist'] as $tbl) {
                if ($this->hasTable($tbl)) {
                    DB::table($tbl)->where('slno', $slno)->delete();
                }
            }

            if ($billControl !== 1) {
                return;
            }

            $extra = (array) ($calc['extra'] ?? []);
            $billNo = trim((string) ($payload['bill_no'] ?? ''));
            $billDate = trim((string) ($payload['bill_date'] ?? ''));
            $billDateSql = $this->parseDate($billDate) ?: now()->toDateString();
            $billTime = $sqlTime;
            $custCode = trim((string) ($payload['customer_code'] ?? ''));
            $custName = trim((string) ($payload['customer_name'] ?? ''));
            $addr = trim((string) ($payload['address'] ?? ''));
            $counter = trim((string) ($payload['counter_code'] ?? ''));
            $smCode = trim((string) ($payload['salesman_code'] ?? ''));
            $billType = trim((string) ($payload['bill_type'] ?? ''));
            $taxPerc = $this->toNum($payload['extra']['tax_perc'] ?? $payload['extra']['taxperc'] ?? 0);
            $taxAmt = $this->toNum($extra['tax'] ?? 0);
            $astAmt = $this->toNum($extra['ast'] ?? 0);
            $disc = $this->toNum($extra['discount'] ?? 0);
            $rate = $this->toNum($payload['rate_per_gm'] ?? 0);
            $netTotal = $this->toNum($calc['net_total'] ?? 0);
            $exAmt = $this->toNum($calc['exchange_amount'] ?? 0);
            $retAmt = $this->toNum($calc['return_amount'] ?? 0);
            $srTaxPerc = $this->toNum($calc['extra']['sr_tax_perc'] ?? $payload['sr_tax_perc'] ?? 0);
            $srTaxAmt = $this->toNum($calc['extra']['sr_tax_amt'] ?? $payload['sr_tax_amt'] ?? 0);
            $srCessPerc = $this->toNum($calc['extra']['sr_cess_perc'] ?? $payload['sr_cess_perc'] ?? 0);
            $srCessAmt = $this->toNum($calc['extra']['sr_cess_amt'] ?? $payload['sr_cess_amt'] ?? 0);
            $srDiscountAmt = $this->toNum($calc['extra']['sr_discount_amt'] ?? $payload['sr_discount_amt'] ?? 0);
            $srBillAmt = round($retAmt + $srDiscountAmt - $srTaxAmt - $srCessAmt, 2);
            $rcvd = $this->toNum($extra['received'] ?? 0);
            $isCst = $this->toBool($payload['extra']['is_cst'] ?? false) ? 'Y' : 'N';
            $control = $billControl;

            $exchange = collect($payload['exchange'] ?? [])->filter(fn($r) => trim((string)($r['item_code'] ?? '')) !== '')->values();
            $salesReturn = collect($payload['sales_return'] ?? [])->filter(fn($r) => trim((string)($r['item_code'] ?? '')) !== '')->values();

            // purchasem + purchased (exchange)
            if ($exAmt > 0 && $this->hasTable('purchasem')) {
                DB::table('purchasem')->insert([
                    'slno' => $slno,
                    'tdate' => $billDateSql,
                    'ttime' => $billTime,
                    'billno' => $billNo,
                    'docno' => $billNo,
                    'suppcode' => $custCode,
                    'name' => $custName,
                    'billamt' => round($exAmt, 2),
                    'pamt' => round($exAmt, 2),
                    'status' => 1,
                    'pr' => 'E',
                    'eamt' => 0,
                    'control' => $control,
                    'rate' => $rate,
                    'smcode' => $smCode,
                    'netamt' => round($exAmt, 2),
                    'taxamt' => 0,
                    'taxperc' => 0,
                    'discount' => 0,
                    'billtype' => $billType,
                    'astamt' => 0,
                    'ic' => '',
                    'fr' => 'N',
                    'counter' => $counter,
                    'addr' => $addr,
                    'cst' => $isCst,
                    'tcsperc' => 0,
                    'tcsamt' => 0,
                    'hmc' => 0,
                ]);
            }
            if ($exchange->isNotEmpty() && $this->hasTable('purchased')) {
                $sno = 1;
                $rows = [];
                foreach ($exchange as $exr) {
                    $rows[] = [
                        'slno' => $slno,
                        'code' => trim((string)($exr['item_code'] ?? '')),
                        'name' => trim((string)($exr['item_name'] ?? '')),
                        'qty' => (int) $this->toNum($exr['qty'] ?? 0),
                        'rate' => $this->toNum($exr['rate'] ?? 0),
                        'weight' => $this->toNum($exr['weight'] ?? 0),
                        'lesswgt' => $this->toNum($exr['less_wgt'] ?? 0),
                        'lessperc' => $this->toNum($exr['less_perc'] ?? 0),
                        'amount' => round($this->toNum($exr['amount'] ?? 0), 2),
                        'cost' => $this->toNum($exr['cost'] ?? 0),
                        'stwgt' => $this->toNum($exr['stone_wgt'] ?? 0),
                        'stprice' => $this->toNum($exr['stone_price'] ?? 0),
                        'mud' => $this->toNum($exr['mud_less'] ?? 0),
                        'sno' => $sno++,
                        'fr' => (int) $this->toNum($exr['fr'] ?? 0),
                        'mark' => trim((string)($exr['stkfd'] ?? '')),
                        'stktype' => trim((string)($exr['stktype'] ?? '')),
                        'iqtype' => trim((string)($exr['qtype'] ?? '')),
                        'stktouch' => $this->toNum($exr['stktouch'] ?? 0),
                        'bcode' => (int) $this->toNum($exr['bcode'] ?? 0),
                        'dmdamt' => $this->toNum($exr['dmdamt'] ?? 0),
                        'dmdwgt' => $this->toNum($exr['dmdwgt'] ?? 0),
                        'mcharge' => $this->toNum($exr['making_charge'] ?? 0),
                        'mperc' => $this->toNum($exr['mc_perc'] ?? 0),
                        'wastage' => $this->toNum($exr['wastage'] ?? 0),
                        'rate2' => $this->toNum($exr['rate2'] ?? $exr['extra_rate'] ?? 0),
                        'batch' => trim((string)($exr['batch'] ?? '')),
                    ];
                }
                DB::table('purchased')->insert($rows);
            }

            // salesrm + salesrd (sales return)
            if ($retAmt > 0 && $this->hasTable('salesrm')) {
                $salesReturnMasterRow = [
                    'slno' => $slno,
                    'billno' => $billNo,
                    'tdate' => $billDateSql,
                    'ttime' => $billTime,
                    'custcode' => $custCode,
                    'custname' => $custName,
                    'billamt' => $srBillAmt,
                    'pamt' => round($retAmt, 2),
                    'grate' => $rate,
                    'status' => 1,
                    'sr' => 'E',
                    'control' => $control,
                    'smcode' => $smCode,
                    'netamt' => round($retAmt, 2),
                    'discount' => round($srDiscountAmt, 2),
                    'staxperc' => round($srTaxPerc, 3),
                    'staxamt' => round($srTaxAmt, 2),
                    'astperc' => round($srCessPerc, 3),
                    'astamt' => round($srCessAmt, 2),
                    'billtype' => $billType,
                    'cst' => $isCst,
                ];
                DB::table('salesrm')->insert($this->fitLegacyRowToSchema('salesrm', $salesReturnMasterRow, ['billno']));
            }
            if ($salesReturn->isNotEmpty() && $this->hasTable('salesrd')) {
                $sno = 1;
                $rows = [];
                foreach ($salesReturn as $srr) {
                    $rows[] = [
                        'slno' => $slno,
                        'code' => trim((string)($srr['item_code'] ?? '')),
                        'name' => trim((string)($srr['item_name'] ?? '')),
                        'qty' => (int) $this->toNum($srr['qty'] ?? 0),
                        'weight' => $this->toNum($srr['weight'] ?? 0),
                        'rate' => $this->toNum($srr['rate'] ?? 0),
                        'stonewgt' => $this->toNum($srr['stone_wgt'] ?? 0),
                        'stoneprice' => $this->toNum($srr['stone_price'] ?? 0),
                        'mcharge' => $this->toNum($srr['making_charge'] ?? 0),
                        'wastage' => $this->toNum($srr['wastage'] ?? 0),
                        'amount' => round($this->toNum($srr['amount'] ?? 0), 2),
                        'sno' => $sno++,
                        'stktype' => trim((string)($srr['stktype'] ?? '')),
                        'iqtype' => trim((string)($srr['qtype'] ?? '')),
                        'stktouch' => $this->toNum($srr['stktouch'] ?? 0),
                        'bcode' => (int) $this->toNum($srr['bcode'] ?? 0),
                        'dmdwgt' => $this->toNum($srr['dmdwgt'] ?? 0),
                        'vaperc' => $this->toNum($srr['vaperc'] ?? 0),
                        'note' => trim((string)($srr['note'] ?? '')),
                    ];
                }
                DB::table('salesrd')->insert($rows);
            }

            // daybookpart + daybook minimal financial mirror
            if ($this->hasTable('daybookpart')) {
                DB::table('daybookpart')->insert([
                    'slno' => $slno,
                    'particular' => mb_substr('By Sales (' . $billNo . ') To ' . $custName, 0, 100),
                    'vchno' => '',
                    'ic' => '',
                    'uid' => '',
                    'ttime' => $billTime,
                    'rate' => $rate,
                ]);
            }
            $this->insertLegacyDaybookEntries(
                $slno,
                $payload,
                $calc,
                $items,
                $exchange->all(),
                $billDateSql,
                $control
            );

            if ($this->hasTable('daybookratewgt') && $custCode !== '' && $rate > 0) {
                $balance = $this->toNum($extra['balance'] ?? 0);
                if ($balance != 0) {
                    DB::table('daybookratewgt')->insert([
                        'slno' => $slno,
                        'rate' => $rate,
                        'mcp' => 0,
                        'wgt' => round(-($balance / $rate), 3),
                        'code' => $custCode,
                        'tdate' => $billDateSql,
                        'control' => $control,
                    ]);
                }
            }

            if ($this->hasTable('spdmddet')) {
                $sno = 1;
                foreach ($items as $it) {
                    $dw = $this->toNum($it['dmdwgt'] ?? 0);
                    $da = $this->toNum($it['dmdamt'] ?? 0);
                    $dn = (int) $this->toNum($it['dmdnos'] ?? 0);
                    if ($dw <= 0 && $da <= 0 && $dn <= 0) {
                        $sno++;
                        continue;
                    }
                    DB::table('spdmddet')->insert([
                        'slno' => $slno,
                        'sno' => $sno++,
                        'dmdwgt' => $dw,
                        'dmdunit' => trim((string) ($it['dmdunit'] ?? '')),
                        'dmdamt' => $da,
                        'dmdnos' => $dn,
                        'brand' => '',
                        'purity' => '',
                        'centrate' => 0,
                    ]);
                }
            }

            if ($this->hasTable('stkandprofit')) {
                try {
                    DB::table('stkandprofit')->insert([
                        'slno' => $slno,
                        'tdate' => $billDateSql,
                        'stkvalue' => -round($this->toNum($calc['bill_total'] ?? 0), 2),
                        'profit' => round(($this->toNum($calc['bill_total'] ?? 0) - $disc + $taxAmt + $astAmt), 2),
                        'note' => 'Sales',
                        'control' => $control,
                        'docno' => $billNo,
                    ]);
                } catch (\Throwable $e) {
                    // stkvalue column too narrow — widen via Administration > SQL Updt
                    \Log::warning('stkandprofit insert skipped: ' . $e->getMessage());
                }
            }

            $this->applyLegacyInventoryEffects(
                $items,
                $exchange->all(),
                $salesReturn->all(),
                $slno,
                $control,
                $billDateSql
            );
        });

        return $slno;
    }

    private function cleanupLegacySalesLinkedRowsByBillNo(string $billNo, int $currentSlno): void
    {
        $billNo = trim($billNo);
        if ($billNo === '') {
            return;
        }

        $linkedSlnos = collect();

        if ($this->hasTable('purchasem')) {
            $linkedSlnos = $linkedSlnos->merge(
                DB::table('purchasem')
                    ->where(function ($query) use ($billNo) {
                        $query->where('billno', $billNo)->orWhere('docno', $billNo);
                    })
                    ->when(Schema::hasColumn('purchasem', 'pr'), fn ($query) => $query->where('pr', 'E'))
                    ->pluck('slno')
            );
        }

        if ($this->hasTable('salesrm')) {
            $linkedSlnos = $linkedSlnos->merge(
                DB::table('salesrm')
                    ->where('billno', $billNo)
                    ->when(Schema::hasColumn('salesrm', 'sr'), fn ($query) => $query->where('sr', 'E'))
                    ->pluck('slno')
            );
        }

        $staleSlnos = $linkedSlnos
            ->filter(fn ($value) => (int) $value > 0)
            ->map(fn ($value) => (int) $value)
            ->reject(fn (int $value) => $value === $currentSlno)
            ->unique()
            ->values()
            ->all();

        if ($staleSlnos === []) {
            return;
        }

        foreach (['purchasem', 'purchased', 'salesrm', 'salesrd', 'daybook', 'daybookpart', 'daybookratewgt', 'spdmddet', 'stkandprofit', 'oglist', 'kuricolln', 'pdclist'] as $tbl) {
            if ($this->hasTable($tbl)) {
                DB::table($tbl)->whereIn('slno', $staleSlnos)->delete();
            }
        }
    }

    private function reverseLegacyInventoryEffects(int $slno, int $control): void
    {
        if ($slno <= 0) {
            return;
        }

        if ($this->hasTable('salesd')) {
            $rows = DB::table('salesd')
                ->where('slno', $slno)
                ->get(['code', 'qty', 'weight', 'stonewgt', 'stktype', 'bcode']);
            foreach ($rows as $r) {
                $this->adjustItemStock(
                    trim((string) ($r->code ?? '')),
                    $this->toNum($r->qty ?? 0),
                    $this->toNum($r->weight ?? 0),
                    $this->toNum($r->stonewgt ?? 0),
                    trim((string) ($r->stktype ?? '')),
                    $control
                );
                $bcode = (int) $this->toNum($r->bcode ?? 0);
                if ($bcode > 0 && $this->hasTable('barcode')) {
                    DB::table('barcode')->where('bcode', $bcode)->update(['stk' => 'Y']);
                }
            }
        }

        if ($this->hasTable('purchased')) {
            $rows = DB::table('purchased')
                ->where('slno', $slno)
                ->get(['code', 'qty', 'weight', 'stwgt', 'stktype']);
            foreach ($rows as $r) {
                $this->adjustItemStock(
                    trim((string) ($r->code ?? '')),
                    -$this->toNum($r->qty ?? 0),
                    -$this->toNum($r->weight ?? 0),
                    -$this->toNum($r->stwgt ?? 0),
                    trim((string) ($r->stktype ?? '')),
                    $control
                );
            }
        }

        if ($this->hasTable('salesrd')) {
            $rows = DB::table('salesrd')
                ->where('slno', $slno)
                ->get(['code', 'qty', 'weight', 'stonewgt', 'stktype']);
            foreach ($rows as $r) {
                $this->adjustItemStock(
                    trim((string) ($r->code ?? '')),
                    -$this->toNum($r->qty ?? 0),
                    -$this->toNum($r->weight ?? 0),
                    -$this->toNum($r->stonewgt ?? 0),
                    trim((string) ($r->stktype ?? '')),
                    $control
                );
            }
        }
    }

    private function deleteLegacySalesBundle(int $slno): void
    {
        if ($slno <= 0) {
            return;
        }

        foreach (['salesd', 'purchasem', 'purchased', 'salesrm', 'salesrd', 'daybook', 'daybookpart', 'daybookratewgt', 'spdmddet', 'stkandprofit', 'oglist', 'kuricolln', 'pdclist'] as $tbl) {
            if ($this->hasTable($tbl)) {
                DB::table($tbl)->where('slno', $slno)->delete();
            }
        }

        if ($this->hasTable('salesm')) {
            DB::table('salesm')->where('slno', $slno)->delete();
        }
    }

    private function applyLegacyInventoryEffects(array $items, array $exchange, array $salesReturn, int $slno, int $control, string $billDateSql): void
    {
        foreach ($items as $it) {
            $code = trim((string) ($it['item_code'] ?? ''));
            if ($code === '') {
                continue;
            }
            $this->adjustItemStock(
                $code,
                -$this->toNum($it['qty'] ?? 0),
                -$this->toNum($it['weight'] ?? 0),
                -$this->toNum($it['stone_wgt'] ?? 0),
                trim((string) ($it['stktype'] ?? '')),
                $control
            );

            $bcode = (int) $this->toNum($it['bcode'] ?? 0);
            if ($bcode > 0 && $this->hasTable('barcode')) {
                DB::table('barcode')->where('bcode', $bcode)->update([
                    'stk' => 'N',
                    'islno' => $slno,
                    'sdate' => $billDateSql,
                ]);
            }
        }

        foreach ($exchange as $ex) {
            $code = trim((string) ($ex['item_code'] ?? ''));
            if ($code === '') {
                continue;
            }
            $this->adjustItemStock(
                $code,
                $this->toNum($ex['qty'] ?? 0),
                $this->toNum($ex['weight'] ?? 0),
                -$this->toNum($ex['stone_wgt'] ?? 0),
                trim((string) ($ex['stktype'] ?? '')),
                $control
            );
        }

        foreach ($salesReturn as $sr) {
            $code = trim((string) ($sr['item_code'] ?? ''));
            if ($code === '') {
                continue;
            }
            $this->adjustItemStock(
                $code,
                $this->toNum($sr['qty'] ?? 0),
                $this->toNum($sr['weight'] ?? 0),
                $this->toNum($sr['stone_wgt'] ?? 0),
                trim((string) ($sr['stktype'] ?? '')),
                $control
            );

            $bcode = (int) $this->toNum($sr['bcode'] ?? 0);
            if ($bcode > 0 && $this->hasTable('barcode')) {
                DB::table('barcode')->where('bcode', $bcode)->update([
                    'stk' => 'Y',
                    'rslno' => $slno,
                ]);
            }
        }
    }

    private function adjustItemStock(string $code, float $qtyDelta, float $weightDelta, float $stoneDelta, string $stkType, int $control): void
    {
        $code = trim($code);
        if ($code === '' || !$this->hasTable('items')) {
            return;
        }

        $itemCols = $this->getColumns('items');
        $updates = [];

        if (in_array('qtyb', $itemCols, true)) {
            $updates['qtyb'] = DB::raw('COALESCE(qtyb,0) + ' . $qtyDelta);
        }
        if (in_array('weightb', $itemCols, true)) {
            $updates['weightb'] = DB::raw('COALESCE(weightb,0) + ' . $weightDelta);
        }
        if (in_array('stonewgtb', $itemCols, true)) {
            $updates['stonewgtb'] = DB::raw('COALESCE(stonewgtb,0) + ' . $stoneDelta);
        }
        if ($control === 1) {
            if (in_array('qty', $itemCols, true)) {
                $updates['qty'] = DB::raw('COALESCE(qty,0) + ' . $qtyDelta);
            }
            if (in_array('weight', $itemCols, true)) {
                $updates['weight'] = DB::raw('COALESCE(weight,0) + ' . $weightDelta);
            }
            if (in_array('stonewgt', $itemCols, true)) {
                $updates['stonewgt'] = DB::raw('COALESCE(stonewgt,0) + ' . $stoneDelta);
            }
        }
        if ($updates !== []) {
            DB::table('items')->where('code', $code)->update($updates);
        }

        if ($stkType === '' || !$this->hasTable('itemsstk')) {
            return;
        }

        $stkCols = $this->getColumns('itemsstk');
        if (!in_array('code', $stkCols, true) || !in_array('stktype', $stkCols, true)) {
            return;
        }

        $exists = DB::table('itemsstk')->where('code', $code)->where('stktype', $stkType)->exists();
        if (!$exists) {
            DB::table('itemsstk')->insert([
                'code' => $code,
                'stktype' => $stkType,
            ]);
        }

        $updates = [];
        if (in_array('qtyb', $stkCols, true)) {
            $updates['qtyb'] = DB::raw('COALESCE(qtyb,0) + ' . $qtyDelta);
        }
        if (in_array('weightb', $stkCols, true)) {
            $updates['weightb'] = DB::raw('COALESCE(weightb,0) + ' . $weightDelta);
        }
        if (in_array('stonewgtb', $stkCols, true)) {
            $updates['stonewgtb'] = DB::raw('COALESCE(stonewgtb,0) + ' . $stoneDelta);
        }
        if ($control === 1) {
            if (in_array('qty', $stkCols, true)) {
                $updates['qty'] = DB::raw('COALESCE(qty,0) + ' . $qtyDelta);
            }
            if (in_array('weight', $stkCols, true)) {
                $updates['weight'] = DB::raw('COALESCE(weight,0) + ' . $weightDelta);
            }
            if (in_array('stonewgt', $stkCols, true)) {
                $updates['stonewgt'] = DB::raw('COALESCE(stonewgt,0) + ' . $stoneDelta);
            }
        }
        if ($updates !== []) {
            DB::table('itemsstk')->where('code', $code)->where('stktype', $stkType)->update($updates);
        }
    }

    private function insertLegacyDaybookEntries(
        int $slno,
        array $payload,
        array $calc,
        array $items,
        array $exchange,
        string $billDateSql,
        int $control
    ): void {
        if (!$this->hasTable('daybook')) {
            return;
        }

        $settingsPayload = $this->loadSettingsPayload();
        $software = (array) ($settingsPayload['Software'] ?? []);
        $sw = static fn (array $arr, string $key, string $default = ''): string => (string) ($arr[$key] ?? $default);
        $flagY = static fn (mixed $v, string $def = 'N'): bool => strtoupper(trim((string) ($v ?? $def))) === 'Y';

        $extra = (array) ($calc['extra'] ?? []);
        $billNo = trim((string) ($payload['bill_no'] ?? ''));
        $custCode = trim((string) ($payload['customer_code'] ?? ''));
        $coPartyCode = trim((string) ($payload['co_party_code'] ?? ''));
        $cbcode = trim((string) ($payload['cashbank_code'] ?? ''));
        if ($cbcode === '' || strtoupper($cbcode) === 'CASH IN HAND') {
            $cbcode = 'CASH';
        }
        if ($coPartyCode === '' && $custCode !== '') {
            if ($this->hasTable('clients')) {
                $coPartyCode = trim((string) (DB::table('clients')->whereRaw('TRIM(code)=?', [$custCode])->value('cocode') ?? ''));
            }
            if ($coPartyCode === '' && $this->hasTable('clients_kuridet')) {
                $coPartyCode = trim((string) (DB::table('clients_kuridet')->where('custlinkac', $custCode)->value('code') ?? ''));
            }
        }
        $chqBank = trim((string) ($extra['chq_bank'] ?? $extra['cheque_bank'] ?? $cbcode));
        if ($chqBank === '') {
            $chqBank = $cbcode;
        }
        $opAcCode = 'RS';
        $billAmt = round($this->toNum($calc['bill_total'] ?? 0), 2);
        $exAmt = round($this->toNum($calc['exchange_amount'] ?? 0), 2);
        $retAmt = round($this->toNum($calc['return_amount'] ?? 0), 2);
        $srTaxAmt = round($this->toNum($extra['sr_tax_amt'] ?? 0), 2);
        $srCessAmt = round($this->toNum($extra['sr_cess_amt'] ?? 0), 2);
        $disc = round($this->toNum($extra['discount'] ?? 0), 2);
        $taxAmt = round($this->toNum($extra['tax'] ?? 0), 2);
        $sgstAmt = round($this->toNum($extra['sgst'] ?? 0), 2);
        $cgstAmt = round($this->toNum($extra['cgst'] ?? 0), 2);
        $igstAmt = round($this->toNum($extra['igst'] ?? 0), 2);
        if ($taxAmt != 0.0 && $sgstAmt == 0.0 && $cgstAmt == 0.0 && $igstAmt == 0.0) {
            $fallbackSplit = $this->salesTaxSplitFromAmount($taxAmt, $this->toBool($payload['extra']['is_cst'] ?? false));
            $sgstAmt = $fallbackSplit['sgst'];
            $cgstAmt = $fallbackSplit['cgst'];
            $igstAmt = $fallbackSplit['igst'];
        }
        $astAmt = round($this->toNum($extra['ast'] ?? 0), 2);
        $tcsAmt = round($this->toNum($extra['tcs_amt'] ?? 0), 2);
        $rcAmt = round($this->toNum($extra['repair_charge'] ?? 0), 2);
        $advAmt = round($this->toNum($extra['advance'] ?? 0), 2);
        $fancyAmt = round($this->toNum($extra['fancy_amt'] ?? 0), 2);
        $schemeAmt = round($this->toNum($extra['scheme_amt'] ?? 0), 2);
        $schemeLedger = strtoupper(trim((string) ($extra['scheme_ledger'] ?? 'APP')));
        if (!in_array($schemeLedger, ['APP', 'SCHMAMT'], true)) {
            $schemeLedger = 'APP';
        }
        $hmcAmt = round($this->toNum($extra['hallmark_charge'] ?? 0), 2);
        $netAmt = round($this->toNum($calc['net_total'] ?? 0), 2);
        $rcvd = round($this->toNum($extra['received'] ?? 0), 2);
        $isCst = $this->toBool($payload['extra']['is_cst'] ?? false);
        $taxSystem = strtoupper(trim($sw($software, 'TaxSystem', 'GST')));
        $vaSepAc = $flagY($sw($software, 'VASepAc', 'N'));
        $addBc = $this->toBool($extra['add_bank_charge'] ?? false);

        $ccAmt = round($this->toNum($extra['cc_amt'] ?? $extra['ccamt'] ?? 0), 2);
        $chqAmt = round($this->toNum($extra['chq_amt'] ?? $extra['cheque_amt'] ?? 0), 2);
        $ptaxAmt = round($this->toNum($extra['ptax'] ?? $extra['ptax_amt'] ?? 0), 2);
        $bcPerc = round($this->toNum($extra['bc_perc'] ?? $extra['bcperc'] ?? 0), 3);
        if ($bcPerc <= 0) {
            $bcPerc = round($this->toNum($this->readGeneraldValue('BANKCOMN', '0')), 3);
        }
        $bcTaxPerc = round($this->toNum($this->readGeneraldValue('BCOMNTAX', '0')), 3);
        $dtva = 0.0;
        foreach ($items as $it) {
            $dtva += $this->toNum($it['making_charge'] ?? 0) + ($this->toNum($it['wastage'] ?? 0) * $this->toNum($it['rate'] ?? 0));
        }

        $hasOgExchange = collect($exchange)->contains(function ($r) {
            return strtoupper(trim((string) ($r['item_code'] ?? ''))) === 'OG';
        });

        $entries = [];
        $add = static function (array &$bucket, string $accode, float $amount, string $op = ''): void {
            $ac = trim($accode);
            $amt = round($amount, 2);
            if ($ac === '' || $amt == 0.0) {
                return;
            }
            $bucket[] = ['accode' => $ac, 'amount' => $amt, 'opaccode' => $op];
        };

        // Receipt split (cash/bank/cc/chq + bank commission) - PB sign convention
        if ($rcvd != 0.0) {
            if ($rcvd > ($ccAmt + $chqAmt) || $rcvd < 0) {
                $dacamt = -($rcvd - $ccAmt - $chqAmt);
                $saccode = ($cbcode !== 'CASH' && ($ccAmt + $chqAmt) == 0.0) ? $cbcode : 'CASH';
                $add($entries, $saccode, $dacamt, $opAcCode);
            }

            if ($ccAmt > 0) {
                $saccode = $this->toBool($extra['cc_pdc'] ?? false) ? 'CNC' : $cbcode;
                $add($entries, $saccode, -$ccAmt, $opAcCode);
            }
            if ($chqAmt > 0) {
                $saccode = $this->toBool($extra['chq_pdc'] ?? false) ? 'CNC' : $chqBank;
                $add($entries, $saccode, -$chqAmt, $opAcCode);
            }

            if ($cbcode !== 'CASH') {
                $baseForComn = $ccAmt > 0 ? $ccAmt : ($addBc ? ($netAmt - round($this->toNum($extra['bank_charge'] ?? 0), 2)) : $rcvd);
                $dcomn = round(($baseForComn * $bcPerc) / 100, 1);
                $dcomnTax = round(($dcomn * $bcTaxPerc) / 100, 1);

                if ($dcomn != 0.0) {
                    $add($entries, $cbcode, $dcomn, $opAcCode);
                    if (!$addBc) {
                        $add($entries, 'BCOMN', -$dcomn, $opAcCode);
                    }
                }
                if ($dcomnTax != 0.0) {
                    $add($entries, $cbcode, $dcomnTax, $opAcCode);
                    if (!$addBc) {
                        $add($entries, 'BCOMNTAX', -$dcomnTax, $opAcCode);
                    }
                }
            }
        }

        // PDC/CCPDC entries (pdclist parity)
        if ($this->hasTable('pdclist')) {
            $chqNo = trim((string) ($extra['chq_no'] ?? $extra['cheque_no'] ?? $billNo));
            $chqDateRaw = trim((string) ($extra['chq_date'] ?? $extra['cheque_date'] ?? ''));
            $chqDate = $this->parseDate($chqDateRaw) ?: $billDateSql;
            $isChqPdc = $this->toBool($extra['chq_pdc'] ?? $extra['pdc'] ?? false);
            $isCcPdc = $this->toBool($extra['cc_pdc'] ?? $extra['ccpdc'] ?? false);
            $particular = mb_substr('By Sales (' . $billNo . ')', 0, 100);

            if ($isChqPdc && $chqAmt > 0) {
                $this->insertPdclistRow([
                    'slno' => $slno,
                    'tdate' => $billDateSql,
                    'docno' => $billNo,
                    'bank' => $chqBank,
                    'code' => 'CNC',
                    'chqno' => $chqNo,
                    'chqdate' => $chqDate,
                    'amount' => $chqAmt,
                    'particulars' => $particular,
                    'rp' => 'R',
                    'pend' => 'Y',
                    'control' => $control,
                ]);
            }

            if ($isCcPdc && $ccAmt > 0) {
                $ccPdcDate = $chqDate;
                try {
                    $ccPdcDate = Carbon::parse($billDateSql)->addMonths(3)->toDateString();
                } catch (\Throwable) {
                    // keep fallback
                }
                $this->insertPdclistRow([
                    'slno' => $slno,
                    'tdate' => $billDateSql,
                    'docno' => $billNo,
                    'bank' => $cbcode,
                    'code' => 'CNC',
                    'chqno' => $billNo,
                    'chqdate' => $ccPdcDate,
                    'amount' => $ccAmt,
                    'particulars' => $particular,
                    'rp' => 'R',
                    'pend' => 'Y',
                    'control' => $control,
                ]);
            }
        }

        // Customer debit/credit pair
        if ($custCode !== '') {
            $add($entries, $custCode, -($netAmt - $disc), $opAcCode);
            if ($rcvd != 0.0) {
                $add($entries, $custCode, $rcvd, $opAcCode);
            }
        }

        // Standard charge heads
        $sdiscAc = $this->readGeneralsValue('SDISCAC', 'DISC');
        $add($entries, $sdiscAc, -$disc, $opAcCode);
        $add($entries, 'HMC', $hmcAmt, $opAcCode);
        $add($entries, 'TCSAC', $tcsAmt, $opAcCode);
        $add($entries, 'RCAMT', $rcAmt, $opAcCode);
        $advanceAc = $custCode !== '' ? $custCode : 'ADVANCE';
        $add($entries, $advanceAc, -$advAmt, $opAcCode);
        $add($entries, 'FANCY', $fancyAmt, $opAcCode);

        if ($schemeAmt != 0.0) {
            if ($flagY($sw($software, 'AdjustSchemeAmtWithSchemeColln', 'N')) && $this->hasTable('kuricolln')) {
                $spcode = $custCode;
                if ($custCode !== '' && $this->hasTable('clients_kuridet')) {
                    $kcode = DB::table('clients_kuridet')->where('custlinkac', $custCode)->value('code');
                    $spcode = trim((string) ($kcode ?? $custCode));
                }
                DB::table('kuricolln')->insert([
                    'slno' => $slno,
                    'tdate' => $billDateSql,
                    'code' => $spcode ?: $custCode,
                    'amount' => -$schemeAmt,
                    'control' => $control,
                    'sno' => 1,
                    'grate' => $this->toNum($payload['rate_per_gm'] ?? 0),
                    'agent' => '',
                    'rcptno' => '',
                    'closed' => 'N',
                    'wgt' => 0,
                    'docno' => $billNo,
                    'note' => 'Amt adjusted with Sales',
                ]);
                $add($entries, $spcode ?: $custCode, -$schemeAmt, $opAcCode);
            } else {
                $schemePostAc = $schemeLedger;
                if ($schemePostAc === '' && $coPartyCode !== '') {
                    $schemePostAc = $coPartyCode;
                }
                if ($schemePostAc === '') {
                    $schemePostAc = 'APP';
                }
                $add($entries, $schemePostAc, -$schemeAmt, $opAcCode);
            }
        }

        // Tax heads
        if ($taxAmt != 0.0) {
            if ($taxSystem === 'VAT') {
                $staxAc = $this->readGeneralsValue('STAXAC', 'TAX');
                $add($entries, $staxAc, $taxAmt, $opAcCode);
            } else {
                if ($isCst) {
                    $add($entries, 'IGST', $igstAmt ?: $taxAmt, $opAcCode);
                } else {
                    $add($entries, 'SGST', $sgstAmt, $opAcCode);
                    $add($entries, 'CGST', $cgstAmt, $opAcCode);
                }
            }
        }
        $add($entries, 'AST', $astAmt, $opAcCode);

        if ($retAmt != 0.0) {
            if ($srTaxAmt != 0.0) {
                if ($taxSystem === 'VAT') {
                    $staxAc = $this->readGeneralsValue('STAXAC', 'TAX');
                    $add($entries, $staxAc, -$srTaxAmt, $opAcCode);
                } elseif ($isCst) {
                    $add($entries, 'IGST', -$srTaxAmt, $opAcCode);
                } else {
                    $add($entries, 'SGST', -$srTaxAmt / 2, $opAcCode);
                    $add($entries, 'CGST', -$srTaxAmt / 2, $opAcCode);
                }
            }
            $add($entries, 'AST', -$srCessAmt, $opAcCode);
        }

        // Purchase tax external mapping parity
        if ($ptaxAmt != 0.0) {
            $ptaxAc = $this->readGeneralsValue('PTAXAC', 'PTAX');
            $add($entries, $ptaxAc, -$ptaxAmt, $opAcCode);
            $add($entries, 'PTAXEXP', $ptaxAmt, $opAcCode);
        }

        // Sales / Exchange / Sales Return heads
        if ($billAmt != 0.0) {
            $baseSales = $billAmt - ($vaSepAc ? $dtva : 0.0);
            $sop = $rcvd != 0.0 ? $cbcode : ($custCode !== '' ? $custCode : $opAcCode);
            $add($entries, 'RS', $baseSales, $sop);
        }
        if ($retAmt != 0.0) {
            $add($entries, 'ESR', -($retAmt - $srTaxAmt - $srCessAmt), $opAcCode);
        }
        if ($exAmt != 0.0) {
            $epAc = $hasOgExchange ? $sw($software, 'OGPurchaseAc', 'EP') : 'EP';
            $add($entries, $epAc, -$exAmt, $opAcCode);
        }
        if ($vaSepAc && $dtva != 0.0) {
            $add($entries, 'VA', $dtva, 'RS');
        }

        // Insert daybook rows
        $sno = 1;
        foreach ($entries as $e) {
            $this->insertLegacyDaybookRow($slno, $sno++, $billDateSql, $e['accode'], $e['amount'], $control, $e['opaccode'] ?: $opAcCode);
        }

        // PB round balancing entry
        if ($this->hasTable('daybook')) {
            $sum = (float) (DB::table('daybook')->where('slno', $slno)->sum('amount') ?? 0);
            $sum = round($sum, 2);
            if ($sum != 0.0) {
                $this->insertLegacyDaybookRow($slno, $sno, $billDateSql, 'ROUND', -$sum, $control, 'RS');
            }
        }
    }

    private function insertLegacyDaybookRow(
        int $slno,
        int $sno,
        string $tdate,
        string $accode,
        float $amount,
        int $control,
        string $opaccode
    ): void {
        if (!$this->hasTable('daybook')) {
            return;
        }
        $cols = $this->getColumns('daybook');
        $row = [];
        if (in_array('slno', $cols, true)) $row['slno'] = $slno;
        if (in_array('sno', $cols, true)) $row['sno'] = $sno;
        if (in_array('tdate', $cols, true)) $row['tdate'] = $tdate;
        if (in_array('ddate', $cols, true)) $row['ddate'] = $tdate;
        if (in_array('accode', $cols, true)) $row['accode'] = mb_substr($accode, 0, 20);
        if (in_array('amount', $cols, true)) $row['amount'] = round($amount, 2);
        if (in_array('control', $cols, true)) $row['control'] = $control;
        if (in_array('opaccode', $cols, true)) $row['opaccode'] = mb_substr($opaccode, 0, 20);
        if (in_array('vtype', $cols, true)) $row['vtype'] = 'SL';
        if (in_array('vno', $cols, true)) $row['vno'] = $slno;
        if (in_array('userid', $cols, true)) $row['userid'] = '';
        if (in_array('narration', $cols, true)) {
            $row['narration'] = 'Sales entry';
        }
        DB::table('daybook')->insert($row);
    }

    private function insertPdclistRow(array $data): void
    {
        if (!$this->hasTable('pdclist')) {
            return;
        }
        $cols = $this->getColumns('pdclist');
        $row = [];
        foreach (
            ['slno', 'tdate', 'docno', 'bank', 'code', 'chqno', 'chqdate', 'amount', 'particulars', 'rp', 'pend', 'control']
            as $k
        ) {
            if (in_array($k, $cols, true) && array_key_exists($k, $data)) {
                $row[$k] = $data[$k];
            }
        }
        if ($row !== []) {
            DB::table('pdclist')->insert($row);
        }
    }

    public function cancelBill(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'bill_no' => 'required|string|max:40',
            'reason' => 'nullable|string|max:255',
        ]);

        $billNo = trim((string) $payload['bill_no']);
        $legacy = $this->hasTable('salesm')
            ? DB::table('salesm')->where('billno', $billNo)->first(['slno', 'control'])
            : null;
        $bill = $this->hasSalesBillsTable()
            ? SalesBill::query()->where('bill_no', $billNo)->first()
            : null;

        if (!$legacy && !$bill) {
            return response()->json(['ok' => false, 'message' => 'Bill not found.'], 404);
        }

        $legacySlno = (int) ($legacy->slno ?? 0);
        $legacyControl = (int) ($legacy->control ?? 1);
        $this->recordCancelledSalesBillAudit($request, $billNo, $legacySlno, $bill, (string) ($payload['reason'] ?? ''));

        DB::transaction(function () use ($billNo, $bill, $legacySlno, $legacyControl) {
            if ($legacySlno > 0) {
                $this->reverseLegacyInventoryEffects($legacySlno, $legacyControl);
                $this->deleteLegacySalesBundle($legacySlno);
            }

            if ($bill) {
                $bill->delete();
            } elseif ($this->hasSalesBillsTable()) {
                SalesBill::query()->where('bill_no', $billNo)->delete();
            }
        });

        $this->logDelpart($request, 'Sales Bill(' . $billNo . ') Deleted', ['utype' => 'D', 'ttype' => 'T']);
        $message = 'Bill deleted from database.';
        if ($legacySlno > 0 && SecondaryDatabaseSync::userCanUse((string) $request->session()->get('user_code'))) {
            $message .= ' Secondary sync was skipped for hard delete.';
        }

        return response()->json(['ok' => true, 'message' => $message]);
    }

    private function recordCancelledSalesBillAudit(Request $request, string $billNo, int $legacySlno, ?SalesBill $bill, string $reason = ''): void
    {
        $this->ensureCancelledBillAuditTable();
        if (!$this->hasTable('cancelled_bill_audits')) {
            return;
        }

        $salesm = null;
        if ($legacySlno > 0 && $this->hasTable('salesm')) {
            $salesm = DB::table('salesm')->where('slno', $legacySlno)->first();
        }

        $weight = 0.0;
        $qty = 0.0;
        $itemCount = 0;
        if ($legacySlno > 0 && $this->hasTable('salesd')) {
            $detail = DB::table('salesd')
                ->selectRaw('COALESCE(SUM(weight),0) as weight, COALESCE(SUM(qty),0) as qty, COUNT(*) as item_count')
                ->where('slno', $legacySlno)
                ->first();
            $weight = (float) ($detail->weight ?? 0);
            $qty = (float) ($detail->qty ?? 0);
            $itemCount = (int) ($detail->item_count ?? 0);
        } elseif ($bill) {
            $items = json_decode((string) ($bill->items_json ?? '[]'), true);
            if (is_array($items)) {
                foreach ($items as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $weight += (float) ($item['weight'] ?? $item['wgt'] ?? 0);
                    $qty += (float) ($item['qty'] ?? 0);
                    $itemCount++;
                }
            }
        }

        $customerCode = trim((string) ($salesm->custcode ?? $bill->customer_code ?? ''));
        $customerName = trim((string) ($salesm->custname ?? $bill->customer_name ?? ''));
        $mobile = trim((string) ($salesm->mobno ?? $bill->mobile ?? ''));
        $address = trim((string) ($salesm->addr ?? $bill->address ?? ''));

        if ($customerCode !== '' && $this->hasTable('clients')) {
            $client = DB::table('clients')
                ->whereRaw('TRIM(code) = ?', [$customerCode])
                ->first(['name', 'mobile', 'telephone', 'addr1', 'addr2', 'addr3', 'city']);
            if ($client) {
                $customerName = $customerName !== '' ? $customerName : trim((string) ($client->name ?? ''));
                $mobile = $mobile !== '' ? $mobile : trim((string) ($client->mobile ?? $client->telephone ?? ''));
                if ($address === '') {
                    $address = trim(implode(' ', array_filter([
                        trim((string) ($client->addr1 ?? '')),
                        trim((string) ($client->addr2 ?? '')),
                        trim((string) ($client->addr3 ?? '')),
                        trim((string) ($client->city ?? '')),
                    ])));
                }
            }
        }

        DB::table('cancelled_bill_audits')->insert([
            'module' => 'sales',
            'bill_no' => mb_substr($billNo, 0, 40),
            'slno' => $legacySlno ?: null,
            'control' => (int) ($salesm->control ?? 1),
            'bill_date' => $this->normalizeAuditDate($salesm->tdate ?? $bill->bill_date ?? null),
            'bill_time' => mb_substr(trim((string) ($salesm->ttime ?? $bill->bill_time ?? '')), 0, 20),
            'customer_code' => mb_substr($customerCode, 0, 30),
            'customer_name' => mb_substr($customerName, 0, 160),
            'mobile' => mb_substr($mobile, 0, 40),
            'address' => mb_substr($address, 0, 255),
            'bill_amount' => round((float) ($salesm->billamt ?? $bill->bill_total ?? 0), 2),
            'net_amount' => round((float) ($salesm->netamt ?? $bill->net_total ?? 0), 2),
            'gross_weight' => round($weight, 3),
            'qty' => round($qty, 3),
            'item_count' => $itemCount,
            'reason' => mb_substr(trim($reason), 0, 255),
            'cancelled_by' => mb_substr(trim((string) $request->session()->get('user_code', $request->session()->get('user_id', ''))), 0, 30),
            'cancelled_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ensureCancelledBillAuditTable(): void
    {
        if (Schema::hasTable('cancelled_bill_audits')) {
            return;
        }

        Schema::create('cancelled_bill_audits', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id();
            $table->string('module', 30)->default('sales');
            $table->string('bill_no', 40)->index();
            $table->unsignedBigInteger('slno')->nullable()->index();
            $table->integer('control')->default(1);
            $table->date('bill_date')->nullable()->index();
            $table->string('bill_time', 20)->nullable();
            $table->string('customer_code', 30)->nullable()->index();
            $table->string('customer_name', 160)->nullable();
            $table->string('mobile', 40)->nullable();
            $table->string('address', 255)->nullable();
            $table->decimal('bill_amount', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2)->default(0);
            $table->decimal('gross_weight', 15, 3)->default(0);
            $table->decimal('qty', 15, 3)->default(0);
            $table->integer('item_count')->default(0);
            $table->string('reason', 255)->nullable();
            $table->string('cancelled_by', 30)->nullable();
            $table->timestamp('cancelled_at')->nullable()->index();
            $table->timestamps();
        });
    }

    private function normalizeAuditDate(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        $time = strtotime($text);
        return $time === false ? null : date('Y-m-d', $time);
    }

    public function confirmBill(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'bill_no' => 'required|string|max:40',
        ]);

        if ($this->hasSalesBillsTable()) {
            $bill = SalesBill::query()->where('bill_no', trim((string) $payload['bill_no']))->first();
            if (!$bill) {
                return response()->json(['ok' => false, 'message' => 'Bill not found.'], 404);
            }
            if ($bill->status === 'cancelled') {
                return response()->json(['ok' => false, 'message' => 'Cancelled bill cannot be confirmed.'], 422);
            }

            $bill->status = 'confirmed';
            $bill->confirmed_at = now();
            $bill->save();
        } elseif ($this->hasTable('salesm')) {
            $updated = DB::table('salesm')
                ->where('billno', trim((string) $payload['bill_no']))
                ->where('status', '!=', 0)
                ->update(['prn' => 1]);
            if (!$updated) {
                return response()->json(['ok' => false, 'message' => 'Bill not found.'], 404);
            }
        } else {
            return response()->json(['ok' => false, 'message' => 'Bill not found.'], 404);
        }

        $this->logDelpart($request, 'Sales Bill(' . trim((string) $payload['bill_no']) . ') Confirmed', ['utype' => 'E', 'ttype' => 'T']);
        return response()->json(['ok' => true, 'message' => 'Bill confirmed.']);
    }

    public function reprint(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'bill_no' => 'required|string|max:40',
        ]);

        $billNo = trim((string) $payload['bill_no']);
        if ($this->hasSalesBillsTable()) {
            $bill = SalesBill::query()->where('bill_no', $billNo)->first();
            if (!$bill) {
                return response()->json(['ok' => false, 'message' => 'Bill not found.'], 404);
            }

            return response()->json([
                'ok' => true,
                'message' => 'Reprint payload ready.',
                'data' => $this->mapBill($bill),
            ]);
        }

        if ($this->hasTable('salesm')) {
            $legacy = DB::table('salesm')->where('billno', $billNo)->first();
            if ($legacy) {
                return response()->json([
                    'ok' => true,
                    'message' => 'Reprint payload ready.',
                    'data' => $this->mapLegacySalesBill($legacy),
                ]);
            }
        }

        return response()->json(['ok' => false, 'message' => 'Bill not found.'], 404);
    }

    public function generateEInvoice(Request $request): JsonResponse
    {
        set_time_limit(120);

        $payload = $request->validate([
            'bill_no' => 'required|string|max:40',
            'password' => 'nullable|string|max:255',
        ]);

        $billNo = trim((string) $payload['bill_no']);
        $settingsPayload = $this->loadSettingsPayload();
        $software = (array) ($settingsPayload['Software'] ?? []);
        $apiSettings = (array) ($settingsPayload['API'] ?? []);
        $sw = static fn (array $arr, string $key, string $default = ''): string => (string) ($arr[$key] ?? $default);
        $flagY = static fn (mixed $v, string $def = 'N'): bool => strtoupper(trim((string) ($v ?? $def))) === 'Y';

        if (!$flagY($sw($software, 'AskEInvoiceAboveAmount', 'Y'))) {
            return response()->json(['ok' => false, 'message' => 'E-invoice prompt is disabled in Application Settings.'], 422);
        }

        $billData = $this->findBillData($billNo);
        if ($billData === null) {
            return response()->json(['ok' => false, 'message' => 'Bill not found.'], 404);
        }

        if (!$this->isB2BEInvoiceBill($billData)) {
            return response()->json([
                'ok' => false,
                'message' => 'E-invoice can be generated only for B2B bills.',
            ], 422);
        }

        $buyerGstin = strtoupper(trim((string) ($billData['gst_no'] ?? '')));
        if (!preg_match('/^\d{2}[A-Z0-9]{13}$/', $buyerGstin)) {
            return response()->json([
                'ok' => false,
                'message' => 'E-invoice can be generated only for B2B bills with a valid GSTIN.',
            ], 422);
        }

        $provider = strtolower(preg_replace('/[^a-z0-9]+/', '', trim((string) ($apiSettings['EINVOICEPROVIDER'] ?? ''))));
        if ($provider === '' && trim((string) ($apiSettings['EINVOICEAPIURL'] ?? '')) === '') {
            $provider = 'mastersindia';
        }
        $isMastersIndia = $provider === 'mastersindia'
            || str_contains(strtolower((string) ($apiSettings['EINVOICEAPIURL'] ?? '')), 'mastersindia.co');

        $apiUrl = trim((string) ($apiSettings['EINVOICEAPIURL'] ?? ''));
        if ($apiUrl === '' && $isMastersIndia) {
            $apiUrl = 'https://prod-api.mastersindia.co/api/v1/einvoice/';
        }
        $username = trim((string) ($apiSettings['EINVOICEUSERNAME'] ?? ''));
        if ($username === '' && $isMastersIndia) {
            $username = (string) env('MASTERSINDIA_USERNAME', '');
        }
        $password = trim((string) ($payload['password'] ?? ''));
        if ($password === '') {
            $password = (string) ($apiSettings['EINVOICEPASSWORD'] ?? '');
        }
        if ($password === '' && $isMastersIndia) {
            $password = (string) env('MASTERSINDIA_PASSWORD', '');
        }

        if ($apiUrl === '') {
            return response()->json(['ok' => false, 'message' => 'Set EInvoice API URL in Application Settings before generating.'], 422);
        }
        $authMode = strtolower(trim((string) ($apiSettings['EINVOICEAUTHMODE'] ?? 'basic')));
        if ($authMode === '') {
            $authMode = 'basic';
        }

        if (($isMastersIndia || $authMode === 'basic') && ($username === '' || $password === '')) {
            return response()->json(['ok' => false, 'message' => 'Set EInvoice username and password in Application Settings before generating.'], 422);
        }
        if (!$isMastersIndia && in_array($authMode, ['bearer', 'api_key', 'apikey'], true) && trim((string) ($apiSettings['EINVOICEAPIKEY'] ?? '')) === '') {
            return response()->json(['ok' => false, 'message' => 'Set EInvoice API key in Application Settings before generating.'], 422);
        }

        $invoicePayload = $isMastersIndia
            ? $this->buildMastersIndiaEInvoicePayload($billData, $settingsPayload, $apiSettings)
            : $this->buildEInvoicePayload($billData, $settingsPayload);
        $requestPayload = $this->wrapEInvoicePayload($invoicePayload, $apiSettings);

        try {
            if ($isMastersIndia) {
                $token = $this->fetchMastersIndiaToken($apiSettings, $username, $password);
                $response = $this->mastersIndiaHttp(20)
                    ->acceptJson()
                    ->asJson()
                    ->withHeaders(['Authorization' => 'JWT ' . $token])
                    ->post($apiUrl, $requestPayload);
            } else {
                $response = $this->buildEInvoiceHttpRequest($apiSettings, $username, $password)
                    ->post($apiUrl, $requestPayload);
            }
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'E-invoice request failed: ' . $e->getMessage()], 502);
        }

        $decoded = null;
        try {
            $decoded = $response->json();
        } catch (\Throwable) {
            $decoded = null;
        }

        if (!$response->successful()) {
            $message = is_array($decoded)
                ? (string) ($decoded['message'] ?? $decoded['msg'] ?? 'E-invoice provider rejected the request.')
                : trim($response->body());
            if ($message === '') {
                $message = 'E-invoice provider rejected the request.';
            }
            \Log::warning('E-invoice provider HTTP failure', [
                'bill_no' => $billNo,
                'status' => $response->status(),
                'response' => $decoded ?? $response->body(),
                'request_payload' => $requestPayload,
            ]);

            $this->persistEInvoiceResult($billNo, [], $decoded ?? $response->body(), [
                'bill_data' => $billData,
                'request_payload' => $requestPayload,
                'provider' => $isMastersIndia ? 'mastersindia' : ($apiSettings['EINVOICEPROVIDER'] ?? ''),
                'user_code' => (string) $request->session()->get('user_code', ''),
                'status' => 'failed',
            ]);

            return response()->json([
                'ok' => false,
                'message' => $message,
                'provider_status' => $response->status(),
                'provider_response' => $decoded ?? $response->body(),
            ], 422);
        }

        $providerMessage = is_array($decoded)
            ? (string) (data_get($decoded, 'results.errorMessage') ?: data_get($decoded, 'results.status') ?: ($decoded['message'] ?? $decoded['msg'] ?? $decoded['status'] ?? ''))
            : '';
        if ($providerMessage === '') {
            $providerMessage = 'E-invoice generated successfully.';
        }

        $resultStatus = strtoupper(trim((string) data_get($decoded, 'results.status', '')));
        $resultCode = trim((string) data_get($decoded, 'results.code', ''));
        $providerFailed = is_array($decoded)
            && ($resultStatus !== '' || $resultCode !== '')
            && ($resultStatus !== 'SUCCESS' || !in_array($resultCode, ['', '0', '200'], true));
        if ($providerFailed) {
            \Log::warning('E-invoice provider failed response', [
                'bill_no' => $billNo,
                'status' => $response->status(),
                'response' => $decoded,
                'request_payload' => $requestPayload,
            ]);
            $this->persistEInvoiceResult($billNo, [], $decoded, [
                'bill_data' => $billData,
                'request_payload' => $requestPayload,
                'provider' => $isMastersIndia ? 'mastersindia' : ($apiSettings['EINVOICEPROVIDER'] ?? ''),
                'user_code' => (string) $request->session()->get('user_code', ''),
                'status' => 'failed',
            ]);
            return response()->json([
                'ok' => false,
                'message' => (string) (data_get($decoded, 'results.errorMessage') ?: data_get($decoded, 'results.message') ?: 'E-invoice provider rejected the request.'),
                'provider_status' => $response->status(),
                'provider_response' => $decoded,
                'request_payload' => $requestPayload,
            ], 422);
        }

        $result = is_array($decoded) ? $this->extractEInvoiceProviderResult($decoded, $apiSettings) : [];
        if (trim((string) ($result['irn'] ?? '')) === '') {
            $this->persistEInvoiceResult($billNo, $result, $decoded ?? $response->body(), [
                'bill_data' => $billData,
                'request_payload' => $requestPayload,
                'provider' => $isMastersIndia ? 'mastersindia' : ($apiSettings['EINVOICEPROVIDER'] ?? ''),
                'user_code' => (string) $request->session()->get('user_code', ''),
                'status' => 'failed',
            ]);

            return response()->json([
                'ok' => false,
                'message' => $providerMessage !== '' ? $providerMessage : 'E-invoice provider did not return an IRN.',
                'provider_status' => $response->status(),
                'provider_response' => $decoded ?? $response->body(),
                'request_payload' => $requestPayload,
            ], 422);
        }

        $this->persistEInvoiceResult($billNo, $result, $decoded ?? $response->body(), [
            'bill_data' => $billData,
            'request_payload' => $requestPayload,
            'provider' => $isMastersIndia ? 'mastersindia' : ($apiSettings['EINVOICEPROVIDER'] ?? ''),
            'user_code' => (string) $request->session()->get('user_code', ''),
        ]);

        $this->logDelpart($request, 'Sales Bill(' . $billNo . ') E-Invoice Requested', ['utype' => 'E', 'ttype' => 'T']);

        return response()->json([
            'ok' => true,
            'message' => $providerMessage,
            'irn' => $result['irn'] ?? '',
            'ack_no' => $result['ack_no'] ?? '',
            'provider_status' => $response->status(),
            'provider_response' => $decoded ?? $response->body(),
            'request_payload' => $requestPayload,
        ]);
    }

    public function generateEWayBill(Request $request): JsonResponse
    {
        set_time_limit(120);

        $payload = $request->validate([
            'bill_no' => 'required|string|max:40',
            'password' => 'nullable|string|max:255',
        ]);

        $billNo = trim((string) $payload['bill_no']);
        $billData = $this->findBillData($billNo);
        if ($billData === null) {
            return response()->json(['ok' => false, 'message' => 'Bill not found.'], 404);
        }

        $settingsPayload = $this->loadSettingsPayload();
        $software = (array) ($settingsPayload['Software'] ?? []);
        $threshold = max(0.0, $this->toNum($software['EWayBillThresholdAmount'] ?? $software['EInvoiceThresholdAmount'] ?? 1000000));
        $billAmount = $this->toNum($billData['net_total'] ?? $billData['bill_total'] ?? 0);
        if ($threshold > 0 && $billAmount < $threshold) {
            return response()->json([
                'ok' => false,
                'message' => 'E-way bill can be generated only for bills of Rs. ' . number_format($threshold, 2, '.', '') . ' and above.',
            ], 422);
        }

        $apiSettings = (array) ($settingsPayload['API'] ?? []);
        $username = trim((string) ($apiSettings['EWAYUSERNAME'] ?? $apiSettings['EINVOICEUSERNAME'] ?? ''));
        if ($username === '') {
            $username = (string) env('MASTERSINDIA_USERNAME', '');
        }
        $password = trim((string) ($payload['password'] ?? ''));
        if ($password === '') {
            $password = (string) ($apiSettings['EWAYPASSWORD'] ?? $apiSettings['EINVOICEPASSWORD'] ?? '');
        }
        if ($password === '') {
            $password = (string) env('MASTERSINDIA_PASSWORD', '');
        }

        if ($username === '' || $password === '') {
            return response()->json(['ok' => false, 'message' => 'Set Masters India username and password before generating e-way bill.'], 422);
        }

        $vehicleNo = strtoupper(preg_replace('/[^A-Z0-9]+/', '', (string) ($billData['vehicle_no'] ?? '')));
        if ($vehicleNo === '') {
            return response()->json(['ok' => false, 'message' => 'Vehicle number is required for e-way bill generation.'], 422);
        }

        $requestPayload = $this->buildMastersIndiaEWayBillPayload($billData, $settingsPayload, $apiSettings);
        $apiUrl = trim((string) ($apiSettings['EWAYAPIURL'] ?? ''));
        if ($apiUrl === '') {
            $apiUrl = 'https://prod-api.mastersindia.co/api/v1/ewayBillsGenerate/';
        }

        try {
            $token = $this->fetchMastersIndiaToken($apiSettings, $username, $password);
            $response = $this->mastersIndiaHttp(20)
                ->acceptJson()
                ->asJson()
                ->withHeaders(['Authorization' => 'JWT ' . $token])
                ->post($apiUrl, $requestPayload);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'E-way bill request failed: ' . $e->getMessage()], 502);
        }

        $decoded = null;
        try {
            $decoded = $response->json();
        } catch (\Throwable) {
            $decoded = null;
        }

        $status = strtoupper((string) data_get($decoded, 'results.status', ''));
        $code = (int) data_get($decoded, 'results.code', 0);
        $message = data_get($decoded, 'results.message');
        $messageText = is_array($message)
            ? (string) ($message['alert'] ?? 'E-way bill generated successfully.')
            : trim((string) ($message ?: 'E-way bill provider rejected the request.'));

        if (!$response->successful() || $status !== 'SUCCESS' || ($code !== 0 && $code !== 200)) {
            return response()->json([
                'ok' => false,
                'message' => $messageText !== '' ? $messageText : 'E-way bill provider rejected the request.',
                'provider_status' => $response->status(),
                'provider_response' => $decoded ?? $response->body(),
                'request_payload' => $requestPayload,
            ], 422);
        }

        $ewayBillNo = is_array($message) ? (string) ($message['ewayBillNo'] ?? '') : '';
        $ewayBillDate = is_array($message) ? (string) ($message['ewayBillDate'] ?? '') : '';
        $validUpto = is_array($message) ? (string) ($message['validUpto'] ?? '') : '';
        $printUrl = is_array($message) ? (string) ($message['url'] ?? '') : '';

        $this->logDelpart($request, 'Sales Bill(' . $billNo . ') E-Way Bill Requested', ['utype' => 'E', 'ttype' => 'T']);

        return response()->json([
            'ok' => true,
            'message' => 'E-way bill generated successfully.',
            'eway_bill_no' => $ewayBillNo,
            'eway_bill_date' => $ewayBillDate,
            'valid_upto' => $validUpto,
            'print_url' => $printUrl,
            'provider_status' => $response->status(),
            'provider_response' => $decoded ?? $response->body(),
            'request_payload' => $requestPayload,
        ]);
    }

    public function recalc(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'items' => 'nullable|array',
            'exchange' => 'nullable|array',
            'sales_return' => 'nullable|array',
            'sr_tax_perc' => 'nullable|numeric',
            'sr_tax_amt' => 'nullable|numeric',
            'sr_cess_perc' => 'nullable|numeric',
            'sr_cess_amt' => 'nullable|numeric',
            'sr_discount_amt' => 'nullable|numeric',
            'extra' => 'nullable|array',
        ]);

        return response()->json([
            'ok' => true,
            'data' => $this->calcTotals($payload),
        ]);
    }

    public function itemLookup(Request $request): JsonResponse
    {
        $rawCode = trim((string) $request->query('code', ''));
        $requestedGroup = strtoupper(trim((string) $request->query('group_code', '')));
        $taxPerc = $this->toNum($request->query('tax_perc', 0));
        $astPerc = $this->toNum($request->query('ast_perc', 0));
        $isCst = $request->boolean('is_cst', false);

        if ($rawCode === '') {
            return response()->json(['ok' => false, 'message' => 'Code required.'], 422);
        }

        $settingsPayload = $this->loadSettingsPayload();
        $software = (array) ($settingsPayload['Software'] ?? []);
        $ratesCfg = (array) ($settingsPayload['Rates'] ?? []);
        $sw = static function (array $arr, string $key, string $default = ''): string {
            return (string) ($arr[$key] ?? $default);
        };
        $isYes = static function (mixed $v): bool {
            $s = strtoupper(trim((string) $v));
            return in_array($s, ['Y', 'YES', 'TRUE', '1'], true);
        };
        $pickNum = function (array $arr, array $keys, float $default = 0.0): float {
            foreach ($keys as $k) {
                if (array_key_exists($k, $arr)) {
                    return $this->toNum($arr[$k]);
                }
            }
            return $default;
        };

        $barcodeCutLastDigit = $isYes($sw($software, 'BarcodeCutLastDigit', 'N'));
        $autoRearrangeMcStAmt = $isYes($sw($software, 'AutoReArrangeMcStAmt', $sw($software, 'AutoRearrangeMcStAmt', 'N')));
        $internationalRate = $isYes($sw($software, 'InternationalRate', 'N'));
        $maxMcPerc = $pickNum($software, ['MaxMcPerc', 'MaxMCPerc', 'dimaxmcperc'], 0.0);
        $goldRate = $this->toNum($request->query('gold_rate', 0));
        $silverRate = $this->toNum($request->query('silver_rate', 0));
        $platinumRate = $this->toNum($request->query('platinum_rate', 0));
        if ($goldRate <= 0) {
            $goldRate = $pickNum($ratesCfg, ['GRATE'], $this->toNum($sw($software, 'GRATE', '0')));
        }
        if ($silverRate <= 0) {
            $silverRate = $pickNum($ratesCfg, ['SRATE'], $this->toNum($sw($software, 'SRATE', '0')));
        }
        if ($platinumRate <= 0) {
            $platinumRate = $pickNum($ratesCfg, ['PRATE'], $this->toNum($sw($software, 'PRATE', '0')));
        }
        // Fallback to generald (where Rate Update screen writes) when not set in INI.
        $generaldRate = function (string $code): float {
            try {
                $v = DB::table('generald')->where('code', $code)->value('cvalue');
                return $this->toNum($v);
            } catch (\Throwable) {
                return 0.0;
            }
        };
        $g18Rate = $pickNum($ratesCfg, ['G18RATE'], $this->toNum($sw($software, 'G18RATE', '0')));
        if ($g18Rate <= 0) { $g18Rate = $generaldRate('G18RATE'); }
        $g14Rate = $pickNum($ratesCfg, ['G14RATE'], $this->toNum($sw($software, 'G14RATE', '0')));
        if ($g14Rate <= 0) { $g14Rate = $generaldRate('G14RATE'); }
        $g9Rate  = $pickNum($ratesCfg, ['G9RATE'],  $this->toNum($sw($software, 'G9RATE',  '0')));
        if ($g9Rate  <= 0) { $g9Rate  = $generaldRate('G9RATE');  }
        $thRate  = $pickNum($ratesCfg, ['THRATE'],  $this->toNum($sw($software, 'THRATE',  '0')));
        if ($thRate  <= 0) { $thRate  = $generaldRate('THRATE');  }

        $rateByPurity = function (string $qtype) use ($goldRate, $g18Rate, $g14Rate, $g9Rate, $thRate): float {
            $key = strtoupper(trim($qtype));
            $rates = [
                '22' => $goldRate,
                '18' => $g18Rate,
                '14' => $g14Rate,
                '9'  => $g9Rate,
                '24' => $thRate,
            ];
            if (isset($rates[$key]) && $rates[$key] > 0) {
                return (float) $rates[$key];
            }
            foreach ($rates as $prefix => $r) {
                if ($r > 0 && str_starts_with($key, $prefix)) {
                    return (float) $r;
                }
            }
            return 0.0;
        };

        $lookupCode = $rawCode;
        if ($barcodeCutLastDigit && ctype_digit($lookupCode) && strlen($lookupCode) > 1) {
            $lookupCode = substr($lookupCode, 0, -1);
        }

        $barcodeRow = null;
        if (ctype_digit($lookupCode) && $this->hasTable('barcode')) {
            $barcodeCols = $this->getColumns('barcode');
            if (in_array('bcode', $barcodeCols, true)) {
                $barcodeRow = DB::table('barcode')->where('bcode', (int) $lookupCode)->first();
            }
        }

        $itemCode = $rawCode;
        if ($barcodeRow) {
            $itemCode = trim((string) ($barcodeRow->icode ?? $barcodeRow->code ?? ''));
        }

        $item = Item::query()->where('code', $itemCode)->first();
        if (!$item) {
            return response()->json(['ok' => false, 'message' => 'Item not found.'], 404);
        }

        $itemCols = $this->getColumns('items');
        $itemDb = DB::table('items')->where('code', $itemCode)->first();
        $itemGroup = strtoupper(trim((string) $this->col($itemDb, 'grpcode', '')));
        if ($requestedGroup !== '' && in_array('grpcode', $itemCols, true) && strcasecmp($itemGroup, $requestedGroup) !== 0) {
            return response()->json(['ok' => false, 'message' => 'Check the item group.'], 422);
        }

        $itype = $this->col($itemDb, 'itype', 'G');
        $taxInternal = $isYes($this->col($itemDb, 'taxinternal', 'N'));
        $vaOffer = $isYes($this->col($itemDb, 'vaoffer', 'N'));
        $qty = $this->toNum($this->col($itemDb, 'defqty', 0));
        $weight = 0.0;
        $stoneWgt = 0.0;
        $stonePrice = 0.0;
        $wastageRate = $this->toNum($this->col($itemDb, 'wastage', 0));
        $mcRate = $this->toNum($this->col($itemDb, 'mcharge', 0));
        $mcAmt = 0.0;
        $rate = $this->pickRateByType($itype, $goldRate, $silverRate, $platinumRate, $this->toNum($this->col($itemDb, 'rate', 0)));
        $model = '';
        $qtype = '';
        $qtype2 = trim((string) $this->col($itemDb, 'defquality', ''));
        $itemBillType = trim((string) $this->col($itemDb, 'billtype', ''));
        $stockType = $this->col($itemDb, 'defstktype', '');
        $stkinnos = strtoupper(trim((string) $this->col($itemDb, 'stkinnos', 'N')));
        $vaperc = $this->toNum($this->col($itemDb, 'vaperc', 0));
        $vaperqty = $this->toNum($this->col($itemDb, 'vaperqty', 0));
        $stockQty = $this->toNum($this->col($itemDb, 'qtyb', $this->col($itemDb, 'qty', 0)));
        $stockWgt = $this->toNum($this->col($itemDb, 'weightb', $this->col($itemDb, 'weight', 0)));
        $stktouch = $this->toNum($this->col($itemDb, 'stktouch', 0));
        $stockFlag = 'Y';
        $huid = '';
        $part = '';
        $cost = 0.0;
        $ttouch = 0.0;
        $scode = '';
        $mcCost = 0.0;
        $pcostPerc = 0.0;
        $bcode = 0;
        $dmdAmt = 0.0;
        $dmdWgt = 0.0;
        $dmdNos = 0;
        $dmdUnit = '';
        $source = 'item';
        $tamt = 0.0;

        if ($barcodeRow) {
            $source = 'barcode';
            $bcode = (int) ($barcodeRow->bcode ?? 0);
            $qty = $this->toNum($barcodeRow->qty ?? $qty);
            $weight = $this->toNum($barcodeRow->weight ?? 0);
            $stoneWgt = $this->toNum($barcodeRow->stweight ?? $barcodeRow->stonewgt ?? 0);
            $stonePrice = $this->toNum($barcodeRow->stprice ?? 0);
            $wastageRate = $this->toNum($barcodeRow->wastage ?? $wastageRate);
            $mcRate = $this->toNum($barcodeRow->mcrate ?? $mcRate);
            $mcAmt = $this->toNum($barcodeRow->mc ?? 0);
            $barcodeRate = $this->toNum($barcodeRow->rate ?? 0);
            if ($barcodeRate > 0) {
                $rate = $barcodeRate;
            }
            $model = trim((string) ($barcodeRow->model ?? ''));
            $qtype = trim((string) ($barcodeRow->qtype ?? $qtype));
            $stockType = trim((string) ($barcodeRow->stktype ?? $stockType));
            $stktouch = $this->toNum($barcodeRow->stktouch ?? $stktouch);
            $huid = trim((string) ($barcodeRow->huid ?? ''));
            $part = trim((string) ($barcodeRow->part ?? ''));
            $cost = $this->toNum($barcodeRow->cost ?? 0);
            $ttouch = $this->toNum($barcodeRow->transtouch ?? 0);
            $scode = trim((string) ($barcodeRow->smithcode ?? ''));
            $mcCost = $this->toNum($barcodeRow->costmc ?? 0);
            $pcostPerc = $this->toNum($barcodeRow->costperc ?? 0);
            $stockFlag = strtoupper(trim((string) ($barcodeRow->stk ?? 'Y')));
            $stkinnos = strtoupper(trim((string) ($barcodeRow->stkinnos ?? $stkinnos)));
            $vapFromBarcode = $this->toNum($barcodeRow->vap ?? 0);
            if ($vapFromBarcode > 0) {
                $vaperc = $vapFromBarcode;
            }
            $tamt = $this->toNum($barcodeRow->tamt ?? 0);

            if ($this->hasTable('barcodedmd')) {
                $dmd = DB::table('barcodedmd')->where('bcode', $bcode)->first();
                if ($dmd) {
                    $dmdAmt = $this->toNum($dmd->dmdamt ?? 0);
                    $dmdWgt = $this->toNum($dmd->dmdwgt ?? 0);
                    $dmdNos = (int) ($dmd->dmdnos ?? 0);
                    $dmdUnit = trim((string) ($dmd->dmdunit ?? ''));
                }
            }

            if ($stockFlag !== 'Y') {
                return response()->json(['ok' => false, 'message' => 'No Stock in this barcode...'], 422);
            }
        }

        if ($qtype === '') {
            $qtype = $qtype2;
        }

        $isGold = strtoupper(trim((string) $itype)) === 'G';
        $purityRate = ($isGold && $qtype !== '') ? $rateByPurity($qtype) : 0.0;

        // Auto-pick row rate from purity (gold only). Skip when barcode supplied its own rate.
        if ($purityRate > 0 && !$barcodeRow) {
            $rate = $purityRate;
        }

        $typeRate = $this->pickRateByType($itype, $goldRate, $silverRate, $platinumRate, 0);
        if ($internationalRate && $qtype !== '' && $this->hasTable('itemsqtype')) {
            $itemsQCols = $this->getColumns('itemsqtype');
            if (in_array('code', $itemsQCols, true) && in_array('rate', $itemsQCols, true)) {
                $qRate = DB::table('itemsqtype')->where('code', $qtype)->value('rate');
                $qRateNum = $this->toNum($qRate);
                if ($qRateNum > 0) {
                    $typeRate = $qRateNum;
                }
            }
        }
        if ($isGold && $purityRate > 0) {
            $typeRate = $purityRate;
        }
        if ($typeRate <= 0) {
            $typeRate = $this->pickRateByType($itype, $goldRate, $silverRate, $platinumRate, $rate);
        }

        if ($vaOffer) {
            $mcRate = 0;
            $mcAmt = 0;
            $vaperc = 0;
        }

        if ($vaperc > 0) {
            $mcRate = ($typeRate * $vaperc) / 100;
        }

        $dastPerc = $isCst ? 0.0 : $astPerc;
        $netWgt = max($weight - $stoneWgt, 0);
        if ($tamt > 0) {
            if ($taxInternal) {
                $base = 100 + $taxPerc + $dastPerc;
                if ($base > 0) {
                    $tamt = ($tamt * 100) / $base;
                }
            }

            if ($autoRearrangeMcStAmt) {
                $mcAmt = $tamt - ($netWgt * $typeRate);
                if ($stonePrice > 0) {
                    if (($mcAmt - $stonePrice) > 0) {
                        $mcAmt -= $stonePrice;
                    } else {
                        $stonePrice = $mcAmt;
                        $mcAmt = 0;
                    }
                } elseif ($maxMcPerc > 0 && $stoneWgt > 0) {
                    $stonePrice = $mcAmt - round(($mcAmt * $maxMcPerc) / 100, 0);
                    $mcAmt -= $stonePrice;
                }

                if ($mcAmt < 0) {
                    $mcAmt = 0;
                }
                if ($stonePrice < 0) {
                    $stonePrice = 0;
                }
            }
        }

        $mcAmt2 = $mcAmt;
        $netWgt = max($weight - $stoneWgt, 0);
        if ($mcAmt <= 0 && $mcRate > 0 && $netWgt > 0) {
            $mcAmt = $netWgt * $mcRate;
        } elseif ($mcAmt > 0 && $netWgt > 0) {
            $mcRate = $mcAmt / $netWgt;
        }

        if ($tamt > 0 && $netWgt >= 0) {
            if ($netWgt > 0) {
                $rate = ($tamt - $mcAmt - $stonePrice - $dmdAmt) / $netWgt;
            } else {
                $rate = $tamt - $mcAmt - $stonePrice - $dmdAmt;
            }
        }

        if ($stkinnos === 'Y' && $tamt > 0) {
            $rate = $tamt;
        }

        if ($taxInternal) {
            $base = 100 + $taxPerc + $dastPerc;
            if ($base > 0) {
                $rate = ($rate * 100) / $base;
            }
        }

        $displayCategory = strtoupper(trim((string) $this->col($itemDb, 'display', '')));
        $itemNameForDiamond = strtoupper(trim((string) $this->col($itemDb, 'name', $item->name)));
        $dmdPlate = strtoupper(trim((string) $this->col($itemDb, 'dmdplt', '')));
        $itemOpDmdWgt = $this->toNum($this->col($itemDb, 'opdmdwgt', 0));
        $itemOpStoneAmt = $this->toNum($this->col($itemDb, 'opstoneamt', 0));
        $diamondTypeCodes = ['D', 'DM', 'DMD', 'DIA', 'DIAMOND'];
        $isDiamondItem = in_array(strtoupper(trim((string) $itype)), $diamondTypeCodes, true)
            || str_contains($displayCategory, 'DIAM')
            || str_contains($itemNameForDiamond, 'DIAM')
            || $dmdPlate === 'D'
            || $itemGroup === 'DD'
            || $itemOpDmdWgt != 0.0
            || $itemOpStoneAmt > 0
            || $dmdWgt > 0
            || $dmdAmt > 0;

        $amount = $stkinnos === 'Y'
            ? round(($qty * $rate) + $stonePrice + $mcAmt + $dmdAmt, 2)
            : round(($netWgt * $rate) + $stonePrice + $mcAmt + $dmdAmt, 2);
        $warnings = [];
        if (in_array('disabled', $itemCols, true) && (int) ($itemDb->disabled ?? 0) === 1) {
            $warnings[] = 'Item is disabled.';
        }

        return response()->json([
            'ok' => true,
            'data' => [
                'source' => $source,
                'bcode' => $bcode,
                'item_code' => $itemCode,
                'item_name' => $this->col($itemDb, 'name', $item->name),
                'group_code' => $itemGroup,
                'item_type' => $itype,
                'display_category' => $displayCategory,
                'is_diamond' => $isDiamondItem,
                'purity' => $qtype,
                'model' => $model,
                'hsn' => trim((string) $this->col(
                    $itemDb,
                    'hsncode',
                    $this->col($itemDb, 'hsnvat', $this->col($itemDb, 'vatcode', $this->col($itemDb, 'hsn', '')))
                )),
                'item_part' => $part,
                'qty' => $qty,
                'weight' => round($weight, 3),
                'stone_wgt' => round($stoneWgt, 3),
                'net_wgt' => round($netWgt, 3),
                'rate' => round($rate, 2),
                'stone_price' => round($stonePrice, 2),
                'wstg_rate' => round($wastageRate, 3),
                'wastage' => round($wastageRate, 3),
                'mcrate' => round($mcRate, 2),
                'making_charge' => round($mcAmt, 2),
                'bcmcamt' => round($mcAmt2, 2),
                'amount' => $amount,
                'stock_qty' => $stockQty,
                'stock_wgt' => round($stockWgt, 3),
                'stock_flag' => $stockFlag,
                'stktype' => $stockType,
                'stktouch' => $stktouch,
                'stkinnos' => $stkinnos,
                'billtype' => $itemBillType,
                'vaperc' => $vaperc,
                'vaperqty' => $vaperqty,
                'cost' => $cost,
                'ttouch' => $ttouch,
                'scode' => $scode,
                'mccost' => $mcCost,
                'pcostperc' => $pcostPerc,
                'taxinternal' => $taxInternal ? 'Y' : 'N',
                'vaoffer' => $vaOffer ? 'Y' : 'N',
                'huid' => $huid,
                'dmdamt' => round($dmdAmt, 2),
                'dmdwgt' => round($dmdWgt, 3),
                'dmdnos' => $dmdNos,
                'dmdunit' => $dmdUnit,
                'warnings' => $warnings,
            ],
        ]);
    }

    private function mapBill(SalesBill $bill): array
    {
        $extra = $this->toArray($bill->extra_json);
        $legacySlno = $this->hasTable('salesm')
            ? (int) (DB::table('salesm')->where('billno', trim((string) $bill->bill_no))->value('slno') ?? 0)
            : 0;
        $calc = $this->calcTotals([
            'items' => $this->toArray($bill->items_json),
            'exchange' => $this->toArray($bill->exchange_json),
            'sales_return' => $this->toArray($bill->return_json),
            'extra' => $extra,
        ]);

        return [
            'slno' => $legacySlno,
            'bill_no' => $bill->bill_no,
            'bill_date' => optional($bill->bill_date)->format('d/m/Y'),
            'bill_time' => $bill->bill_time,
            'bill_type' => $bill->bill_type,
            'customer_name' => $bill->customer_name,
            'customer_code' => $bill->customer_code,
            'address' => $bill->address,
            'mobile' => $bill->mobile,
            'gst_no' => $bill->gst_no,
            'pan_no' => $bill->pan_no,
            'state_code' => $bill->state_code,
            'rate_per_gm' => (float) $bill->rate_per_gm,
            'counter_name' => $bill->counter_name,
            'counter_code' => $bill->counter_code,
            'salesman_name' => $bill->salesman_name,
            'salesman_code' => $bill->salesman_code,
            'agent_code' => $bill->agent_code,
            'approved_by' => $bill->approved_by,
            'cashbank_code' => $bill->cashbank_code,
            'co_party_code' => trim((string) ($extra['co_party_code'] ?? '')),
            'due_date' => trim((string) ($extra['due_date'] ?? '')),
            'vehicle_no' => trim((string) ($extra['vehicle_no'] ?? '')),
            'supply_place' => trim((string) ($extra['supply_place'] ?? '')),
            'distance' => (int) $this->toNum($extra['distance'] ?? 0),
            'bill_total' => (float) $bill->bill_total,
            'exchange_amount' => (float) $bill->exchange_amount,
            'return_amount' => (float) $bill->return_amount,
            'net_total' => (float) $bill->net_total,
            'status' => $bill->status,
            'cancel_reason' => $bill->cancel_reason,
            'is_quotation' => !empty($extra['is_quotation']),
            'source_quotation_bill_no' => trim((string) ($extra['source_quotation_bill_no'] ?? '')),
            'items' => $this->toArray($bill->items_json),
            'exchange' => $this->toArray($bill->exchange_json),
            'sales_return' => $this->toArray($bill->return_json),
            'extra' => $calc['extra'],
        ];
    }

    private function mapLegacySalesBill(object $salesm): array
    {
        $slno = (int) $this->toNum($salesm->slno ?? 0);
        $items = [];
        $exchange = [];
        $salesReturn = [];
        $ptaxPerc = 0.0;
        $ptaxAmt = 0.0;
        $srTaxPerc = 0.0;
        $srTaxAmt = 0.0;
        $srCessPerc = 0.0;
        $srCessAmt = 0.0;
        $srDiscountAmt = 0.0;

        if ($slno > 0 && $this->hasTable('salesd')) {
            $sdRows = DB::table('salesd')
                ->where('slno', $slno)
                ->orderBy('sno')
                ->get();

            $nameByCode = [];
            $groupByCode = [];
                    $displayByCode = [];
                    $diamondByCode = [];
            if ($this->hasTable('items')) {
                $codes = $sdRows->pluck('code')->filter()->map(fn ($c) => trim((string) $c))->unique()->values()->all();
                if ($codes !== []) {
                    $itemSelect = ['code', 'name'];
                    if (in_array('grpcode', $this->getColumns('items'), true)) {
                        $itemSelect[] = 'grpcode';
                    }
                    if (in_array('display', $this->getColumns('items'), true)) {
                        $itemSelect[] = 'display';
                    }
                    foreach (['dmdplt', 'opdmdwgt', 'opstoneamt'] as $col) {
                        if (in_array($col, $this->getColumns('items'), true)) {
                            $itemSelect[] = $col;
                        }
                    }
                    $itemMasterRows = DB::table('items')
                        ->whereIn('code', $codes)
                        ->get($itemSelect);
                    $nameByCode = $itemMasterRows
                        ->mapWithKeys(fn ($row) => [trim((string) $row->code) => trim((string) ($row->name ?? ''))])
                        ->toArray();
                    $groupByCode = $itemMasterRows
                        ->mapWithKeys(fn ($row) => [trim((string) $row->code) => strtoupper(trim((string) ($row->grpcode ?? '')))])
                        ->toArray();
                    $displayByCode = $itemMasterRows
                        ->mapWithKeys(fn ($row) => [trim((string) $row->code) => strtoupper(trim((string) ($row->display ?? '')))])
                        ->toArray();
                    $diamondByCode = $itemMasterRows
                        ->mapWithKeys(function ($row) {
                            $display = strtoupper(trim((string) ($row->display ?? '')));
                            $name = strtoupper(trim((string) ($row->name ?? '')));
                            $group = strtoupper(trim((string) ($row->grpcode ?? '')));
                            $dmdPlate = strtoupper(trim((string) ($row->dmdplt ?? '')));
                            $opDmdWgt = $this->toNum($row->opdmdwgt ?? 0);
                            $opStoneAmt = $this->toNum($row->opstoneamt ?? 0);

                            return [
                                trim((string) $row->code) => str_contains($display, 'DIAM')
                                    || str_contains($name, 'DIAM')
                                    || $group === 'DD'
                                    || $dmdPlate === 'D'
                                    || $opDmdWgt != 0.0
                                    || $opStoneAmt > 0,
                            ];
                        })
                        ->toArray();
                }
            }

            $items = $sdRows->map(function ($r) use ($nameByCode, $groupByCode, $displayByCode, $diamondByCode) {
                $itemCode = trim((string) ($r->code ?? ''));
                $itemName = trim((string) ($r->name ?? ''));
                if ($itemName === '' && isset($nameByCode[$itemCode])) {
                    $itemName = $nameByCode[$itemCode];
                }
                $qty = $this->toNum($r->qty ?? 0);
                $weight = $this->toNum($r->weight ?? 0);
                $stoneWgt = $this->toNum($r->stonewgt ?? 0);
                $amount = round($this->toNum($r->amount ?? 0), 2);
                return [
                    'item_code' => $itemCode,
                    'item_name' => $itemName,
                    'item_type' => 'G',
                    'group_code' => $groupByCode[$itemCode] ?? '',
                    'display_category' => $displayByCode[$itemCode] ?? '',
                    'is_diamond' => (bool) ($diamondByCode[$itemCode] ?? str_contains($displayByCode[$itemCode] ?? '', 'DIAM')),
                    'purity' => trim((string) ($r->iqtype ?? '')),
                    'model' => trim((string) ($r->model ?? '')),
                    'note' => trim((string) ($r->note ?? '')),
                    'huid' => trim((string) ($r->huid ?? '')),
                    'qty' => $qty,
                    'weight' => round($weight, 3),
                    'stone_wgt' => round($stoneWgt, 3),
                    'net_wgt' => round(max($weight - $stoneWgt, 0), 3),
                    'stone_price' => round($this->toNum($r->stoneprice ?? 0), 2),
                    'mc_perc' => round($this->toNum($r->vaperc ?? 0), 3),
                    'vaperc' => round($this->toNum($r->vaperc ?? 0), 3),
                    'va_disc_perc' => round($this->toNum($r->va_disc_perc ?? $r->vadiscperc ?? 0), 3),
                    'va_disc_amt' => round($this->toNum($r->va_disc_amt ?? $r->vadiscamt ?? 0), 2),
                    'making_charge' => round($this->toNum($r->mcharge ?? 0), 2),
                    'rate' => round($this->toNum($r->rate ?? 0), 2),
                    'amount' => $amount,
                ];
            })->values()->all();
        }

        if ($slno > 0 && $this->hasTable('purchased')) {
            $pRows = DB::table('purchased')
                ->where('slno', $slno)
                ->orderBy('sno')
                ->get();

            $exchange = $pRows->map(function ($r) {
                $qty = (int) $this->toNum($r->qty ?? 0);
                $weight = $this->toNum($r->weight ?? 0);
                $stoneWgt = $this->toNum($r->stwgt ?? 0);
                $lessWgt = $this->toNum($r->lesswgt ?? 0);
                $mudLess = $this->toNum($r->mud ?? 0);
                return [
                    'item_code' => trim((string) ($r->code ?? '')),
                    'item_name' => trim((string) ($r->name ?? '')),
                    'item_type' => 'G',
                    'qty' => $qty,
                    'weight' => round($weight, 3),
                    'mud_less' => round($mudLess, 3),
                    'touch' => round($this->toNum($r->touch ?? 0), 3),
                    'less_perc' => round($this->toNum($r->lessperc ?? 0), 3),
                    'less_wgt' => round($lessWgt, 3),
                    'stone_wgt' => round($stoneWgt, 3),
                    'stone_price' => round($this->toNum($r->stprice ?? 0), 2),
                    'net_wgt' => round(max($weight - $lessWgt - $stoneWgt - $mudLess, 0), 3),
                    'rate' => round($this->toNum($r->rate ?? 0), 2),
                    'rate2' => round($this->toNum($r->rate2 ?? 0), 2),
                    'amount' => round($this->toNum($r->amount ?? 0), 2),
                    'cost' => round($this->toNum($r->cost ?? 0), 2),
                    'stkfd' => trim((string) ($r->mark ?? '')),
                    'stktype' => trim((string) ($r->stktype ?? '')),
                    'qtype' => trim((string) ($r->iqtype ?? '')),
                    'stktouch' => round($this->toNum($r->stktouch ?? 0), 3),
                    'bcode' => (int) $this->toNum($r->bcode ?? 0),
                    'dmdamt' => round($this->toNum($r->dmdamt ?? 0), 2),
                    'dmdwgt' => round($this->toNum($r->dmdwgt ?? 0), 3),
                    'making_charge' => round($this->toNum($r->mcharge ?? 0), 2),
                    'mc_perc' => round($this->toNum($r->mperc ?? 0), 3),
                    'wastage' => round($this->toNum($r->wastage ?? 0), 3),
                    'batch' => trim((string) ($r->batch ?? '')),
                    'stkinnos' => strtoupper(trim((string) ($r->stkinnos ?? 'N'))),
                ];
            })->values()->all();
        }

        if ($slno > 0 && $this->hasTable('salesrd')) {
            $srRows = DB::table('salesrd')
                ->where('slno', $slno)
                ->orderBy('sno')
                ->get();

            $salesReturn = $srRows->map(function ($r) {
                $qty = (int) $this->toNum($r->qty ?? 0);
                $weight = $this->toNum($r->weight ?? 0);
                $stoneWgt = $this->toNum($r->stonewgt ?? 0);
                return [
                    'item_code' => trim((string) ($r->code ?? '')),
                    'item_name' => trim((string) ($r->name ?? '')),
                    'item_type' => 'G',
                    'model' => trim((string) ($r->model ?? '')),
                    'note' => trim((string) ($r->note ?? '')),
                    'qty' => $qty,
                    'weight' => round($weight, 3),
                    'stone_wgt' => round($stoneWgt, 3),
                    'net_wgt' => round(max($weight - $stoneWgt, 0), 3),
                    'stone_price' => round($this->toNum($r->stoneprice ?? 0), 2),
                    'wastage' => round($this->toNum($r->wastage ?? 0), 3),
                    'making_charge' => round($this->toNum($r->mcharge ?? 0), 2),
                    'rate' => round($this->toNum($r->rate ?? 0), 2),
                    'amount' => round($this->toNum($r->amount ?? 0), 2),
                    'bcode' => (int) $this->toNum($r->bcode ?? 0),
                    'stktype' => trim((string) ($r->stktype ?? '')),
                    'qtype' => trim((string) ($r->iqtype ?? '')),
                    'stktouch' => round($this->toNum($r->stktouch ?? 0), 3),
                    'dmdwgt' => round($this->toNum($r->dmdwgt ?? 0), 3),
                    'vaperc' => round($this->toNum($r->vaperc ?? 0), 3),
                    'cost' => round($this->toNum($r->cost ?? 0), 2),
                    'stkfd' => trim((string) ($r->mark ?? '')),
                ];
            })->values()->all();
        }

        if ($slno > 0 && $this->hasTable('salesrm')) {
            $salesRmSelect = ['discount', 'staxperc', 'staxamt', 'astamt'];
            if (Schema::hasColumn('salesrm', 'astperc')) {
                $salesRmSelect[] = 'astperc';
            }
            $salesReturnMaster = DB::table('salesrm')
                ->where('slno', $slno)
                ->first($salesRmSelect);
            if ($salesReturnMaster) {
                $srDiscountAmt = round($this->toNum($salesReturnMaster->discount ?? 0), 2);
                $srTaxPerc = round($this->toNum($salesReturnMaster->staxperc ?? 0), 3);
                $srTaxAmt = round($this->toNum($salesReturnMaster->staxamt ?? 0), 2);
                $srCessPerc = round($this->toNum($salesReturnMaster->astperc ?? 0), 3);
                $srCessAmt = round($this->toNum($salesReturnMaster->astamt ?? 0), 2);
            }
        }

        if ($slno > 0 && $this->hasTable('purchasem')) {
            $purchaseM = DB::table('purchasem')
                ->where('slno', $slno)
                ->first(['taxperc', 'taxamt']);
            if ($purchaseM) {
                $ptaxPerc = round($this->toNum($purchaseM->taxperc ?? 0), 3);
                $ptaxAmt = round($this->toNum($purchaseM->taxamt ?? 0), 2);
            }
        }

        $billType = trim((string) ($salesm->billtype ?? ''));
        if ($billType === '') {
            $billType = trim((string) ($salesm->saletype ?? ''));
        }
        if ($billType === '') {
            $billType = 'G';
        }
        $salesmanCode = trim((string) ($salesm->smcode ?? ''));
        $salesmanName = '';
        if ($salesmanCode !== '' && $this->hasTable('sman')) {
            $salesmanName = trim((string) (DB::table('sman')
                ->where(function ($q) use ($salesmanCode) {
                    $q->whereRaw('UPPER(TRIM(code)) = ?', [strtoupper($salesmanCode)])
                        ->orWhereRaw('UPPER(TRIM(name)) = ?', [strtoupper($salesmanCode)]);
                })
                ->value('name') ?? ''));
        }

        $schemeLedger = 'APP';
        $schemeAmt = round($this->toNum($salesm->schmamt ?? 0), 2);
        if ($schemeAmt != 0.0 && $this->hasTable('daybook')) {
            $schemeEntry = DB::table('daybook')
                ->where('slno', (int) $slno)
                ->whereIn('accode', ['APP', 'SCHMAMT'])
                ->whereRaw('ROUND(amount,2) = ?', [round(-$schemeAmt, 2)])
                ->orderByRaw("CASE WHEN accode='SCHMAMT' THEN 0 ELSE 1 END")
                ->first(['accode']);
            if ($schemeEntry) {
                $schemeLedger = strtoupper(trim((string) ($schemeEntry->accode ?? 'APP')));
            }
        }

        $extra = [
            'discount' => round($this->toNum($salesm->discount ?? 0), 2),
            'discount_perc' => round($this->toNum($salesm->discperc ?? 0), 3),
            'tax' => round($this->toNum($salesm->staxamt ?? 0), 2),
            'tax_perc' => round($this->toNum($salesm->staxperc ?? 0), 3),
            'ast' => round($this->toNum($salesm->astamt ?? 0), 2),
            'ast_perc' => round($this->toNum($salesm->astperc ?? 0), 3),
            'repair_charge' => round($this->toNum($salesm->rcamt ?? 0), 2),
            'hallmark_charge' => round($this->toNum($salesm->hmc ?? 0), 2),
            'advance' => round($this->toNum($salesm->advance ?? 0), 2),
            'fancy_amt' => round($this->toNum($salesm->fancyamt ?? 0), 2),
            'scheme_amt' => $schemeAmt,
            'scheme_ledger' => $schemeLedger,
            'bank_charge' => round($this->toNum($salesm->bcharge ?? 0), 2),
            'bc_perc' => round($this->toNum($salesm->bcperc ?? 0), 3),
            'cc_amt' => round($this->toNum($salesm->ccamt ?? 0), 2),
            'chq_amt' => round($this->toNum($salesm->chqamt ?? 0), 2),
            'chq_bank' => trim((string) ($salesm->chqbank ?? '')),
            'chq_no' => trim((string) ($salesm->chqno ?? '')),
            'chq_date' => !empty($salesm->chqdate) ? Carbon::parse((string) $salesm->chqdate)->format('d/m/Y') : '',
            'chq_pdc' => strtoupper(trim((string) ($salesm->chqpdc ?? 'N'))) === 'Y',
            'cc_pdc' => strtoupper(trim((string) ($salesm->ccpdc ?? 'N'))) === 'Y',
            'add_bank_charge' => strtoupper(trim((string) ($salesm->addbcharge ?? 'N'))) === 'Y',
            'opening_balance' => round($this->toNum($salesm->ob ?? 0), 2),
            'received' => round($this->toNum($salesm->ramt ?? 0), 2),
            'cash_amt' => round(max(
                $this->toNum($salesm->ramt ?? 0)
                - $this->toNum($salesm->ccamt ?? 0)
                - $this->toNum($salesm->chqamt ?? 0),
                0
            ), 2),
            'credit' => strtoupper(trim((string) ($salesm->loan ?? 'N'))) === 'Y',
            'note' => trim((string) ($salesm->note ?? '')),
            'tcs_perc' => round($this->toNum($salesm->tcsperc ?? 0), 3),
            'tcs_amt' => round($this->toNum($salesm->tcsamt ?? 0), 2),
            'redeem_points' => round($this->toNum($salesm->redmpoints ?? 0), 2),
            'ptax_perc' => $ptaxPerc,
            'ptax_amt' => $ptaxAmt,
            'sr_discount_amt' => $srDiscountAmt,
            'sr_tax_perc' => $srTaxPerc,
            'sr_tax_amt' => $srTaxAmt,
            'sr_cess_perc' => $srCessPerc,
            'sr_cess_amt' => $srCessAmt,
        ];

        return [
            'slno' => $slno,
            'bill_no' => trim((string) ($salesm->billno ?? '')),
            'bill_date' => !empty($salesm->tdate) ? Carbon::parse((string) $salesm->tdate)->format('d/m/Y') : '',
            'bill_time' => trim((string) ($salesm->ttime ?? '')),
            'bill_type' => $billType,
            'customer_name' => trim((string) ($salesm->custname ?? '')),
            'customer_code' => trim((string) ($salesm->custcode ?? '')),
            'address' => trim((string) ($salesm->addr ?? '')),
            'mobile' => trim((string) ($salesm->mobno ?? '')),
            'gst_no' => trim((string) ($salesm->tin ?? '')),
            'pan_no' => trim((string) ($salesm->pan ?? '')),
            'state_code' => trim((string) ($salesm->statecode ?? '')),
            'rate_per_gm' => round($this->toNum($salesm->grate ?? 0), 2),
            'counter_name' => '',
            'counter_code' => trim((string) ($salesm->counter ?? '')),
            'salesman_name' => $salesmanName,
            'salesman_code' => $salesmanCode,
            'agent_code' => trim((string) ($salesm->agcode ?? '')),
            'approved_by' => trim((string) ($salesm->approvedby ?? '')),
            'cashbank_code' => trim((string) ($salesm->cbcode ?? '')),
            'co_party_code' => trim((string) ($salesm->cocode ?? '')),
            'due_date' => !empty($salesm->duedate) ? Carbon::parse((string) $salesm->duedate)->format('d/m/Y') : '',
            'vehicle_no' => trim((string) ($salesm->vehno ?? '')),
            'supply_place' => trim((string) ($salesm->placeos ?? '')),
            'distance' => (int) $this->toNum($salesm->distance ?? 0),
            'bill_total' => round($this->toNum($salesm->billamt ?? 0), 2),
            'exchange_amount' => round($this->toNum($salesm->eamt ?? 0), 2),
            'return_amount' => round($this->toNum($salesm->sretamt ?? 0), 2),
            'net_total' => round($this->toNum($salesm->netamt ?? 0), 2),
            'status' => ((int) $this->toNum($salesm->status ?? 1)) === 0 ? 'cancelled' : 'saved',
            'cancel_reason' => null,
            'is_quotation' => (int) $this->toNum($salesm->control ?? 1) !== 1,
            'source_quotation_bill_no' => '',
            'items' => $items,
            'exchange' => $exchange,
            'sales_return' => $salesReturn,
            'extra' => $extra,
        ];
    }

    private function findBillData(string $billNo): ?array
    {
        if ($this->hasSalesBillsTable()) {
            $bill = SalesBill::query()->where('bill_no', $billNo)->first();
            if ($bill) {
                return $this->mapBill($bill);
            }
        }

        if ($this->hasTable('salesm')) {
            $legacy = DB::table('salesm')->where('billno', $billNo)->first();
            if ($legacy) {
                return $this->mapLegacySalesBill($legacy);
            }
        }

        return null;
    }

    private function buildEInvoicePayload(array $billData, array $settingsPayload): array
    {
        $company = (array) ($settingsPayload['Company'] ?? []);
        $extra = (array) ($billData['extra'] ?? []);

        return [
            'transaction_type' => 'SALES',
            'bill_no' => trim((string) ($billData['bill_no'] ?? '')),
            'bill_date' => trim((string) ($billData['bill_date'] ?? '')),
            'bill_time' => trim((string) ($billData['bill_time'] ?? '')),
            'bill_type' => trim((string) ($billData['bill_type'] ?? '')),
            'customer' => [
                'code' => trim((string) ($billData['customer_code'] ?? '')),
                'name' => trim((string) ($billData['customer_name'] ?? '')),
                'address' => trim((string) ($billData['address'] ?? '')),
                'mobile' => trim((string) ($billData['mobile'] ?? '')),
                'gst_no' => trim((string) ($billData['gst_no'] ?? '')),
                'pan_no' => trim((string) ($billData['pan_no'] ?? '')),
                'state_code' => trim((string) ($billData['state_code'] ?? '')),
            ],
            'company' => [
                'name' => trim((string) ($company['Name'] ?? '')),
                'address' => trim((string) implode(' ', array_filter([
                    (string) ($company['Addr'] ?? ''),
                    (string) ($company['Addr1'] ?? ''),
                    (string) ($company['Addr2'] ?? ''),
                ]))),
                'phone' => trim((string) ($company['Phone'] ?? '')),
                'gst_no' => trim((string) ($company['KGST'] ?? '')),
                'state_code' => trim((string) ($company['DefStateCode'] ?? '')),
            ],
            'totals' => [
                'bill_total' => round($this->toNum($billData['bill_total'] ?? 0), 2),
                'exchange_amount' => round($this->toNum($billData['exchange_amount'] ?? 0), 2),
                'return_amount' => round($this->toNum($billData['return_amount'] ?? 0), 2),
                'net_total' => round($this->toNum($billData['net_total'] ?? 0), 2),
                'discount' => round($this->toNum($extra['discount'] ?? 0), 2),
                'tax' => round($this->toNum($extra['tax'] ?? 0), 2),
                'tax_perc' => round($this->toNum($extra['tax_perc'] ?? 0), 3),
                'tcs_amt' => round($this->toNum($extra['tcs_amt'] ?? 0), 2),
                'received' => round($this->toNum($extra['received'] ?? 0), 2),
                'balance' => round($this->toNum($extra['balance'] ?? 0), 2),
            ],
            'items' => collect($billData['items'] ?? [])->map(function ($item) {
                return [
                    'item_code' => trim((string) ($item['item_code'] ?? '')),
                    'item_name' => trim((string) ($item['item_name'] ?? '')),
                    'purity' => trim((string) ($item['purity'] ?? $item['qtype'] ?? '')),
                    'qty' => round($this->toNum($item['qty'] ?? 0), 3),
                    'weight' => round($this->toNum($item['weight'] ?? 0), 3),
                    'net_wgt' => round($this->toNum($item['net_wgt'] ?? 0), 3),
                    'rate' => round($this->toNum($item['rate'] ?? 0), 2),
                    'making_charge' => round($this->toNum($item['making_charge'] ?? 0), 2),
                    'stone_price' => round($this->toNum($item['stone_price'] ?? 0), 2),
                    'amount' => round($this->toNum($item['amount'] ?? 0), 2),
                    'hsn' => trim((string) ($item['hsn_code'] ?? $item['hsn'] ?? '')),
                ];
            })->values()->all(),
            'exchange_items' => collect($billData['exchange'] ?? [])->map(function ($item) {
                return [
                    'item_code' => trim((string) ($item['item_code'] ?? '')),
                    'item_name' => trim((string) ($item['item_name'] ?? '')),
                    'qty' => round($this->toNum($item['qty'] ?? 0), 3),
                    'weight' => round($this->toNum($item['weight'] ?? 0), 3),
                    'amount' => round($this->toNum($item['amount'] ?? 0), 2),
                ];
            })->values()->all(),
            'meta' => [
                'source' => 'goldapp',
                'generated_at' => now()->toDateTimeString(),
            ],
        ];
    }

    private function isB2BEInvoiceBill(array $billData): bool
    {
        $billType = strtoupper(trim((string) ($billData['bill_type'] ?? '')));
        $billNo = strtoupper(trim((string) ($billData['bill_no'] ?? '')));
        $candidates = [$billType, $billNo];

        if ($this->hasTable('salestype') && $billType !== '') {
            $cols = $this->getColumns('salestype');
            $row = DB::table('salestype')
                ->whereRaw('upper(trim(code)) = ?', [$billType])
                ->first();
            if ($row) {
                foreach (['code', 'name', 'prefix'] as $col) {
                    if (in_array($col, $cols, true)) {
                        $candidates[] = strtoupper(trim((string) ($row->{$col} ?? '')));
                    }
                }
            }
        }

        foreach ($candidates as $text) {
            if ($text === '') {
                continue;
            }
            if ($text === 'B2B' || str_contains($text, 'B2B') || str_contains($text, 'B3B')) {
                return true;
            }
            if (preg_match('/^B[23][A-Z0-9\/-]*/', $text) === 1) {
                return true;
            }
        }

        return false;
    }

    private function fetchMastersIndiaToken(array $apiSettings, string $username, string $password): string
    {
        $tokenUrl = trim((string) ($apiSettings['EWAYTOKENURL'] ?? $apiSettings['EINVOICETOKENURL'] ?? ''));
        if ($tokenUrl === '') {
            $tokenUrl = 'https://prod-api.mastersindia.co/api/v1/token-auth/';
        }

        $response = $this->mastersIndiaHttp(15)
            ->acceptJson()
            ->asJson()
            ->post($tokenUrl, [
                'username' => $username,
                'password' => $password,
            ]);

        $decoded = $response->json();
        $token = is_array($decoded) ? trim((string) ($decoded['token'] ?? '')) : '';
        if (!$response->successful() || $token === '') {
            $message = is_array($decoded)
                ? (string) ($decoded['error'] ?? $decoded['message'] ?? 'Unable to login to Masters India.')
                : trim($response->body());
            throw new \RuntimeException($message !== '' ? $message : 'Unable to login to Masters India.');
        }

        return $token;
    }

    private function mastersIndiaHttp(int $timeout = 20): \Illuminate\Http\Client\PendingRequest
    {
        $curlOptions = [];
        if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
            $curlOptions[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
        }

        return Http::connectTimeout(5)
            ->timeout($timeout)
            ->retry(0, 0)
            ->withOptions($curlOptions !== [] ? ['curl' => $curlOptions] : []);
    }

    private function buildMastersIndiaEInvoicePayload(array $billData, array $settingsPayload, array $apiSettings): array
    {
        $company = (array) ($settingsPayload['Company'] ?? []);
        $extra = (array) ($billData['extra'] ?? []);

        $sellerGstin = strtoupper(trim((string) (
            $apiSettings['EINVOICESELLERGSTIN']
            ?? $apiSettings['EINVOICEUSERGSTIN']
            ?? $company['KGST']
            ?? ''
        )));
        $buyerOverrideGstin = strtoupper(trim((string) ($apiSettings['EINVOICEBUYERGSTIN'] ?? '')));
        $buyerGstin = $buyerOverrideGstin !== ''
            ? $buyerOverrideGstin
            : strtoupper(trim((string) ($billData['gst_no'] ?? '')));
        $userGstin = strtoupper(trim((string) ($apiSettings['EINVOICEUSERGSTIN'] ?? '')));
        if ($userGstin === '') {
            $userGstin = $sellerGstin;
        }

        $sellerState = $this->mastersStateCode((string) ($company['DefStateCode'] ?? ''), $sellerGstin);
        $buyerState = $this->mastersStateCode((string) ($billData['state_code'] ?? ''), $buyerGstin);
        if ($buyerState === '') {
            $buyerState = $sellerState;
        }
        $interState = $sellerState !== '' && $buyerState !== '' && $sellerState !== $buyerState;

        $sellerAddress = trim((string) ($company['Addr'] ?? ''));
        if ($sellerAddress === '') {
            $sellerAddress = trim((string) (($company['Addr1'] ?? '') . ' ' . ($company['Addr2'] ?? '')));
        }
        if ($sellerAddress === '') {
            $sellerAddress = $this->readGeneralsValue('SHOPADDR', '');
        }
        $sellerName = trim((string) ($company['Name'] ?? ''));
        if ($sellerName === '') {
            $sellerName = $this->readGeneralsValue('SHOPNM', 'SALEENA GOLD AND DIAMONDS');
        }
        $sellerPhone = trim((string) ($company['Phone'] ?? ''));
        if ($sellerPhone === '') {
            $sellerPhone = $this->readGeneralsValue('SHOPPHONE', '');
        }
        $buyerAddress = trim((string) ($billData['address'] ?? ''));
        $buyerName = trim((string) ($billData['customer_name'] ?? ''));
        if ($buyerName === '') {
            $buyerName = 'CUSTOMER';
        }

        $items = [];
        $assessableTotal = 0.0;
        $cgstTotal = 0.0;
        $sgstTotal = 0.0;
        $igstTotal = 0.0;

        foreach (array_values((array) ($billData['items'] ?? [])) as $index => $item) {
            $qty = max(1.0, $this->toNum($item['qty'] ?? 1));
            $amount = round($this->toNum($item['amount'] ?? 0), 2);
            if ($amount <= 0) {
                $amount = round($this->toNum($item['rate'] ?? 0) * $qty, 2);
            }
            $taxRate = round($this->toNum($extra['tax_perc'] ?? 0), 2);
            $taxAmount = round($amount * $taxRate / 100, 2);
            $igst = $interState ? $taxAmount : 0.0;
            $cgst = $interState ? 0.0 : round($taxAmount / 2, 2);
            $sgst = $interState ? 0.0 : round($taxAmount - $cgst, 2);
            $hsn = preg_replace('/\D+/', '', trim((string) ($item['hsn'] ?? $item['hsn_code'] ?? ''))) ?: '7113';

            $assessableTotal += $amount;
            $cgstTotal += $cgst;
            $sgstTotal += $sgst;
            $igstTotal += $igst;

            $items[] = [
                'item_serial_number' => (string) ($index + 1),
                'product_description' => mb_substr(trim((string) ($item['item_name'] ?? $item['name'] ?? 'JEWELLERY')), 0, 300) ?: 'JEWELLERY',
                'is_service' => 'N',
                'hsn_code' => $hsn,
                'bar_code' => $this->mastersBarCode((string) ($item['item_code'] ?? '')),
                'quantity' => $qty,
                'free_quantity' => 0,
                'unit' => 'NOS',
                'unit_price' => round($amount / $qty, 2),
                'total_amount' => $amount,
                'pre_tax_value' => 0,
                'discount' => 0,
                'other_charge' => 0,
                'assessable_value' => $amount,
                'gst_rate' => $taxRate,
                'igst_amount' => $igst,
                'cgst_amount' => $cgst,
                'sgst_amount' => $sgst,
                'cess_rate' => 0,
                'cess_amount' => 0,
                'cess_nonadvol_amount' => 0,
                'state_cess_rate' => 0,
                'state_cess_amount' => 0,
                'state_cess_nonadvol_amount' => 0,
                'total_item_value' => round($amount + $igst + $cgst + $sgst, 2),
            ];
        }

        if ($items === []) {
            $amount = max(1.0, round($this->toNum($billData['bill_total'] ?? $billData['net_total'] ?? 1), 2));
            $assessableTotal = $amount;
            $items[] = [
                'item_serial_number' => '1',
                'product_description' => 'JEWELLERY',
                'is_service' => 'N',
                'hsn_code' => '7113',
                'quantity' => 1,
                'free_quantity' => 0,
                'unit' => 'NOS',
                'unit_price' => $amount,
                'total_amount' => $amount,
                'pre_tax_value' => 0,
                'discount' => 0,
                'other_charge' => 0,
                'assessable_value' => $amount,
                'gst_rate' => 0,
                'igst_amount' => 0,
                'cgst_amount' => 0,
                'sgst_amount' => 0,
                'cess_rate' => 0,
                'cess_amount' => 0,
                'cess_nonadvol_amount' => 0,
                'state_cess_rate' => 0,
                'state_cess_amount' => 0,
                'state_cess_nonadvol_amount' => 0,
                'total_item_value' => $amount,
            ];
        }

        $invoiceValue = round($assessableTotal + $cgstTotal + $sgstTotal + $igstTotal, 2);

        return [
            'user_gstin' => $userGstin,
            'data_source' => 'erp',
            'transaction_details' => [
                'supply_type' => 'B2B',
                'charge_type' => 'N',
                'igst_on_intra' => 'N',
                'ecommerce_gstin' => '',
            ],
            'document_details' => [
                'document_type' => 'INV',
                'document_number' => $this->mastersDocumentNumber((string) ($billData['bill_no'] ?? '')),
                'document_date' => $this->mastersDate((string) ($billData['bill_date'] ?? '')),
            ],
            'seller_details' => [
                'gstin' => $sellerGstin,
                'legal_name' => mb_substr($sellerName, 0, 100),
                'trade_name' => mb_substr($sellerName, 0, 100),
                'address1' => $this->mastersAddressLine($sellerAddress, 'Shop Address'),
                'address2' => $this->mastersAddressLine((string) ($company['Addr2'] ?? ''), ''),
                'location' => $this->mastersLocation((string) ($company['Branch'] ?? $company['Loc'] ?? 'KOZHIKODE')),
                'pincode' => $this->mastersPin($company['PIN'] ?? $company['Pin'] ?? null, $sellerState),
                'state_code' => $sellerState,
                'phone_number' => preg_replace('/\D+/', '', $sellerPhone) ?: '',
                'email' => trim((string) ($company['HOMailID'] ?? '')),
            ],
            'buyer_details' => [
                'gstin' => $buyerGstin,
                'legal_name' => mb_substr($buyerName, 0, 100),
                'trade_name' => mb_substr($buyerName, 0, 100),
                'address1' => $this->mastersAddressLine($buyerAddress, 'Buyer Address'),
                'address2' => '',
                'location' => $this->mastersLocation((string) ($billData['city'] ?? 'CITY')),
                'pincode' => $this->mastersPin($billData['pincode'] ?? null, $buyerState),
                'place_of_supply' => $buyerState,
                'state_code' => $buyerState,
                'phone_number' => preg_replace('/\D+/', '', (string) ($billData['mobile'] ?? '')) ?: '',
                'email' => '',
            ],
            'value_details' => [
                'total_assessable_value' => round($assessableTotal, 2),
                'total_cgst_value' => round($cgstTotal, 2),
                'total_sgst_value' => round($sgstTotal, 2),
                'total_igst_value' => round($igstTotal, 2),
                'total_cess_value' => 0,
                'total_cess_value_of_state' => 0,
                'total_discount' => 0,
                'total_other_charge' => 0,
                'total_invoice_value' => $invoiceValue,
                'round_off_amount' => 0,
                'total_invoice_value_additional_currency' => 0,
            ],
            'item_list' => $items,
        ];
    }

    private function buildMastersIndiaEWayBillPayload(array $billData, array $settingsPayload, array $apiSettings): array
    {
        $company = (array) ($settingsPayload['Company'] ?? []);
        $extra = (array) ($billData['extra'] ?? []);

        $sellerGstin = strtoupper(trim((string) (
            $apiSettings['EWAYUSERGSTIN']
            ?? $apiSettings['EWAYSELLERGSTIN']
            ?? $apiSettings['EINVOICESELLERGSTIN']
            ?? $apiSettings['EINVOICEUSERGSTIN']
            ?? $company['KGST']
            ?? ''
        )));
        $buyerGstin = strtoupper(trim((string) ($billData['gst_no'] ?? '')));
        if (!preg_match('/^\d{2}[A-Z0-9]{13}$/', $buyerGstin)) {
            $buyerGstin = 'URP';
        }

        $sellerStateCode = $this->mastersStateCode((string) ($company['DefStateCode'] ?? ''), $sellerGstin);
        $buyerStateCode = $buyerGstin !== 'URP' ? $this->mastersStateCode((string) ($billData['state_code'] ?? ''), $buyerGstin) : '';
        if ($buyerStateCode === '') {
            $buyerStateCode = $sellerStateCode;
        }
        $interState = $sellerStateCode !== '' && $buyerStateCode !== '' && $sellerStateCode !== $buyerStateCode;

        $sellerName = trim((string) ($company['Name'] ?? '')) ?: $this->readGeneralsValue('SHOPNM', 'SALEENA GOLD AND DIAMONDS');
        $sellerAddress = trim((string) ($company['Addr'] ?? '')) ?: $this->readGeneralsValue('SHOPADDR', 'Shop Address');
        $sellerAddress2 = trim((string) (($company['Addr1'] ?? '') . ' ' . ($company['Addr2'] ?? '')));
        $buyerName = trim((string) ($billData['customer_name'] ?? '')) ?: 'CUSTOMER';
        $buyerAddress = trim((string) ($billData['address'] ?? '')) ?: 'Buyer Address';

        $taxRate = round($this->toNum($extra['tax_perc'] ?? 0), 3);
        $cgstRate = $interState ? 0.0 : round($taxRate / 2, 3);
        $sgstRate = $interState ? 0.0 : round($taxRate / 2, 3);
        $igstRate = $interState ? $taxRate : 0.0;

        $items = [];
        $taxableTotal = 0.0;
        foreach (array_values((array) ($billData['items'] ?? [])) as $item) {
            $qty = max(1.0, $this->toNum($item['qty'] ?? 1));
            $amount = round($this->toNum($item['amount'] ?? 0), 2);
            if ($amount <= 0) {
                $amount = round($this->toNum($item['rate'] ?? 0) * $qty, 2);
            }
            if ($amount <= 0) {
                continue;
            }
            $taxableTotal += $amount;
            $name = mb_substr(trim((string) ($item['item_name'] ?? $item['name'] ?? 'JEWELLERY')), 0, 100) ?: 'JEWELLERY';
            $items[] = [
                'product_name' => $name,
                'product_description' => $name,
                'hsn_code' => preg_replace('/\D+/', '', trim((string) ($item['hsn'] ?? $item['hsn_code'] ?? ''))) ?: '7113',
                'quantity' => $qty,
                'unit_of_product' => 'NOS',
                'cgst_rate' => $cgstRate,
                'sgst_rate' => $sgstRate,
                'igst_rate' => $igstRate,
                'cess_rate' => 0,
                'cessNonAdvol' => 0,
                'taxable_amount' => $amount,
            ];
        }

        if ($items === []) {
            $amount = max(1.0, round($this->toNum($billData['bill_total'] ?? $billData['net_total'] ?? 1), 2));
            $taxableTotal = $amount;
            $items[] = [
                'product_name' => 'JEWELLERY',
                'product_description' => 'JEWELLERY',
                'hsn_code' => '7113',
                'quantity' => 1,
                'unit_of_product' => 'NOS',
                'cgst_rate' => $cgstRate,
                'sgst_rate' => $sgstRate,
                'igst_rate' => $igstRate,
                'cess_rate' => 0,
                'cessNonAdvol' => 0,
                'taxable_amount' => $amount,
            ];
        }

        $cgstAmount = $interState ? 0.0 : round($taxableTotal * $taxRate / 200, 2);
        $sgstAmount = $interState ? 0.0 : round($taxableTotal * $taxRate / 200, 2);
        $igstAmount = $interState ? round($taxableTotal * $taxRate / 100, 2) : 0.0;
        $invoiceValue = round($this->toNum($billData['net_total'] ?? 0), 2);
        if ($invoiceValue <= 0) {
            $invoiceValue = round($taxableTotal + $cgstAmount + $sgstAmount + $igstAmount, 2);
        }

        $sellerStateName = $this->mastersStateName($sellerStateCode);
        $buyerStateName = $this->mastersStateName($buyerStateCode);
        $sellerPin = $this->mastersPin($company['PIN'] ?? $company['Pin'] ?? null, $sellerStateCode);
        $buyerPin = $this->mastersPin($billData['pincode'] ?? null, $buyerStateCode);
        $vehicleNo = strtoupper(preg_replace('/[^A-Z0-9]+/', '', (string) ($billData['vehicle_no'] ?? '')));
        $distance = (int) $this->toNum($billData['distance'] ?? 0);
        if ($distance < 0 || $distance > 4000) {
            $distance = 0;
        }

        return [
            'userGstin' => $sellerGstin,
            'supply_type' => 'outward',
            'sub_supply_type' => 'Supply',
            'sub_supply_description' => '',
            'document_type' => 'Tax Invoice',
            'document_number' => $this->mastersDocumentNumber((string) ($billData['bill_no'] ?? '')),
            'document_date' => $this->mastersDate((string) ($billData['bill_date'] ?? '')),
            'gstin_of_consignor' => $sellerGstin,
            'legal_name_of_consignor' => mb_substr($sellerName, 0, 100),
            'address1_of_consignor' => $this->mastersAddressLine($sellerAddress, 'Shop Address'),
            'address2_of_consignor' => $this->mastersAddressLine($sellerAddress2, ''),
            'place_of_consignor' => $this->mastersLocation((string) ($company['Branch'] ?? $company['Loc'] ?? 'KOZHIKODE')),
            'pincode_of_consignor' => $sellerPin,
            'state_of_consignor' => $sellerStateName,
            'actual_from_state_name' => $sellerStateName,
            'gstin_of_consignee' => $buyerGstin,
            'legal_name_of_consignee' => mb_substr($buyerName, 0, 100),
            'address1_of_consignee' => $this->mastersAddressLine($buyerAddress, 'Buyer Address'),
            'address2_of_consignee' => '',
            'place_of_consignee' => $this->mastersLocation((string) ($billData['supply_place'] ?? $billData['city'] ?? 'CITY')),
            'pincode_of_consignee' => $buyerPin,
            'state_of_supply' => $buyerStateName,
            'actual_to_state_name' => $buyerStateName,
            'transaction_type' => 1,
            'other_value' => 0,
            'total_invoice_value' => $invoiceValue,
            'taxable_amount' => round($taxableTotal, 2),
            'cgst_amount' => $cgstAmount,
            'sgst_amount' => $sgstAmount,
            'igst_amount' => $igstAmount,
            'cess_amount' => 0,
            'cess_nonadvol_value' => 0,
            'transporter_id' => trim((string) ($apiSettings['EWAYTRANSPORTERID'] ?? '')),
            'transporter_name' => trim((string) ($apiSettings['EWAYTRANSPORTERNAME'] ?? '')),
            'transporter_document_number' => '',
            'transporter_document_date' => '',
            'transportation_mode' => 'Road',
            'transportation_distance' => (string) $distance,
            'vehicle_number' => $vehicleNo,
            'vehicle_type' => 'Regular',
            'generate_status' => 1,
            'data_source' => 'erp',
            'user_ref' => '',
            'location_code' => '',
            'eway_bill_status' => 'ABC',
            'auto_print' => 'N',
            'email' => '',
            'delete_record' => 'N',
            'itemList' => $items,
        ];
    }

    private function mastersBarCode(string $itemCode): string
    {
        $code = trim($itemCode);
        if ($code === '') {
            return 'ITM';
        }
        if (mb_strlen($code) >= 3) {
            return mb_substr($code, 0, 64);
        }
        return str_pad($code, 3, '0', STR_PAD_LEFT);
    }

    private function mastersDocumentNumber(string $billNo): string
    {
        $docNo = strtoupper(trim($billNo));
        $docNo = preg_replace('/[^A-Z0-9\/-]+/', '', $docNo) ?: 'INV1';
        $docNo = ltrim($docNo, '0/-');
        if ($docNo === '') {
            return 'INV1';
        }

        $docNo = preg_replace_callback('/(\d+)$/', function (array $match) {
            $serial = ltrim($match[1], '0');
            return $serial !== '' ? $serial : '1';
        }, $docNo) ?: $docNo;

        if (strlen($docNo) <= 16) {
            return $docNo;
        }

        $parts = preg_split('/[\/-]+/', $docNo, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $compact = implode('', $parts);
        if ($compact !== '' && strlen($compact) <= 16) {
            return $compact;
        }

        $serial = (string) (preg_match('/(\d+)$/', $docNo, $m) ? $m[1] : '');
        if ($serial !== '') {
            $prefix = preg_replace('/[^A-Z0-9]+/', '', substr($docNo, 0, -strlen($serial))) ?: 'INV';
            return substr($prefix, 0, max(1, 16 - strlen($serial))) . $serial;
        }

        return substr($compact !== '' ? $compact : $docNo, -16);
    }

    private function mastersDate(string $value): string
    {
        $value = trim($value);
        // Try DD/MM/YYYY first (Indian format stored in this system)
        $dt = \DateTime::createFromFormat('d/m/Y', $value);
        if ($dt !== false) {
            return $dt->format('d/m/Y');
        }
        // Fallback for YYYY-MM-DD or other unambiguous formats
        $ts = strtotime($value);
        return $ts ? date('d/m/Y', $ts) : date('d/m/Y');
    }

    private function mastersStateCode(string $stateCode, string $gstin = ''): string
    {
        $stateCode = trim($stateCode);
        if (preg_match('/^\d{1,2}$/', $stateCode)) {
            return str_pad($stateCode, 2, '0', STR_PAD_LEFT);
        }
        $gstin = strtoupper(trim($gstin));
        return preg_match('/^\d{2}[A-Z0-9]{13}$/', $gstin) ? substr($gstin, 0, 2) : '';
    }

    private function mastersStateName(string $stateCode): string
    {
        $stateCode = str_pad(preg_replace('/\D+/', '', $stateCode), 2, '0', STR_PAD_LEFT);
        return [
            '01' => 'JAMMU AND KASHMIR',
            '02' => 'HIMACHAL PRADESH',
            '03' => 'PUNJAB',
            '04' => 'CHANDIGARH',
            '05' => 'UTTARAKHAND',
            '06' => 'HARYANA',
            '07' => 'DELHI',
            '08' => 'RAJASTHAN',
            '09' => 'UTTAR PRADESH',
            '10' => 'BIHAR',
            '11' => 'SIKKIM',
            '12' => 'ARUNACHAL PRADESH',
            '13' => 'NAGALAND',
            '14' => 'MANIPUR',
            '15' => 'MIZORAM',
            '16' => 'TRIPURA',
            '17' => 'MEGHALAYA',
            '18' => 'ASSAM',
            '19' => 'WEST BENGAL',
            '20' => 'JHARKHAND',
            '21' => 'ODISHA',
            '22' => 'CHHATTISGARH',
            '23' => 'MADHYA PRADESH',
            '24' => 'GUJARAT',
            '25' => 'DAMAN AND DIU',
            '26' => 'DADRA AND NAGAR HAVELI AND DAMAN AND DIU',
            '27' => 'MAHARASHTRA',
            '28' => 'ANDHRA PRADESH',
            '29' => 'KARNATAKA',
            '30' => 'GOA',
            '31' => 'LAKSHADWEEP',
            '32' => 'KERALA',
            '33' => 'TAMIL NADU',
            '34' => 'PUDUCHERRY',
            '35' => 'ANDAMAN AND NICOBAR ISLANDS',
            '36' => 'TELANGANA',
            '37' => 'ANDHRA PRADESH',
            '38' => 'LADAKH',
            '96' => 'OTHER COUNTRY',
            '97' => 'OTHER TERRITORY',
            '99' => 'OTHER COUNTRY',
        ][$stateCode] ?? 'KERALA';
    }

    private function mastersPin(mixed $pin, string $stateCode): int
    {
        $digits = preg_replace('/\D+/', '', (string) $pin);
        if (strlen($digits) === 6) {
            return (int) $digits;
        }
        return match ($stateCode) {
            '05' => 263001,
            '06' => 122001,
            '07' => 110001,
            '09' => 201301,
            '24' => 380001,
            '27' => 400001,
            '29' => 560001,
            '32' => 673001,
            '33' => 600001,
            default => 673001,
        };
    }

    private function mastersAddressLine(string $value, string $fallback): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value));
        if (mb_strlen($value) < 3) {
            if ($fallback === '') {
                return '';
            }
            $value = $fallback;
        }
        return mb_substr($value, 0, 100);
    }

    private function mastersLocation(string $value): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value));
        if (mb_strlen($value) < 3) {
            $value = 'CITY';
        }
        return mb_substr($value, 0, 50);
    }

    private function buildEInvoiceHttpRequest(array $apiSettings, string $username, string $password)
    {
        $authMode = strtolower(trim((string) ($apiSettings['EINVOICEAUTHMODE'] ?? 'basic')));
        $apiKey = trim((string) ($apiSettings['EINVOICEAPIKEY'] ?? ''));
        $apiKeyHeader = trim((string) ($apiSettings['EINVOICEAPIKEYHEADER'] ?? 'x-api-key')) ?: 'x-api-key';

        $request = Http::timeout(30)
            ->acceptJson()
            ->asJson();

        $headers = $this->parseEInvoiceHeaders((string) ($apiSettings['EINVOICEEXTRAHEADERS'] ?? ''));

        if ($authMode === 'basic') {
            $request = $request->withBasicAuth($username, $password);
        } elseif ($authMode === 'bearer') {
            $request = $request->withToken($apiKey);
        } elseif ($authMode === 'api_key' || $authMode === 'apikey') {
            $headers[$apiKeyHeader] = $apiKey;
        } elseif ($authMode !== 'none') {
            $headers[$apiKeyHeader] = $apiKey;
        }

        return $headers !== [] ? $request->withHeaders($headers) : $request;
    }

    private function parseEInvoiceHeaders(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return collect($decoded)
                ->filter(fn ($value, $key) => is_string($key) && trim($key) !== '' && !is_array($value))
                ->mapWithKeys(fn ($value, $key) => [trim((string) $key) => trim((string) $value)])
                ->all();
        }

        $headers = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }
            [$key, $value] = explode(':', $line, 2);
            $key = trim($key);
            if ($key !== '') {
                $headers[$key] = trim($value);
            }
        }

        return $headers;
    }

    private function wrapEInvoicePayload(array $payload, array $apiSettings): array
    {
        $root = trim((string) ($apiSettings['EINVOICEPAYLOADROOT'] ?? ''));
        return $root === '' ? $payload : [$root => $payload];
    }

    private function extractEInvoiceProviderResult(array $response, array $apiSettings): array
    {
        return [
            'irn' => $this->eInvoiceResponseValue($response, (string) ($apiSettings['EINVOICEIRNKEY'] ?? ''), [
                'irn', 'Irn', 'IRN', 'data.irn', 'data.Irn', 'Data.Irn', 'result.irn', 'result.Irn',
                'results.message.Irn', 'Einvoice.Irn', 'eInvoice.Irn',
            ]),
            'ack_no' => $this->eInvoiceResponseValue($response, (string) ($apiSettings['EINVOICEACKNOKEY'] ?? ''), [
                'ack_no', 'AckNo', 'AckNum', 'ackNo', 'data.AckNo', 'Data.AckNo', 'result.AckNo',
                'results.message.AckNo',
            ]),
            'ack_date' => $this->eInvoiceResponseValue($response, (string) ($apiSettings['EINVOICEACKDATEKEY'] ?? ''), [
                'ack_date', 'AckDt', 'AckDate', 'ackDate', 'data.AckDt', 'Data.AckDt', 'result.AckDt',
                'results.message.AckDt',
            ]),
            'signed_qr_code' => $this->eInvoiceResponseValue($response, (string) ($apiSettings['EINVOICEQRKEY'] ?? ''), [
                'SignedQRCode', 'SignedQrCode', 'signed_qr_code', 'data.SignedQRCode', 'Data.SignedQRCode',
                'result.SignedQRCode', 'results.message.SignedQRCode',
            ]),
        ];
    }

    private function eInvoiceResponseValue(array $response, string $configuredPath, array $fallbackPaths): string
    {
        $paths = array_values(array_filter(array_merge([$configuredPath], $fallbackPaths), fn ($path) => trim((string) $path) !== ''));

        foreach ($paths as $path) {
            $value = data_get($response, $path);
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        $needleParts = [];
        foreach ($paths as $path) {
            $parts = explode('.', (string) $path);
            $needleParts[] = strtolower((string) end($parts));
        }

        return $this->findEInvoiceResponseValue($response, array_unique($needleParts));
    }

    private function findEInvoiceResponseValue(array $data, array $keys): string
    {
        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), $keys, true) && is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
            if (is_array($value)) {
                $found = $this->findEInvoiceResponseValue($value, $keys);
                if ($found !== '') {
                    return $found;
                }
            }
        }

        return '';
    }

    private function persistEInvoiceResult(string $billNo, array $result, mixed $providerResponse, array $context = []): void
    {
        $status = strtolower(trim((string) ($context['status'] ?? 'generated')));
        if (!in_array($status, ['generated', 'failed', 'cancelled'], true)) {
            $status = 'generated';
        }
        $responseText = is_string($providerResponse)
            ? $providerResponse
            : (json_encode($providerResponse, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');

        $updates = [
            'e_invoice_status' => $status,
            'einvoice_status' => $status,
            'irn' => $result['irn'] ?? '',
            'ack_no' => $result['ack_no'] ?? '',
            'ackno' => $result['ack_no'] ?? '',
            'ack_date' => $result['ack_date'] ?? '',
            'ackdt' => $result['ack_date'] ?? '',
            'signed_qr_code' => $result['signed_qr_code'] ?? '',
            'signedqrcode' => $result['signed_qr_code'] ?? '',
            'e_invoice_response' => $responseText,
            'einvoice_response' => $responseText,
        ];

        $this->updateExistingColumns('sales_bills', 'bill_no', $billNo, $updates);
        $this->updateExistingColumns('salesm', 'billno', $billNo, $updates);

        if ($this->hasTable('e_invoices')) {
            $billData = (array) ($context['bill_data'] ?? []);
            $requestPayload = $context['request_payload'] ?? null;
            $requestText = is_string($requestPayload)
                ? $requestPayload
                : (json_encode($requestPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');

            $row = [
                'bill_no' => $billNo,
                'bill_type' => 'S',
                'bill_date' => $this->parseDate((string) ($billData['bill_date'] ?? ''), true),
                'customer_code' => mb_substr((string) ($billData['customer_code'] ?? ''), 0, 20),
                'customer_name' => mb_substr((string) ($billData['customer_name'] ?? ''), 0, 150),
                'gst_no' => mb_substr((string) ($billData['gst_no'] ?? ''), 0, 20),
                'net_total' => round($this->toNum($billData['net_total'] ?? 0), 2),
                'status' => $status,
                'irn' => mb_substr((string) ($result['irn'] ?? ''), 0, 120),
                'ack_no' => mb_substr((string) ($result['ack_no'] ?? ''), 0, 80),
                'ack_date' => mb_substr((string) ($result['ack_date'] ?? ''), 0, 80),
                'signed_qr_code' => mb_substr((string) ($result['signed_qr_code'] ?? ''), 0, 65000),
                'request_payload' => mb_substr((string) $requestText, 0, 65000),
                'response_payload' => mb_substr((string) $responseText, 0, 65000),
                'provider' => mb_substr((string) ($context['provider'] ?? ''), 0, 50),
                'generated_by' => mb_substr((string) ($context['user_code'] ?? ''), 0, 20),
                'generated_at' => now(),
                'cancel_reason' => null,
                'cancel_remark' => null,
                'cancelled_by' => null,
                'cancelled_at' => null,
                'cancel_response' => null,
                'updated_at' => now(),
            ];

            $existing = DB::table('e_invoices')
                ->where('bill_no', $billNo)
                ->where('bill_type', 'S')
                ->first(['id']);

            if ($existing) {
                DB::table('e_invoices')->where('id', $existing->id)->update($row);
            } else {
                $row['created_at'] = now();
                DB::table('e_invoices')->insert($row);
            }
        }
    }

    private function updateExistingColumns(string $table, string $whereColumn, string $whereValue, array $updates): void
    {
        if (!$this->hasTable($table)) {
            return;
        }

        $columns = $this->getColumns($table);
        if (!in_array($whereColumn, $columns, true)) {
            return;
        }

        $payload = [];
        foreach ($updates as $column => $value) {
            if (in_array(strtolower($column), $columns, true)) {
                $payload[$column] = is_string($value) ? mb_substr($value, 0, 65000) : $value;
            }
        }

        if ($payload !== []) {
            DB::table($table)->where($whereColumn, $whereValue)->update($payload);
        }
    }

    private function calcTotals(array $payload): array
    {
        $items = collect($payload['items'] ?? []);
        $exchange = collect($payload['exchange'] ?? []);
        $salesReturn = collect($payload['sales_return'] ?? []);
        $extra = (array) ($payload['extra'] ?? []);

        $taxPercForInclusive = $this->toNum($extra['tax_perc'] ?? 0);
        $astPercForInclusive = $this->toBool($extra['is_cst'] ?? false)
            ? 0.0
            : $this->toNum($extra['ast_perc'] ?? $extra['cess_perc'] ?? 0);
        $inclusiveRate = $taxPercForInclusive + $astPercForInclusive;
        $inclusiveBase = 0.0;
        $inclusiveTax = 0.0;
        $inclusiveAst = 0.0;
        $exclusiveBase = 0.0;

        foreach ($items as $row) {
            $amount = $this->toNum($row['amount'] ?? 0);
            $taxInternal = $this->toBool($row['taxinternal'] ?? $row['tax_internal'] ?? false);
            if ($taxInternal && $inclusiveRate > 0) {
                $base = ($amount * 100) / (100 + $inclusiveRate);
                $included = $amount - $base;
                $inclusiveBase += $base;
                if ($inclusiveRate > 0) {
                    $inclusiveTax += $included * ($taxPercForInclusive / $inclusiveRate);
                    $inclusiveAst += $included * ($astPercForInclusive / $inclusiveRate);
                }
            } else {
                $exclusiveBase += $amount;
            }
        }

        $billTotal = round($exclusiveBase + $inclusiveBase, 2);
        $exchangeAmount = round($exchange->sum(fn ($r) => $this->toNum($r['amount'] ?? 0)), 2);
        $srTaxAmt = $this->toNum($payload['sr_tax_amt'] ?? 0);
        $srTaxPerc = $this->toNum($payload['sr_tax_perc'] ?? 0);
        $srCessAmt = $this->toNum($payload['sr_cess_amt'] ?? 0);
        $srCessPerc = $this->toNum($payload['sr_cess_perc'] ?? 0);
        $srDiscountAmt = $this->toNum($payload['sr_discount_amt'] ?? 0);
        $returnAmount = round($salesReturn->sum(fn ($r) => $this->toNum($r['amount'] ?? 0)) - $srDiscountAmt + $srTaxAmt + $srCessAmt, 2);

        $discount = $this->toNum($extra['discount'] ?? 0);
        $discountPerc = $this->toNum($extra['discount_perc'] ?? 0);
        $tax = $this->toNum($extra['tax'] ?? 0);
        $ast = $this->toNum($extra['ast'] ?? $extra['cess'] ?? 0);
        $tcsPerc = $this->toNum($extra['tcs_perc'] ?? 0);
        $rcAmt = $this->toNum($extra['repair_charge'] ?? 0);
        $hmc = $this->toNum($extra['hallmark_charge'] ?? 0);
        $advance = $this->toNum($extra['advance'] ?? 0);
        $fancy = $this->toNum($extra['fancy_amt'] ?? 0);
        $scheme = $this->toNum($extra['scheme_amt'] ?? 0);
        $schemeLedger = strtoupper(trim((string) ($extra['scheme_ledger'] ?? 'APP')));
        $bankCharge = $this->toNum($extra['bank_charge'] ?? 0);
        $addBankCharge = $this->toBool($extra['add_bank_charge'] ?? false);
        $openingBal = $this->toNum($extra['opening_balance'] ?? 0);
        $roundMode = (int) ($extra['round_to'] ?? 0);
        $credit = $this->toBool($extra['credit'] ?? false);
        $autoRcvd = $this->toBool($extra['auto_rcvd'] ?? false);
        $grandToRcvd = $this->toBool($extra['grand_to_rcvd'] ?? false);
        $rcvd = $this->toNum($extra['received'] ?? 0);

        if ($discount == 0 && $discountPerc > 0) {
            $discount = round(($billTotal * $discountPerc) / 100, 2);
        } elseif ($billTotal > 0 && $discount > 0 && $discountPerc == 0) {
            $discountPerc = round(($discount * 100) / $billTotal, 3);
        }

        if ($taxPercForInclusive > 0 || $inclusiveTax > 0) {
            $tax = round($inclusiveTax + (($exclusiveBase * $taxPercForInclusive) / 100), 2);
        }
        if ($astPercForInclusive > 0 || $inclusiveAst > 0) {
            $ast = round($inclusiveAst + (($exclusiveBase * $astPercForInclusive) / 100), 2);
        }

        $netBeforeTcs = $billTotal + $tax + $ast + $rcAmt + $hmc - $exchangeAmount - $returnAmount - $advance + $fancy - $scheme;
        if ($addBankCharge) {
            $netBeforeTcs += $bankCharge;
        }
        if ($roundMode === 1) {
            $netBeforeTcs = round($netBeforeTcs, 0);
        } else {
            $netBeforeTcs = round($netBeforeTcs, 2);
        }

        $tcsAmt = round((($netBeforeTcs - $discount) * $tcsPerc) / 100, 0);
        $netTotal = $netBeforeTcs + $tcsAmt;
        if ($roundMode === 1) {
            $netTotal = round($netTotal, 0);
        } else {
            $netTotal = round($netTotal, 2);
        }

        $netAmt = $netTotal - $discount;
        $grandAmt = $openingBal + $netAmt;

        if ($autoRcvd && !$credit) {
            $rcvd = $grandToRcvd ? $grandAmt : $netAmt;
        }
        $rcvd = round($rcvd, 0);

        $balance = round($netAmt - $rcvd, 2);
        $openingAdjustment = $schemeLedger === 'SCHMAMT' ? 0.0 : $scheme;
        $netBalance = round($openingBal - $openingAdjustment - $balance, 2);

        $extra['discount'] = round($discount, 2);
        $extra['discount_perc'] = round($discountPerc, 3);
        $extra['tax'] = round($tax, 2);
        $taxSplit = $this->salesTaxSplitFromAmount((float) $extra['tax'], $this->toBool($extra['is_cst'] ?? false));
        $extra['sgst'] = $taxSplit['sgst'];
        $extra['cgst'] = $taxSplit['cgst'];
        $extra['igst'] = $taxSplit['igst'];
        $extra['ast'] = round($ast, 2);
        $extra['tcs_perc'] = round($tcsPerc, 3);
        $extra['tcs_amt'] = round($tcsAmt, 2);
        $extra['repair_charge'] = round($rcAmt, 2);
        $extra['hallmark_charge'] = round($hmc, 2);
        $extra['advance'] = round($advance, 2);
        $extra['fancy_amt'] = round($fancy, 2);
        $extra['scheme_amt'] = round($scheme, 2);
        $extra['scheme_ledger'] = $schemeLedger === 'SCHMAMT' ? 'SCHMAMT' : 'APP';
        $extra['bank_charge'] = round($bankCharge, 2);
        $extra['add_bank_charge'] = $addBankCharge;
        $extra['opening_balance'] = round($openingBal, 2);
        $extra['round_to'] = $roundMode;
        $extra['received'] = round($rcvd, 2);
        $extra['balance'] = $balance;
        $extra['net_balance'] = $netBalance;
        $extra['net_amt'] = round($netAmt, 2);
        $extra['grand_amt'] = round($grandAmt, 2);
        $extra['credit'] = $credit;
        $extra['auto_rcvd'] = $autoRcvd;
        $extra['grand_to_rcvd'] = $grandToRcvd;
        $extra['sr_tax_perc'] = round($srTaxPerc, 3);
        $extra['sr_tax_amt'] = round($srTaxAmt, 2);
        $extra['sr_cess_perc'] = round($srCessPerc, 3);
        $extra['sr_cess_amt'] = round($srCessAmt, 2);
        $extra['sr_discount_amt'] = round($srDiscountAmt, 2);

        return [
            'bill_total' => round($billTotal, 2),
            'exchange_amount' => round($exchangeAmount, 2),
            'return_amount' => round($returnAmount, 2),
            'net_total' => round($netTotal, 2),
            'extra' => $extra,
        ];
    }

    private function salesTaxSplit(float $taxable, float $taxPerc, bool $isCst = false): array
    {
        $taxable = max(round($taxable, 2), 0.0);
        $taxPerc = max($taxPerc, 0.0);
        if ($isCst) {
            $igst = round(($taxable * $taxPerc) / 100, 2);
            return ['sgst' => 0.0, 'cgst' => 0.0, 'igst' => $igst, 'total' => $igst];
        }

        $halfPerc = $taxPerc / 2;
        $sgst = round(($taxable * $halfPerc) / 100, 2);
        $cgst = round(($taxable * $halfPerc) / 100, 2);

        return ['sgst' => $sgst, 'cgst' => $cgst, 'igst' => 0.0, 'total' => round($sgst + $cgst, 2)];
    }

    private function salesTaxSplitFromAmount(float $taxAmount, bool $isCst = false): array
    {
        $taxAmount = round($taxAmount, 2);
        if ($isCst) {
            return ['sgst' => 0.0, 'cgst' => 0.0, 'igst' => $taxAmount, 'total' => $taxAmount];
        }

        $cgst = round($taxAmount / 2, 2);
        $sgst = round($taxAmount - $cgst, 2);

        return ['sgst' => $sgst, 'cgst' => $cgst, 'igst' => 0.0, 'total' => round($sgst + $cgst, 2)];
    }

    private function toArray(?string $json): array
    {
        if (!$json) {
            return [];
        }
        $arr = json_decode($json, true);
        return is_array($arr) ? $arr : [];
    }

    private function parseDate(string $value, bool $nullable = false): ?string
    {
        $value = trim($value);
        if ($value === '' || $value === '00/00/00' || $value === '00/00/0000' || $value === '0000-00-00') {
            return $nullable ? null : now()->toDateString();
        }

        try {
            $parsed = str_contains($value, '/')
                ? Carbon::createFromFormat('d/m/Y', $value)
                : Carbon::parse($value);

            // Carbon silently produces negative/zero years for inputs like 00/00/00
            if ($parsed->year <= 0) {
                return $nullable ? null : now()->toDateString();
            }

            return $parsed->toDateString();
        } catch (\Throwable) {
            return $nullable ? null : now()->toDateString();
        }
    }

    private function toNum(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        $v = trim((string) $value);
        $v = str_replace(',', '', $v);
        return is_numeric($v) ? (float) $v : 0.0;
    }

    private function toSqlTime(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return now()->format('H:i:s');
        }
        try {
            return Carbon::parse($value)->format('H:i:s');
        } catch (\Throwable) {
            return now()->format('H:i:s');
        }
    }

    private function pickClientMobile(object $client, array $cols): string
    {
        $mobile = in_array('mobile', $cols, true) ? trim((string) ($client->mobile ?? '')) : '';
        if ($mobile !== '') {
            return $mobile;
        }
        return in_array('telephone', $cols, true) ? trim((string) ($client->telephone ?? '')) : '';
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        $str = strtolower(trim((string) $value));
        return in_array($str, ['1', 'true', 'y', 'yes'], true);
    }

    private function getColumns(string $table): array
    {
        static $cache = [];
        if (!isset($cache[$table])) {
            try {
                $cache[$table] = array_map('strtolower', $this->columnList($table));
            } catch (\Throwable) {
                $cache[$table] = [];
            }
        }
        return $cache[$table];
    }

    private function col(object|null $row, string $name, mixed $default = null): mixed
    {
        if (!$row) {
            return $default;
        }
        return $row->{$name} ?? $default;
    }

    private function pickRateByType(float|string $itype, float $goldRate, float $silverRate, float $platinumRate, float $itemRate): float
    {
        $type = strtoupper(trim((string) $itype));
        if ($itemRate > 0) {
            return $itemRate;
        }
        return match ($type) {
            'S' => $silverRate,
            'P' => $platinumRate,
            default => $goldRate,
        };
    }

    private function syncBillNoCounter(string $billNo): void
    {
        if (!$this->hasTable('generali') || $billNo === '') {
            return;
        }
        $settingsPayload = $this->loadSettingsPayload();
        $software = (array) ($settingsPayload['Software'] ?? []);
        $sw = static fn (array $arr, string $key, string $default = ''): string => (string) ($arr[$key] ?? $default);
        $billTypewiseBillNo = strtoupper(trim($sw($software, 'BillTypewiseBillNo', 'N'))) === 'Y';

        if ($billTypewiseBillNo && $this->hasTable('salestype')) {
            $types = DB::table('salestype')->get(['prefix']);
            foreach ($types as $type) {
                $pfx = trim((string) ($type->prefix ?? ''));
                if ($pfx !== '' && str_starts_with($billNo, $pfx)) {
                    $numStr = substr($billNo, strlen($pfx));
                    $num = (int) $this->toNum($numStr);
                    $counterCode = 'SALES' . $pfx;
                    $current = (int) $this->toNum((string) (DB::table('generali')->where('code', $counterCode)->value('cvalue') ?? '0'));
                    if ($num > $current) {
                        DB::table('generali')->updateOrInsert(['code' => $counterCode], ['cvalue' => (string) $num]);
                    }
                    return;
                }
            }
        }

        $prefix = $this->readGeneralsValue('SBPREF', 'SLB/');
        if (str_starts_with($billNo, $prefix)) {
            $numStr = substr($billNo, strlen($prefix));
            $num = (int) $this->toNum($numStr);
            $current = (int) $this->toNum((string) (DB::table('generali')->where('code', 'SALESB')->value('cvalue') ?? '0'));
            if ($num > $current) {
                DB::table('generali')->updateOrInsert(['code' => 'SALESB'], ['cvalue' => (string) $num]);
            }
        }
    }

    private function rewindBillNoCounterToSavedMax(string $billNo): void
    {
        if (!$this->hasTable('generali') || $billNo === '') {
            return;
        }

        $match = $this->counterCodeAndPrefixForBillNo($billNo);
        if (!$match) {
            return;
        }

        [$counterCode, $prefix] = $match;
        $maxSaved = $this->maxSavedBillNumberForPrefix($prefix);
        $current = (int) $this->toNum((string) (DB::table('generali')->where('code', $counterCode)->value('cvalue') ?? '0'));

        if ($current !== $maxSaved) {
            DB::table('generali')->updateOrInsert(['code' => $counterCode], ['cvalue' => (string) $maxSaved]);
        }
    }

    private function counterCodeAndPrefixForBillNo(string $billNo): ?array
    {
        if ($billNo === '') {
            return null;
        }

        $settingsPayload = $this->loadSettingsPayload();
        $software = (array) ($settingsPayload['Software'] ?? []);
        $sw = static fn (array $arr, string $key, string $default = ''): string => (string) ($arr[$key] ?? $default);
        $billTypewiseBillNo = strtoupper(trim($sw($software, 'BillTypewiseBillNo', 'N'))) === 'Y';

        if ($billTypewiseBillNo && $this->hasTable('salestype')) {
            $types = DB::table('salestype')
                ->whereNotNull('prefix')
                ->get(['prefix'])
                ->map(fn ($row) => trim((string) ($row->prefix ?? '')))
                ->filter(fn ($prefix) => $prefix !== '')
                ->sortByDesc(fn ($prefix) => strlen($prefix))
                ->values();

            foreach ($types as $prefix) {
                if (str_starts_with($billNo, $prefix)) {
                    return ['SALES' . $prefix, $prefix];
                }
            }
        }

        $prefix = $this->readGeneralsValue('SBPREF', 'SLB/');
        if ($prefix !== '' && str_starts_with($billNo, $prefix)) {
            return ['SALESB', $prefix];
        }

        $estimatePrefix = $this->readGeneralsValue('SEPREF', 'SLE/');
        if ($estimatePrefix !== '' && str_starts_with($billNo, $estimatePrefix)) {
            return ['SALESE', $estimatePrefix];
        }

        return null;
    }

    private function maxSavedBillNumberForPrefix(string $prefix): int
    {
        $max = 0;
        if ($prefix === '') {
            return $max;
        }

        if ($this->hasTable('salesm')) {
            $rows = DB::table('salesm')
                ->where('billno', 'like', $prefix . '%')
                ->pluck('billno');
            foreach ($rows as $billNo) {
                $max = max($max, (int) $this->toNum(substr(trim((string) $billNo), strlen($prefix))));
            }
        }

        if ($this->hasSalesBillsTable()) {
            $rows = DB::table('sales_bills')
                ->where('bill_no', 'like', $prefix . '%')
                ->pluck('bill_no');
            foreach ($rows as $billNo) {
                $max = max($max, (int) $this->toNum(substr(trim((string) $billNo), strlen($prefix))));
            }
        }

        return $max;
    }

    private function readGeneraliCounter(string $code): int
    {
        if (!$this->hasTable('generali')) {
            return $this->fallbackCounterFromSalesBills($code);
        }
        $val = DB::table('generali')->where('code', $code)->value('cvalue');
        if ($val === null || $val === '') {
            return 0;
        }
        return (int) $this->toNum((string) $val);
    }

    private function reserveGeneraliCounterValue(string $code): int
    {
        if (!$this->hasTable('generali')) {
            return $this->fallbackCounterFromSalesBills($code) + 1;
        }

        return DB::transaction(function () use ($code) {
            $current = DB::table('generali')
                ->where('code', $code)
                ->lockForUpdate()
                ->value('cvalue');

            $next = (int) $this->toNum((string) ($current ?? '0')) + 1;

            DB::table('generali')->updateOrInsert(
                ['code' => $code],
                ['cvalue' => (string) $next]
            );

            return $next;
        }, 3);
    }

    private function reserveGlobalSerialNo(): int
    {
        // Atomic: lock the SERIALNO counter row for the duration of the transaction so two concurrent
        // saves from different terminals can't both compute the same slno and collide (or worse,
        // cause the second save to "edit" the first by matching its slno through a duplicate billno).
        if (!$this->hasTable('generali')) {
            $maxUsed = 0;
            foreach (['salesm', 'salesrm', 'purchasem', 'purchaserm', 'daybook', 'daybookpart', 'orderm', 'smithm', 'refinerym', 'repairm'] as $table) {
                if ($this->hasTable($table) && Schema::hasColumn($table, 'slno')) {
                    $maxUsed = max($maxUsed, (int) (DB::table($table)->max('slno') ?? 0));
                }
            }
            return $maxUsed + 1;
        }

        return DB::transaction(function () {
            $row = DB::table('generali')
                ->where('code', 'SERIALNO')
                ->lockForUpdate()
                ->first();
            $current = (int) $this->toNum((string) ($row->cvalue ?? '0'));

            $maxUsed = 0;
            foreach (['salesm', 'salesrm', 'purchasem', 'purchaserm', 'daybook', 'daybookpart', 'orderm', 'smithm', 'refinerym', 'repairm'] as $table) {
                if ($this->hasTable($table) && Schema::hasColumn($table, 'slno')) {
                    $maxUsed = max($maxUsed, (int) (DB::table($table)->max('slno') ?? 0));
                }
            }

            $next = max($current, $maxUsed) + 1;
            DB::table('generali')->updateOrInsert(['code' => 'SERIALNO'], ['cvalue' => (string) $next]);
            return $next;
        });
    }

    private function readGeneralsValue(string $code, string $default = ''): string
    {
        if (!$this->hasTable('generals')) {
            return $default;
        }
        $val = DB::table('generals')->where('code', $code)->value('cvalue');
        $txt = trim((string) ($val ?? ''));
        return $txt === '' ? $default : $txt;
    }

    private function readGeneraldValue(string $code, string $default = ''): string
    {
        if (!$this->hasTable('generald')) {
            return $default;
        }
        $val = DB::table('generald')->where('code', $code)->value('cvalue');
        $txt = trim((string) ($val ?? ''));
        return $txt === '' ? $default : $txt;
    }

    private function fetchSalesTypePrefixAndTax(string $billType): array
    {
        if (!$this->hasTable('salestype')) {
            return ['', 0.0];
        }
        $cols = $this->getColumns('salestype');
        if (!in_array('prefix', $cols, true)) {
            return ['', 0.0];
        }

        $billTypeTxt = strtoupper(trim($billType));
        $aliases = [$billTypeTxt];
        if ($billTypeTxt === 'GOLD') {
            $aliases[] = 'G';
        } elseif ($billTypeTxt === 'SILVER') {
            $aliases[] = 'S';
        } elseif ($billTypeTxt !== '') {
            $aliases[] = substr($billTypeTxt, 0, 1);
        }
        $aliases = array_values(array_unique(array_filter($aliases, static fn ($x) => $x !== '')));

        $row = DB::table('salestype')
            ->where(function ($q) use ($aliases, $cols) {
                foreach ($aliases as $alias) {
                    $q->orWhereRaw('upper(trim(code)) = ?', [$alias]);
                    if (in_array('name', $cols, true)) {
                        $q->orWhereRaw('upper(trim(name)) = ?', [$alias]);
                    }
                }
            })
            ->orderByRaw("case when upper(trim(code)) = ? then 0 else 1 end", [$aliases[0] ?? ''])
            ->first();

        if (!$row) {
            return ['', 0.0];
        }

        $prefix = trim((string) ($row->prefix ?? ''));
        $tax = isset($row->taxperc) ? (float) $row->taxperc : 0.0;
        return [$prefix, $tax];
    }

    private function fallbackCounterFromSalesBills(string $code): int
    {
        $prefix = '';
        if (str_starts_with($code, 'SALES')) {
            $suffix = substr($code, 5);
            if ($suffix === 'B') {
                $prefix = 'SL/';
            } elseif ($suffix === 'E') {
                $prefix = 'SLE/';
            }
        } elseif (str_starts_with($code, 'TSALES')) {
            $suffix = substr($code, 6);
            if ($suffix === 'B') {
                $prefix = 'TSB/';
            } elseif ($suffix === 'E') {
                $prefix = 'TSE/';
            }
        }
        if ($prefix === '') {
            return 0;
        }

        $last = SalesBill::query()
            ->where('bill_no', 'like', $prefix . '%')
            ->orderByDesc('bill_no')
            ->value('bill_no');

        if (!$last || !str_starts_with($last, $prefix)) {
            return 0;
        }
        return (int) $this->toNum(substr($last, strlen($prefix)));
    }

    private function salesBillNumberLength(): int
    {
        $len = (int) $this->toNum($this->readGeneralsValue('SBLEN', '4'));
        return $len > 0 ? $len : 4;
    }

    private function loadSettingsPayload(): array
    {
        // Primary source for whole software settings: INI (editable by users).
        $iniPaths = [
            storage_path('app/software-settings.ini'),
        ];
        foreach ($iniPaths as $iniPath) {
            if (File::exists($iniPath)) {
                return $this->parseLegacyIni((string) File::get($iniPath));
            }
        }

        return ['Software' => [], 'Rates' => []];
    }

    private function parseLegacyIni(string $raw): array
    {
        $result = [];
        $section = '';
        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, ';') || str_starts_with($line, '#') || str_starts_with($line, '//')) {
                continue;
            }
            if (preg_match('/^\[(.+)\]$/', $line, $m) === 1) {
                $section = trim($m[1]);
                if ($section !== '' && !isset($result[$section])) {
                    $result[$section] = [];
                }
                continue;
            }
            $eqPos = strpos($line, '=');
            if ($eqPos === false) {
                continue;
            }
            $k = trim(substr($line, 0, $eqPos));
            $v = trim(substr($line, $eqPos + 1));
            if ($k === '') {
                continue;
            }
            if ($section === '') {
                $section = 'Software';
                if (!isset($result[$section])) {
                    $result[$section] = [];
                }
            }
            $result[$section][$k] = $v;
        }

        if (!isset($result['Software'])) {
            $result['Software'] = [];
        }
        if (!isset($result['Rates'])) {
            $result['Rates'] = [];
        }
        return $result;
    }

    private function resolveGisemi(Request $request): int
    {
        $q = trim((string) $request->query('gisemi', ''));
        if ($q !== '' && is_numeric($q)) {
            return max(1, (int) $q);
        }
        $fromSession = (string) ($request->session()->get('semi')
            ?? $request->session()->get('control')
            ?? '2');
        return is_numeric($fromSession) ? max(1, (int) $fromSession) : 2;
    }

    private function normDate(string $d): ?string
    {
        $d = trim($d);
        if ($d === '') return null;
        // Handle dd/mm/yyyy format
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $d, $m)) {
            $d = "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        $t = strtotime($d);
        if ($t === false || (int) date('Y', $t) < 1990) return null;
        return date('Y-m-d', $t);
    }
}
