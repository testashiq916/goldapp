<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class RefineryBillController extends Controller
{
    // ─── View ────────────────────────────────────────────────────────────────

    public function index(Request $request, ?string $mode = null)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $mode = strtolower((string) ($mode ?: $request->query('mode', 'bill')));
        if (!in_array($mode, ['bill', 'edit', 'cancel'], true)) {
            $mode = 'bill';
        }

        $titleMap = [
            'bill'   => 'Refinery Details',
            'edit'   => 'Edit Refinery Entry',
            'cancel' => 'Cancel Refinery Entry',
        ];

        $settingsPayload = $this->loadSettingsPayload();
        $software = (array) ($settingsPayload['Software'] ?? []);
        $sw = static fn(array $arr, string $key, string $default = ''): string => (string) ($arr[$key] ?? $default);

        // Load salesmen
        $salesmen = [];
        if ($this->hasTable('sman')) {
            $salesmen = DB::table('sman')->orderBy('name')
                ->get(['code', 'name'])
                ->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name ?? '')])
                ->values()->all();
        }

        // Load items for item code lookup
        $items = [];
        if ($this->hasTable('items')) {
            $itemCols = $this->getColumns('items');
            $select = ['code', 'name', 'itype'];
            foreach (['cost', 'touch', 'defstktype', 'stkinnos'] as $col) {
                if (in_array($col, $itemCols, true)) $select[] = $col;
            }
            $items = DB::table('items')
                ->whereNotNull('code')->where('code', '!=', '')
                ->orderBy('code')
                ->get($select)
                ->map(fn($r) => [
                    'code' => trim($r->code),
                    'name' => trim($r->name ?? ''),
                    'itype' => strtoupper(trim($r->itype ?? 'G')),
                    'cost' => (float) ($r->cost ?? 0),
                    'defstktype' => trim((string) ($r->defstktype ?? '')),
                ])
                ->values()->all();
        }

        return view('refinery-bill.index', [
            'mode'     => $mode,
            'title'    => $titleMap[$mode],
            'nextDocNo'=> $mode === 'bill' ? $this->peekNextDocNo('REFINEB', 'RFB') : '',
            'salesmen' => $salesmen,
            'items'    => $items,
            'software' => [
                'DefStkType'         => $sw($software, 'DefStkType', ''),
                'StktypeStrict'      => $sw($software, 'StktypeStrict', 'N'),
                'SMCompulsary'       => $sw($software, 'SMCompulsary', 'N'),
                'SManCodeEntry'      => $sw($software, 'SManCodeEntry', 'N'),
                'EnterSave'          => $sw($software, 'EnterSave', 'Y'),
                'RefineryDefStkType' => $sw($software, 'RefineryDefStkType', ''),
            ],
        ]);
    }

    public function picker(Request $request, string $action = 'edit'): View
    {
        $action = strtolower($action);
        if (!in_array($action, ['edit', 'cancel'], true)) {
            $action = 'edit';
        }

        $titles = [
            'edit' => 'Edit Refinery Entry',
            'cancel' => 'Cancel Refinery Entry',
        ];

        return view('purchase-bill.doc-picker', [
            'title' => (string) $request->query('title', $titles[$action]),
            'actionMode' => $action,
            'docType' => 'refinery',
            'searchUrl' => url('/api/refinery-bill/picker-search'),
            'resolveUrl' => url('/api/refinery-bill/picker-resolve'),
            'targetBaseUrl' => url('/refinery-bill'),
            'showViewOption' => false,
        ]);
    }

    public function allInOne(Request $request): View
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('refinery-bill.all-in-one');
    }

    public function pickerSearch(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }
        if (!$this->hasTable('refinerym')) {
            return response()->json(['ok' => true, 'rows' => []]);
        }

        $q = trim((string) $request->query('q', ''));
        $tdate = $this->parsePickerDate((string) $request->query('tdate', ''));
        $action = strtolower(trim((string) $request->query('action', 'edit')));

        $query = DB::table('refinerym')->where('status', '!=', 2);
        if ($action === 'cancel') {
            $query->where('status', 1);
        }
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('docno', 'like', $q . '%')
                  ->orWhere('refcode', 'like', "%{$q}%");
            });
        }
        if ($tdate) {
            $query->whereDate('tdate', $tdate);
        }

        $rows = $query
            ->orderByDesc('slno')
            ->get(['slno', 'docno', 'tdate', 'refcode']);

        $refCodes = $rows->pluck('refcode')->filter()->map(fn ($c) => trim((string) $c))->unique()->values()->all();
        $refNames = [];
        if ($refCodes !== [] && $this->hasTable('clients')) {
            $refNames = DB::table('clients')->whereIn('code', $refCodes)
                ->pluck('name', 'code')
                ->mapWithKeys(fn ($v, $k) => [trim((string) $k) => trim((string) $v)])
                ->toArray();
        }

        $mappedRows = $rows->map(function ($r) use ($refNames) {
            $docNo = trim((string) ($r->docno ?? ''));
            $refCode = trim((string) ($r->refcode ?? ''));
            return [
                'slno' => (int) ($r->slno ?? 0),
                'doc_no' => $docNo,
                'tdate' => !empty($r->tdate) ? date('d/m/Y', strtotime((string) $r->tdate)) : '',
                'party_name' => $refNames[$refCode] ?? $refCode,
                'has_return' => $this->findReturnByOriginalDoc($docNo, $refCode) !== null,
            ];
        })->values();

        if ($action === 'cancel') {
            $mappedRows = $mappedRows->filter(fn ($row) => !$row['has_return'])->values();
        }

        return response()->json([
            'ok' => true,
            'rows' => $mappedRows->map(function ($row) {
                return [
                    'slno' => (int) ($row['slno'] ?? 0),
                    'doc_no' => trim((string) ($row['doc_no'] ?? '')),
                    'tdate' => trim((string) ($row['tdate'] ?? '')),
                    'party_name' => trim((string) ($row['party_name'] ?? '')),
                ];
            })->values(),
        ]);
    }

    public function pickerResolve(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }
        if (!$this->hasTable('refinerym')) {
            return response()->json(['ok' => false, 'message' => 'Refinery table not found.'], 404);
        }

        $docNo = strtoupper(trim((string) $request->input('doc_no', '')));
        $tdate = $this->parsePickerDate((string) $request->input('tdate', ''));
        $action = strtolower(trim((string) $request->input('action', 'edit')));

        if ($docNo === '') {
            return response()->json(['ok' => false, 'message' => 'Doc no is required.'], 422);
        }

        $query = DB::table('refinerym')->whereRaw('UPPER(TRIM(docno)) = ?', [$docNo]);
        if ($action === 'cancel') {
            $query->where('status', 1);
        } else {
            $query->where('status', '!=', 2);
        }
        if ($tdate) {
            $query->whereDate('tdate', $tdate);
        }
        $row = $query->orderByDesc('slno')->first(['docno']);

        if (!$row) {
            return response()->json(['ok' => false, 'message' => 'This bill number does not exist...'], 404);
        }

        if ($action === 'cancel') {
            $master = DB::table('refinerym')->whereRaw('UPPER(TRIM(docno)) = ?', [$docNo])->orderByDesc('slno')->first(['docno', 'refcode']);
            $refCode = trim((string) ($master->refcode ?? ''));
            $linkedReturn = $this->findReturnByOriginalDoc($docNo, $refCode);
            if ($linkedReturn) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Return entry already exists for this refinery bill (' . trim((string) ($linkedReturn->docno ?? '')) . '). Cancel the return first.',
                ], 422);
            }
        }

        return response()->json([
            'ok' => true,
            'doc_no' => trim((string) ($row->docno ?? '')),
            'url' => url('/refinery-bill/' . $action . '?' . http_build_query([
                'doc_no' => trim((string) ($row->docno ?? '')),
            ])),
        ]);
    }

    // ─── API: Refiner Lookup ──────────────────────────────────────────────

    public function refinerLookup(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $code = strtoupper(trim((string) $request->query('code', '')));
        if ($code === '') {
            return response()->json(['ok' => false, 'message' => 'Code required.'], 422);
        }

        if (!$this->hasTable('clients')) {
            return response()->json(['ok' => false, 'message' => 'Clients table not found.']);
        }

        $clientCols = $this->getColumns('clients');
        $ctypeFilter = in_array('ctype', $clientCols, true);

        $query = DB::table('clients')->where('code', $code);
        if ($ctypeFilter) {
            $query->where('ctype', 'R');
        }
        $client = $query->first();

        if (!$client) {
            return response()->json(['ok' => false, 'message' => 'Invalid Refiner Code.'], 404);
        }

        return response()->json([
            'ok'   => true,
            'code' => $code,
            'name' => trim((string) ($client->name ?? '')),
        ]);
    }

    // ─── API: Refiner Search ──────────────────────────────────────────────

    public function refinerSearch(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $q = trim((string) $request->query('q', ''));
        if (!$this->hasTable('clients')) {
            return response()->json(['ok' => true, 'results' => []]);
        }

        $clientCols = $this->getColumns('clients');
        $query = DB::table('clients');
        if (in_array('ctype', $clientCols, true)) {
            $query->where('ctype', 'R');
        }
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('code', 'like', "%{$q}%")
                  ->orWhere('name', 'like', "%{$q}%");
            });
        }

        $rows = $query->orderBy('code')->limit(30)->get(['code', 'name']);
        $results = $rows->map(fn($r) => [
            'code' => trim($r->code ?? ''),
            'name' => trim($r->name ?? ''),
        ])->values()->all();

        return response()->json(['ok' => true, 'results' => $results]);
    }

    // ─── API: Item Lookup ─────────────────────────────────────────────────

    public function itemLookup(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $code = strtoupper(trim((string) $request->query('code', '')));
        if ($code === '') {
            return response()->json(['ok' => false, 'message' => 'Code required.'], 422);
        }

        if (!$this->hasTable('items')) {
            return response()->json(['ok' => false, 'message' => 'Items table not found.']);
        }

        $item = DB::table('items')->where('code', $code)->first();
        if (!$item) {
            return response()->json(['ok' => false, 'message' => 'Item not found.'], 404);
        }

        return response()->json([
            'ok'         => true,
            'code'       => trim($item->code),
            'name'       => trim($item->name ?? ''),
            'itype'      => strtoupper(trim($item->itype ?? 'G')),
            'cost'       => round($this->toNum($item->cost ?? 0), 2),
            'touch'      => round($this->toNum($item->touch ?? 0), 2),
            'defstktype' => trim((string) ($item->defstktype ?? '')),
            'stk_qty'    => (int) ($item->qtyb ?? $item->qty ?? 0),
            'stk_wgt'    => round($this->toNum($item->weightb ?? $item->weight ?? 0), 3),
        ]);
    }

    // ─── API: Item Search ─────────────────────────────────────────────────

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
            'code'  => trim($r->code ?? ''),
            'name'  => trim($r->name ?? ''),
            'itype' => strtoupper(trim($r->itype ?? 'G')),
        ])->values()->all();

        return response()->json(['ok' => true, 'results' => $results]);
    }

    // ─── API: Save (New Entry / Edit) ─────────────────────────────────────
    // Follows PB w_refnenter.cb_save logic:
    // - Items are ISSUED to refiner → stock DECREASES
    // - Test pieces → TP item stock INCREASES
    // - No daybook/charge/paidamt in this window

    public function save(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $payload = $request->validate([
            'doc_no'       => 'nullable|string|max:40',
            'bill_date'    => 'nullable|string|max:20',
            'refiner_code' => 'required|string|max:20',
            'refiner_name' => 'nullable|string|max:120',
            'sm_code'      => 'nullable|string|max:20',
            'test_perc'    => 'nullable',
            'exp_wgt'      => 'nullable',
            'note'         => 'nullable|string|max:40',
            'items'        => 'nullable|array',
        ]);

        $refCode = strtoupper(trim((string) ($payload['refiner_code'] ?? '')));
        if ($refCode === '') {
            return response()->json(['ok' => false, 'message' => "Refiner's code empty. You can't save..."], 422);
        }

        if (!$this->hasTable('refinerym') || !$this->hasTable('refineryd')) {
            return response()->json(['ok' => false, 'message' => 'Refinery tables not found.']);
        }

        $items = collect($payload['items'] ?? [])
            ->filter(fn($row) => trim((string) ($row['item_code'] ?? '')) !== '')
            ->values()
            ->map(function ($row) {
                foreach (['weight', 'wgt_amt', 'stone_wgt', 'test_pcs', 'cost', 'mud_less'] as $col) {
                    $row[$col] = $this->toNum($row[$col] ?? 0);
                }
                $row['qty'] = (int) $this->toNum($row['qty'] ?? 0);
                return $row;
            })->values()->all();

        if ($items === []) {
            return response()->json(['ok' => false, 'message' => "Incomplete Transaction. This transaction is not complete..."], 422);
        }

        // Validate: all items must have weight > 0
        foreach ($items as $it) {
            if ($this->toNum($it['weight'] ?? 0) <= 0) {
                return response()->json([
                    'ok' => false,
                    'message' => "Check Weight (" . trim($it['item_code'] ?? '') . "). Weight can't be zero",
                ], 422);
            }
        }

        $billDate = $this->parseDate((string) ($payload['bill_date'] ?? ''));
        $billDateSql = $billDate ?: now()->toDateString();
        $sqlTime = now()->format('H:i:s');
        $smCode = trim((string) ($payload['sm_code'] ?? ''));
        $testPerc = $this->toNum($payload['test_perc'] ?? 0);
        $expWgt = $this->toNum($payload['exp_wgt'] ?? 0);
        $note = mb_substr(trim((string) ($payload['note'] ?? '')), 0, 40);
        $incharge = $request->session()->get('user_code', '');

        // Totals from items
        $tTestPcs = 0;
        $tIssuedWgt = 0;
        foreach ($items as $it) {
            $tTestPcs += $this->toNum($it['test_pcs'] ?? 0);
            $tIssuedWgt += $this->toNum($it['weight'] ?? 0);
        }

        $docNo = trim((string) ($payload['doc_no'] ?? ''));
        $isEdit = false;
        $existingMaster = null;

        if ($docNo !== '') {
            $existingMaster = DB::table('refinerym')->where('docno', $docNo)->first();
            if ($existingMaster) {
                $isEdit = true;
            }
        }

        DB::transaction(function () use (
            $isEdit, $existingMaster, $docNo, $billDateSql, $sqlTime,
            $refCode, $smCode, $testPerc, $expWgt,
            $note, $incharge, $items, $tTestPcs, $tIssuedWgt, &$payload
        ) {
            $control = 1; // "B" book mode

            if ($isEdit) {
                $slno = (int) ($existingMaster->slno ?? 0);

                // Reverse previous stock effects
                $this->reverseStockEffects($slno);

                // Update refinerym
                $mUpdate = [
                    'refcode'       => $refCode,
                    'tdate'         => $billDateSql,
                    'toldissuedwgt' => round($tIssuedWgt, 3),
                    'status'        => 1,
                    'ttestpcs'      => round($tTestPcs, 3),
                    'smcode'        => $smCode,
                    'expwgt'        => round($expWgt, 3),
                ];
                $mCols = $this->getColumns('refinerym');
                $mUpdate = array_filter($mUpdate, fn($k) => in_array($k, $mCols, true), ARRAY_FILTER_USE_KEY);
                DB::table('refinerym')->where('slno', $slno)->update($mUpdate);

                // Delete old detail rows
                DB::table('refineryd')->where('slno', $slno)->delete();
            } else {
                $slno = $this->reserveGlobalSerialNo();

                // Get next bill number
                $counterCode = 'REFINEB';
                $billNum = $this->readGeneraliCounter($counterCode);
                $billNum++;
                $docNo = 'RFB/' . str_pad($billNum, 5, '0', STR_PAD_LEFT);
                $this->incrementGeneraliCounter($counterCode, $billNum);

                // Insert refinerym
                $mRow = [
                    'slno'          => $slno,
                    'tdate'         => $billDateSql,
                    'ttime'         => $sqlTime,
                    'docno'         => $docNo,
                    'refcode'       => $refCode,
                    'tbottlestk'    => 0,
                    'ttestpcs'      => round($tTestPcs, 3),
                    'toldissuedwgt' => round($tIssuedWgt, 3),
                    'testperc'      => round($testPerc, 2),
                    'status'        => 1,
                    'control'       => $control,
                    'smcode'        => $smCode,
                    'ic'            => $incharge,
                    'expwgt'        => round($expWgt, 3),
                    'note'          => $note,
                ];
                $mCols = $this->getColumns('refinerym');
                $mRow = array_filter($mRow, fn($k) => in_array($k, $mCols, true), ARRAY_FILTER_USE_KEY);
                DB::table('refinerym')->insert($mRow);
            }

            // Insert refineryd and apply stock effects
            $dCols = $this->getColumns('refineryd');
            $sno = 1;
            foreach ($items as $it) {
                $code = trim((string) ($it['item_code'] ?? ''));
                if ($code === '') continue;

                $weight   = $this->toNum($it['weight'] ?? 0);
                $wgtAmt   = $this->toNum($it['wgt_amt'] ?? 0);
                $qty      = (int) $this->toNum($it['qty'] ?? 0);
                $stoneWgt = $this->toNum($it['stone_wgt'] ?? 0);
                $mudLess  = $this->toNum($it['mud_less'] ?? 0);
                $testPcs  = $this->toNum($it['test_pcs'] ?? 0);
                $cost     = $this->toNum($it['cost'] ?? 0);
                $stktype  = trim((string) ($it['stktype'] ?? ''));
                $stktouch = $this->toNum($it['stktouch'] ?? 100);
                $touch    = $this->toNum($it['touch'] ?? 0);

                // Insert refineryd row (matching PB insert)
                $dRow = [
                    'slno'         => $slno,
                    'code'         => $code,
                    'issuedwgt'    => round($weight, 3),
                    'issuedqty'    => $qty,
                    'status'       => 1,
                    'cost'         => round($cost, 2),
                    'issuedwgtamt' => round($wgtAmt, 2),
                    'coper'        => 0,
                    'mudless'      => round($mudLess, 3),
                    'sno'          => $sno++,
                    'issuedstwgt'  => round($stoneWgt, 3),
                    'testpcs'      => round($testPcs, 3),
                    'rcvdwgt'      => round($testPcs, 3), // PB sets rcvdwgt = testpcs on new entry
                    'oissuedwgt'   => 0,
                    'stktype'      => $stktype,
                    'stktouch'     => round($stktouch, 2),
                    'touch'        => round($touch, 2),
                ];
                $dRow = array_filter($dRow, fn($k) => in_array($k, $dCols, true), ARRAY_FILTER_USE_KEY);
                DB::table('refineryd')->insert($dRow);

                // ── Stock effects: DECREASE items (issued to refiner) ──
                $this->adjustItemStock($code, -$qty, -$weight, -$stoneWgt, $stktype, $control);

                // ── Test pieces → INCREASE TP item stock ──
                if ($testPcs > 0) {
                    $this->adjustItemStock('TP', 0, $testPcs, 0, $stktype, $control);
                }
            }

            $payload['_doc_no'] = $docNo;
            $payload['_slno'] = $slno;
        });

        return response()->json([
            'ok'     => true,
            'doc_no' => $payload['_doc_no'] ?? $docNo,
            'slno'   => $payload['_slno'] ?? 0,
        ]);
    }

    // ─── API: Get (load existing bill) ────────────────────────────────────

    public function get(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $docNo = trim((string) $request->query('doc_no', ''));
        if ($docNo === '') {
            return response()->json(['ok' => false, 'message' => 'Doc number required.'], 422);
        }

        if (!$this->hasTable('refinerym')) {
            return response()->json(['ok' => false, 'message' => 'Table not found.']);
        }

        $master = DB::table('refinerym')->where('docno', $docNo)->first();
        if (!$master) {
            return response()->json(['ok' => false, 'message' => 'Bill not found.'], 404);
        }

        $slno = (int) ($master->slno ?? 0);

        // Load refiner name
        $refName = '';
        if ($this->hasTable('clients')) {
            $refName = trim((string) (DB::table('clients')->where('code', trim($master->refcode ?? ''))->value('name') ?? ''));
        }

        // Load items
        $items = [];
        if ($this->hasTable('refineryd') && $slno > 0) {
            $dRows = DB::table('refineryd')->where('slno', $slno)->orderBy('sno')->get();
            $nameByCode = [];
            if ($this->hasTable('items')) {
                $codes = $dRows->pluck('code')->filter()->map(fn($c) => trim((string) $c))->unique()->values()->all();
                if ($codes !== []) {
                    $nameByCode = DB::table('items')
                        ->whereIn('code', $codes)
                        ->pluck('name', 'code')
                        ->mapWithKeys(fn($v, $k) => [trim((string) $k) => trim((string) $v)])
                        ->toArray();
                }
            }
            $items = $dRows->map(function ($r) use ($nameByCode) {
                $code = trim((string) ($r->code ?? ''));
                $name = $nameByCode[$code] ?? '';
                return [
                    'item_code' => $code,
                    'item_name' => $name,
                    'weight'    => round($this->toNum($r->issuedwgt ?? 0), 3),
                    'wgt_amt'   => round($this->toNum($r->issuedwgtamt ?? 0), 2),
                    'qty'       => (int) ($r->issuedqty ?? 0),
                    'stone_wgt' => round($this->toNum($r->issuedstwgt ?? 0), 3),
                    'mud_less'  => round($this->toNum($r->mudless ?? 0), 3),
                    'test_pcs'  => round($this->toNum($r->testpcs ?? 0), 3),
                    'cost'      => round($this->toNum($r->cost ?? 0), 2),
                    'stktype'   => trim((string) ($r->stktype ?? '')),
                    'stktouch'  => round($this->toNum($r->stktouch ?? 100), 2),
                    'touch'     => round($this->toNum($r->touch ?? 0), 2),
                ];
            })->values()->all();
        }

        return response()->json([
            'ok'           => true,
            'doc_no'       => trim((string) ($master->docno ?? '')),
            'bill_date'    => !empty($master->tdate) ? Carbon::parse((string) $master->tdate)->format('d/m/Y') : '',
            'refiner_code' => trim((string) ($master->refcode ?? '')),
            'refiner_name' => $refName,
            'sm_code'      => trim((string) ($master->smcode ?? '')),
            'test_perc'    => round($this->toNum($master->testperc ?? 0), 2),
            'exp_wgt'      => round($this->toNum($master->expwgt ?? 0), 3),
            'note'         => trim((string) ($master->note ?? '')),
            'status'       => (int) ($master->status ?? 1),
            'slno'         => $slno,
            'items'        => $items,
        ]);
    }

    // ─── API: Search ──────────────────────────────────────────────────────

    public function search(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $q = trim((string) $request->query('q', ''));
        $action = strtolower(trim((string) $request->query('action', 'edit')));
        if (!$this->hasTable('refinerym')) {
            return response()->json(['ok' => true, 'results' => []]);
        }

        $query = DB::table('refinerym')->where('status', '!=', 2);
        if ($action === 'cancel') {
            $query->where('status', 1);
        }
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('docno', 'like', "%{$q}%")
                  ->orWhere('refcode', 'like', "%{$q}%");
            });
        }

        $rows = $query->orderByDesc('slno')
            ->limit(30)
            ->get(['docno', 'tdate', 'refcode', 'status']);

        $refCodes = $rows->pluck('refcode')->filter()->map(fn($c) => trim((string) $c))->unique()->values()->all();
        $refNames = [];
        if ($refCodes !== [] && $this->hasTable('clients')) {
            $refNames = DB::table('clients')
                ->whereIn('code', $refCodes)
                ->pluck('name', 'code')
                ->mapWithKeys(fn($v, $k) => [trim((string) $k) => trim((string) $v)])
                ->toArray();
        }

        $results = $rows->map(function ($r) use ($refNames) {
            $refCode = trim($r->refcode ?? '');
            return [
                'doc_no'   => trim($r->docno ?? ''),
                'date'     => $r->tdate ?? '',
                'ref_code' => $refCode,
                'ref_name' => $refNames[$refCode] ?? '',
                'status'   => (int) ($r->status ?? 1),
                'has_return' => $this->findReturnByOriginalDoc(trim((string) ($r->docno ?? '')), $refCode) !== null,
            ];
        })->filter(function ($row) use ($action) {
            if ($action !== 'cancel') {
                return true;
            }
            return !$row['has_return'];
        })->values()->all();

        return response()->json(['ok' => true, 'results' => $results]);
    }

    // ─── API: Prev / Next ─────────────────────────────────────────────────

    public function prevBill(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }
        $current = trim((string) $request->query('doc_no', ''));
        if (!$this->hasTable('refinerym')) {
            return response()->json(['ok' => false, 'message' => 'No bills.']);
        }
        $currentSlno = 0;
        if ($current !== '') {
            $currentSlno = (int) (DB::table('refinerym')->where('docno', $current)->value('slno') ?? 0);
        }
        $prev = DB::table('refinerym')
            ->when($currentSlno > 0, fn($q) => $q->where('slno', '<', $currentSlno))
            ->orderByDesc('slno')
            ->first(['docno']);
        if (!$prev) {
            return response()->json(['ok' => false, 'message' => 'No previous bill.']);
        }
        return response()->json(['ok' => true, 'doc_no' => trim($prev->docno)]);
    }

    public function nextBill(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }
        $current = trim((string) $request->query('doc_no', ''));
        if (!$this->hasTable('refinerym')) {
            return response()->json(['ok' => false, 'message' => 'No bills.']);
        }
        $currentSlno = 0;
        if ($current !== '') {
            $currentSlno = (int) (DB::table('refinerym')->where('docno', $current)->value('slno') ?? 0);
        }
        $next = DB::table('refinerym')
            ->when($currentSlno > 0, fn($q) => $q->where('slno', '>', $currentSlno))
            ->orderBy('slno')
            ->first(['docno']);
        if (!$next) {
            return response()->json(['ok' => false, 'message' => 'No next bill.']);
        }
        return response()->json(['ok' => true, 'doc_no' => trim($next->docno)]);
    }

    // ─── API: Cancel ──────────────────────────────────────────────────────

    public function cancelBill(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $docNo = trim((string) $request->input('doc_no', ''));
        if ($docNo === '') {
            return response()->json(['ok' => false, 'message' => 'Doc number required.'], 422);
        }

        if (!$this->hasTable('refinerym')) {
            return response()->json(['ok' => false, 'message' => 'Table not found.']);
        }

        $master = DB::table('refinerym')->where('docno', $docNo)->first();
        if (!$master) {
            return response()->json(['ok' => false, 'message' => 'Bill not found.'], 404);
        }

        $status = (int) ($master->status ?? 1);
        if ($status === 0) {
            return response()->json(['ok' => false, 'message' => 'This refinery entry is already cancelled.'], 422);
        }
        if ($status === 2) {
            return response()->json(['ok' => false, 'message' => 'This is a refinery return entry. Cancel it from return entry screen.'], 422);
        }

        $refCode = trim((string) ($master->refcode ?? ''));
        $linkedReturn = $this->findReturnByOriginalDoc($docNo, $refCode);
        if ($linkedReturn) {
            return response()->json([
                'ok' => false,
                'message' => 'Return entry already exists for this refinery bill (' . trim((string) ($linkedReturn->docno ?? '')) . '). Cancel the return first.',
            ], 422);
        }

        $slno = (int) ($master->slno ?? 0);

        DB::transaction(function () use ($slno) {
            $this->reverseStockEffects($slno);
            DB::table('refinerym')->where('slno', $slno)->update(['status' => 0]);
        });

        return response()->json(['ok' => true]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ─── REFINERY RETURNS ─────────────────────────────────────────────────
    // ═══════════════════════════════════════════════════════════════════════

    public function returnIndex(Request $request, ?string $mode = null)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $mode = strtolower((string) ($mode ?: $request->query('mode', 'bill')));
        if (!in_array($mode, ['bill', 'edit'], true)) {
            $mode = 'bill';
        }

        $settingsPayload = $this->loadSettingsPayload();
        $software = (array) ($settingsPayload['Software'] ?? []);
        $sw = static fn(array $arr, string $key, string $default = ''): string => (string) ($arr[$key] ?? $default);

        $salesmen = [];
        if ($this->hasTable('sman')) {
            $salesmen = DB::table('sman')->orderBy('name')
                ->get(['code', 'name'])
                ->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name ?? '')])
                ->values()->all();
        }

        return view('refinery-return.index', [
            'mode'     => $mode,
            'title'    => $mode === 'edit' ? 'Edit Refinery Return' : 'Refinery Return Details',
            'salesmen' => $salesmen,
            'software' => [
                'DefStkType'    => $sw($software, 'DefStkType', ''),
                'StktypeStrict' => $sw($software, 'StktypeStrict', 'N'),
                'SMCompulsary'  => $sw($software, 'SMCompulsary', 'N'),
                'THCTouch'      => $sw($software, 'THCTouch', '100'),
            ],
        ]);
    }

    // ─── Load original entry for creating a new return ────────────────────

    public function loadForReturn(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $docNo = strtoupper(trim((string) $request->query('doc_no', '')));
        if ($docNo === '') {
            return response()->json(['ok' => false, 'message' => 'Doc number required.'], 422);
        }

        if (!$this->hasTable('refinerym') || !$this->hasTable('refineryd')) {
            return response()->json(['ok' => false, 'message' => 'Table not found.']);
        }

        // Load original entry (status=1 means active issued entry)
        $master = DB::table('refinerym')->where('docno', $docNo)->where('status', 1)->first();
        if (!$master) {
            return response()->json(['ok' => false, 'message' => 'Entry not found or not active.'], 404);
        }

        $slno = (int) ($master->slno ?? 0);
        $refCode = trim((string) ($master->refcode ?? ''));

        // Check if a return already exists for this entry
        $existingReturn = $this->findReturnByOriginalDoc($docNo, $refCode);

        $refName = '';
        if ($this->hasTable('clients') && $refCode !== '') {
            $refName = trim((string) (DB::table('clients')->where('code', $refCode)->value('name') ?? ''));
        }

        // Load items with summary calculations
        $items = [];
        $tIssuedWgt = 0; $tStoneWgt = 0; $tMud = 0; $tTestPcs = 0;
        $tIssuedWgtAmt = 0;

        if ($slno > 0) {
            $dRows = DB::table('refineryd')->where('slno', $slno)->orderBy('sno')->get();
            $nameByCode = $this->getItemNames($dRows->pluck('code')->filter()->map(fn($c) => trim((string) $c))->unique()->values()->all());

            foreach ($dRows as $r) {
                $code = trim((string) ($r->code ?? ''));
                $iWgt = $this->toNum($r->issuedwgt ?? 0);
                $iQty = (int) ($r->issuedqty ?? 0);
                $iStWgt = $this->toNum($r->issuedstwgt ?? 0);
                $iMud = $this->toNum($r->mudless ?? 0);
                $iTP = $this->toNum($r->testpcs ?? 0);
                $iWgtAmt = $this->toNum($r->issuedwgtamt ?? 0);
                $iCost = $this->toNum($r->cost ?? 0);
                $oIssuedWgt = $this->toNum($r->oissuedwgt ?? 0);

                $tIssuedWgt += $iWgt;
                $tStoneWgt += $iStWgt;
                $tMud += $iMud;
                $tTestPcs += $iTP;
                $tIssuedWgtAmt += $iWgtAmt;

                $items[] = [
                    'item_code'      => $code,
                    'item_name'      => $nameByCode[$code] ?? '',
                    'issued_wgt'     => round($iWgt, 3),
                    'issued_qty'     => $iQty,
                    'issued_st_wgt'  => round($iStWgt, 3),
                    'issued_wgt_amt' => round($iWgtAmt, 2),
                    'oissuedwgt'     => round($oIssuedWgt, 3),
                    'cost'           => round($iCost, 2),
                    'stktype'        => trim((string) ($r->stktype ?? '')),
                    'stktouch'       => round($this->toNum($r->stktouch ?? 100), 2),
                    'touch'          => round($this->toNum($r->touch ?? 0), 2),
                    // Default return fields
                    'rcvd_touch'     => 0,
                    'mud_less'       => 0,
                    'rcvd_qty'       => 0,
                    'rcvd_wgt'       => 0,
                    'rate'           => round($iCost, 2),
                    'rcvd_wgt_amt'   => 0,
                    'bottle_stk'     => 0,
                    'test_pcs'       => 0,
                    'sno'            => (int) ($r->sno ?? 0),
                ];
            }
        }

        $tBottleStk = $this->toNum($master->tbottlestk ?? 0);
        $tTestPcsM = $this->toNum($master->ttestpcs ?? 0);
        $testPerc = $this->toNum($master->testperc ?? 0);
        $netIssued = $tIssuedWgt - $tStoneWgt - $tBottleStk - $tMud - $tTestPcsM;

        return response()->json([
            'ok'             => true,
            'doc_no'         => trim((string) ($master->docno ?? '')),
            'existing_return_doc_no' => trim((string) ($existingReturn->docno ?? '')),
            'bill_date'      => !empty($master->tdate) ? Carbon::parse((string) $master->tdate)->format('d/m/Y') : '',
            'refiner_code'   => $refCode,
            'refiner_name'   => $refName,
            'sm_code'        => trim((string) ($master->smcode ?? '')),
            'slno'           => $slno,
            'control'        => (int) ($master->control ?? 1),
            // Summary
            'issued_wgt'     => round($tIssuedWgt, 3),
            'stone_wgt'      => round($tStoneWgt, 3),
            'bottle_stk'     => round($tBottleStk, 3),
            'mud'            => round($tMud, 3),
            'test_pcs'       => round($tTestPcsM, 3),
            'net_issued'     => round(max($netIssued, 0), 3),
            'test_perc'      => round($testPerc, 2),
            'net_issued_amt' => round($tIssuedWgtAmt, 2),
            'items'          => $items,
        ]);
    }

    // ─── Save refinery return (creates NEW refinerym entry with status=2) ─

    public function saveReturn(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $payload = $request->validate([
            'orig_doc_no'   => 'required|string|max:40',
            'return_doc_no' => 'nullable|string|max:40',
            'bill_date'     => 'nullable|string|max:20',
            'sm_code'       => 'nullable|string|max:20',
            'charge'        => 'nullable',
            'paidamt'       => 'nullable',
            'note'          => 'nullable|string|max:40',
            'items'         => 'required|array',
        ]);

        $origDocNo = strtoupper(trim((string) ($payload['orig_doc_no'] ?? '')));
        $returnDocNo = trim((string) ($payload['return_doc_no'] ?? ''));

        if (!$this->hasTable('refinerym') || !$this->hasTable('refineryd')) {
            return response()->json(['ok' => false, 'message' => 'Tables not found.']);
        }

        // Load original entry
        $origMaster = DB::table('refinerym')->where('docno', $origDocNo)->where('status', 1)->first();
        if (!$origMaster) {
            return response()->json(['ok' => false, 'message' => 'Original entry not found.'], 404);
        }

        $refCode = trim((string) ($origMaster->refcode ?? ''));
        $control = (int) ($origMaster->control ?? 1);

        $items = collect($payload['items'] ?? [])
            ->filter(fn($row) => trim((string) ($row['item_code'] ?? '')) !== '')
            ->values()
            ->map(function ($row) {
                foreach (['rcvd_wgt', 'rate', 'rcvd_wgt_amt', 'bottle_stk', 'test_pcs', 'mud_less',
                           'rcvd_touch', 'issued_wgt', 'issued_wgt_amt', 'issued_st_wgt', 'oissuedwgt', 'cost'] as $col) {
                    $row[$col] = $this->toNum($row[$col] ?? 0);
                }
                $row['rcvd_qty'] = (int) $this->toNum($row['rcvd_qty'] ?? 0);
                $row['issued_qty'] = (int) $this->toNum($row['issued_qty'] ?? 0);
                return $row;
            })->values()->all();

        if ($items === []) {
            return response()->json(['ok' => false, 'message' => 'No items to save.'], 422);
        }

        $billDate = $this->parseDate((string) ($payload['bill_date'] ?? ''));
        $billDateSql = $billDate ?: now()->toDateString();
        $sqlTime = now()->format('H:i:s');
        $smCode = trim((string) ($payload['sm_code'] ?? ''));
        $charge = $this->toNum($payload['charge'] ?? 0);
        $paidamt = $this->toNum($payload['paidamt'] ?? 0);
        $note = mb_substr(trim((string) ($payload['note'] ?? '')), 0, 40);
        $incharge = $request->session()->get('user_code', '');

        // Calculate totals
        $tRcvdWgt = 0; $tBottleStk = 0; $tTestPcs = 0; $tOldIssuedWgt = 0; $tTestPerc = 0;
        foreach ($items as $it) {
            $tRcvdWgt += $this->toNum($it['rcvd_wgt'] ?? 0);
            $tBottleStk += $this->toNum($it['bottle_stk'] ?? 0);
            $tTestPcs += $this->toNum($it['test_pcs'] ?? 0);
            $tOldIssuedWgt += $this->toNum($it['oissuedwgt'] ?? ($it['issued_wgt'] ?? 0));
        }
        if ($tOldIssuedWgt > 0) {
            $tTestPerc = (($tRcvdWgt - $tBottleStk) / $tOldIssuedWgt) * 100;
        }

        $isEdit = false;
        $existingReturn = null;
        if ($returnDocNo !== '') {
            $existingReturn = DB::table('refinerym')->where('docno', $returnDocNo)->where('status', 2)->first();
            if ($existingReturn) {
                $isEdit = true;
            }
        } else {
            $existingReturn = $this->findReturnByOriginalDoc($origDocNo, $refCode);
            if ($existingReturn) {
                $isEdit = true;
                $returnDocNo = trim((string) ($existingReturn->docno ?? ''));
            }
        }

        $finalDocNo = $returnDocNo;

        DB::transaction(function () use (
            $isEdit, $existingReturn, &$finalDocNo, $origDocNo,
            $billDateSql, $sqlTime, $refCode, $control, $smCode,
            $charge, $paidamt, $note, $incharge, $items,
            $tRcvdWgt, $tBottleStk, $tTestPcs, $tOldIssuedWgt, $tTestPerc
        ) {
            $mCols = $this->getColumns('refinerym');
            $dCols = $this->getColumns('refineryd');

            if ($isEdit) {
                $slno = (int) ($existingReturn->slno ?? 0);

                // Reverse previous stock effects of this return
                $this->reverseReturnStockEffects($slno);

                // Delete old daybook
                $this->deleteDaybookEntries($slno);

                // Update refinerym
                $mUpdate = [
                    'tdate'         => $billDateSql,
                    'tbottlestk'    => round($tBottleStk, 3),
                    'ttestpcs'      => round($tTestPcs, 3),
                    'charge'        => round($charge, 2),
                    'paidamt'       => round($paidamt, 2),
                    'toldissuedwgt' => round($tOldIssuedWgt, 3),
                    'testperc'      => round($tTestPerc, 2),
                    'smcode'        => $smCode,
                    'note'          => $note !== '' ? "Ret:{$origDocNo} {$note}" : "Ret:{$origDocNo}",
                ];
                $mUpdate = array_filter($mUpdate, fn($k) => in_array($k, $mCols, true), ARRAY_FILTER_USE_KEY);
                DB::table('refinerym')->where('slno', $slno)->update($mUpdate);

                // Delete old detail rows
                DB::table('refineryd')->where('slno', $slno)->delete();
            } else {
                $slno = $this->reserveGlobalSerialNo();

                // Get next return bill number
                $billNum = $this->readGeneraliCounter('REFINEE');
                $billNum++;
                $finalDocNo = 'RFE/' . str_pad($billNum, 5, '0', STR_PAD_LEFT);
                $this->incrementGeneraliCounter('REFINEE', $billNum);

                // Insert refinerym (status=2 for return)
                $mRow = [
                    'slno'          => $slno,
                    'tdate'         => $billDateSql,
                    'ttime'         => $sqlTime,
                    'docno'         => $finalDocNo,
                    'refcode'       => $refCode,
                    'tbottlestk'    => round($tBottleStk, 3),
                    'ttestpcs'      => round($tTestPcs, 3),
                    'charge'        => round($charge, 2),
                    'paidamt'       => round($paidamt, 2),
                    'toldissuedwgt' => round($tOldIssuedWgt, 3),
                    'testperc'      => round($tTestPerc, 2),
                    'status'        => 2,
                    'control'       => $control,
                    'smcode'        => $smCode,
                    'ic'            => $incharge,
                    'expwgt'        => 0,
                    'note'          => $note !== '' ? "Ret:{$origDocNo} {$note}" : "Ret:{$origDocNo}",
                ];
                $mRow = array_filter($mRow, fn($k) => in_array($k, $mCols, true), ARRAY_FILTER_USE_KEY);
                DB::table('refinerym')->insert($mRow);
            }

            // Insert detail rows and apply stock effects
            $sno = 1;
            foreach ($items as $it) {
                $code = trim((string) ($it['item_code'] ?? ''));
                if ($code === '') continue;

                $rcvdWgt    = $this->toNum($it['rcvd_wgt'] ?? 0);
                $rcvdQty    = (int) $this->toNum($it['rcvd_qty'] ?? 0);
                $rate       = $this->toNum($it['rate'] ?? 0);
                $rcvdWgtAmt = $this->toNum($it['rcvd_wgt_amt'] ?? 0);
                $bottleStk  = $this->toNum($it['bottle_stk'] ?? 0);
                $testPcs    = $this->toNum($it['test_pcs'] ?? 0);
                $mudLess    = $this->toNum($it['mud_less'] ?? 0);
                $rcvdTouch  = $this->toNum($it['rcvd_touch'] ?? 0);
                $issuedWgt  = 0.0;
                $issuedQty  = 0;
                $issuedWgtAmt = $this->toNum($it['issued_wgt_amt'] ?? 0);
                $issuedStWgt = $this->toNum($it['issued_st_wgt'] ?? 0);
                $oissuedwgt = $this->toNum($it['oissuedwgt'] ?? 0);
                $cost       = $this->toNum($it['cost'] ?? 0);
                $stktype    = trim((string) ($it['stktype'] ?? ''));
                $stktouch   = $this->toNum($it['stktouch'] ?? 100);

                $dRow = [
                    'slno'         => $slno,
                    'code'         => $code,
                    'issuedwgt'    => round($issuedWgt, 3),
                    'issuedqty'    => $issuedQty,
                    'rcvdwgt'      => round($rcvdWgt, 3),
                    'rcvdqty'      => $rcvdQty,
                    'bottlestk'    => round($bottleStk, 3),
                    'testpcs'      => round($testPcs, 3),
                    'oissuedwgt'   => round($oissuedwgt > 0 ? $oissuedwgt : $issuedWgt, 3),
                    'status'       => 2,
                    'cost'         => round($cost, 2),
                    'rate'         => round($rate, 2),
                    'rcvdwgtamt'   => round($rcvdWgtAmt, 2),
                    'issuedwgtamt' => round($issuedWgtAmt, 2),
                    'sno'          => $sno++,
                    'mudless'      => round($mudLess, 3),
                    'issuedstwgt'  => round($issuedStWgt, 3),
                    'stktype'      => $stktype,
                    'stktouch'     => round($stktouch, 2),
                    'rcvdtouch'    => round($rcvdTouch, 2),
                ];
                $dRow = array_filter($dRow, fn($k) => in_array($k, $dCols, true), ARRAY_FILTER_USE_KEY);
                DB::table('refineryd')->insert($dRow);

                // ── Stock effects ──
                // Received items INCREASE stock
                $netQtyDelta = $rcvdQty - $issuedQty;
                $netWgtDelta = $rcvdWgt - $bottleStk - $testPcs - $issuedWgt;
                if ($netWgtDelta != 0.0 || $netQtyDelta != 0) {
                    $this->adjustItemStock($code, $netQtyDelta, $netWgtDelta, 0, $stktype, $control);
                }

                // Bottle stock → BS item INCREASE
                if ($bottleStk > 0) {
                    $this->adjustItemStock('BS', 0, $bottleStk, 0, $stktype, $control);
                }

                // Test pieces → TP item DECREASE
                if ($testPcs > 0) {
                    $this->adjustItemStock('TP', 0, $testPcs, 0, $stktype, $control);
                }

                // WA cost must use the actual stock added to the item,
                // not the gross received weight before bottle/test deductions.
                if ($netWgtDelta > 0 && $rate > 0) {
                    $this->updateWACost($code, $netWgtDelta, $rate);
                }
            }

            // ── Daybook entries ──
            if ($charge > 0 || $paidamt > 0) {
                $this->insertReturnDaybookEntries(
                    $slno, $finalDocNo, $billDateSql, $sqlTime,
                    $refCode, $charge, $paidamt, $control, $incharge
                );
            }
        });

        return response()->json([
            'ok'     => true,
            'doc_no' => $finalDocNo,
        ]);
    }

    // ─── Get existing return entry (for edit mode) ────────────────────────

    public function getReturn(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $docNo = strtoupper(trim((string) $request->query('doc_no', '')));
        if ($docNo === '') {
            return response()->json(['ok' => false, 'message' => 'Doc number required.'], 422);
        }

        if (!$this->hasTable('refinerym') || !$this->hasTable('refineryd')) {
            return response()->json(['ok' => false, 'message' => 'Table not found.']);
        }

        // Load return entry (status=2)
        $master = DB::table('refinerym')->where('docno', $docNo)->where('status', 2)->first();
        if (!$master) {
            return response()->json(['ok' => false, 'message' => 'Return entry not found.'], 404);
        }

        $slno = (int) ($master->slno ?? 0);
        $refCode = trim((string) ($master->refcode ?? ''));

        // Try to find the original entry from note field
        $origDocNo = '';
        $noteStr = trim((string) ($master->note ?? ''));
        $displayNote = $noteStr;
        if (preg_match('/^Ret:([^\s]+)\s*(.*)$/', $noteStr, $m)) {
            $origDocNo = trim($m[1]);
            $displayNote = trim((string) ($m[2] ?? ''));
        }

        $refName = '';
        if ($this->hasTable('clients') && $refCode !== '') {
            $refName = trim((string) (DB::table('clients')->where('code', $refCode)->value('name') ?? ''));
        }

        // Load items
        $items = [];
        $tIssuedWgt = 0; $tStoneWgt = 0; $tMud = 0; $tTestPcs = 0;
        $tIssuedWgtAmt = 0; $tRcvdWgt = 0;

        if ($slno > 0) {
            $dRows = DB::table('refineryd')->where('slno', $slno)->orderBy('sno')->get();
            $nameByCode = $this->getItemNames($dRows->pluck('code')->filter()->map(fn($c) => trim((string) $c))->unique()->values()->all());

            foreach ($dRows as $r) {
                $code = trim((string) ($r->code ?? ''));
                $iWgt = $this->toNum($r->issuedwgt ?? 0);
                $iQty = (int) ($r->issuedqty ?? 0);
                $iStWgt = $this->toNum($r->issuedstwgt ?? 0);
                $iWgtAmt = $this->toNum($r->issuedwgtamt ?? 0);
                $iMud = $this->toNum($r->mudless ?? 0);
                $iTP = $this->toNum($r->testpcs ?? 0);
                $oBs = $this->toNum($r->bottlestk ?? 0);
                $rWgt = $this->toNum($r->rcvdwgt ?? 0);

                $tIssuedWgt += $iWgt;
                $tStoneWgt += $iStWgt;
                $tMud += $iMud;
                $tTestPcs += $iTP;
                $tIssuedWgtAmt += $iWgtAmt;
                $tRcvdWgt += $rWgt;

                $items[] = [
                    'item_code'      => $code,
                    'item_name'      => $nameByCode[$code] ?? '',
                    'issued_wgt'     => round($iWgt, 3),
                    'issued_qty'     => $iQty,
                    'issued_st_wgt'  => round($iStWgt, 3),
                    'issued_wgt_amt' => round($iWgtAmt, 2),
                    'oissuedwgt'     => round($this->toNum($r->oissuedwgt ?? 0), 3),
                    'cost'           => round($this->toNum($r->cost ?? 0), 2),
                    'stktype'        => trim((string) ($r->stktype ?? '')),
                    'stktouch'       => round($this->toNum($r->stktouch ?? 100), 2),
                    'touch'          => round($this->toNum($r->touch ?? 0), 2),
                    'rcvd_touch'     => round($this->toNum($r->rcvdtouch ?? 0), 2),
                    'mud_less'       => round($this->toNum($r->mudless ?? 0), 3),
                    'rcvd_qty'       => (int) ($r->rcvdqty ?? 0),
                    'rcvd_wgt'       => round($rWgt, 3),
                    'rate'           => round($this->toNum($r->rate ?? 0), 2),
                    'rcvd_wgt_amt'   => round($this->toNum($r->rcvdwgtamt ?? 0), 2),
                    'bottle_stk'     => round($oBs, 3),
                    'test_pcs'       => round($iTP, 3),
                    'sno'            => (int) ($r->sno ?? 0),
                ];
            }
        }

        $masterIssuedWgt = $this->toNum($master->toldissuedwgt ?? 0);
        $tBottleStk = $this->toNum($master->tbottlestk ?? 0);
        $tTestPcsM = $this->toNum($master->ttestpcs ?? 0);
        $netIssued = $masterIssuedWgt - $tStoneWgt - $tBottleStk - $tMud - $tTestPcsM;

        return response()->json([
            'ok'             => true,
            'doc_no'         => trim((string) ($master->docno ?? '')),
            'orig_doc_no'    => $origDocNo,
            'orig_slno'      => 0,
            'bill_date'      => !empty($master->tdate) ? Carbon::parse((string) $master->tdate)->format('d/m/Y') : '',
            'refiner_code'   => $refCode,
            'refiner_name'   => $refName,
            'sm_code'        => trim((string) ($master->smcode ?? '')),
            'control'        => (int) ($master->control ?? 1),
            'charge'         => round($this->toNum($master->charge ?? 0), 2),
            'paidamt'        => round($this->toNum($master->paidamt ?? 0), 2),
            'note'           => $displayNote,
            // Summary
            'issued_wgt'     => round($masterIssuedWgt, 3),
            'stone_wgt'      => round($tStoneWgt, 3),
            'bottle_stk'     => round($tBottleStk, 3),
            'mud'            => round($tMud, 3),
            'test_pcs'       => round($tTestPcsM, 3),
            'net_issued'     => round(max($netIssued, 0), 3),
            'test_perc'      => round($this->toNum($master->testperc ?? 0), 2),
            'net_issued_amt' => round($tIssuedWgtAmt, 2),
            'items'          => $items,
        ]);
    }

    // ─── Search original entries for new return ───────────────────────────

    public function searchForReturn(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $q = trim((string) $request->query('q', ''));
        if (!$this->hasTable('refinerym')) {
            return response()->json(['ok' => true, 'results' => []]);
        }

        // Status=1 means active issued entries (not returns, not cancelled)
        $query = DB::table('refinerym')->where('status', 1);
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('docno', 'like', "%{$q}%")
                  ->orWhere('refcode', 'like', "%{$q}%");
            });
        }

        $rows = $query->orderByDesc('slno')->limit(100)->get(['docno', 'tdate', 'refcode']);
        $refNames = $this->getRefinerNames($rows->pluck('refcode')->filter()->map(fn($c) => trim((string) $c))->unique()->values()->all());

        $results = $rows->take(50)->map(function ($r) use ($refNames) {
            $refCode = trim($r->refcode ?? '');
            $docNo   = trim($r->docno ?? '');
            $existing = $this->findReturnByOriginalDoc($docNo, $refCode);
            return [
                'doc_no'          => $docNo,
                'date'            => $r->tdate ?? '',
                'ref_code'        => $refCode,
                'ref_name'        => $refNames[$refCode] ?? '',
                'has_return'      => $existing ? true : false,
                'return_doc_no'   => $existing ? trim((string) ($existing->docno ?? '')) : '',
            ];
        })->values()->all();

        return response()->json(['ok' => true, 'results' => $results]);
    }

    // ─── Search existing return entries (for edit mode) ───────────────────

    public function searchReturn(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $q = trim((string) $request->query('q', ''));
        if (!$this->hasTable('refinerym')) {
            return response()->json(['ok' => true, 'results' => []]);
        }

        // Status=2 means return entries
        $query = DB::table('refinerym')->where('status', 2);
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('docno', 'like', "%{$q}%")
                  ->orWhere('refcode', 'like', "%{$q}%");
            });
        }

        $rows = $query->orderByDesc('slno')->limit(30)->get(['docno', 'tdate', 'refcode']);
        $refNames = $this->getRefinerNames($rows->pluck('refcode')->filter()->map(fn($c) => trim((string) $c))->unique()->values()->all());

        $results = $rows->map(function ($r) use ($refNames) {
            $refCode = trim($r->refcode ?? '');
            return [
                'doc_no'   => trim($r->docno ?? ''),
                'date'     => $r->tdate ?? '',
                'ref_code' => $refCode,
                'ref_name' => $refNames[$refCode] ?? '',
            ];
        })->values()->all();

        return response()->json(['ok' => true, 'results' => $results]);
    }

    // ─── Prev / Next return entries ───────────────────────────────────────

    public function prevReturn(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }
        $current = strtoupper(trim((string) $request->query('doc_no', '')));
        if (!$this->hasTable('refinerym')) {
            return response()->json(['ok' => false, 'message' => 'No entries.']);
        }
        $currentSlno = 0;
        if ($current !== '') {
            $currentSlno = (int) (DB::table('refinerym')->where('docno', $current)->value('slno') ?? 0);
        }
        $prev = DB::table('refinerym')
            ->where('status', 2)
            ->when($currentSlno > 0, fn($q) => $q->where('slno', '<', $currentSlno))
            ->orderByDesc('slno')
            ->first(['docno']);
        if (!$prev) {
            return response()->json(['ok' => false, 'message' => 'No previous return.']);
        }
        return response()->json(['ok' => true, 'doc_no' => trim($prev->docno)]);
    }

    public function nextReturn(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }
        $current = strtoupper(trim((string) $request->query('doc_no', '')));
        if (!$this->hasTable('refinerym')) {
            return response()->json(['ok' => false, 'message' => 'No entries.']);
        }
        $currentSlno = 0;
        if ($current !== '') {
            $currentSlno = (int) (DB::table('refinerym')->where('docno', $current)->value('slno') ?? 0);
        }
        $next = DB::table('refinerym')
            ->where('status', 2)
            ->when($currentSlno > 0, fn($q) => $q->where('slno', '>', $currentSlno))
            ->orderBy('slno')
            ->first(['docno']);
        if (!$next) {
            return response()->json(['ok' => false, 'message' => 'No next return.']);
        }
        return response()->json(['ok' => true, 'doc_no' => trim($next->docno)]);
    }

    private function findReturnByOriginalDoc(string $origDocNo, string $refCode = ''): ?object
    {
        $origDocNo = strtoupper(trim($origDocNo));
        if ($origDocNo === '' || !$this->hasTable('refinerym')) {
            return null;
        }

        $query = DB::table('refinerym')->where('status', 2);
        if ($refCode !== '') {
            $query->where('refcode', trim($refCode));
        }

        return $query
            ->where(function ($w) use ($origDocNo) {
                $w->where('note', 'like', "Ret:{$origDocNo}%")
                  ->orWhere('note', 'like', "%Ret:{$origDocNo}%")
                  ->orWhere('note', 'like', "%{$origDocNo}%");
            })
            ->orderByDesc('slno')
            ->first();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ─── Private: Reverse stock effects ───────────────────────────────────
    // ═══════════════════════════════════════════════════════════════════════

    private function reverseStockEffects(int $slno): void
    {
        if ($slno <= 0 || !$this->hasTable('refineryd') || !$this->hasTable('refinerym')) return;

        $master = DB::table('refinerym')->where('slno', $slno)->first();
        $control = (int) ($master->control ?? 1);

        $rows = DB::table('refineryd')->where('slno', $slno)->get();
        foreach ($rows as $r) {
            $code = trim((string) ($r->code ?? ''));
            if ($code === '') continue;
            $issuedWgt = $this->toNum($r->issuedwgt ?? 0);
            $issuedQty = (int) ($r->issuedqty ?? 0);
            $stoneWgt = $this->toNum($r->issuedstwgt ?? 0);
            $testPcs = $this->toNum($r->testpcs ?? 0);
            $stktype = trim((string) ($r->stktype ?? ''));

            // Reverse: issued items were decreased, so INCREASE them back
            $this->adjustItemStock($code, $issuedQty, $issuedWgt, $stoneWgt, $stktype, $control);

            // Reverse: TP was increased, so DECREASE it back
            if ($testPcs > 0) {
                $this->adjustItemStock('TP', 0, -$testPcs, 0, $stktype, $control);
            }
        }
    }

    // ─── Private: Stock adjustment ────────────────────────────────────────

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
        if (in_array('stonewgtb', $itemCols, true) && $stoneDelta != 0) {
            $updates['stonewgtb'] = DB::raw('COALESCE(stonewgtb,0) + ' . $stoneDelta);
        }
        if ($control === 1) {
            if (in_array('qty', $itemCols, true)) {
                $updates['qty'] = DB::raw('COALESCE(qty,0) + ' . $qtyDelta);
            }
            if (in_array('weight', $itemCols, true)) {
                $updates['weight'] = DB::raw('COALESCE(weight,0) + ' . $weightDelta);
            }
            if (in_array('stonewgt', $itemCols, true) && $stoneDelta != 0) {
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
        if (in_array('stonewgtb', $stkCols, true) && $stoneDelta != 0) {
            $updates['stonewgtb'] = DB::raw('COALESCE(stonewgtb,0) + ' . $stoneDelta);
        }
        if ($control === 1) {
            if (in_array('qty', $stkCols, true)) {
                $updates['qty'] = DB::raw('COALESCE(qty,0) + ' . $qtyDelta);
            }
            if (in_array('weight', $stkCols, true)) {
                $updates['weight'] = DB::raw('COALESCE(weight,0) + ' . $weightDelta);
            }
            if (in_array('stonewgt', $stkCols, true) && $stoneDelta != 0) {
                $updates['stonewgt'] = DB::raw('COALESCE(stonewgt,0) + ' . $stoneDelta);
            }
        }
        if ($updates !== []) {
            DB::table('itemsstk')->where('code', $code)->where('stktype', $stkType)->update($updates);
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    private function toNum(mixed $value): float
    {
        if ($value === null || $value === '') return 0.0;
        $v = (float) preg_replace('/[^0-9.\-]/', '', (string) $value);
        return is_nan($v) ? 0.0 : $v;
    }

    private function parseDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') return null;
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $value, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        if (preg_match('#^\d{4}-\d{2}-\d{2}#', $value)) return substr($value, 0, 10);
        return null;
    }

    private function parsePickerDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') return null;
        foreach (['d/m/Y', 'Y-m-d'] as $format) {
            $dt = \DateTime::createFromFormat($format, $value);
            if ($dt && $dt->format($format) === $value) {
                return $dt->format('Y-m-d');
            }
        }
        return null;
    }

    private function getColumns(string $table): array
    {
        try {
            return array_map('strtolower', $this->columnList($table));
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function readGeneraliCounter(string $code): int
    {
        if (!$this->hasTable('generali')) return 0;
        $row = DB::table('generali')->where('code', $code)->first();
        if (!$row) {
            DB::table('generali')->insert(['code' => $code, 'cvalue' => 0]);
            return 0;
        }
        return (int) ($row->cvalue ?? 0);
    }

    private function peekNextDocNo(string $counterCode, string $prefix): string
    {
        $next = $this->readGeneraliCounter($counterCode) + 1;
        return $prefix . '/' . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    private function incrementGeneraliCounter(string $code, int $newValue): void
    {
        if (!$this->hasTable('generali')) return;
        DB::table('generali')->where('code', $code)->update(['cvalue' => $newValue]);
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
        $this->incrementGeneraliCounter('SERIALNO', $next);

        return $next;
    }

    private function loadSettingsPayload(): array
    {
        $iniPath = base_path('gmine.ini');
        if (!file_exists($iniPath)) {
            $iniPath = 'C:\\gmine\\gmine.ini';
        }
        if (!file_exists($iniPath)) {
            return [];
        }
        return $this->parseLegacyIni(file_get_contents($iniPath));
    }

    private function parseLegacyIni(string $raw): array
    {
        $result = [];
        $section = 'General';
        foreach (explode("\n", $raw) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === ';' || $line[0] === '#') continue;
            if (preg_match('/^\[(.+)\]$/', $line, $m)) { $section = trim($m[1]); continue; }
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $result[$section][trim($parts[0])] = trim($parts[1]);
            }
        }
        return $result;
    }

    // ─── Return-specific helpers ─────────────────────────────────────────

    private function reverseReturnStockEffects(int $slno): void
    {
        if ($slno <= 0 || !$this->hasTable('refineryd') || !$this->hasTable('refinerym')) return;

        $master = DB::table('refinerym')->where('slno', $slno)->first();
        $control = (int) ($master->control ?? 1);

        $rows = DB::table('refineryd')->where('slno', $slno)->get();
        foreach ($rows as $r) {
            $code = trim((string) ($r->code ?? ''));
            if ($code === '') continue;
            $rcvdWgt = $this->toNum($r->rcvdwgt ?? 0);
            $rcvdQty = (int) ($r->rcvdqty ?? 0);
            $bottleStk = $this->toNum($r->bottlestk ?? 0);
            $testPcs = $this->toNum($r->testpcs ?? 0);
            $issuedWgt = $this->toNum($r->issuedwgt ?? 0);
            $issuedQty = (int) ($r->issuedqty ?? 0);
            $stktype = trim((string) ($r->stktype ?? ''));

            $netQtyDelta = $rcvdQty - $issuedQty;
            $netWgtDelta = $rcvdWgt - $bottleStk - $testPcs - $issuedWgt;
            if ($netWgtDelta != 0.0 || $netQtyDelta != 0) {
                $this->adjustItemStock($code, -$netQtyDelta, -$netWgtDelta, 0, $stktype, $control);
            }

            // Reverse bottle stock: DECREASE BS
            if ($bottleStk > 0) {
                $this->adjustItemStock('BS', 0, -$bottleStk, 0, $stktype, $control);
            }

            // Reverse test pieces: DECREASE TP back
            if ($testPcs > 0) {
                $this->adjustItemStock('TP', 0, -$testPcs, 0, $stktype, $control);
            }
        }
    }

    private function deleteDaybookEntries(int $slno): void
    {
        if ($slno <= 0) return;
        if ($this->hasTable('daybookpart')) {
            DB::table('daybookpart')->where('slno', $slno)->delete();
        }
        if ($this->hasTable('daybook')) {
            DB::table('daybook')->where('slno', $slno)->delete();
        }
    }

    private function insertReturnDaybookEntries(
        int $slno, string $docNo, string $date, string $time,
        string $refCode, float $charge, float $paidamt,
        int $control, string $incharge
    ): void {
        if (!$this->hasTable('daybook') || !$this->hasTable('daybookpart')) return;

        $dbCols = $this->getColumns('daybook');
        $dpCols = $this->getColumns('daybookpart');

        // Get refiner name
        $refName = '';
        if ($this->hasTable('clients') && $refCode !== '') {
            $refName = trim((string) (DB::table('clients')->where('code', $refCode)->value('name') ?? ''));
        }

        $sno = 1;

        // Charge entry: refiner account
        if ($charge > 0) {
            $db = [
                'slno'     => $slno,
                'tdate'    => $date,
                'accode'   => $refCode,
                'amount'   => round($charge, 2),
                'control'  => $control,
                'opaccode' => $refCode,
                'sno'      => $sno,
            ];
            $db = array_filter($db, fn($k) => in_array($k, $dbCols, true), ARRAY_FILTER_USE_KEY);
            DB::table('daybook')->insert($db);
            $sno++;
        }

        // Paid entry: CASH account
        if ($paidamt > 0) {
            $db = [
                'slno'     => $slno,
                'tdate'    => $date,
                'accode'   => 'CASH',
                'amount'   => round($paidamt, 2),
                'control'  => $control,
                'opaccode' => $refCode,
                'sno'      => $sno,
            ];
            $db = array_filter($db, fn($k) => in_array($k, $dbCols, true), ARRAY_FILTER_USE_KEY);
            DB::table('daybook')->insert($db);
        }

        // Daybookpart (one entry per transaction)
        $particular = "By Refinery Return ({$docNo}) To {$refName}";
        $dp = [
            'slno'       => $slno,
            'vchno'      => $docNo,
            'particular' => mb_substr($particular, 0, 100),
            'ic'         => $incharge,
            'ttime'      => $time,
        ];
        $dp = array_filter($dp, fn($k) => in_array($k, $dpCols, true), ARRAY_FILTER_USE_KEY);
        DB::table('daybookpart')->insert($dp);
    }

    private function updateWACost(string $code, float $rcvdWgt, float $rate): void
    {
        if ($code === '' || !$this->hasTable('items')) return;
        if ($rcvdWgt <= 0 || $rate <= 0) return;

        $item = DB::table('items')->where('code', $code)->first(['weightb', 'weight', 'cost']);
        if (!$item) return;

        $currentWgt = $this->toNum($item->weightb ?? $item->weight ?? 0);
        $currentCost = $this->toNum($item->cost ?? 0);

        // currentWgt already includes the received weight after stock update.
        // Guard against stale/rounded weights in edit mode so WA cost does not overflow.
        if ($currentWgt > 0) {
            $effectiveRcvdWgt = min($rcvdWgt, $currentWgt);
            $prevWgt = max(0, $currentWgt - $effectiveRcvdWgt);
            $newCost = ($prevWgt * $currentCost + $effectiveRcvdWgt * $rate) / $currentWgt;
            DB::table('items')->where('code', $code)->update(['cost' => round($newCost, 2)]);
        }
    }

    private function getItemNames(array $codes): array
    {
        if ($codes === [] || !$this->hasTable('items')) return [];
        return DB::table('items')
            ->whereIn('code', $codes)
            ->pluck('name', 'code')
            ->mapWithKeys(fn($v, $k) => [trim((string) $k) => trim((string) $v)])
            ->toArray();
    }

    private function getRefinerNames(array $codes): array
    {
        if ($codes === [] || !$this->hasTable('clients')) return [];
        return DB::table('clients')
            ->whereIn('code', $codes)
            ->pluck('name', 'code')
            ->mapWithKeys(fn($v, $k) => [trim((string) $k) => trim((string) $v)])
            ->toArray();
    }
}
