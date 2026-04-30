<?php

namespace App\Http\Controllers;

use App\Support\SecondaryDatabaseSync;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class OrderSaleController extends Controller
{
    /** @var array<string, int|null> */
    private array $legacyColumnLengthCache = [];
    /** @var array<string, array{precision:int,scale:int}|null> */
    private array $legacyDecimalMetaCache = [];

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

    private function legacyDecimalMeta(string $table, string $column): ?array
    {
        $cacheKey = strtolower($table) . '.' . strtolower($column);
        if (array_key_exists($cacheKey, $this->legacyDecimalMetaCache)) {
            return $this->legacyDecimalMetaCache[$cacheKey];
        }

        $row = DB::selectOne(
            "SELECT NUMERIC_PRECISION AS num_precision, NUMERIC_SCALE AS num_scale
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
             LIMIT 1",
            [$table, $column]
        );

        $precision = isset($row->num_precision) ? (int) $row->num_precision : 0;
        $scale = isset($row->num_scale) ? (int) $row->num_scale : 0;

        if ($precision <= 0) {
            return $this->legacyDecimalMetaCache[$cacheKey] = null;
        }

        return $this->legacyDecimalMetaCache[$cacheKey] = [
            'precision' => $precision,
            'scale' => max(0, $scale),
        ];
    }

    private function fitLegacyNumericRowToSchema(string $table, array $row): array
    {
        if (!$this->hasTable($table)) {
            return $row;
        }

        foreach ($row as $column => $value) {
            if (!is_int($value) && !is_float($value) && !is_numeric($value)) {
                continue;
            }

            $meta = $this->legacyDecimalMeta($table, (string) $column);
            if ($meta === null) {
                continue;
            }

            $precision = (int) ($meta['precision'] ?? 0);
            $scale = (int) ($meta['scale'] ?? 0);
            $integerDigits = $precision - $scale;
            if ($integerDigits <= 0) {
                continue;
            }

            $maxAbs = pow(10, $integerDigits) - pow(10, -$scale);
            $numericValue = round((float) $value, $scale);

            if ($numericValue > $maxAbs) {
                $numericValue = $maxAbs;
            } elseif ($numericValue < -$maxAbs) {
                $numericValue = -$maxAbs;
            }

            $row[$column] = $numericValue;
        }

        return $row;
    }
    // ─── View ────────────────────────────────────────────────────────────────

    public function index(Request $request, ?string $mode = null)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $mode = strtolower((string) ($mode ?: $request->query('mode', 'bill')));
        if (!in_array($mode, ['bill', 'edit', 'without-order'], true)) {
            $mode = 'bill';
        }

        $titleMap = [
            'bill' => 'Enter Order Sale Details',
            'edit' => 'Edit Order Sale',
            'without-order' => 'Sale W/o Order',
        ];

        $settingsPayload = $this->loadSettingsPayload();
        $software = (array) ($settingsPayload['Software'] ?? []);
        $ratesCfg = (array) ($settingsPayload['Rates'] ?? []);
        $sw = static fn(array $arr, string $key, string $default = ''): string => (string) ($arr[$key] ?? $default);
        $useNwRates = strtoupper(trim($sw($software, 'NW', 'N'))) === 'Y';

        // Load exchange rates (short cache — rates change during the day)
        $rateCodes = ['GRATE', 'SRATE', 'OGRATE', 'OSRATE', 'BULRATE', 'BULTOUCH', 'THRATE', 'G18RATE'];
        $ratesRaw = Cache::remember('os_rates', 60, function () use ($rateCodes) {
            if (!$this->hasTable('generald')) return [];
            return DB::table('generald')->whereIn('code', $rateCodes)->pluck('cvalue', 'code')->toArray();
        });
        $rates = [];
        foreach ($rateCodes as $c) {
            $dbRate = round((float) ($ratesRaw[$c] ?? 0), 2);
            if ($useNwRates) { $rates[$c] = $dbRate; continue; }
            if ($dbRate > 0) { $rates[$c] = $dbRate; continue; }
            $iniRate = round($this->toNum((string) ($ratesCfg[$c] ?? '0')), 2);
            if ($iniRate > 0) { $rates[$c] = $iniRate; continue; }
            $settingRate = round($this->toNum($sw($software, $c, (string) $dbRate)), 2);
            $rates[$c] = $settingRate > 0 ? $settingRate : $dbRate;
        }

        // Load dropdown data — cached for 5 minutes (master data changes rarely)
        [$exchItems, $counters, $salesmen, $cashBanks, $states, $billTypes, $agents, $approvedBy]
            = Cache::remember('os_dropdowns', 300, function () {
            // Items for exchange dropdown
            $exchItems = [];
            if ($this->hasTable('items')) {
                $itemCols = $this->getColumns('items');
                $select = ['code', 'name', 'itype'];
                foreach (['touch', 'cost', 'disabled', 'defstktype', 'defquality', 'stkinnos', 'ornament'] as $col) {
                    if (in_array($col, $itemCols, true)) $select[] = $col;
                }
                $exchItems = DB::table('items')
                    ->whereNotNull('code')->where('code', '!=', '')
                    ->orderBy('code')->get($select)
                    ->map(fn($r) => [
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
                    ])->values()->all();
            }

            $counters = $this->hasTable('counter')
                ? DB::table('counter')->whereNotNull('code')->where('code', '!=', '')->orderBy('code')
                    ->get(['code', 'name'])->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name ?? '')])->values()->all()
                : [];

            $salesmen = $this->hasTable('sman')
                ? DB::table('sman')->orderBy('name')->get(['code', 'name'])
                    ->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name ?? '')])->values()->all()
                : [];

            $cashBanks = $this->hasTable('accountm')
                ? DB::table('accountm')->whereIn('actype2', ['H', 'B'])
                    ->orderByRaw("CASE WHEN actype2='H' THEN 0 ELSE 1 END, accode")
                    ->get(['accode as code', 'name', 'actype2'])
                    ->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name ?? ''), 'type' => $r->actype2])->values()->all()
                : [];

            $states = [];
            $stateTable = $this->hasTable('statestate') ? 'statestate' : ($this->hasTable('state') ? 'state' : null);
            if ($stateTable) {
                $stCols = $this->getColumns($stateTable);
                $codeCol = in_array('code', $stCols, true) ? 'code' : (in_array('statecode', $stCols, true) ? 'statecode' : null);
                $nameCol = in_array('name', $stCols, true) ? 'name' : (in_array('statename', $stCols, true) ? 'statename' : null);
                if ($codeCol && $nameCol) {
                    $states = DB::table($stateTable)->orderBy($codeCol)
                        ->get([$codeCol . ' as code', $nameCol . ' as name'])
                        ->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name ?? '')])->values()->all();
                }
            }

            $billTypes = [];
            if ($this->hasTable('salestype')) {
                $stCols = $this->getColumns('salestype');
                $select = ['code'];
                if (in_array('name', $stCols, true)) $select[] = 'name';
                if (in_array('taxperc', $stCols, true)) $select[] = 'taxperc';
                if (in_array('prefix', $stCols, true)) $select[] = 'prefix';
                $billTypes = DB::table('salestype')->whereNotNull('code')->where('code', '!=', '')->orderBy('code')
                    ->get($select)->map(fn($r) => [
                        'code' => strtoupper(trim((string) ($r->code ?? ''))),
                        'name' => strtoupper(trim((string) ($r->name ?? $r->code ?? ''))),
                        'taxperc' => (float) ($r->taxperc ?? 0),
                        'prefix' => strtoupper(trim((string) ($r->prefix ?? ''))),
                    ])->values()->all();
            }
            if ($billTypes === []) {
                $billTypes = [
                    ['code' => 'G', 'name' => 'GOLD', 'taxperc' => 0, 'prefix' => ''],
                    ['code' => 'S', 'name' => 'SILVER', 'taxperc' => 0, 'prefix' => ''],
                ];
            }

            $agents = [];
            if ($this->hasTable('clients')) {
                $clientCols = $this->getColumns('clients');
                if (in_array('agent', $clientCols, true)) {
                    $agents = DB::table('clients')->where('agent', 'Y')->whereNotNull('code')->where('code', '!=', '')
                        ->orderBy('code')->get(['code', 'name'])
                        ->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name ?? '')])->values()->all();
                }
            }

            $approvedBy = $this->hasTable('userm')
                ? DB::table('userm')->whereNotNull('code')->where('code', '!=', '')->orderBy('code')
                    ->get(['code', 'name'])->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name ?? '')])->values()->all()
                : [];

            return [$exchItems, $counters, $salesmen, $cashBanks, $states, $billTypes, $agents, $approvedBy];
        });

        return view('order-sale.index', [
            'mode' => $mode,
            'title' => $titleMap[$mode],
            'rates' => $rates,
            'exchItems' => $exchItems,
            'counters' => $counters,
            'salesmen' => $salesmen,
            'agents' => $agents,
            'approvedBy' => $approvedBy,
            'cashBanks' => $cashBanks,
            'states' => $states,
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
                'RoundOffAllAmt' => $sw($software, 'RoundOffAllAmt', 'N'),
                'RoundDec' => $sw($software, 'RoundDec', '2'),
                'DefRate' => $sw($software, 'DefRate', 'RTR'),
                'SRateBasedOnBullionRate' => $sw($software, 'SRateBasedOnBullionRate', 'N'),
                'SRateBeforeTax' => $sw($software, 'SRateBeforeTax', 'N'),
                'SalesDefCredit' => $sw($software, 'SalesDefCredit', 'N'),
                'BillTypewiseBillNo' => $sw($software, 'BillTypewiseBillNo', 'N'),
                'TaxBillTypeWise' => $sw($software, 'TaxBillTypeWise', 'N'),
                'DefBillType' => $sw($software, 'DefBillType', ''),
                'DefCounter' => $sw($software, 'DefCounter', ''),
                'BulTouch' => $sw($software, 'BulTouch', '99.5'),
                'TaxSystem' => $sw($software, 'TaxSystem', 'GST'),
                'DefStateCode' => (string) (($settingsPayload['Company'] ?? [])['DefStateCode'] ?? ''),
            ],
        ]);
    }

    // ─── API: Load Order ─────────────────────────────────────────────────────

    public function loadOrder(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $ordno = strtoupper(trim((string) $request->query('order_no', '')));
        if ($ordno === '') {
            return response()->json(['ok' => false, 'message' => 'Order number required.'], 422);
        }

        if (!$this->hasTable('orderm')) {
            return response()->json(['ok' => false, 'message' => 'Order table not found.']);
        }

        $order = DB::table('orderm')->where('ordno', $ordno)->first();
        if (!$order) {
            return response()->json(['ok' => false, 'message' => 'Order not found.']);
        }

        if ((int) ($order->status ?? 0) !== 1) {
            return response()->json(['ok' => false, 'message' => 'This order is cancelled or not active.']);
        }

        // Check blocked
        if (strtoupper(trim((string) ($order->blocked ?? 'N'))) === 'Y') {
            return response()->json(['ok' => false, 'message' => 'This order is blocked.']);
        }

        // Check if already sold
        if ($this->hasTable('salesm')) {
            $existingSale = DB::table('salesm')
                ->where('orderno', $ordno)
                ->where('status', 1)
                ->exists();
            if ($existingSale) {
                return response()->json(['ok' => false, 'message' => 'Sales bill already exists for this order.']);
            }
        }

        $slno = (int) ($order->slno ?? 0);
        $custCode = trim((string) ($order->custcode ?? ''));
        $custName = trim((string) ($order->custname ?? ''));
        $rate = (float) ($order->rate ?? 0);
        $advance = (float) ($order->advance ?? 0);
        $gadvance = (float) ($order->gadvance ?? 0);
        $eamt = (float) ($order->eamt ?? 0);
        $sretamt = (float) ($order->sretamt ?? 0);
        $amttowgt = strtoupper(trim((string) ($order->amttowgt ?? 'N')));
        $control = (int) ($order->control ?? 1);

        // Load order items from orderd
        $items = [];
        if ($this->hasTable('orderd')) {
            $orderdRows = DB::table('orderd')->where('slno', $slno)->orderBy('sno')->get();
            foreach ($orderdRows as $r) {
                $itemCode = trim((string) ($r->code ?? ''));
                $itemRow = $itemCode !== '' && $this->hasTable('items')
                    ? DB::table('items')->where('code', $itemCode)->first()
                    : null;
                $items[] = [
                    'item_code' => $itemCode,
                    'item_name' => trim((string) ($itemRow->name ?? $r->name ?? '')),
                    'item_type' => strtoupper(trim((string) ($itemRow->itype ?? 'G'))),
                    'qty' => (int) ($r->qty ?? 0),
                    'weight' => round((float) ($r->weight ?? 0), 3),
                    'stone_wgt' => round((float) ($r->stonewgt ?? 0), 3),
                    'stone_price' => round((float) ($r->stoneprice ?? 0), 2),
                    'making_charge' => round((float) ($r->mcharge ?? 0), 2),
                    'wastage' => round((float) ($r->wastage ?? 0), 3),
                    'rate' => round((float) ($r->rate ?? 0), 2),
                    'amount' => round((float) ($r->amount ?? 0), 2),
                    'vaperc' => round((float) ($itemRow->vaperc ?? $r->vaperc ?? 0), 3),
                    'stktype' => trim((string) ($r->stktype ?? '')),
                    'iqtype' => trim((string) ($r->iqtype ?? '')),
                    'cost' => round((float) ($itemRow->cost ?? 0), 2),
                ];
            }
        }

        // Load exchange items from purchased
        $exchangeItems = [];
        if ($this->hasTable('purchased')) {
            $exchRows = DB::table('purchased')->where('slno', $slno)->orderBy('sno')->get();
            foreach ($exchRows as $r) {
                $exchangeItems[] = [
                    'item_code' => trim((string) ($r->code ?? '')),
                    'item_name' => trim((string) ($r->name ?? '')),
                    'qty' => (int) ($r->qty ?? 0),
                    'weight' => round((float) ($r->weight ?? 0), 3),
                    'rate' => round((float) ($r->rate ?? 0), 2),
                    'less_wgt' => round((float) ($r->lesswgt ?? 0), 3),
                    'less_perc' => round((float) ($r->lessperc ?? 0), 3),
                    'amount' => round((float) ($r->amount ?? 0), 2),
                    'stone_wgt' => round((float) ($r->stwgt ?? 0), 3),
                    'stone_price' => round((float) ($r->stprice ?? 0), 2),
                    'mud_less' => round((float) ($r->mud ?? 0), 3),
                    'stktype' => trim((string) ($r->stktype ?? '')),
                    'cost' => round((float) ($r->cost ?? 0), 2),
                ];
            }
        }

        // Load gold advance items from orderdga
        $goldAdvItems = [];
        if ($this->hasTable('orderdga')) {
            $gaRows = DB::table('orderdga')->where('slno', $slno)->orderBy('sno')->get();
            foreach ($gaRows as $r) {
                $goldAdvItems[] = [
                    'code' => trim((string) ($r->code ?? '')),
                    'qty' => (int) ($r->qty ?? 0),
                    'weight' => round((float) ($r->weight ?? 0), 3),
                    'stonewgt' => round((float) ($r->stonewgt ?? 0), 3),
                    'lesswgt' => round((float) ($r->lesswgt ?? 0), 3),
                ];
            }
        }

        // Load sales return items from salesrd
        $sretItems = [];
        if ($this->hasTable('salesrd')) {
            $srRows = DB::table('salesrd')->where('slno', $slno)->orderBy('sno')->get();
            foreach ($srRows as $r) {
                $sretItems[] = [
                    'item_code' => trim((string) ($r->code ?? '')),
                    'item_name' => trim((string) ($r->name ?? '')),
                    'qty' => (int) ($r->qty ?? 0),
                    'weight' => round((float) ($r->weight ?? 0), 3),
                    'rate' => round((float) ($r->rate ?? 0), 2),
                    'stone_wgt' => round((float) ($r->stonewgt ?? 0), 3),
                    'stone_price' => round((float) ($r->stoneprice ?? 0), 2),
                    'making_charge' => round((float) ($r->mcharge ?? 0), 2),
                    'wastage' => round((float) ($r->wastage ?? 0), 3),
                    'amount' => round((float) ($r->amount ?? 0), 2),
                    'stktype' => trim((string) ($r->stktype ?? '')),
                ];
            }
        }

        // Calculate total advance (PB w_ordersale.open logic)
        $dadvance = $advance;
        $dgadvwgt = $gadvance;
        $dcashwgt = 0.0;

        if ($amttowgt === 'Y' && $rate > 0) {
            $dcashwgt = $advance / $rate;
            $dadvance = 0;
        }

        // Advance-after amounts
        $dwgtafter = 0.0;
        $damt_cash = 0.0;
        $damt_wgt = 0.0;
        $amttowgt_total = 0.0;

        if ($this->hasTable('advafter')) {
            $advCols = $this->getColumns('advafter');
            if (in_array('ordno', $advCols, true)) {
                $dwgtafter = (float) (DB::table('advafter')->where('ordno', $ordno)->sum('wgt') ?? 0);

                if (in_array('amttowgt', $advCols, true)) {
                    $damt_cash = (float) (DB::table('advafter')
                        ->where('ordno', $ordno)
                        ->where(function ($q) {
                            $q->where('amttowgt', '!=', 'Y')->orWhereNull('amttowgt');
                        })
                        ->sum('amount') ?? 0);
                    $damt_wgt = (float) (DB::table('advafter')
                        ->where('ordno', $ordno)
                        ->where('amttowgt', 'Y')
                        ->sum('amount') ?? 0);
                } else {
                    $damt_cash = (float) (DB::table('advafter')->where('ordno', $ordno)->sum('amount') ?? 0);
                }
            }
        }

        // Gold advance weight from orderdga
        $dgadvwgt2 = 0.0;
        if ($this->hasTable('orderdga')) {
            $gaAllRows = DB::table('orderdga')->where('slno', $slno)->get();
            foreach ($gaAllRows as $r) {
                $dgadvwgt2 += (float) ($r->weight ?? 0) - (float) ($r->stonewgt ?? 0) - (float) ($r->lesswgt ?? 0);
            }
        }

        $dgadvwgt += $dwgtafter + $dcashwgt + $dgadvwgt2;
        $dadvance += $damt_cash;
        $amttowgt_total = $damt_wgt;
        if ($amttowgt === 'Y') {
            $amttowgt_total += $advance;
        }

        // Customer OB
        $ob = 0.0;
        if ($custCode !== '' && $this->hasTable('daybook')) {
            $ob = round((float) (DB::table('daybook')->where('accode', $custCode)->sum('amount') ?? 0), 2);
        }

        return response()->json([
            'ok' => true,
            'order_no' => $ordno,
            'next_bill_no' => $this->peekBillNumber(trim((string) ($order->billtype ?? 'G'))),
            'slno' => $slno,
            'cust_code' => $custCode,
            'cust_name' => $custName,
            'address' => trim((string) ($order->addr ?? '')),
            'phone' => trim((string) ($order->phone ?? '')),
            'date' => $order->tdate ?? '',
            'gold_rate' => $rate,
            'salesman' => trim((string) ($order->smcode ?? '')),
            'counter' => trim((string) ($order->counter ?? '')),
            'note' => trim((string) ($order->note ?? '')),
            'bill_type' => trim((string) ($order->billtype ?? 'G')),
            'control' => $control,
            'ob' => $ob,
            'advance' => round($dadvance, 2),
            'gold_advance_wgt' => round($dgadvwgt, 3),
            'exchange_amt' => round($eamt, 2),
            'sret_amt' => round($sretamt, 2),
            'amttowgt' => $amttowgt,
            'amttowgt_total' => round($amttowgt_total, 2),
            'items' => $items,
            'exchange_items' => $exchangeItems,
            'gold_advance_items' => $goldAdvItems,
            'sales_return_items' => $sretItems,
        ]);
    }

    // ─── API: Customer Details ───────────────────────────────────────────────

    public function customerDetails(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $code = strtoupper(trim((string) $request->query('code', '')));
        if ($code === '') {
            return response()->json(['ok' => false, 'message' => 'Customer code required.'], 422);
        }

        $ob = Cache::remember('os_ob_' . $code, 60, function () use ($code) {
            if (!$this->hasTable('daybook')) return 0.0;
            return round((float) (DB::table('daybook')->where('accode', $code)->sum('amount') ?? 0), 2);
        });

        $client = null;
        if ($this->hasTable('clients')) {
            $client = DB::table('clients')->where('code', $code)->first();
        }

        return response()->json([
            'ok' => true,
            'code' => $code,
            'name' => trim((string) ($client->name ?? '')),
            'addr1' => trim((string) ($client->addr1 ?? '')),
            'ob' => $ob,
            'tin' => trim((string) ($client->tin ?? '')),
            'panadhar' => trim((string) ($client->panadhar ?? '')),
            'state' => trim((string) ($client->state ?? '')),
        ]);
    }

    // ─── API: Order Search ───────────────────────────────────────────────────

    public function orderSearch(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $q = trim((string) $request->query('q', ''));
        if (!$this->hasTable('orderm')) {
            return response()->json(['ok' => true, 'results' => []]);
        }

        $ordermCols = $this->getColumns('orderm');
        $select = ['ordno', 'slno', 'tdate', 'custcode', 'custname', 'billamt', 'advance', 'status'];
        foreach (['eamt', 'sretamt', 'gadvance', 'rate', 'duedate', 'smcode', 'counter', 'note', 'blocked'] as $col) {
            if (in_array($col, $ordermCols, true)) $select[] = $col;
        }

        $rows = DB::table('orderm')
            ->where('status', 1)
            ->where(function ($qb) use ($q) {
                if ($q !== '') {
                    $qb->where('ordno', 'like', "%{$q}%")
                        ->orWhere('custname', 'like', "%{$q}%")
                        ->orWhere('custcode', 'like', "%{$q}%");
                }
            })
            ->orderByDesc('slno')
            ->limit(30)
            ->get($select);

        // Check which orders already have a sale bill
        $soldOrders = [];
        if ($this->hasTable('salesm')) {
            $ordNos = $rows->pluck('ordno')->filter()->unique()->values()->all();
            if ($ordNos !== []) {
                $soldOrders = DB::table('salesm')
                    ->whereIn('orderno', $ordNos)
                    ->where('status', 1)
                    ->pluck('orderno')
                    ->map(fn($v) => trim((string) $v))
                    ->unique()
                    ->all();
            }
        }

        $results = $rows->map(function ($r) use ($soldOrders) {
            $ordno = trim($r->ordno ?? '');
            $billAmt = (float) ($r->billamt ?? 0);
            $advance = (float) ($r->advance ?? 0);
            $eamt = (float) ($r->eamt ?? 0);
            $sretamt = (float) ($r->sretamt ?? 0);
            $balance = $billAmt - $advance - $eamt - $sretamt;

            // Count items
            $itemCount = 0;
            if ($this->hasTable('orderd') && ($r->slno ?? 0) > 0) {
                $itemCount = DB::table('orderd')->where('slno', $r->slno)->count();
            }

            return [
                'ordno' => $ordno,
                'date' => $r->tdate ?? '',
                'cust_code' => trim($r->custcode ?? ''),
                'cust_name' => trim($r->custname ?? ''),
                'rate' => round((float) ($r->rate ?? 0), 2),
                'bill_amt' => round($billAmt, 2),
                'advance' => round($advance, 2),
                'exchange' => round($eamt, 2),
                'sret_amt' => round($sretamt, 2),
                'balance' => round($balance, 2),
                'due_date' => $r->duedate ?? '',
                'salesman' => trim($r->smcode ?? ''),
                'item_count' => $itemCount,
                'has_sale' => in_array($ordno, $soldOrders, true),
                'blocked' => strtoupper(trim((string) ($r->blocked ?? 'N'))) === 'Y',
            ];
        })->values()->all();

        return response()->json(['ok' => true, 'results' => $results]);
    }

    // ─── API: Item Lookup ────────────────────────────────────────────────────

    public function itemLookup(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $rawCode = trim((string) $request->query('code', ''));
        if ($rawCode === '') {
            return response()->json(['ok' => false, 'message' => 'Code required.'], 422);
        }

        if (!$this->hasTable('items')) {
            return response()->json(['ok' => false, 'message' => 'Items table not found.']);
        }

        $goldRate = $this->toNum($request->query('gold_rate', 0));
        if ($goldRate <= 0) {
            $goldRate = $this->readGeneraldValue('GRATE', 0);
        }
        $silverRate = $this->toNum($request->query('silver_rate', 0));
        if ($silverRate <= 0) {
            $silverRate = $this->readGeneraldValue('SRATE', 0);
        }

        // Check barcode first
        $barcodeRow = null;
        $lookupCode = $rawCode;
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

        $itemDb = DB::table('items')->where('code', $itemCode)->first();
        if (!$itemDb) {
            return response()->json(['ok' => false, 'message' => 'Item not found.'], 404);
        }

        $itype = strtoupper(trim((string) ($itemDb->itype ?? 'G')));
        $vaperc = $this->toNum($itemDb->vaperc ?? 0);
        $mcRate = $this->toNum($itemDb->mcharge ?? 0);
        $wastageRate = $this->toNum($itemDb->wastage ?? 0);
        $itemRate = $this->toNum($itemDb->rate ?? 0);
        $rate = $itemRate > 0 ? $itemRate : ($itype === 'S' ? $silverRate : $goldRate);

        $qty = 0;
        $weight = 0.0;
        $stoneWgt = 0.0;
        $stonePrice = 0.0;
        $mcAmt = 0.0;
        $bcode = 0;
        $stktype = trim((string) ($itemDb->defstktype ?? ''));
        $stktouch = $this->toNum($itemDb->stktouch ?? 0);
        $stkinnos = strtoupper(trim((string) ($itemDb->stkinnos ?? 'N')));
        $huid = '';
        $model = '';
        $qtype = trim((string) ($itemDb->defquality ?? ''));

        if ($barcodeRow) {
            $bcode = (int) ($barcodeRow->bcode ?? 0);
            $qty = (int) ($barcodeRow->qty ?? 0);
            $weight = $this->toNum($barcodeRow->weight ?? 0);
            $stoneWgt = $this->toNum($barcodeRow->stweight ?? $barcodeRow->stonewgt ?? 0);
            $stonePrice = $this->toNum($barcodeRow->stprice ?? 0);
            $mcAmt = $this->toNum($barcodeRow->mc ?? 0);
            $barcodeRate = $this->toNum($barcodeRow->rate ?? 0);
            if ($barcodeRate > 0) $rate = $barcodeRate;
            $model = trim((string) ($barcodeRow->model ?? ''));
            $huid = trim((string) ($barcodeRow->huid ?? ''));
            $stktype = trim((string) ($barcodeRow->stktype ?? $stktype));
            $stktouch = $this->toNum($barcodeRow->stktouch ?? $stktouch);
            $qtype = trim((string) ($barcodeRow->qtype ?? $qtype));
            $vapFromBarcode = $this->toNum($barcodeRow->vap ?? 0);
            if ($vapFromBarcode > 0) $vaperc = $vapFromBarcode;
        }

        if ($vaperc > 0 && $rate > 0) {
            $mcRate = ($rate * $vaperc) / 100;
        }

        $netWgt = max($weight - $stoneWgt, 0);
        if ($mcAmt <= 0 && $mcRate > 0 && $netWgt > 0) {
            $mcAmt = $netWgt * $mcRate;
        }

        $amount = $stkinnos === 'Y'
            ? round(($qty * $rate) + $stonePrice + $mcAmt, 2)
            : round(($netWgt * $rate) + $stonePrice + $mcAmt, 2);

        return response()->json([
            'ok' => true,
            'item_code' => $itemCode,
            'item_name' => trim((string) ($itemDb->name ?? '')),
            'item_type' => $itype,
            'purity' => $qtype,
            'model' => $model,
            'qty' => $qty ?: 1,
            'weight' => round($weight, 3),
            'stone_wgt' => round($stoneWgt, 3),
            'net_wgt' => round($netWgt, 3),
            'rate' => round($rate, 2),
            'stone_price' => round($stonePrice, 2),
            'wastage' => round($wastageRate, 3),
            'making_charge' => round($mcAmt, 2),
            'amount' => $amount,
            'vaperc' => $vaperc,
            'stktype' => $stktype,
            'stktouch' => $stktouch,
            'stkinnos' => $stkinnos,
            'huid' => $huid,
            'bcode' => $bcode,
            'cost' => round($this->toNum($itemDb->cost ?? 0), 2),
        ]);
    }

    // ─── API: Item Search ────────────────────────────────────────────────────

    public function itemSearch(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $q = trim((string) $request->query('q', ''));
        if (!$this->hasTable('items')) {
            return response()->json(['ok' => true, 'results' => []]);
        }

        $query = DB::table('items');
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('code', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%");
            });
        }

        $rows = $query->orderBy('code')->limit(30)->get(['code', 'name', 'itype']);
        $results = $rows->map(fn($r) => [
            'code' => trim($r->code ?? ''),
            'name' => trim($r->name ?? ''),
            'itype' => strtoupper(trim($r->itype ?? 'G')),
        ])->values()->all();

        return response()->json(['ok' => true, 'results' => $results]);
    }

    // ─── API: Recalc ─────────────────────────────────────────────────────────

    public function recalc(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $payload = $request->all();
        return response()->json([
            'ok' => true,
            'data' => $this->calcTotals($payload),
        ]);
    }

    // ─── API: Save ───────────────────────────────────────────────────────────

    public function save(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $payload = $request->validate([
            'bill_no' => 'nullable|string|max:40',
            'order_no' => 'nullable|string|max:40',
            'without_order_mode' => 'nullable',
            'bill_date' => 'nullable|string|max:20',
            'due_date' => 'nullable|string|max:20',
            'bill_time' => 'nullable|string|max:20',
            'bill_type' => 'nullable|string|max:30',
            'customer_name' => 'nullable|string|max:120',
            'customer_code' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'mobile' => 'nullable|string|max:30',
            'gst_no' => 'nullable|string|max:40',
            'pan_no' => 'nullable|string|max:30',
            'state_code' => 'nullable|string|max:20',
            'rate_per_gm' => 'nullable',
            'counter_code' => 'nullable|string|max:20',
            'salesman_code' => 'nullable|string|max:20',
            'cashbank_code' => 'nullable|string|max:20',
            'agent_code' => 'nullable|string|max:20',
            'approved_by' => 'nullable|string|max:20',
            'distance' => 'nullable',
            'items' => 'nullable|array',
            'exchange' => 'nullable|array',
            'sales_return' => 'nullable|array',
            'extra' => 'nullable|array',
            'secondary_sync' => 'nullable|boolean',
        ]);

        $shouldSecondarySync = (bool) ($payload['secondary_sync'] ?? false);
        if ($shouldSecondarySync && !SecondaryDatabaseSync::userCanUse($request->session()->get('user_code'))) {
            return response()->json(['ok' => false, 'message' => 'You do not have permission for secondary database sync.'], 403);
        }

        $orderno = strtoupper(trim((string) ($payload['order_no'] ?? '')));
        $withoutOrderMode = filter_var(($payload['without_order_mode'] ?? false), FILTER_VALIDATE_BOOLEAN);
        if ($orderno === '') {
            if ($withoutOrderMode) {
                $orderno = $this->generateWithoutOrderNo(1);
            } else {
                return response()->json(['ok' => false, 'message' => 'Order number required.'], 422);
            }
        }

        if (!$this->hasTable('salesm') || !$this->hasTable('salesd')) {
            return response()->json(['ok' => false, 'message' => 'Sales tables not found.']);
        }

        $items = collect($payload['items'] ?? [])
            ->filter(fn($row) => trim((string) ($row['item_code'] ?? '')) !== '')
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
                $row['stone_price'] = $stonePrice;
                $row['making_charge'] = $mc;
                $row['rate'] = $rate;
                $row['amount'] = round($amount, 2);
                return $row;
            })->values()->all();

        $exchange = collect($payload['exchange'] ?? [])
            ->filter(fn($row) => trim((string) ($row['item_code'] ?? '')) !== '')
            ->values()
            ->map(function ($row) {
                $row['amount'] = round($this->toNum($row['amount'] ?? 0), 2);
                return $row;
            })->values()->all();

        $salesReturn = collect($payload['sales_return'] ?? [])
            ->filter(fn($row) => trim((string) ($row['item_code'] ?? '')) !== '')
            ->values()
            ->map(function ($row) {
                $row['amount'] = round($this->toNum($row['amount'] ?? 0), 2);
                return $row;
            })->values()->all();

        $payload['items'] = $items;
        $payload['exchange'] = $exchange;
        $payload['sales_return'] = $salesReturn;
        $calc = $this->calcTotals($payload);

        if ($items === [] && $exchange === [] && $salesReturn === []) {
            return response()->json(['ok' => false, 'message' => "There are no entries. You can't proceed..."], 422);
        }

        $legacyCheck = $this->validateLegacySaveRules($request, $payload, $items, $exchange, $salesReturn, $calc);
        if (!$legacyCheck['ok']) {
            return response()->json([
                'ok' => false,
                'message' => $legacyCheck['message'] ?? 'Validation failed.',
            ], 422);
        }

        $extra = (array) ($calc['extra'] ?? []);
        $billDate = $this->parseDate((string) ($payload['bill_date'] ?? ''));
        $sqlTime = $this->toSqlTime((string) ($payload['bill_time'] ?? ''));
        $custCode = mb_substr(trim((string) ($payload['customer_code'] ?? '')), 0, 8);
        $custName = mb_substr(trim((string) ($payload['customer_name'] ?? '')), 0, 30);
        $addr = mb_substr(trim((string) ($payload['address'] ?? '')), 0, 60);
        $smCode = trim((string) ($payload['salesman_code'] ?? ''));
        $counter = trim((string) ($payload['counter_code'] ?? ''));
        $billType = trim((string) ($payload['bill_type'] ?? ''));
        $rate = $this->toNum($payload['rate_per_gm'] ?? 0);
        $cbcode = trim((string) ($payload['cashbank_code'] ?? ''));
        if ($cbcode === '' || strtoupper($cbcode) === 'CASH IN HAND') {
            $cbcode = 'CASH';
        }

        // Generate or use provided bill number
        $billNo = trim((string) ($payload['bill_no'] ?? ''));
        $isEdit = false;
        $existingSalesm = null;

        if ($billNo !== '') {
            $existingSalesm = DB::table('salesm')->where('billno', $billNo)->first();
            if ($existingSalesm) {
                $isEdit = true;
            }
        }

        if ($billNo === '' || !$isEdit) {
            $billNo = $this->generateBillNumber($billType);
        }

        $slno = $existingSalesm
            ? (int) ($existingSalesm->slno ?? 0)
            : $this->reserveGlobalSerialNo();
        if ($slno <= 0) $slno = 1;

        $taxPerc = $this->toNum($payload['extra']['tax_perc'] ?? $payload['extra']['taxperc'] ?? 0);
        $isCredit = $this->toBool($extra['credit'] ?? false);
        $isCst = $this->toBool($payload['extra']['is_cst'] ?? false);
        $addBc = $this->toBool($extra['add_bank_charge'] ?? false);
        $control = 1;

        $row = [
            'slno' => $slno,
            'billno' => $billNo,
            'tdate' => $billDate ?: now()->toDateString(),
            'ttime' => $sqlTime,
            'custcode' => $custCode,
            'custname' => $custName,
            'billamt' => round($this->toNum($calc['bill_total'] ?? 0), 2),
            'eamt' => round($this->toNum($calc['exchange_amount'] ?? 0), 2),
            'sretamt' => round($this->toNum($calc['return_amount'] ?? 0), 2),
            'staxperc' => round($taxPerc, 3),
            'staxamt' => round($this->toNum($extra['tax'] ?? 0), 2),
            'discount' => round($this->toNum($extra['discount'] ?? 0), 2),
            'discperc' => round($this->toNum($extra['discount_perc'] ?? 0), 3),
            'ramt' => round($this->toNum($extra['received'] ?? 0), 2),
            'grate' => round($rate, 2),
            'sr' => 'S',
            'status' => 1,
            'control' => $control,
            'smcode' => $smCode,
            'ob' => round($this->toNum($extra['opening_balance'] ?? 0), 2),
            'netamt' => round($this->toNum($calc['net_total'] ?? 0), 2),
            'advance' => round($this->toNum($extra['advance'] ?? 0), 2),
            'astamt' => round($this->toNum($extra['ast'] ?? 0), 2),
            'astperc' => round($this->toNum($payload['extra']['ast_perc'] ?? 0), 3),
            'billtype' => mb_substr($billType, 0, 5),
            'counter' => mb_substr($counter, 0, 5),
            'cbcode' => mb_substr($cbcode, 0, 10),
            'addr' => $addr,
            'note' => mb_substr(trim((string) ($extra['note'] ?? '')), 0, 40),
            'bcharge' => round($this->toNum($extra['bank_charge'] ?? 0), 2),
            'bcperc' => round($this->toNum($extra['bc_perc'] ?? 0), 3),
            'addbcharge' => $addBc ? 'Y' : 'N',
            'hmc' => round($this->toNum($extra['hallmark_charge'] ?? 0), 2),
            'rcamt' => round($this->toNum($extra['repair_charge'] ?? 0), 2),
            'ccamt' => round($this->toNum($extra['ccard_amt'] ?? 0), 2),
            'chqamt' => round($this->toNum($extra['chq_amt'] ?? 0), 2),
            'chqno' => mb_substr(trim((string) ($extra['chq_no'] ?? '')), 0, 30),
            'chqdate' => $this->parseDate((string) ($extra['chq_date'] ?? '')),
            'chqpdc' => $this->toBool($extra['pdc'] ?? false) ? 'Y' : 'N',
            'ccpdc' => $this->toBool($extra['ccard_pdc'] ?? false) ? 'Y' : 'N',
            'loan' => $isCredit ? 'Y' : 'N',
            'cst' => $isCst ? 'Y' : 'N',
            'mobno' => mb_substr(trim((string) ($payload['mobile'] ?? '')), 0, 25),
            'fancyamt' => round($this->toNum($extra['fancy_amt'] ?? 0), 2),
            'schmamt' => round($this->toNum($extra['scheme_amt'] ?? 0), 2),
            'pan' => mb_substr(trim((string) ($payload['pan_no'] ?? '')), 0, 20),
            'tin' => mb_substr(trim((string) ($payload['gst_no'] ?? '')), 0, 20),
            'statecode' => mb_substr(trim((string) ($payload['state_code'] ?? '')), 0, 10),
            'placeos' => mb_substr(trim((string) ($payload['supply_place'] ?? '')), 0, 60),
            'redmpoints' => round($this->toNum($extra['redeem_points'] ?? 0), 2),
            'printwanda' => $this->toBool($extra['print_wgt_amt_bal'] ?? false) ? 'Y' : 'N',
            'tcsperc' => round($this->toNum($extra['tcs_perc'] ?? 0), 3),
            'tcsamt' => round($this->toNum($extra['tcs_amt'] ?? 0), 2),
            'orderno' => $orderno,
            'agcode' => mb_substr(trim((string) ($payload['agent_code'] ?? '')), 0, 10),
            'approvedby' => mb_substr(trim((string) ($payload['approved_by'] ?? '')), 0, 10),
            'distance' => (int) $this->toNum($payload['distance'] ?? 0),
            'duedate' => $this->parseDate((string) ($payload['due_date'] ?? '')),
        ];

        // Filter row to only include columns that actually exist
        $salesmCols = $this->getColumns('salesm');
        $row = array_filter($row, fn($k) => in_array($k, $salesmCols, true), ARRAY_FILTER_USE_KEY);
        $row = $this->fitLegacyRowToSchema('salesm', $row, ['billno', 'orderno']);

        try {
        DB::transaction(function () use ($existingSalesm, $row, $slno, $items, $exchange, $salesReturn, $payload, $calc, $sqlTime, $billNo, $custCode, $custName, $billType, $smCode, $counter, $rate, $cbcode, $addr, $isCst, $control, $billDate, $extra, $taxPerc, $addBc, $isEdit, $orderno) {
            $billDateSql = $billDate ?: now()->toDateString();

            if ($existingSalesm) {
                $prevControl = (int) ($existingSalesm->control ?? 1);
                $this->reverseLegacyInventoryEffects($slno, $prevControl);
                DB::table('salesm')->where('slno', $slno)->update($row);
            } else {
                DB::table('salesm')->insert($row);
            }

            // salesd
            DB::table('salesd')->where('slno', $slno)->delete();
            $insRows = [];
            $sno = 1;
            foreach ($items as $it) {
                $code = trim((string) ($it['item_code'] ?? ''));
                if ($code === '') continue;
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
            // Filter salesd rows
            $salesdCols = $this->getColumns('salesd');
            $insRows = array_map(fn($r) => $this->fitLegacyRowToSchema('salesd', array_filter($r, fn($k) => in_array($k, $salesdCols, true), ARRAY_FILTER_USE_KEY)), $insRows);
            if (!empty($insRows)) {
                DB::table('salesd')->insert($insRows);
            }

            // Clean dependent tables for this slno
            foreach (['purchasem', 'purchased', 'salesrm', 'salesrd', 'daybook', 'daybookpart', 'daybookratewgt', 'stkandprofit', 'pdclist'] as $tbl) {
                if ($this->hasTable($tbl)) {
                    DB::table($tbl)->where('slno', $slno)->delete();
                }
            }

            $exchangeCol = collect($exchange);
            $salesReturnCol = collect($salesReturn);
            $exAmt = $this->toNum($calc['exchange_amount'] ?? 0);
            $retAmt = $this->toNum($calc['return_amount'] ?? 0);
            $isCstStr = $isCst ? 'Y' : 'N';

            // purchasem + purchased (exchange)
            if ($exAmt > 0 && $this->hasTable('purchasem')) {
                $pmRow = [
                    'slno' => $slno,
                    'tdate' => $billDateSql,
                    'ttime' => $sqlTime,
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
                    'cst' => $isCstStr,
                    'tcsperc' => 0,
                    'tcsamt' => 0,
                    'hmc' => 0,
                ];
                $pmCols = $this->getColumns('purchasem');
                $pmRow = array_filter($pmRow, fn($k) => in_array($k, $pmCols, true), ARRAY_FILTER_USE_KEY);
                $pmRow = $this->fitLegacyRowToSchema('purchasem', $pmRow);
                DB::table('purchasem')->insert($pmRow);
            }
            if ($exchangeCol->isNotEmpty() && $this->hasTable('purchased')) {
                $sno = 1;
                $rows = [];
                foreach ($exchange as $exr) {
                    $rows[] = [
                        'slno' => $slno,
                        'code' => trim((string) ($exr['item_code'] ?? '')),
                        'name' => trim((string) ($exr['item_name'] ?? '')),
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
                        'stktype' => trim((string) ($exr['stktype'] ?? '')),
                        'iqtype' => trim((string) ($exr['qtype'] ?? '')),
                        'stktouch' => $this->toNum($exr['stktouch'] ?? 0),
                        'bcode' => (int) $this->toNum($exr['bcode'] ?? 0),
                        'mcharge' => $this->toNum($exr['making_charge'] ?? 0),
                        'wastage' => $this->toNum($exr['wastage'] ?? 0),
                    ];
                }
                $purchasedCols = $this->getColumns('purchased');
                $rows = array_map(fn($r) => $this->fitLegacyRowToSchema('purchased', array_filter($r, fn($k) => in_array($k, $purchasedCols, true), ARRAY_FILTER_USE_KEY)), $rows);
                DB::table('purchased')->insert($rows);
            }

            // salesrm + salesrd (sales return)
            if ($retAmt > 0 && $this->hasTable('salesrm')) {
                $srmRow = [
                    'slno' => $slno,
                    'billno' => $billNo,
                    'tdate' => $billDateSql,
                    'ttime' => $sqlTime,
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
                    'cst' => $isCstStr,
                ];
                $srmCols = $this->getColumns('salesrm');
                $srmRow = array_filter($srmRow, fn($k) => in_array($k, $srmCols, true), ARRAY_FILTER_USE_KEY);
                $srmRow = $this->fitLegacyRowToSchema('salesrm', $srmRow);
                DB::table('salesrm')->insert($srmRow);
            }
            if ($salesReturnCol->isNotEmpty() && $this->hasTable('salesrd')) {
                $sno = 1;
                $rows = [];
                foreach ($salesReturn as $srr) {
                    $rows[] = [
                        'slno' => $slno,
                        'code' => trim((string) ($srr['item_code'] ?? '')),
                        'name' => trim((string) ($srr['item_name'] ?? '')),
                        'qty' => (int) $this->toNum($srr['qty'] ?? 0),
                        'weight' => $this->toNum($srr['weight'] ?? 0),
                        'rate' => $this->toNum($srr['rate'] ?? 0),
                        'stonewgt' => $this->toNum($srr['stone_wgt'] ?? 0),
                        'stoneprice' => $this->toNum($srr['stone_price'] ?? 0),
                        'mcharge' => $this->toNum($srr['making_charge'] ?? 0),
                        'wastage' => $this->toNum($srr['wastage'] ?? 0),
                        'amount' => round($this->toNum($srr['amount'] ?? 0), 2),
                        'sno' => $sno++,
                        'stktype' => trim((string) ($srr['stktype'] ?? '')),
                        'iqtype' => trim((string) ($srr['qtype'] ?? '')),
                        'stktouch' => $this->toNum($srr['stktouch'] ?? 0),
                        'bcode' => (int) $this->toNum($srr['bcode'] ?? 0),
                        'vaperc' => $this->toNum($srr['vaperc'] ?? 0),
                        'note' => trim((string) ($srr['note'] ?? '')),
                    ];
                }
                $salesrdCols = $this->getColumns('salesrd');
                $rows = array_map(fn($r) => $this->fitLegacyRowToSchema('salesrd', array_filter($r, fn($k) => in_array($k, $salesrdCols, true), ARRAY_FILTER_USE_KEY)), $rows);
                DB::table('salesrd')->insert($rows);
            }

            // daybookpart
            if ($this->hasTable('daybookpart')) {
                $dpRow = [
                    'slno' => $slno,
                    'particular' => mb_substr('By Order Sales (' . $billNo . ') To ' . $custName, 0, 100),
                    'vchno' => '',
                    'ic' => '',
                    'uid' => '',
                    'ttime' => $sqlTime,
                    'rate' => $rate,
                ];
                $dpCols = $this->getColumns('daybookpart');
                $dpRow = array_filter($dpRow, fn($k) => in_array($k, $dpCols, true), ARRAY_FILTER_USE_KEY);
                $dpRow = $this->fitLegacyRowToSchema('daybookpart', $dpRow);
                DB::table('daybookpart')->insert($dpRow);
            }

            // Daybook entries
            $this->insertLegacyDaybookEntries(
                $slno, $payload, $calc, $items, $exchange, $billDateSql, $control
            );

            // daybookratewgt
            if ($this->hasTable('daybookratewgt') && $custCode !== '' && $rate > 0) {
                $balance = $this->toNum($extra['balance'] ?? 0);
                if ($balance != 0) {
                    $drwRow = [
                        'slno' => $slno,
                        'rate' => $rate,
                        'mcp' => 0,
                        'wgt' => round(-($balance / $rate), 3),
                        'code' => $custCode,
                        'tdate' => $billDateSql,
                        'control' => $control,
                    ];
                    $drwCols = $this->getColumns('daybookratewgt');
                    $drwRow = array_filter($drwRow, fn($k) => in_array($k, $drwCols, true), ARRAY_FILTER_USE_KEY);
                    DB::table('daybookratewgt')->insert($drwRow);
                }
            }

            // stkandprofit
            if ($this->hasTable('stkandprofit')) {
                $disc = $this->toNum($extra['discount'] ?? 0);
                $taxAmt = $this->toNum($extra['tax'] ?? 0);
                $astAmt = $this->toNum($extra['ast'] ?? 0);
                $spRow = [
                    'slno' => $slno,
                    'tdate' => $billDateSql,
                    'stkvalue' => -round($this->toNum($calc['bill_total'] ?? 0), 2),
                    'profit' => round(($this->toNum($calc['bill_total'] ?? 0) - $disc + $taxAmt + $astAmt), 2),
                    // Keep note short to fit legacy schemas (some DBs have very small note width).
                    'note' => 'Sales',
                    'control' => $control,
                    'docno' => $billNo,
                ];
                $spCols = $this->getColumns('stkandprofit');
                $spRow = array_filter($spRow, fn($k) => in_array($k, $spCols, true), ARRAY_FILTER_USE_KEY);
                $spRow = $this->fitLegacyRowToSchema('stkandprofit', $spRow);
                $spRow = $this->fitLegacyNumericRowToSchema('stkandprofit', $spRow);
                DB::table('stkandprofit')->insert($spRow);
            }

            // Apply inventory effects
            $this->applyLegacyInventoryEffects(
                $items, $exchange, $salesReturn, $slno, $control, $billDateSql
            );

            if ($this->hasTable('orderm')) {
                $ordno = trim((string) $orderno);
                if ($ordno !== '' && !DB::table('orderm')->where('ordno', $ordno)->exists()) {
                    $om = [
                        'slno' => $slno,
                        'ordno' => $ordno,
                        'tdate' => $billDateSql,
                        'duedate' => $row['duedate'] ?? null,
                        'custcode' => $custCode,
                        'custname' => $custName,
                        'billamt' => round($this->toNum($calc['bill_total'] ?? 0), 2),
                        'eamt' => round($this->toNum($calc['exchange_amount'] ?? 0), 2),
                        'advance' => round($this->toNum($extra['advance'] ?? 0), 2),
                        'rate' => $rate,
                        'gadvance' => 0,
                        'sretamt' => round($this->toNum($calc['return_amount'] ?? 0), 2),
                        'status' => 2,
                        'control' => $control,
                        'smcode' => $smCode,
                        'salebill' => $billNo,
                        'counter' => $counter,
                    ];
                    $omCols = $this->getColumns('orderm');
                    $om = array_filter($om, fn($k) => in_array($k, $omCols, true), ARRAY_FILTER_USE_KEY);
                    $om = $this->fitLegacyRowToSchema('orderm', $om, ['ordno', 'salebill']);
                    if ($om !== []) {
                        DB::table('orderm')->insert($om);
                    }
                } elseif ($ordno !== '') {
                    $update = [
                        'status' => 2,
                        'salebill' => $billNo,
                    ];
                    if (in_array('duedate', $this->getColumns('orderm'), true)) {
                        $update['duedate'] = $row['duedate'] ?? null;
                    }
                    DB::table('orderm')
                        ->whereRaw('trim(ordno)=?', [$ordno])
                        ->update($this->fitLegacyRowToSchema('orderm', $update));
                }
            }
        });
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Save failed: ' . $e->getMessage(),
            ], 422);
        }

        $message = 'Order sale saved.';
        $secondarySync = null;
        if ($shouldSecondarySync && $slno > 0) {
            try {
                $secondarySync = (new SecondaryDatabaseSync())->sync('order_sale', (int) $slno, ['order_no' => $orderno]);
                $message .= ' Secondary sync completed to ' . ($secondarySync['database'] ?? '') . '.';
            } catch (\Throwable $e) {
                $message .= ' Primary save completed, but secondary sync failed: ' . $e->getMessage();
            }
        }

        return response()->json([
            'ok' => true,
            'message' => $message,
            'bill_no' => $billNo,
            'slno' => $slno,
            'secondary_sync' => $secondarySync,
        ]);
    }

    // ─── API: Get ────────────────────────────────────────────────────────────

    public function get(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $billNo = trim((string) $request->query('bill_no', ''));
        if ($billNo === '') {
            return response()->json(['ok' => false, 'message' => 'Bill number required.'], 422);
        }

        if (!$this->hasTable('salesm')) {
            return response()->json(['ok' => false, 'message' => 'Table not found.']);
        }

        $salesm = DB::table('salesm')->where('billno', $billNo)->first();
        if (!$salesm) {
            return response()->json(['ok' => false, 'message' => 'Bill not found.'], 404);
        }

        $slno = (int) ($salesm->slno ?? 0);

        // Load salesd items
        $items = [];
        if ($this->hasTable('salesd') && $slno > 0) {
            $sdRows = DB::table('salesd')->where('slno', $slno)->orderBy('sno')->get();
            $nameByCode = [];
            if ($this->hasTable('items')) {
                $codes = $sdRows->pluck('code')->filter()->map(fn($c) => trim((string) $c))->unique()->values()->all();
                if ($codes !== []) {
                    $nameByCode = DB::table('items')
                        ->whereIn('code', $codes)
                        ->pluck('name', 'code')
                        ->mapWithKeys(fn($v, $k) => [trim((string) $k) => trim((string) $v)])
                        ->toArray();
                }
            }
            $items = $sdRows->map(function ($r) use ($nameByCode) {
                $itemCode = trim((string) ($r->code ?? ''));
                $itemName = trim((string) ($r->name ?? ''));
                if ($itemName === '' && isset($nameByCode[$itemCode])) {
                    $itemName = $nameByCode[$itemCode];
                }
                return [
                    'item_code' => $itemCode,
                    'item_name' => $itemName,
                    'qty' => $this->toNum($r->qty ?? 0),
                    'weight' => round($this->toNum($r->weight ?? 0), 3),
                    'stone_wgt' => round($this->toNum($r->stonewgt ?? 0), 3),
                    'stone_price' => round($this->toNum($r->stoneprice ?? 0), 2),
                    'making_charge' => round($this->toNum($r->mcharge ?? 0), 2),
                    'wastage' => round($this->toNum($r->wastage ?? 0), 3),
                    'rate' => round($this->toNum($r->rate ?? 0), 2),
                    'amount' => round($this->toNum($r->amount ?? 0), 2),
                    'model' => trim((string) ($r->model ?? '')),
                    'huid' => trim((string) ($r->huid ?? '')),
                    'iqtype' => trim((string) ($r->iqtype ?? '')),
                    'stktype' => trim((string) ($r->stktype ?? '')),
                    'stktouch' => $this->toNum($r->stktouch ?? 0),
                    'vaperc' => $this->toNum($r->vaperc ?? 0),
                    'bcode' => (int) $this->toNum($r->bcode ?? 0),
                ];
            })->values()->all();
        }

        // Load exchange items
        $exchangeItems = [];
        if ($this->hasTable('purchased') && $slno > 0) {
            $exchRows = DB::table('purchased')->where('slno', $slno)->orderBy('sno')->get();
            $exchangeItems = $exchRows->map(fn($r) => [
                'item_code' => trim((string) ($r->code ?? '')),
                'item_name' => trim((string) ($r->name ?? '')),
                'qty' => (int) ($r->qty ?? 0),
                'weight' => round($this->toNum($r->weight ?? 0), 3),
                'rate' => round($this->toNum($r->rate ?? 0), 2),
                'less_wgt' => round($this->toNum($r->lesswgt ?? 0), 3),
                'less_perc' => round($this->toNum($r->lessperc ?? 0), 3),
                'amount' => round($this->toNum($r->amount ?? 0), 2),
                'stone_wgt' => round($this->toNum($r->stwgt ?? 0), 3),
                'stone_price' => round($this->toNum($r->stprice ?? 0), 2),
                'mud_less' => round($this->toNum($r->mud ?? 0), 3),
                'stktype' => trim((string) ($r->stktype ?? '')),
                'cost' => round($this->toNum($r->cost ?? 0), 2),
            ])->values()->all();
        }

        // Load sales return items
        $sretItems = [];
        if ($this->hasTable('salesrd') && $slno > 0) {
            $srRows = DB::table('salesrd')->where('slno', $slno)->orderBy('sno')->get();
            $sretItems = $srRows->map(fn($r) => [
                'item_code' => trim((string) ($r->code ?? '')),
                'item_name' => trim((string) ($r->name ?? '')),
                'qty' => (int) ($r->qty ?? 0),
                'weight' => round($this->toNum($r->weight ?? 0), 3),
                'rate' => round($this->toNum($r->rate ?? 0), 2),
                'stone_wgt' => round($this->toNum($r->stonewgt ?? 0), 3),
                'stone_price' => round($this->toNum($r->stoneprice ?? 0), 2),
                'making_charge' => round($this->toNum($r->mcharge ?? 0), 2),
                'wastage' => round($this->toNum($r->wastage ?? 0), 3),
                'amount' => round($this->toNum($r->amount ?? 0), 2),
                'stktype' => trim((string) ($r->stktype ?? '')),
            ])->values()->all();
        }

        $billType = trim((string) ($salesm->billtype ?? ''));
        if ($billType === '') $billType = 'G';

        $balanceDue = round(
            $this->toNum($salesm->netamt ?? 0)
            - $this->toNum($salesm->discount ?? 0)
            - $this->toNum($salesm->ramt ?? 0),
            2
        );

        $extra = [
            'discount' => round($this->toNum($salesm->discount ?? 0), 2),
            'discount_perc' => round($this->toNum($salesm->discperc ?? 0), 3),
            'tax' => round($this->toNum($salesm->staxamt ?? 0), 2),
            'tax_perc' => round($this->toNum($salesm->staxperc ?? 0), 3),
            'ast' => round($this->toNum($salesm->astamt ?? 0), 2),
            'repair_charge' => round($this->toNum($salesm->rcamt ?? 0), 2),
            'hallmark_charge' => round($this->toNum($salesm->hmc ?? 0), 2),
            'advance' => round($this->toNum($salesm->advance ?? 0), 2),
            'fancy_amt' => round($this->toNum($salesm->fancyamt ?? 0), 2),
            'scheme_amt' => round($this->toNum($salesm->schmamt ?? 0), 2),
            'bank_charge' => round($this->toNum($salesm->bcharge ?? 0), 2),
            'bc_perc' => round($this->toNum($salesm->bcperc ?? 0), 3),
            'add_bank_charge' => strtoupper(trim((string) ($salesm->addbcharge ?? 'N'))) === 'Y',
            'opening_balance' => round($this->toNum($salesm->ob ?? 0), 2),
            'received' => round($this->toNum($salesm->ramt ?? 0), 2),
            'credit' => strtoupper(trim((string) ($salesm->loan ?? 'N'))) === 'Y' || $balanceDue > 0,
            'note' => trim((string) ($salesm->note ?? '')),
            'redeem_points' => round($this->toNum($salesm->redmpoints ?? 0), 2),
            'print_wgt_amt_bal' => strtoupper(trim((string) ($salesm->printwanda ?? 'N'))) === 'Y',
            'tcs_perc' => round($this->toNum($salesm->tcsperc ?? 0), 3),
            'tcs_amt' => round($this->toNum($salesm->tcsamt ?? 0), 2),
            'ccard_amt' => round($this->toNum($salesm->ccamt ?? 0), 2),
            'chq_amt' => round($this->toNum($salesm->chqamt ?? 0), 2),
            'chq_no' => trim((string) ($salesm->chqno ?? '')),
            'chq_date' => !empty($salesm->chqdate) ? Carbon::parse((string) $salesm->chqdate)->format('d/m/Y') : '',
            'pdc' => strtoupper(trim((string) ($salesm->chqpdc ?? 'N'))) === 'Y',
            'ccard_pdc' => strtoupper(trim((string) ($salesm->ccpdc ?? 'N'))) === 'Y',
        ];

        $salesmCols = $this->getColumns('salesm');

        return response()->json([
            'ok' => true,
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
            'supply_place' => trim((string) ($salesm->placeos ?? '')),
            'due_date' => !empty($salesm->duedate) ? Carbon::parse((string) $salesm->duedate)->format('d/m/Y') : '',
            'rate_per_gm' => round($this->toNum($salesm->grate ?? 0), 2),
            'counter_code' => trim((string) ($salesm->counter ?? '')),
            'salesman_code' => trim((string) ($salesm->smcode ?? '')),
            'cashbank_code' => trim((string) ($salesm->cbcode ?? '')),
            'agent_code' => trim((string) ($salesm->agcode ?? '')),
            'approved_by' => trim((string) ($salesm->approvedby ?? '')),
            'order_no' => in_array('orderno', $salesmCols, true) ? trim((string) ($salesm->orderno ?? '')) : '',
            'bill_total' => round($this->toNum($salesm->billamt ?? 0), 2),
            'exchange_amount' => round($this->toNum($salesm->eamt ?? 0), 2),
            'return_amount' => round($this->toNum($salesm->sretamt ?? 0), 2),
            'net_total' => round($this->toNum($salesm->netamt ?? 0), 2),
            'status' => (int) ($salesm->status ?? 1),
            'slno' => $slno,
            'items' => $items,
            'exchange' => $exchangeItems,
            'sales_return' => $sretItems,
            'extra' => $extra,
        ]);
    }

    // ─── API: Search ─────────────────────────────────────────────────────────

    public function search(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $q = trim((string) $request->query('q', ''));
        if (!$this->hasTable('salesm')) {
            return response()->json(['ok' => true, 'results' => []]);
        }

        $query = DB::table('salesm')
            ->whereNotNull('orderno')
            ->where('orderno', '!=', '');

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('billno', 'like', "%{$q}%")
                    ->orWhere('custname', 'like', "%{$q}%")
                    ->orWhere('orderno', 'like', "%{$q}%");
            });
        }

        $rows = $query->orderByDesc('slno')
            ->limit(25)
            ->get(['billno', 'tdate', 'custname', 'billamt', 'orderno', 'status']);

        $results = $rows->map(fn($r) => [
            'bill_no' => trim($r->billno ?? ''),
            'date' => $r->tdate,
            'cust_name' => trim($r->custname ?? ''),
            'bill_total' => round((float) ($r->billamt ?? 0), 2),
            'order_no' => trim($r->orderno ?? ''),
            'status' => (int) ($r->status ?? 1),
        ])->values()->all();

        return response()->json(['ok' => true, 'results' => $results]);
    }

    // ─── API: Navigation ─────────────────────────────────────────────────────

    public function prevBill(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $billNo = trim((string) $request->query('bill_no', ''));
        if (!$this->hasTable('salesm')) {
            return response()->json(['ok' => true, 'bill_no' => null]);
        }

        $current = DB::table('salesm')
            ->where('billno', $billNo)
            ->first(['slno']);

        if ($current) {
            $targetSlno = (int) DB::table('salesm')
                ->where('slno', '<', (int) $current->slno)
                ->whereNotNull('orderno')
                ->where('orderno', '!=', '')
                ->max('slno');
        } else {
            $targetSlno = (int) DB::table('salesm')
                ->whereNotNull('orderno')
                ->where('orderno', '!=', '')
                ->max('slno');
        }

        if ($targetSlno > 0) {
            $targetBillNo = trim((string) (DB::table('salesm')->where('slno', $targetSlno)->value('billno') ?? ''));
            if ($targetBillNo !== '') {
                return response()->json(['ok' => true, 'bill_no' => $targetBillNo]);
            }
        }

        return response()->json(['ok' => true, 'bill_no' => null]);
    }

    public function nextBill(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $billNo = trim((string) $request->query('bill_no', ''));
        if (!$this->hasTable('salesm')) {
            return response()->json(['ok' => true, 'bill_no' => null]);
        }

        $current = DB::table('salesm')
            ->where('billno', $billNo)
            ->first(['slno']);

        $targetSlno = 0;
        if ($current) {
            $targetSlno = (int) (DB::table('salesm')
                ->where('slno', '>', (int) $current->slno)
                ->whereNotNull('orderno')
                ->where('orderno', '!=', '')
                ->min('slno') ?? 0);
        }

        if ($targetSlno > 0) {
            $targetBillNo = trim((string) (DB::table('salesm')->where('slno', $targetSlno)->value('billno') ?? ''));
            if ($targetBillNo !== '') {
                return response()->json(['ok' => true, 'bill_no' => $targetBillNo]);
            }
        }

        return response()->json(['ok' => true, 'bill_no' => null]);
    }

    // ─── API: Cancel ─────────────────────────────────────────────────────────

    public function cancelBill(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $billNo = trim((string) ($request->input('bill_no') ?? ''));
        if ($billNo === '') {
            return response()->json(['ok' => false, 'message' => 'Bill number required.'], 422);
        }

        if (!$this->hasTable('salesm')) {
            return response()->json(['ok' => false, 'message' => 'Table not found.']);
        }

        $salesm = DB::table('salesm')->where('billno', $billNo)->first();
        if (!$salesm) {
            return response()->json(['ok' => false, 'message' => 'Bill not found.'], 404);
        }

        $slno = (int) ($salesm->slno ?? 0);
        $control = (int) ($salesm->control ?? 1);
        $orderno = trim((string) ($salesm->orderno ?? ''));

        DB::transaction(function () use ($slno, $control, $billNo, $orderno) {
            // Reverse inventory
            $this->reverseLegacyInventoryEffects($slno, $control);

            // Set status to 0
            DB::table('salesm')->where('billno', $billNo)->update(['status' => 0]);

            // Clean dependent tables
            foreach (['daybook', 'daybookpart', 'daybookratewgt', 'stkandprofit', 'pdclist'] as $tbl) {
                if ($this->hasTable($tbl)) {
                    DB::table($tbl)->where('slno', $slno)->delete();
                }
            }

            if ($orderno !== '' && $this->hasTable('orderm')) {
                $update = ['status' => 1];
                if (in_array('salebill', $this->getColumns('orderm'), true)) {
                    $update['salebill'] = null;
                }
                DB::table('orderm')
                    ->whereRaw('trim(ordno)=?', [$orderno])
                    ->update($update);
            }
        });

        $message = 'Order sale cancelled.';
        if (SecondaryDatabaseSync::userCanUse((string) $request->session()->get('user_code')) && $slno > 0) {
            try {
                $secondarySync = (new SecondaryDatabaseSync())->sync('order_sale', $slno, ['order_no' => $orderno]);
                $message .= ' Secondary sync completed to ' . ($secondarySync['database'] ?? '') . '.';
            } catch (\Throwable $e) {
                $message .= ' Primary cancel completed, but secondary sync failed: ' . $e->getMessage();
            }
        }

        return response()->json(['ok' => true, 'message' => $message]);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Private Helpers
    // ═════════════════════════════════════════════════════════════════════════

    private function generateBillNumber(string $billType): string
    {
        $settingsPayload = $this->loadSettingsPayload();
        $software = (array) ($settingsPayload['Software'] ?? []);
        $sw = static fn(array $arr, string $key, string $default = ''): string => (string) ($arr[$key] ?? $default);
        $billTypewiseBillNo = strtoupper(trim($sw($software, 'BillTypewiseBillNo', 'N'))) === 'Y';

        if ($billTypewiseBillNo && $this->hasTable('salestype')) {
            $billTypeTxt = strtoupper(trim($billType));
            $aliases = [$billTypeTxt];
            if ($billTypeTxt === 'GOLD') $aliases[] = 'G';
            elseif ($billTypeTxt === 'SILVER') $aliases[] = 'S';
            elseif ($billTypeTxt !== '') $aliases[] = substr($billTypeTxt, 0, 1);
            $aliases = array_values(array_unique(array_filter($aliases)));

            $stCols = $this->getColumns('salestype');
            if (in_array('prefix', $stCols, true)) {
                $row = DB::table('salestype')
                    ->where(function ($q) use ($aliases, $stCols) {
                        foreach ($aliases as $alias) {
                            $q->orWhereRaw('upper(trim(code)) = ?', [$alias]);
                            if (in_array('name', $stCols, true)) {
                                $q->orWhereRaw('upper(trim(name)) = ?', [$alias]);
                            }
                        }
                    })
                    ->first();

                if ($row) {
                    $prefix = trim((string) ($row->prefix ?? ''));
                    if ($prefix !== '') {
                        $counterCode = 'SALES' . $prefix;
                        $lbillno = $this->readGeneraliCounter($counterCode);
                        $maxUsed = $this->lastSalesBillNumberForPrefix($prefix);
                        $next = max($lbillno, $maxUsed) + 1;
                        $this->incrementGeneraliCounter($counterCode, $next);
                        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
                    }
                }
            }
        }

        $prefix = $this->readGeneralsValue('SBPREF', 'SLB/');
        $len = (int) $this->toNum($this->readGeneralsValue('SBLEN', '5'));
        if ($len <= 0) $len = 5;

        $lbillno = $this->readGeneraliCounter('SALESB');
        $maxUsed = $this->lastSalesBillNumberForPrefix($prefix);
        $next = max($lbillno, $maxUsed) + 1;
        $this->incrementGeneraliCounter('SALESB', $next);

        return $prefix . str_pad((string) $next, $len, '0', STR_PAD_LEFT);
    }

    private function peekBillNumber(string $billType): string
    {
        $settingsPayload = $this->loadSettingsPayload();
        $software = (array) ($settingsPayload['Software'] ?? []);
        $sw = static fn(array $arr, string $key, string $default = ''): string => (string) ($arr[$key] ?? $default);
        $billTypewiseBillNo = strtoupper(trim($sw($software, 'BillTypewiseBillNo', 'N'))) === 'Y';

        if ($billTypewiseBillNo && $this->hasTable('salestype')) {
            $billTypeTxt = strtoupper(trim($billType));
            $aliases = [$billTypeTxt];
            if ($billTypeTxt === 'GOLD') $aliases[] = 'G';
            elseif ($billTypeTxt === 'SILVER') $aliases[] = 'S';
            elseif ($billTypeTxt !== '') $aliases[] = substr($billTypeTxt, 0, 1);
            $aliases = array_values(array_unique(array_filter($aliases)));

            $stCols = $this->getColumns('salestype');
            if (in_array('prefix', $stCols, true)) {
                $row = DB::table('salestype')
                    ->where(function ($q) use ($aliases, $stCols) {
                        foreach ($aliases as $alias) {
                            $q->orWhereRaw('upper(trim(code)) = ?', [$alias]);
                            if (in_array('name', $stCols, true)) {
                                $q->orWhereRaw('upper(trim(name)) = ?', [$alias]);
                            }
                        }
                    })
                    ->first();

                if ($row) {
                    $prefix = trim((string) ($row->prefix ?? ''));
                    if ($prefix !== '') {
                        $lbillno = $this->readGeneraliCounter('SALES' . $prefix);
                        $maxUsed = $this->lastSalesBillNumberForPrefix($prefix);
                        $next = max($lbillno, $maxUsed) + 1;
                        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
                    }
                }
            }
        }

        $prefix = $this->readGeneralsValue('SBPREF', 'SLB/');
        $len = (int) $this->toNum($this->readGeneralsValue('SBLEN', '5'));
        if ($len <= 0) $len = 5;

        $lbillno = $this->readGeneraliCounter('SALESB');
        $maxUsed = $this->lastSalesBillNumberForPrefix($prefix);
        $next = max($lbillno, $maxUsed) + 1;
        return $prefix . str_pad((string) $next, $len, '0', STR_PAD_LEFT);
    }

    private function lastSalesBillNumberForPrefix(string $prefix): int
    {
        if ($prefix === '' || !$this->hasTable('salesm')) {
            return 0;
        }

        $maxNo = 0;
        $rows = DB::table('salesm')
            ->whereNotNull('billno')
            ->where('billno', 'like', $prefix . '%')
            ->get(['billno']);

        foreach ($rows as $row) {
            $value = trim((string) ($row->billno ?? ''));
            if ($value === '' || !str_starts_with($value, $prefix)) {
                continue;
            }

            $suffix = substr($value, strlen($prefix));
            if (!ctype_digit($suffix)) {
                continue;
            }

            $number = (int) $suffix;
            if ($number > $maxNo) {
                $maxNo = $number;
            }
        }

        return $maxNo;
    }

    private function calcTotals(array $payload): array
    {
        $items = collect($payload['items'] ?? []);
        $exchange = collect($payload['exchange'] ?? []);
        $salesReturn = collect($payload['sales_return'] ?? []);
        $extra = (array) ($payload['extra'] ?? []);

        $billTotal = round($items->sum(fn($r) => $this->toNum($r['amount'] ?? 0)), 2);
        $exchangeAmount = round($exchange->sum(fn($r) => $this->toNum($r['amount'] ?? 0)), 2);
        $returnAmount = round($salesReturn->sum(fn($r) => $this->toNum($r['amount'] ?? 0)), 2);

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
        $ccardAmt = $this->toNum($extra['ccard_amt'] ?? 0);
        $chqAmt   = $this->toNum($extra['chq_amt']   ?? 0);
        $cashLoss = $this->toNum($extra['cash_less']  ?? 0);

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

        $balance = round($netAmt - $rcvd - $ccardAmt - $chqAmt - $cashLoss, 2);
        $netBalance = round($openingBal + $balance, 2);

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
        $extra['ccard_amt'] = round($ccardAmt, 2);
        $extra['chq_amt']   = round($chqAmt, 2);
        $extra['cash_less'] = round($cashLoss, 2);
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

    private function reverseLegacyInventoryEffects(int $slno, int $control): void
    {
        if ($slno <= 0) return;

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

    private function applyLegacyInventoryEffects(array $items, array $exchange, array $salesReturn, int $slno, int $control, string $billDateSql): void
    {
        // Sale items = decrease stock
        foreach ($items as $it) {
            $code = trim((string) ($it['item_code'] ?? ''));
            if ($code === '') continue;
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

        // Exchange items = increase stock
        foreach ($exchange as $ex) {
            $code = trim((string) ($ex['item_code'] ?? ''));
            if ($code === '') continue;
            $this->adjustItemStock(
                $code,
                $this->toNum($ex['qty'] ?? 0),
                $this->toNum($ex['weight'] ?? 0),
                -$this->toNum($ex['stone_wgt'] ?? 0),
                trim((string) ($ex['stktype'] ?? '')),
                $control
            );
        }

        // Sales return = increase stock
        foreach ($salesReturn as $sr) {
            $code = trim((string) ($sr['item_code'] ?? ''));
            if ($code === '') continue;
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
        if ($code === '' || !$this->hasTable('items')) return;

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

        if ($stkType === '' || !$this->hasTable('itemsstk')) return;

        $stkCols = $this->getColumns('itemsstk');
        if (!in_array('code', $stkCols, true) || !in_array('stktype', $stkCols, true)) return;

        $exists = DB::table('itemsstk')->where('code', $code)->where('stktype', $stkType)->exists();
        if (!$exists) {
            DB::table('itemsstk')->insert(['code' => $code, 'stktype' => $stkType]);
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
        if (!$this->hasTable('daybook')) return;

        $settingsPayload = $this->loadSettingsPayload();
        $software = (array) ($settingsPayload['Software'] ?? []);
        $sw = static fn(array $arr, string $key, string $default = ''): string => (string) ($arr[$key] ?? $default);
        $flagY = static fn(mixed $v, string $def = 'N'): bool => strtoupper(trim((string) ($v ?? $def))) === 'Y';

        $extra = (array) ($calc['extra'] ?? []);
        $billNo = trim((string) ($payload['bill_no'] ?? ''));
        $custCode = trim((string) ($payload['customer_code'] ?? ''));
        $coPartyCode = trim((string) ($payload['co_party_code'] ?? ''));
        $cbcode = trim((string) ($payload['cashbank_code'] ?? ''));
        if ($cbcode === '' || strtoupper($cbcode) === 'CASH IN HAND') $cbcode = 'CASH';
        if ($coPartyCode === '' && $custCode !== '') {
            if ($this->hasTable('clients')) {
                $coPartyCode = trim((string) (DB::table('clients')->whereRaw('TRIM(code)=?', [$custCode])->value('cocode') ?? ''));
            }
            if ($coPartyCode === '' && $this->hasTable('clients_kuridet')) {
                $coPartyCode = trim((string) (DB::table('clients_kuridet')->where('custlinkac', $custCode)->value('code') ?? ''));
            }
        }
        $chqBank = trim((string) ($extra['chq_bank'] ?? $extra['cheque_bank'] ?? $cbcode));
        if ($chqBank === '') $chqBank = $cbcode;

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
        $hmcAmt = round($this->toNum($extra['hallmark_charge'] ?? 0), 2);
        $netAmt = round($this->toNum($calc['net_total'] ?? 0), 2);
        $rcvd = round($this->toNum($extra['received'] ?? 0), 2);
        $isCst = $flagY($payload['extra']['is_cst'] ?? 'N');
        $taxSystem = strtoupper(trim($sw($software, 'TaxSystem', 'GST')));
        $vaSepAc = $flagY($sw($software, 'VASepAc', 'N'));
        $addBc = $flagY($extra['add_bank_charge'] ?? false, 'N');

        $ccAmt = round($this->toNum($extra['cc_amt'] ?? $extra['ccamt'] ?? 0), 2);
        $chqAmt = round($this->toNum($extra['chq_amt'] ?? $extra['cheque_amt'] ?? 0), 2);
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
            if ($ac === '' || $amt == 0.0) return;
            $bucket[] = ['accode' => $ac, 'amount' => $amt, 'opaccode' => $op];
        };

        // Receipt split
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
                    if (!$addBc) $add($entries, 'BCOMN', -$dcomn, $opAcCode);
                }
                if ($dcomnTax != 0.0) {
                    $add($entries, $cbcode, $dcomnTax, $opAcCode);
                    if (!$addBc) $add($entries, 'BCOMNTAX', -$dcomnTax, $opAcCode);
                }
            }
        }

        // PDC entries
        if ($this->hasTable('pdclist')) {
            $chqNo = trim((string) ($extra['chq_no'] ?? $extra['cheque_no'] ?? $billNo));
            $chqDateRaw = trim((string) ($extra['chq_date'] ?? $extra['cheque_date'] ?? ''));
            $chqDate = $this->parseDate($chqDateRaw) ?: $billDateSql;
            $isChqPdc = $flagY($extra['chq_pdc'] ?? $extra['pdc'] ?? 'N');
            $isCcPdc = $flagY($extra['cc_pdc'] ?? $extra['ccpdc'] ?? 'N');
            $particular = mb_substr('By Order Sales (' . $billNo . ')', 0, 100);

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
                } catch (\Throwable) {}
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

        // Customer debit/credit
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
            $add($entries, $coPartyCode !== '' ? $coPartyCode : 'APP', -$schemeAmt, $opAcCode);
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

        // Sales / Exchange / Return heads
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

        // Balancing entry
        if ($this->hasTable('daybook')) {
            $sum = (float) (DB::table('daybook')->where('slno', $slno)->sum('amount') ?? 0);
            $sum = round($sum, 2);
            if ($sum != 0.0) {
                $this->insertLegacyDaybookRow($slno, $sno, $billDateSql, 'ROUND', -$sum, $control, 'RS');
            }
        }
    }

    private function insertLegacyDaybookRow(
        int $slno, int $sno, string $tdate, string $accode, float $amount, int $control, string $opaccode
    ): void {
        if (!$this->hasTable('daybook')) return;

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
        if (in_array('narration', $cols, true)) $row['narration'] = 'Order Sales entry';
        DB::table('daybook')->insert($row);
    }

    private function insertPdclistRow(array $data): void
    {
        if (!$this->hasTable('pdclist')) return;
        $cols = $this->getColumns('pdclist');
        $row = [];
        foreach (['slno', 'tdate', 'docno', 'bank', 'code', 'chqno', 'chqdate', 'amount', 'particulars', 'rp', 'pend', 'control'] as $k) {
            if (in_array($k, $cols, true) && array_key_exists($k, $data)) {
                $row[$k] = $data[$k];
            }
        }
        if ($row !== []) {
            DB::table('pdclist')->insert($row);
        }
    }

    // ─── Utility Helpers ─────────────────────────────────────────────────────

    private function validateLegacySaveRules(
        Request $request,
        array $payload,
        array $items,
        array $exchange,
        array $salesReturn,
        array $calc
    ): array {
        $settingsPayload = $this->loadSettingsPayload();
        $software = (array) ($settingsPayload['Software'] ?? []);
        $sw = static fn(array $arr, string $key, string $default = ''): string => (string) ($arr[$key] ?? $default);

        $extra = (array) ($calc['extra'] ?? []);
        $balance = round($this->toNum($extra['balance'] ?? 0), 2);
        $billTotal = round($this->toNum($calc['bill_total'] ?? 0), 2);
        $discount = round($this->toNum($extra['discount'] ?? 0), 2);
        $rdDisc = round($this->toNum($extra['rddisc'] ?? 0), 2);
        $dueRaw = trim((string) ($payload['due_date'] ?? ''));
        $custCode = trim((string) ($payload['customer_code'] ?? ''));
        $userCode = trim((string) ($request->session()->get('user_code', '')));

        if ($balance > 0 && strtoupper(trim($sw($software, 'DuedateComp', 'Y'))) === 'Y' && $dueRaw === '') {
            return ['ok' => false, 'message' => 'Enter due date for credit sale.'];
        }

        if ($balance != 0 && $custCode === '') {
            return ['ok' => false, 'message' => 'Enter customer for credit sale.'];
        }

        if ($balance > 0 && $userCode !== '' && $this->hasTable('userd')) {
            $creditBlocked = DB::table('userd')
                ->where('code', $userCode)
                ->where('menuitem', 'CREDIT')
                ->exists();
            if ($creditBlocked) {
                return ['ok' => false, 'message' => "You are not permitted to enter credit in sales."];
            }
        }

        if ($balance > 0 && $userCode !== '' && $this->hasTable('userm')) {
            $maxCredit = $this->toNum(DB::table('userm')->where('code', $userCode)->value('maxcredit') ?? 0);
            if ($maxCredit > 0 && $balance > $maxCredit) {
                return ['ok' => false, 'message' => 'Credit is out of limit. You cannot save.'];
            }
        }

        if ($userCode !== '' && $this->hasTable('userm') && $billTotal > 0) {
            $maxDiscPerc = $this->toNum(DB::table('userm')->where('code', $userCode)->value('maxdiscperc') ?? 0);
            if ($maxDiscPerc > 0) {
                $discPerc = (($discount + $rdDisc) * 100) / $billTotal;
                if ($discPerc > $maxDiscPerc) {
                    return ['ok' => false, 'message' => 'Discount is more than allowed percentage.'];
                }
            }
        }

        $stktypeStrict = strtoupper(trim($sw($software, 'StktypeStrict', 'N'))) === 'Y';

        foreach ($items as $it) {
            $code = trim((string) ($it['item_code'] ?? ''));
            if ($code === '') continue;

            $weight = $this->toNum($it['weight'] ?? 0);
            $rate = $this->toNum($it['rate'] ?? 0);
            $stktype = trim((string) ($it['stktype'] ?? ''));
            $stkInNos = 'N';

            if ($this->hasTable('items')) {
                $stkInNos = strtoupper(trim((string) (DB::table('items')->where('code', $code)->value('stkinnos') ?? 'N')));
            }

            if ($stktypeStrict && $stktype === '') {
                return ['ok' => false, 'message' => "Check Stock Type ($code). You can't save..."];
            }
            if ($stkInNos !== 'Y' && $weight <= 0) {
                return ['ok' => false, 'message' => "Check Weight ($code). You can't save..."];
            }
            if ($rate <= 0) {
                return ['ok' => false, 'message' => "Check Rate ($code). You can't save..."];
            }
        }

        $checkRows = static function (array $rows, string $label): array {
            foreach ($rows as $r) {
                $code = trim((string) ($r['item_code'] ?? ''));
                if ($code === '') continue;
                $qty = (float) ($r['qty'] ?? 0);
                $wgt = (float) ($r['weight'] ?? 0);
                if ($qty == 0.0 && $wgt == 0.0) continue;
                if ($wgt <= 0.0) {
                    return ['ok' => false, 'message' => "Check Weight ($code) in $label."];
                }
            }
            return ['ok' => true];
        };

        $exCheck = $checkRows($exchange, 'Exchange');
        if (!$exCheck['ok']) return $exCheck;
        $srCheck = $checkRows($salesReturn, 'Sales Return');
        if (!$srCheck['ok']) return $srCheck;

        return ['ok' => true];
    }

    private function toNum(mixed $value): float
    {
        if (is_numeric($value)) return (float) $value;
        $v = trim((string) $value);
        $v = str_replace(',', '', $v);
        return is_numeric($v) ? (float) $v : 0.0;
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) return $value;
        $str = strtolower(trim((string) $value));
        return in_array($str, ['1', 'true', 'y', 'yes'], true);
    }

    private function generateWithoutOrderNo(int $control = 1): string
    {
        $key = $control === 1 ? 'ORDERB' : 'ORDERE';
        $prefix = $control === 1 ? 'ODBT' : 'ODET';

        if (!$this->hasTable('generali')) {
            return $prefix . str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);
        }

        $raw = DB::table('generali')->where('code', $key)->value('cvalue');
        if ($raw === null) {
            DB::table('generali')->insert(['code' => $key, 'cvalue' => 0]);
            $raw = 0;
        }

        $next = ((int) $raw) + 1;
        DB::table('generali')->where('code', $key)->update(['cvalue' => $next]);
        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    private function parseDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') return now()->toDateString();
        try {
            if (str_contains($value, '/')) {
                return Carbon::createFromFormat('d/m/Y', $value)->toDateString();
            }
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return now()->toDateString();
        }
    }

    private function toSqlTime(string $value): string
    {
        $value = trim($value);
        if ($value === '') return now()->format('H:i:s');
        try {
            return Carbon::parse($value)->format('H:i:s');
        } catch (\Throwable) {
            return now()->format('H:i:s');
        }
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

    private function readGeneraliCounter(string $code): int
    {
        if (!$this->hasTable('generali')) return 0;
        $val = DB::table('generali')->where('code', $code)->value('cvalue');
        return ($val === null || $val === '') ? 0 : (int) $this->toNum((string) $val);
    }

    private function incrementGeneraliCounter(string $code, int $value): void
    {
        if (!$this->hasTable('generali')) return;
        $updated = DB::table('generali')->where('code', $code)->update(['cvalue' => $value]);
        if ($updated === 0) {
            DB::table('generali')->insert(['code' => $code, 'cvalue' => $value]);
        }
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
        if (!$this->hasTable('generals')) return $default;
        $val = DB::table('generals')->where('code', $code)->value('cvalue');
        $txt = trim((string) ($val ?? ''));
        return $txt === '' ? $default : $txt;
    }

    private function readGeneraldValue(string $code, $default = ''): float
    {
        if (!$this->hasTable('generald')) return (float) $default;
        $val = DB::table('generald')->where('code', $code)->value('cvalue');
        return $val !== null ? (float) $val : (float) $default;
    }

    private function loadSettingsPayload(): array
    {
        static $cached = null;
        if ($cached !== null) return $cached;
        $iniPaths = [
            storage_path('app/software-settings.ini'),
        ];
        foreach ($iniPaths as $iniPath) {
            if (File::exists($iniPath)) {
                return $cached = $this->parseLegacyIni((string) File::get($iniPath));
            }
        }
        return $cached = ['Software' => [], 'Rates' => []];
    }

    private function parseLegacyIni(string $raw): array
    {
        $result = [];
        $section = '';
        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, ';') || str_starts_with($line, '#') || str_starts_with($line, '//')) continue;
            if (preg_match('/^\[(.+)\]$/', $line, $m) === 1) {
                $section = trim($m[1]);
                if ($section !== '' && !isset($result[$section])) $result[$section] = [];
                continue;
            }
            $eqPos = strpos($line, '=');
            if ($eqPos === false) continue;
            $k = trim(substr($line, 0, $eqPos));
            $v = trim(substr($line, $eqPos + 1));
            if ($k === '') continue;
            if ($section === '') {
                $section = 'Software';
                if (!isset($result[$section])) $result[$section] = [];
            }
            $result[$section][$k] = $v;
        }
        if (!isset($result['Software'])) $result['Software'] = [];
        if (!isset($result['Rates'])) $result['Rates'] = [];
        return $result;
    }
}
