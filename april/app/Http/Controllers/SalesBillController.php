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
        return view('sales-bill.edit-picker', [
            'title' => (string) $request->query('title', 'Sales Edit'),
            'moduleId' => (string) $request->query('module', 'sales-bill-edit'),
            'actionMode' => 'edit',
            'showViewOption' => false,
        ]);
    }

    public function reprintPicker(Request $request): View
    {
        return view('sales-bill.edit-picker', [
            'title' => (string) $request->query('title', 'Sales Reprint'),
            'moduleId' => (string) $request->query('module', 'sales-bill-reprint'),
            'actionMode' => 'reprint',
            'showViewOption' => true,
        ]);
    }

    public function cancelPicker(Request $request): View
    {
        return view('sales-bill.edit-picker', [
            'title' => (string) $request->query('title', 'Sales Cancellation'),
            'moduleId' => (string) $request->query('module', 'sales-bill-cancel'),
            'actionMode' => 'cancel',
            'showViewOption' => false,
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

        // Load items for exchange dropdown (code, name, itype)
        $exchItems = [];
        if ($this->hasTable('items')) {
            $itemCols = $this->getColumns('items');
            $select = ['code', 'name', 'itype'];
            foreach (['touch', 'cost', 'disabled', 'defstktype', 'defquality', 'stkinnos', 'ornament'] as $col) {
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
        $limit = (int) $request->query('limit', 30);
        if ($limit <= 0) $limit = 30;
        if ($limit > 30) $limit = 30;

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

        if ($date) {
            $query->whereDate('tdate', $date);
        }

        $rows = $query
            ->orderByDesc('tdate')
            ->orderByDesc('slno')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'slno' => (int) ($r->slno ?? 0),
                'billno' => trim((string) ($r->billno ?? '')),
                'tdate' => $r->tdate ? Carbon::parse($r->tdate)->format('d/m/Y') : '',
                'custname' => trim((string) ($r->custname ?? '')),
                'status' => ((int) $this->toNum($r->status ?? 1)) === 0 ? 'cancelled' : 'saved',
            ])
            ->values();

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    public function resolveEditBill(Request $request): JsonResponse
    {
        $billNo = strtoupper(trim((string) $request->input('bill_no', '')));
        $date = $this->normDate((string) $request->input('tdate', ''));

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

        return response()->json([
            'ok' => true,
            'bill_no' => trim((string) ($row->billno ?? '')),
            'tdate' => $row->tdate ? Carbon::parse($row->tdate)->format('d/m/Y') : '',
            'url' => url('/sales-bill/edit?bill_no=' . urlencode(trim((string) ($row->billno ?? '')))),
        ]);
    }

    public function resolveBillAction(Request $request): JsonResponse
    {
        $billNo = strtoupper(trim((string) $request->input('bill_no', '')));
        $date = $this->normDate((string) $request->input('tdate', ''));
        $action = strtolower(trim((string) $request->input('action', 'edit')));
        $viewOnly = filter_var($request->input('view_only', false), FILTER_VALIDATE_BOOLEAN);

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
            ->select(['slno', 'billno', 'tdate', 'status'])
            ->whereRaw('UPPER(TRIM(billno)) = ?', [$billNo])
            ->whereDate('tdate', $date)
            ->first();

        if (!$row) {
            return response()->json(['ok' => false, 'message' => 'This bill number does not exist...'], 404);
        }

        if ((int) ($row->status ?? 1) === 0 && $action !== 'cancel') {
            return response()->json(['ok' => false, 'message' => 'This bill has been cancelled.'], 422);
        }

        $resolvedBillNo = trim((string) ($row->billno ?? ''));
        if ($action === 'reprint') {
            $printQuery = ['slno' => (int) ($row->slno ?? 0)];

            return response()->json([
                'ok' => true,
                'bill_no' => $resolvedBillNo,
                'tdate' => $row->tdate ? Carbon::parse($row->tdate)->format('d/m/Y') : '',
                'url' => url('/sales-bill-print?' . http_build_query($printQuery)),
            ]);
        }

        $query = ['bill_no' => $resolvedBillNo];

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

        if ($this->hasSalesBillsTable()) {
            $bill = SalesBill::query()->where('bill_no', $billNo)->first();
            if ($bill) {
                return response()->json([
                    'ok' => true,
                    'data' => $this->mapBill($bill),
                ]);
            }
        }

        if ($this->hasTable('salesm')) {
            // Legacy PB fallback: load from salesm/salesd when sales_bills row is missing.
            $legacy = DB::table('salesm')->where('billno', $billNo)->first();
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
                'opbalance' => in_array('opbalance', $cols, true) ? round((float) ($client->opbalance ?? 0), 2) : 0,
                'panadhar' => in_array('panadhar', $cols, true) ? trim((string) ($client->panadhar ?? '')) : '',
                'tin' => in_array('tin', $cols, true) ? trim((string) ($client->tin ?? '')) : '',
                'state' => in_array('state', $cols, true) ? trim((string) ($client->state ?? '')) : '',
                'distance' => in_array('distance', $cols, true) ? (int) $this->toNum($client->distance ?? 0) : 0,
                'cocode' => $cocode,
                'ctype' => in_array('ctype', $cols, true) ? trim((string) ($client->ctype ?? '')) : '',
            ],
        ]);
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
            'bill_date' => 'nullable|string|max:20',
            'bill_time' => 'nullable|string|max:20',
            'bill_type' => 'nullable|string|max:30',
            'is_quotation' => 'nullable|boolean',
            'source_quotation_bill_no' => 'nullable|string|max:40',
            'customer_name' => 'nullable|string|max:120',
            'customer_code' => 'nullable|string|max:20',
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
            'extra' => 'nullable|array',
            'secondary_sync' => 'nullable|boolean',
        ]);

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
            $rate = $this->toNum($row['rate'] ?? 0);
            $amount = $this->toNum($row['amount'] ?? 0);
            if ($amount == 0.0) {
                $netWgt = max($weight - $stoneWgt, 0);
                $amount = ($netWgt * $rate) + $stonePrice + $mc;
            }

            $row['qty'] = $qty;
            $row['weight'] = $weight;
            $row['stone_wgt'] = $stoneWgt;
            $row['net_wgt'] = max($weight - $stoneWgt, 0);
            $row['stone_price'] = $stonePrice;
            $row['making_charge'] = $mc;
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
        })->values()->all();

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

        if ($items === [] && $exchange === [] && $salesReturn === []) {
            return response()->json(['ok' => false, 'message' => "There is no entries. You can't proceed..."], 422);
        }

        $balance = $this->toNum(($payload['extra']['balance'] ?? $calc['extra']['balance'] ?? 0));
        if ($balance != 0.0 && trim((string) ($payload['customer_code'] ?? '')) === '') {
            return response()->json(['ok' => false, 'message' => 'Enter customer for credit sale'], 422);
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

        if ($this->hasTable('items')) {
            $itemRows = DB::table('items')->whereIn('code', collect($items)->pluck('item_code')->filter()->all())->get()->keyBy('code');
            foreach ($items as $idx => $it) {
                $rowNo = $idx + 1;
                $code = trim((string) ($it['item_code'] ?? ''));
                $weight = $this->toNum($it['weight'] ?? 0);
                $rate = $this->toNum($it['rate'] ?? 0);
                $qty = $this->toNum($it['qty'] ?? 0);
                $stoneWgt = $this->toNum($it['stone_wgt'] ?? 0);
                $stonePrice = $this->toNum($it['stone_price'] ?? 0);
                $stkinnos = strtoupper(trim((string) ($it['stkinnos'] ?? 'N')));
                $stktype = trim((string) ($it['stktype'] ?? ''));
                $bcode = (int) $this->toNum($it['bcode'] ?? 0);
                $dbItem = $itemRows->get($code);

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
                if ($dbItem) {
                    $bcComp = strtoupper(trim((string) ($dbItem->bccompulsory ?? 'N')));
                    if ($bcode <= 0 && $bcComp === 'Y') {
                        return response()->json(['ok' => false, 'message' => "Barcode Compulsory for item {$code}. You can't continue..."], 422);
                    }
                    $stoneMust = strtoupper(trim((string) ($dbItem->stonemust ?? 'N')));
                    if ($stoneWgt <= 0 && $stoneMust === 'Y') {
                        return response()->json(['ok' => false, 'message' => "Stone Compulsory for item {$code}. You can't continue..."], 422);
                    }
                    if ($billControl === 1 && !$allowInsufficientStockSales && !$isEdit) {
                        $stockQty = $this->toNum($dbItem->qty ?? 0);
                        $stockWgt = $this->toNum($dbItem->weight ?? 0);
                        if ($qty > $stockQty || $weight > $stockWgt) {
                            return response()->json(['ok' => false, 'message' => "Insufficient stock for item {$code}. You can't continue..."], 422);
                        }
                    }
                }
            }
        }

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

        $responseData = null;
        if ($bill) {
            $responseData = $this->mapBill($bill->fresh());
        } elseif ($legacySlno && $this->hasTable('salesm')) {
            $legacy = DB::table('salesm')->where('slno', $legacySlno)->first();
            if ($legacy) {
                $responseData = $this->mapLegacySalesBill($legacy);
            }
        }

        $this->logDelpart($request, 'Sales Bill(' . trim((string) ($payload['bill_no'] ?? '')) . ') Saved', ['utype' => !empty($payload['slno']) ? 'E' : 'A', 'ttype' => 'T', 'slno' => $legacySlno]);
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
            'staxamt' => round($this->toNum($extra['tax'] ?? 0), 2),
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
            foreach ($items as $it) {
                $code = trim((string) ($it['item_code'] ?? ''));
                if ($code === '') {
                    continue;
                }
                $insRows[] = [
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
                DB::table('salesrm')->insert([
                    'slno' => $slno,
                    'billno' => $billNo,
                    'tdate' => $billDateSql,
                    'ttime' => $billTime,
                    'custcode' => $custCode,
                    'custname' => $custName,
                    'billamt' => round($retAmt, 2),
                    'pamt' => round($retAmt, 2),
                    'grate' => $rate,
                    'status' => 1,
                    'sr' => 'E',
                    'control' => $control,
                    'smcode' => $smCode,
                    'netamt' => round($retAmt, 2),
                    'staxperc' => $taxPerc,
                    'staxamt' => 0,
                    'astamt' => 0,
                    'billtype' => $billType,
                    'cst' => $isCst,
                ]);
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
        $disc = round($this->toNum($extra['discount'] ?? 0), 2);
        $taxAmt = round($this->toNum($extra['tax'] ?? 0), 2);
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
        $isCst = $flagY($payload['extra']['is_cst'] ?? 'N');
        $taxSystem = strtoupper(trim($sw($software, 'TaxSystem', 'GST')));
        $vaSepAc = $flagY($sw($software, 'VASepAc', 'N'));
        $addBc = $flagY($extra['add_bank_charge'] ?? false, 'N');

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
                $saccode = $flagY($extra['cc_pdc'] ?? 'N') ? 'CNC' : $cbcode;
                $add($entries, $saccode, -$ccAmt, $opAcCode);
            }
            if ($chqAmt > 0) {
                $saccode = $flagY($extra['chq_pdc'] ?? 'N') ? 'CNC' : $chqBank;
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
            $isChqPdc = $flagY($extra['chq_pdc'] ?? $extra['pdc'] ?? 'N');
            $isCcPdc = $flagY($extra['cc_pdc'] ?? $extra['ccpdc'] ?? 'N');
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
                    $add($entries, 'IGST', $taxAmt, $opAcCode);
                } else {
                    $add($entries, 'SGST', $taxAmt / 2, $opAcCode);
                    $add($entries, 'CGST', $taxAmt / 2, $opAcCode);
                }
            }
        }
        $add($entries, 'AST', $astAmt, $opAcCode);

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
            $add($entries, 'ESR', -$retAmt, $opAcCode);
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

        $threshold = round($this->toNum($sw($software, 'EInvoiceThresholdAmount', '1000000')), 2);
        if ($threshold <= 0) {
            $threshold = 1000000.00;
        }

        $netAmount = round($this->toNum($billData['net_total'] ?? 0), 2);
        if ($netAmount <= $threshold) {
            return response()->json([
                'ok' => false,
                'message' => 'Bill amount is not above the e-invoice threshold.',
                'threshold' => $threshold,
                'net_amount' => $netAmount,
            ], 422);
        }

        $apiUrl = trim((string) ($apiSettings['EINVOICEAPIURL'] ?? ''));
        $username = trim((string) ($apiSettings['EINVOICEUSERNAME'] ?? ''));
        $password = trim((string) ($payload['password'] ?? ''));
        if ($password === '') {
            $password = (string) ($apiSettings['EINVOICEPASSWORD'] ?? '');
        }

        if ($apiUrl === '') {
            return response()->json(['ok' => false, 'message' => 'Set EInvoice API URL in Application Settings before generating.'], 422);
        }
        if ($username === '' || $password === '') {
            return response()->json(['ok' => false, 'message' => 'Set EInvoice username and password in Application Settings before generating.'], 422);
        }

        $requestPayload = $this->buildEInvoicePayload($billData, $settingsPayload);

        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->withBasicAuth($username, $password)
                ->post($apiUrl, $requestPayload);
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

            return response()->json([
                'ok' => false,
                'message' => $message,
                'provider_status' => $response->status(),
                'provider_response' => $decoded ?? $response->body(),
            ], 422);
        }

        $providerMessage = is_array($decoded)
            ? (string) ($decoded['message'] ?? $decoded['msg'] ?? $decoded['status'] ?? '')
            : '';
        if ($providerMessage === '') {
            $providerMessage = 'E-invoice generated successfully.';
        }

        $this->logDelpart($request, 'Sales Bill(' . $billNo . ') E-Invoice Requested', ['utype' => 'E', 'ttype' => 'T']);

        return response()->json([
            'ok' => true,
            'message' => $providerMessage,
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
        $g18Rate = $pickNum($ratesCfg, ['G18RATE'], $this->toNum($sw($software, 'G18RATE', '0')));

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
        if (str_starts_with(strtoupper(trim($qtype)), '18') && strtoupper(trim((string) $itype)) === 'G' && $typeRate <= 0) {
            $typeRate = $g18Rate > 0 ? $g18Rate : $goldRate;
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
                'item_type' => $itype,
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
        $calc = $this->calcTotals([
            'items' => $this->toArray($bill->items_json),
            'exchange' => $this->toArray($bill->exchange_json),
            'sales_return' => $this->toArray($bill->return_json),
            'extra' => $extra,
        ]);

        return [
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

        if ($slno > 0 && $this->hasTable('salesd')) {
            $sdRows = DB::table('salesd')
                ->where('slno', $slno)
                ->orderBy('sno')
                ->get();

            $nameByCode = [];
            if ($this->hasTable('items')) {
                $codes = $sdRows->pluck('code')->filter()->map(fn ($c) => trim((string) $c))->unique()->values()->all();
                if ($codes !== []) {
                    $nameByCode = DB::table('items')
                        ->whereIn('code', $codes)
                        ->pluck('name', 'code')
                        ->mapWithKeys(fn ($v, $k) => [trim((string) $k) => trim((string) $v)])
                        ->toArray();
                }
            }

            $items = $sdRows->map(function ($r) use ($nameByCode) {
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
            'credit' => strtoupper(trim((string) ($salesm->loan ?? 'N'))) === 'Y',
            'note' => trim((string) ($salesm->note ?? '')),
            'tcs_perc' => round($this->toNum($salesm->tcsperc ?? 0), 3),
            'tcs_amt' => round($this->toNum($salesm->tcsamt ?? 0), 2),
            'redeem_points' => round($this->toNum($salesm->redmpoints ?? 0), 2),
            'ptax_perc' => $ptaxPerc,
            'ptax_amt' => $ptaxAmt,
        ];

        return [
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
            'salesman_name' => '',
            'salesman_code' => trim((string) ($salesm->smcode ?? '')),
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

    private function calcTotals(array $payload): array
    {
        $items = collect($payload['items'] ?? []);
        $exchange = collect($payload['exchange'] ?? []);
        $salesReturn = collect($payload['sales_return'] ?? []);
        $extra = (array) ($payload['extra'] ?? []);

        $billTotal = round($items->sum(fn ($r) => $this->toNum($r['amount'] ?? 0)), 2);
        $exchangeAmount = round($exchange->sum(fn ($r) => $this->toNum($r['amount'] ?? 0)), 2);
        $returnAmount = round($salesReturn->sum(fn ($r) => $this->toNum($r['amount'] ?? 0)), 2);

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

        $balance = round($netAmt - $rcvd, 2);
        $netBalance = round($openingBal - $balance, 2);

        $extra['discount'] = round($discount, 2);
        $extra['discount_perc'] = round($discountPerc, 3);
        $extra['tax'] = round($tax, 2);
        $extra['ast'] = round($ast, 2);
        $extra['tcs_perc'] = round($tcsPerc, 3);
        $extra['tcs_amt'] = round($tcsAmt, 2);
        $extra['repair_charge'] = round($rcAmt, 2);
        $extra['hallmark_charge'] = round($hmc, 2);
        $extra['advance'] = round($advance, 2);
        $extra['fancy_amt'] = round($fancy, 2);
        $extra['scheme_amt'] = round($scheme, 2);
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

        return [
            'bill_total' => round($billTotal, 2),
            'exchange_amount' => round($exchangeAmount, 2),
            'return_amount' => round($returnAmount, 2),
            'net_total' => round($netTotal, 2),
            'extra' => $extra,
        ];
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

    private function reserveGlobalSerialNo(): int
    {
        $current = $this->readGeneraliCounter('SERIALNO');
        $maxUsed = 0;
        foreach (['salesm', 'salesrm', 'purchasem', 'purchaserm', 'daybook', 'daybookpart', 'orderm', 'smithm', 'refinerym', 'repairm'] as $table) {
            if ($this->hasTable($table) && Schema::hasColumn($table, 'slno')) {
                $maxUsed = max($maxUsed, (int) (DB::table($table)->max('slno') ?? 0));
            }
        }

        $next = max($current, $maxUsed) + 1;
        if ($this->hasTable('generali')) {
            DB::table('generali')->updateOrInsert(['code' => 'SERIALNO'], ['cvalue' => (string) $next]);
        }

        return $next;
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
