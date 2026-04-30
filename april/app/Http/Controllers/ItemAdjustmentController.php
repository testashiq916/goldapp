<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ItemAdjustmentController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->session()->has('user_code'), 401);

        $module = (string) $request->query('module', 'item-stock-adjustment-stock-transfer');
        $title = (string) $request->query('title', 'Stock Transfer');
        $barcodeMode = str_contains(strtolower($module), 'barcode');

        $salesmen = $this->hasTable('sman')
            ? DB::table('sman')->orderBy('name')->get(['code', 'name'])->toArray()
            : [];

        $stockTypes = $this->hasTable('stktype')
            ? DB::table('stktype')->orderByDesc('def')->orderBy('code')->get(['code', 'name', 'def'])->toArray()
            : [];

        $defaultStockType = '';
        foreach ($stockTypes as $row) {
            if ((int) ($row->def ?? 0) === 1) {
                $defaultStockType = trim((string) ($row->code ?? ''));
                break;
            }
        }
        if ($defaultStockType === '' && isset($stockTypes[0])) {
            $defaultStockType = trim((string) ($stockTypes[0]->code ?? ''));
        }

        return view('item-adjustment.index', [
            'title' => $title,
            'moduleId' => $module,
            'barcodeMode' => $barcodeMode,
            'salesmen' => $salesmen,
            'stockTypes' => $stockTypes,
            'defaultStockType' => $defaultStockType,
            'gisemi' => $this->resolveGisemi($request),
        ]);
    }

    public function multi(Request $request): View
    {
        abort_unless($request->session()->has('user_code'), 401);

        $salesmen = $this->hasTable('sman')
            ? DB::table('sman')->orderBy('name')->get(['code', 'name'])->toArray()
            : [];

        $stockTypes = $this->hasTable('stktype')
            ? DB::table('stktype')->orderByDesc('def')->orderBy('code')->get(['code', 'name', 'def'])->toArray()
            : [];

        $groups = $this->hasTable('itemgrp')
            ? DB::table('itemgrp')->orderBy('code')->get(['code', 'name'])->toArray()
            : [];

        $defaultStockType = '';
        foreach ($stockTypes as $row) {
            if ((int) ($row->def ?? 0) === 1) {
                $defaultStockType = trim((string) ($row->code ?? ''));
                break;
            }
        }
        if ($defaultStockType === '' && isset($stockTypes[0])) {
            $defaultStockType = trim((string) ($stockTypes[0]->code ?? ''));
        }

        $billNo = $this->nextCounterDisplay('STKADJNO', 'ST/');

        return view('item-adjustment.multi', [
            'title' => (string) $request->query('title', 'Stock Transfer Multi Entry'),
            'moduleId' => (string) $request->query('module', 'item-stock-adjustment-stock-transfer-multi-entry'),
            'salesmen' => $salesmen,
            'stockTypes' => $stockTypes,
            'groups' => $groups,
            'defaultStockType' => $defaultStockType,
            'gisemi' => $this->resolveGisemi($request),
            'billNo' => $billNo,
        ]);
    }

    public function addLess(Request $request): View
    {
        abort_unless($request->session()->has('user_code'), 401);

        $salesmen = $this->hasTable('sman')
            ? DB::table('sman')->orderBy('name')->get(['code', 'name'])->toArray()
            : [];

        $stockTypes = $this->hasTable('stktype')
            ? DB::table('stktype')->orderByDesc('def')->orderBy('code')->get(['code', 'name', 'def'])->toArray()
            : [];

        $defaultStockType = '';
        foreach ($stockTypes as $row) {
            if ((int) ($row->def ?? 0) === 1) {
                $defaultStockType = trim((string) ($row->code ?? ''));
                break;
            }
        }
        if ($defaultStockType === '' && isset($stockTypes[0])) {
            $defaultStockType = trim((string) ($stockTypes[0]->code ?? ''));
        }

        return view('item-adjustment.add-less', [
            'title' => (string) $request->query('title', 'Stock Add - Less'),
            'moduleId' => (string) $request->query('module', 'item-stock-adjustment-stock-add-less'),
            'salesmen' => $salesmen,
            'stockTypes' => $stockTypes,
            'defaultStockType' => $defaultStockType,
            'gisemi' => $this->resolveGisemi($request),
        ]);
    }

    public function addLessReport(Request $request): View
    {
        abort_unless($request->session()->has('user_code'), 401);

        $salesmen = $this->hasTable('sman')
            ? DB::table('sman')->orderBy('name')->get(['code', 'name'])->toArray()
            : [];

        $stockTypes = $this->hasTable('stktype')
            ? DB::table('stktype')->orderBy('code')->get(['code', 'name'])->toArray()
            : [];

        return view('reports.item-add-less-report', [
            'title' => (string) $request->query('title', 'Item Add Less Report'),
            'moduleId' => (string) $request->query('module', 'item-reports-item-add-less-report'),
            'date1' => (string) $request->query('date1', date('Y-m-d')),
            'date2' => (string) $request->query('date2', date('Y-m-d')),
            'rlevel' => (int) $request->query('rlevel', (int) $request->session()->get('gilevel', 1) ?: 1),
            'salesmen' => $salesmen,
            'stockTypes' => $stockTypes,
            'reasons' => ['All', 'Add/Less', 'Correction', 'Missing'],
        ]);
    }

    public function addLessReportData(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);

        if (!$this->hasTable('itemadj')) {
            return response()->json([
                'success' => true,
                'rows' => [],
                'totals' => $this->buildAddLessReportTotals([]),
                'message' => 'itemadj table not found',
            ]);
        }

        $date1 = $this->normDate((string) $request->query('date1', '')) ?? date('Y-m-d');
        $date2 = $this->normDate((string) $request->query('date2', '')) ?? date('Y-m-d');
        $rlevel = (int) $request->query('rlevel', (int) $request->session()->get('gilevel', 1));
        if ($rlevel <= 0) {
            $rlevel = 1;
        }

        $itemCode = strtoupper(trim((string) $request->query('item_code', '')));
        $reason = trim((string) $request->query('reason', 'All'));
        $smcode = strtoupper(trim((string) $request->query('smcode', '')));
        $stktype = strtoupper(trim((string) $request->query('stktype', '')));

        $query = DB::table('itemadj as a')
            ->leftJoin('items as fi', DB::raw('UPPER(TRIM(fi.code))'), '=', DB::raw('UPPER(TRIM(a.fromcode))'))
            ->leftJoin('items as ti', DB::raw('UPPER(TRIM(ti.code))'), '=', DB::raw('UPPER(TRIM(a.tocode))'))
            ->where('a.control', '<=', $rlevel)
            ->whereBetween('a.tdate', [$date1, $date2])
            ->where('a.al', '=', 'A');

        if ($itemCode !== '') {
            $query->where(function ($q) use ($itemCode) {
                $q->whereRaw('UPPER(TRIM(a.fromcode)) = ?', [$itemCode])
                    ->orWhereRaw('UPPER(TRIM(a.tocode)) = ?', [$itemCode]);
            });
        }

        if ($reason !== '' && strtoupper($reason) !== 'ALL') {
            $query->where('a.particular', '=', $reason);
        }

        if ($smcode !== '') {
            $query->whereRaw('UPPER(TRIM(COALESCE(a.smcode, \'\'))) = ?', [$smcode]);
        }

        if ($stktype !== '') {
            $query->where(function ($q) use ($stktype) {
                $q->whereRaw('UPPER(TRIM(COALESCE(a.fromstktype, \'\'))) = ?', [$stktype])
                    ->orWhereRaw('UPPER(TRIM(COALESCE(a.tostktype, \'\'))) = ?', [$stktype]);
            });
        }

        $rows = $query
            ->orderBy('a.tdate')
            ->orderBy('a.ttime')
            ->orderBy('a.slno')
            ->get([
                'a.tdate',
                'a.ttime',
                'a.fromqty',
                'a.fromwgt',
                'a.toqty',
                'a.towgt',
                'a.particular',
                'a.smcode',
                'a.slno',
                'a.fromstwgt',
                'a.tostwgt',
                'a.fromstktype',
                'a.tostktype',
                'fi.name as fromname',
                'ti.name as toname',
            ])
            ->map(function ($r) {
                $fromQty = (int) ($r->fromqty ?? 0);
                $fromWgt = (float) ($r->fromwgt ?? 0);
                $toQty = (int) ($r->toqty ?? 0);
                $toWgt = (float) ($r->towgt ?? 0);
                $fromStWgt = (float) ($r->fromstwgt ?? 0);
                $toStWgt = (float) ($r->tostwgt ?? 0);

                return [
                    'tdate' => (string) ($r->tdate ?? ''),
                    'ttime' => (string) ($r->ttime ?? ''),
                    'itemname' => ($fromQty > 0 || $fromWgt > 0)
                        ? trim((string) ($r->fromname ?? ''))
                        : trim((string) ($r->toname ?? '')),
                    'addqty' => ($toQty > 0 || $toWgt > 0) ? $toQty : 0,
                    'addwgt' => ($toQty > 0 || $toWgt > 0) ? round($toWgt, 3) : 0.0,
                    'addstwgt' => ($toQty > 0 || $toWgt > 0 || $toStWgt > 0) ? round($toStWgt, 3) : 0.0,
                    'addstktype' => trim((string) ($r->tostktype ?? '')),
                    'lessqty' => ($fromQty > 0 || $fromWgt > 0) ? $fromQty : 0,
                    'lesswgt' => ($fromQty > 0 || $fromWgt > 0) ? round($fromWgt, 3) : 0.0,
                    'lessstwgt' => ($fromQty > 0 || $fromWgt > 0 || $fromStWgt > 0) ? round($fromStWgt, 3) : 0.0,
                    'lessstktype' => trim((string) ($r->fromstktype ?? '')),
                    'smcode' => trim((string) ($r->smcode ?? '')),
                    'particular' => trim((string) ($r->particular ?? '')),
                    'slno' => (int) ($r->slno ?? 0),
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'rows' => $rows,
            'totals' => $this->buildAddLessReportTotals($rows),
        ]);
    }

    public function stockAdjustment(Request $request): View
    {
        abort_unless($request->session()->has('user_code'), 401);

        $salesmen = $this->hasTable('sman')
            ? DB::table('sman')->orderBy('name')->get(['code', 'name'])->toArray()
            : [];

        $stockTypes = $this->hasTable('stktype')
            ? DB::table('stktype')->orderByDesc('def')->orderBy('code')->get(['code', 'name', 'def'])->toArray()
            : [];

        $groups = $this->hasTable('itemgrp')
            ? DB::table('itemgrp')->orderBy('code')->get(['code', 'name'])->toArray()
            : DB::table('items')->whereNotNull('grpcode')->where('grpcode', '!=', '')->distinct()->orderBy('grpcode')->get(['grpcode as code']);

        return view('item-adjustment/stock-adjustment', [
            'title' => (string) $request->query('title', 'Stock Adjustment'),
            'moduleId' => (string) $request->query('module', 'item-stock-adjustment-stock-adjustment'),
            'salesmen' => $salesmen,
            'stockTypes' => $stockTypes,
            'groups' => $groups,
            'gisemi' => $this->resolveGisemi($request),
        ]);
    }

    public function item(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);

        $code = strtoupper(trim((string) $request->query('code', '')));
        if ($code === '') {
            return response()->json(['success' => false, 'message' => 'Item code required'], 422);
        }

        $row = DB::table('items')
            ->whereRaw('UPPER(TRIM(code)) = ?', [$code])
            ->first(['code', 'name', 'cost', 'disabled', 'defstktype']);

        if (!$row) {
            return response()->json(['success' => false, 'message' => 'This item does not exist'], 404);
        }

        if ((int) ($row->disabled ?? 0) === 1) {
            return response()->json(['success' => false, 'message' => 'This item is disabled'], 422);
        }

        return response()->json([
            'success' => true,
            'item' => [
                'code' => (string) ($row->code ?? ''),
                'name' => (string) ($row->name ?? ''),
                'cost' => (float) ($row->cost ?? 0),
                'defstktype' => trim((string) ($row->defstktype ?? '')),
            ],
        ]);
    }

    public function itemSearch(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);

        $q = '%' . trim((string) $request->query('q', '')) . '%';
        $rows = DB::table('items')
            ->where(function ($query) use ($q) {
                $query->where('code', 'like', $q)->orWhere('name', 'like', $q);
            })
            ->where('disabled', 0)
            ->orderBy('code')
            ->limit(40)
            ->get(['code', 'name']);

        return response()->json(['success' => true, 'rows' => $rows]);
    }

    public function barcode(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);

        $raw = trim((string) $request->query('bcode', ''));
        if ($raw === '' || !is_numeric($raw)) {
            return response()->json(['success' => false, 'message' => 'Invalid barcode'], 422);
        }

        $bcode = (int) $raw;
        $row = DB::table('barcode as b')
            ->leftJoin('items as i', 'i.code', '=', 'b.icode')
            ->where('b.bcode', $bcode)
            ->first([
                'b.bcode',
                'b.icode',
                'b.qty',
                'b.weight',
                'b.weight2',
                'b.stweight',
                'b.stprice',
                'b.stktouch',
                'b.stk',
                'i.name',
                'i.cost',
                'i.defstktype',
            ]);

        if (!$row) {
            return response()->json(['success' => false, 'message' => 'This barcode does not exist'], 404);
        }

        return response()->json([
            'success' => true,
            'barcode' => [
                'bcode' => (int) ($row->bcode ?? 0),
                'code' => (string) ($row->icode ?? ''),
                'name' => (string) ($row->name ?? ''),
                'qty' => (int) ($row->qty ?? 0),
                'weight' => (float) ($row->weight ?? 0),
                'weight2' => (float) ($row->weight2 ?? 0),
                'stonewgt' => (float) ($row->stweight ?? 0),
                'stoneamt' => (float) ($row->stprice ?? 0),
                'stktouch' => (float) ($row->stktouch ?? 0),
                'stk' => (string) ($row->stk ?? 'Y'),
                'cost' => (float) ($row->cost ?? 0),
                'defstktype' => trim((string) ($row->defstktype ?? '')),
            ],
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);

        $data = [
            'tdate' => $this->normDate((string) $request->input('tdate', '')) ?? date('Y-m-d'),
            'smcode' => strtoupper(trim((string) $request->input('smcode', ''))),
            'fromcode' => strtoupper(trim((string) $request->input('fromcode', ''))),
            'tocode' => strtoupper(trim((string) $request->input('tocode', ''))),
            'fromqty' => (int) $request->input('fromqty', 0),
            'toqty' => (int) $request->input('toqty', 0),
            'fromwgt' => (float) $request->input('fromwgt', 0),
            'towgt' => (float) $request->input('towgt', 0),
            'fromstwgt' => (float) $request->input('fromstwgt', 0),
            'tostwgt' => (float) $request->input('tostwgt', 0),
            'fromstamt' => (float) $request->input('fromstamt', 0),
            'tostamt' => (float) $request->input('tostamt', 0),
            'fromcost' => (float) $request->input('fromcost', 0),
            'tocost' => (float) $request->input('tocost', 0),
            'fromstktouch' => (float) $request->input('fromstktouch', 0),
            'tostktouch' => (float) $request->input('tostktouch', 0),
            'fromstktype' => strtoupper(trim((string) $request->input('fromstktype', ''))),
            'tostktype' => strtoupper(trim((string) $request->input('tostktype', ''))),
            'reason' => trim((string) $request->input('reason', '')),
            'ichange' => $request->boolean('ichange') ? 1 : 0,
            'frombcode' => (int) $request->input('frombcode', 0),
            'tobcode' => (int) $request->input('tobcode', 0),
        ];

        if ($data['fromcode'] === '' || $data['tocode'] === '') {
            return response()->json(['success' => false, 'message' => 'From/To item code required'], 422);
        }

        if ($data['fromcode'] !== 'AL' && !$this->itemExists($data['fromcode'])) {
            return response()->json(['success' => false, 'message' => 'From item does not exist'], 422);
        }
        if ($data['tocode'] !== 'AL' && !$this->itemExists($data['tocode'])) {
            return response()->json(['success' => false, 'message' => 'To item does not exist'], 422);
        }

        if (($data['fromcode'] !== 'AL' && $data['tocode'] !== 'AL')
            && ($data['fromwgt'] <= 0 || $data['towgt'] <= 0 || abs($data['fromwgt'] - $data['towgt']) > 0.0001)) {
            return response()->json(['success' => false, 'message' => 'Entries are not correct. Please check'], 422);
        }

        if ($data['reason'] === '') {
            $data['reason'] = 'Item adjusted from ' . $data['fromcode'] . ' to ' . $data['tocode'];
        }
        $data['reason'] = mb_substr($data['reason'], 0, 30);

        if ($data['frombcode'] > 0) {
            $barcode = DB::table('barcode')->where('bcode', $data['frombcode'])->first();
            if (!$barcode) {
                return response()->json(['success' => false, 'message' => 'From barcode not found'], 422);
            }
            if ((string) ($barcode->stk ?? 'Y') === 'N') {
                return response()->json(['success' => false, 'message' => 'This stock not exist'], 422);
            }
        }

        if ($data['tobcode'] > 0 && !DB::table('barcode')->where('bcode', $data['tobcode'])->exists()) {
            return response()->json(['success' => false, 'message' => 'To barcode not found'], 422);
        }

        $gisemi = $this->resolveGisemi($request);
        $userCode = (string) $request->session()->get('user_code', '');
        $ttime = date('H:i:s');

        DB::beginTransaction();

        try {
            $this->touchCounter('BLOCK');
            $slno = $this->nextSerialNo();

            DB::table('itemadj')->insert($this->f('itemadj', [
                'slno' => $slno,
                'fromcode' => $data['fromcode'],
                'fromqty' => $data['fromqty'],
                'fromwgt' => $data['fromwgt'],
                'fromcost' => $data['fromcost'],
                'tocode' => $data['tocode'],
                'toqty' => $data['toqty'],
                'towgt' => $data['towgt'],
                'tocost' => $data['tocost'],
                'particular' => $data['reason'],
                'tdate' => $data['tdate'],
                'ttime' => $ttime,
                'control' => $gisemi,
                'smcode' => $data['smcode'],
                'fromstwgt' => $data['fromstwgt'],
                'tostwgt' => $data['tostwgt'],
                'al' => ' ',
                'fromstktype' => $data['fromstktype'],
                'tostktype' => $data['tostktype'],
                'ichange' => $data['ichange'],
                'fromstamt' => $data['fromstamt'],
                'tostamt' => $data['tostamt'],
                'ic' => $userCode,
                'fromstktouch' => $data['fromstktouch'],
                'tostktouch' => $data['tostktouch'],
                'bcode' => $data['frombcode'],
                'tobcode' => $data['tobcode'],
            ]));

            if ($data['frombcode'] > 0) {
                DB::table('barcode')->where('bcode', $data['frombcode'])->update($this->f('barcode', [
                    'weight' => DB::raw('weight - ' . $data['fromwgt']),
                    'stweight' => DB::raw('stweight - ' . $data['fromstwgt']),
                    'stprice' => DB::raw('stprice - ' . $data['fromstamt']),
                    'qty' => DB::raw('qty - ' . $data['fromqty']),
                ]));

                DB::table('barcode')
                    ->where('bcode', $data['frombcode'])
                    ->where('weight', '<=', 0)
                    ->update($this->f('barcode', ['stk' => 'N']));
            }

            if ($data['tobcode'] > 0) {
                DB::table('barcode')->where('bcode', $data['tobcode'])->update($this->f('barcode', [
                    'weight' => DB::raw('weight + ' . $data['towgt']),
                    'stweight' => DB::raw('stweight + ' . $data['tostwgt']),
                    'stprice' => DB::raw('stprice + ' . $data['tostamt']),
                    'qty' => DB::raw('qty + ' . $data['toqty']),
                ]));
            }

            if ($data['fromcode'] !== 'AL') {
                $this->adjustItemStock(
                    $data['fromcode'],
                    -$data['fromqty'],
                    -$data['fromwgt'],
                    -$data['fromstwgt'],
                    $data['fromstktype'],
                    $gisemi
                );
            }

            if ($data['tocode'] !== 'AL') {
                $dwacost = $this->weightedAverageCost($data['tocode'], $data['towgt'], $data['tocost']);
                $this->adjustItemStock(
                    $data['tocode'],
                    $data['toqty'],
                    $data['towgt'],
                    $data['tostwgt'],
                    $data['tostktype'],
                    $gisemi,
                    $dwacost
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock transfer saved',
                'slno' => $slno,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function bcSummary(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);

        $dateFrom = $this->normDate((string) $request->query('date_from', '')) ?? date('Y-m-d');
        $dateTo = $this->normDate((string) $request->query('date_to', '')) ?? date('Y-m-d');
        $group = strtoupper(trim((string) $request->query('group', '')));
        $pendingOnly = $request->boolean('pending_only');

        $query = DB::table('barcode as bc')
            ->join('items as i', 'i.code', '=', 'bc.icode')
            ->whereBetween('bc.tdate', [$dateFrom, $dateTo]);

        if ($pendingOnly) {
            $query->where('bc.status', 'R');
        }

        if ($group !== '') {
            $query->whereRaw('UPPER(TRIM(COALESCE(i.grpcode, \'\'))) = ?', [$group]);
        }

        $rows = $query
            ->groupBy('bc.icode', 'i.name')
            ->orderBy('bc.icode')
            ->get([
                'bc.icode as itemcode',
                'i.name as itemname',
                DB::raw('COALESCE(SUM(bc.qty),0) as qty'),
                DB::raw('COALESCE(SUM(bc.weight),0) as weight'),
                DB::raw('COALESCE(SUM(bc.stweight),0) as stwgt'),
            ])
            ->map(fn ($r) => [
                'itemcode' => (string) ($r->itemcode ?? ''),
                'itemname' => (string) ($r->itemname ?? ''),
                'qty' => (int) ($r->qty ?? 0),
                'weight' => round((float) ($r->weight ?? 0), 3),
                'stwgt' => round((float) ($r->stwgt ?? 0), 3),
            ])
            ->values();

        return response()->json(['success' => true, 'rows' => $rows]);
    }

    public function saveMulti(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);

        $rows = $request->input('rows', []);
        if (is_string($rows)) {
            $rows = json_decode($rows, true) ?: [];
        }
        if (!is_array($rows)) {
            $rows = [];
        }

        $sfromitem = strtoupper(trim((string) $request->input('fromitem', '')));
        $ssmcode = strtoupper(trim((string) $request->input('smcode', '')));
        $sstktypefrom = strtoupper(trim((string) $request->input('fromstktype', '')));
        $sstktypeto = strtoupper(trim((string) $request->input('tostktype', '')));
        $dtdate = $this->normDate((string) $request->input('tdate', '')) ?? date('Y-m-d');
        $sdocno = trim((string) $request->input('docno', ''));
        $sreason = mb_substr(trim((string) $request->input('reason', '')), 0, 30);
        $sfrombclist = strtoupper(trim((string) $request->input('frombclist', 'N'))) === 'Y';
        $dateFrom = $this->normDate((string) $request->input('date_from', ''));
        $dateTo = $this->normDate((string) $request->input('date_to', ''));

        if ($sfromitem === '') {
            return response()->json(['success' => false, 'message' => 'From item required'], 422);
        }
        if (!$this->itemExists($sfromitem)) {
            return response()->json(['success' => false, 'message' => 'From item does not exist'], 422);
        }

        $validRows = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $itemcode = strtoupper(trim((string) ($row['itemcode'] ?? '')));
            $qty = (int) ($row['qty'] ?? 0);
            $weight = (float) ($row['weight'] ?? 0);
            $stwgt = (float) ($row['stwgt'] ?? 0);

            if ($itemcode === '' || $weight == 0.0) {
                continue;
            }
            if (!$this->itemExists($itemcode)) {
                return response()->json(['success' => false, 'message' => "Invalid item: {$itemcode}"], 422);
            }

            $validRows[] = [
                'itemcode' => $itemcode,
                'qty' => $qty,
                'weight' => $weight,
                'stwgt' => $stwgt,
            ];
        }

        if ($validRows === []) {
            return response()->json(['success' => false, 'message' => 'No valid rows to save'], 422);
        }

        foreach ($validRows as $row) {
            if ($row['itemcode'] !== '' && $row['weight'] != 0.0 && $sstktypefrom === '') {
                return response()->json(['success' => false, 'message' => "Check Stock Type ({$sfromitem}). You can't save..."], 422);
            }
            if ($row['itemcode'] !== '' && $row['weight'] != 0.0 && $sstktypeto === '') {
                return response()->json(['success' => false, 'message' => "Check Stock Type ({$row['itemcode']}). You can't save..."], 422);
            }
        }

        $dfromcost = (float) (DB::table('items')->where('code', $sfromitem)->value('cost') ?? 0);
        $gisemi = $this->resolveGisemi($request);
        $ttime = date('H:i:s');

        DB::beginTransaction();
        try {
            $this->touchCounter('BLOCK');

            $slno = $this->nextSerialNo();
            if ($sdocno === '') {
                $sdocno = $this->incrementCounterDisplay('STKADJNO', 'ST/');
            } else {
                $this->incrementCounterRaw('STKADJNO');
            }

            foreach ($validRows as $row) {
                $dtocost = (float) (DB::table('items')->where('code', $row['itemcode'])->value('cost') ?? 0);

                DB::table('itemadj')->insert($this->f('itemadj', [
                    'slno' => $slno,
                    'fromcode' => $sfromitem,
                    'fromqty' => 0,
                    'fromwgt' => $row['weight'],
                    'tocode' => $row['itemcode'],
                    'toqty' => $row['qty'],
                    'towgt' => $row['weight'],
                    'particular' => $sreason,
                    'tdate' => $dtdate,
                    'ttime' => $ttime,
                    'control' => $gisemi,
                    'fromcost' => $dfromcost,
                    'tocost' => $dtocost,
                    'smcode' => $ssmcode,
                    'fromstwgt' => $row['stwgt'],
                    'tostwgt' => $row['stwgt'],
                    'al' => ' ',
                    'fromstktype' => $sstktypefrom,
                    'tostktype' => $sstktypeto,
                    'refno' => $sdocno,
                ]));

                $this->adjustItemStock($sfromitem, 0, -$row['weight'], -$row['stwgt'], $sstktypefrom, $gisemi);
                $this->adjustItemStock(
                    $row['itemcode'],
                    $row['qty'],
                    $row['weight'],
                    $row['stwgt'],
                    $sstktypeto,
                    $gisemi
                );

                if ($sfrombclist && $dateFrom && $dateTo) {
                    DB::table('barcode')
                        ->whereBetween('tdate', [$dateFrom, $dateTo])
                        ->where('status', 'R')
                        ->where('icode', $row['itemcode'])
                        ->update($this->f('barcode', ['status' => 'A']));
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock transfer multi entry saved',
                'docno' => $this->nextCounterDisplay('STKADJNO', 'ST/'),
                'slno' => $slno,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function saveAddLess(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);

        $rows = $request->input('rows', []);
        if (is_string($rows)) {
            $rows = json_decode($rows, true) ?: [];
        }
        if (!is_array($rows)) {
            $rows = [];
        }

        $dtdate = $this->normDate((string) $request->input('tdate', '')) ?? date('Y-m-d');
        $ssmcode = strtoupper(trim((string) $request->input('smcode', '')));
        $gisemi = $this->resolveGisemi($request);
        $ttime = date('H:i:s');
        $userCode = (string) $request->session()->get('user_code', '');

        $validRows = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $code = strtoupper(trim((string) ($row['code'] ?? '')));
            if ($code === '' || !$this->itemExists($code)) {
                if ($code !== '') {
                    return response()->json(['success' => false, 'message' => "Invalid item: {$code}"], 422);
                }
                continue;
            }

            $validRows[] = [
                'code' => $code,
                'addqty' => (int) ($row['addqty'] ?? 0),
                'addwgt' => (float) ($row['addwgt'] ?? 0),
                'addstwgt' => (float) ($row['addstwgt'] ?? 0),
                'addstamt' => (float) ($row['addstamt'] ?? 0),
                'lessqty' => (int) ($row['lessqty'] ?? 0),
                'lesswgt' => (float) ($row['lesswgt'] ?? 0),
                'lessstwgt' => (float) ($row['lessstwgt'] ?? 0),
                'lessstamt' => (float) ($row['lessstamt'] ?? 0),
                'stktype' => strtoupper(trim((string) ($row['stktype'] ?? ''))),
                'reason' => mb_substr(trim((string) ($row['reason'] ?? '')), 0, 30),
                'bcode' => (int) ($row['bcode'] ?? 0),
            ];
        }

        if ($validRows === []) {
            return response()->json(['success' => false, 'message' => 'No valid rows to save'], 422);
        }

        foreach ($validRows as $row) {
            if (($row['addwgt'] != 0.0 || $row['lesswgt'] != 0.0) && $row['stktype'] === '') {
                return response()->json(['success' => false, 'message' => "Check Stock Type ({$row['code']}). You can't save..."], 422);
            }
        }

        DB::beginTransaction();
        try {
            $this->touchCounter('BLOCK');
            $serial = $this->currentSerialNo();

            foreach ($validRows as $idx => $row) {
                if ($row['addqty'] != 0 || $row['addwgt'] != 0.0 || $row['addstwgt'] != 0.0) {
                    $serial++;
                    $dtocost = (float) (DB::table('items')->where('code', $row['code'])->value('cost') ?? 0);

                    DB::table('itemadj')->insert($this->f('itemadj', [
                        'slno' => $serial,
                        'fromcode' => 'AL',
                        'fromqty' => 0,
                        'fromwgt' => 0,
                        'tocode' => $row['code'],
                        'toqty' => $row['addqty'],
                        'towgt' => $row['addwgt'],
                        'particular' => $row['reason'],
                        'tdate' => $dtdate,
                        'ttime' => $ttime,
                        'control' => $gisemi,
                        'fromcost' => 0,
                        'tocost' => $dtocost,
                        'fromstwgt' => 0,
                        'tostwgt' => $row['addstwgt'],
                        'al' => 'A',
                        'smcode' => $ssmcode,
                        'sno' => $idx + 1,
                        'fromstktype' => '',
                        'tostktype' => $row['stktype'],
                        'fromstamt' => $row['lessstamt'],
                        'tostamt' => $row['addstamt'],
                        'ic' => $userCode,
                        'tbcode' => $row['bcode'] > 0 ? (string) $row['bcode'] : '',
                    ]));

                    $this->adjustItemStock($row['code'], $row['addqty'], $row['addwgt'], $row['addstwgt'], $row['stktype'], $gisemi);
                }

                if ($row['lessqty'] != 0 || $row['lesswgt'] != 0.0 || $row['lessstwgt'] != 0.0) {
                    $serial++;
                    $dfromcost = (float) (DB::table('items')->where('code', $row['code'])->value('cost') ?? 0);

                    DB::table('itemadj')->insert($this->f('itemadj', [
                        'slno' => $serial,
                        'fromcode' => $row['code'],
                        'fromqty' => $row['lessqty'],
                        'fromwgt' => $row['lesswgt'],
                        'tocode' => 'AL',
                        'toqty' => 0,
                        'towgt' => 0,
                        'particular' => $row['reason'],
                        'tdate' => $dtdate,
                        'ttime' => $ttime,
                        'control' => $gisemi,
                        'fromcost' => $dfromcost,
                        'tocost' => 0,
                        'fromstwgt' => $row['lessstwgt'],
                        'tostwgt' => 0,
                        'al' => 'A',
                        'smcode' => $ssmcode,
                        'sno' => $idx + 1,
                        'fromstktype' => $row['stktype'],
                        'tostktype' => '',
                        'fromstamt' => $row['lessstamt'],
                        'tostamt' => $row['addstamt'],
                        'ic' => $userCode,
                        'tbcode' => $row['bcode'] > 0 ? (string) $row['bcode'] : '',
                    ]));

                    $this->adjustItemStock($row['code'], -$row['lessqty'], -$row['lesswgt'], -$row['lessstwgt'], $row['stktype'], $gisemi);
                }

                if ($row['bcode'] > 0) {
                    $current = DB::table('barcode')->where('bcode', $row['bcode'])->first(['qty', 'weight']);
                    if ($current) {
                        $newQty = (float) ($current->qty ?? 0) + $row['addqty'] - $row['lessqty'];
                        $newWeight = (float) ($current->weight ?? 0) + $row['addwgt'] - $row['lesswgt'];
                        if (abs($newWeight) < 0.0001) {
                            DB::table('barcode')->where('bcode', $row['bcode'])->update($this->f('barcode', ['stk' => 'N']));
                        } else {
                            DB::table('barcode')->where('bcode', $row['bcode'])->update($this->f('barcode', [
                                'qty' => DB::raw('qty + (' . ($row['addqty'] - $row['lessqty']) . ')'),
                                'weight' => DB::raw('weight + (' . ($row['addwgt'] - $row['lesswgt']) . ')'),
                                'stweight' => DB::raw('stweight + (' . ($row['addstwgt'] - $row['lessstwgt']) . ')'),
                            ]));
                        }
                    }
                }
            }

            $this->setSerialNo($serial);
            DB::commit();

            return response()->json(['success' => true, 'message' => 'Stock updation completed']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    public function stockAdjustmentData(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);

        $gisemi = $this->resolveGisemi($request);
        $rstktype = strtoupper(trim((string) $request->query('stktype', '')));

        $rows = DB::table('items')
            ->where('disabled', '<>', 1)
            ->orderBy('name')
            ->get([
                'name',
                'code',
                'qty',
                'weight',
                'stonewgt',
                'qtyb',
                'weightb',
                'stonewgtb',
                'grpcode',
                'itype',
                'ornament',
            ])
            ->map(function ($row) use ($gisemi, $rstktype) {
                $compQty = $gisemi === 1 ? (int) ($row->qty ?? 0) : (int) ($row->qtyb ?? 0);
                $compWgt = $gisemi === 1 ? (float) ($row->weight ?? 0) : (float) ($row->weightb ?? 0);
                $compSt = $gisemi === 1 ? (float) ($row->stonewgt ?? 0) : (float) ($row->stonewgtb ?? 0);

                if ($rstktype !== '' && $this->hasTable('itemsstk')) {
                    $stk = DB::table('itemsstk')
                        ->where('code', $row->code)
                        ->where('stktype', $rstktype)
                        ->first(['qty', 'qtyb', 'weight', 'weightb', 'stonewgt', 'stonewgtb']);
                    if ($stk) {
                        $compQty = $gisemi === 1 ? (int) ($stk->qty ?? 0) : (int) ($stk->qtyb ?? 0);
                        $compWgt = $gisemi === 1 ? (float) ($stk->weight ?? 0) : (float) ($stk->weightb ?? 0);
                        $compSt = $gisemi === 1 ? (float) ($stk->stonewgt ?? 0) : (float) ($stk->stonewgtb ?? 0);
                    } else {
                        $compQty = 0;
                        $compWgt = 0;
                        $compSt = 0;
                    }
                }

                return [
                    'name' => (string) ($row->name ?? ''),
                    'code' => (string) ($row->code ?? ''),
                    'cqty' => $compQty,
                    'cwgt' => round($compWgt, 3),
                    'cstwgt' => round($compSt, 3),
                    'curqty' => 0,
                    'curwgt' => 0,
                    'curstwgt' => 0,
                    'grpcode' => (string) ($row->grpcode ?? ''),
                    'itype' => (string) ($row->itype ?? ''),
                    'ornament' => (string) ($row->ornament ?? ''),
                ];
            })
            ->values();

        return response()->json(['success' => true, 'rows' => $rows]);
    }

    public function saveStockAdjustment(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);

        $rows = $request->input('rows', []);
        if (is_string($rows)) {
            $rows = json_decode($rows, true) ?: [];
        }
        if (!is_array($rows)) {
            $rows = [];
        }

        $sstktype = strtoupper(trim((string) $request->input('stktype', '')));
        $ssmcode = strtoupper(trim((string) $request->input('smcode', '')));
        $gisemi = $this->resolveGisemi($request);
        $dttoday = date('Y-m-d');
        $ttime = date('H:i:s');
        $userCode = (string) $request->session()->get('user_code', '');

        $validRows = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $code = strtoupper(trim((string) ($row['code'] ?? '')));
            if ($code === '' || !$this->itemExists($code)) {
                continue;
            }
            $diffqty = (int) ($row['curqty'] ?? 0) - (int) ($row['cqty'] ?? 0);
            $diffwgt = (float) ($row['curwgt'] ?? 0) - (float) ($row['cwgt'] ?? 0);
            $diffstwgt = (float) ($row['curstwgt'] ?? 0) - (float) ($row['cstwgt'] ?? 0);
            if ($diffqty == 0 && abs($diffwgt) < 0.0001 && abs($diffstwgt) < 0.0001) {
                continue;
            }
            $validRows[] = ['code' => $code, 'diffqty' => $diffqty, 'diffwgt' => $diffwgt, 'diffstwgt' => $diffstwgt];
        }

        if ($validRows === []) {
            return response()->json(['success' => false, 'message' => 'No stock differences to update'], 422);
        }

        DB::beginTransaction();
        try {
            $this->touchCounter('BLOCK');
            $serial = $this->currentSerialNo();

            foreach ($validRows as $row) {
                $serial++;

                if ($row['diffwgt'] > 0 || $row['diffqty'] > 0 || $row['diffstwgt'] > 0) {
                    $fromcode = 'AL';
                    $tocode = $row['code'];
                    $fromqty = 0;
                    $towqty = max(0, $row['diffqty']);
                    $fromwgt = 0;
                    $towgt = max(0, $row['diffwgt']);
                    $fromstwgt = 0;
                    $tostwgt = max(0, $row['diffstwgt']);
                } else {
                    $fromcode = $row['code'];
                    $tocode = 'AL';
                    $fromqty = abs($row['diffqty']);
                    $towqty = 0;
                    $fromwgt = abs($row['diffwgt']);
                    $towgt = 0;
                    $fromstwgt = abs($row['diffstwgt']);
                    $tostwgt = 0;
                }

                $dfromcost = (float) (DB::table('items')->where('code', $fromcode)->value('cost') ?? 0);
                $dtocost = (float) (DB::table('items')->where('code', $tocode)->value('cost') ?? 0);

                DB::table('itemadj')->insert($this->f('itemadj', [
                    'slno' => $serial,
                    'fromcode' => $fromcode,
                    'fromqty' => $fromqty,
                    'fromwgt' => $fromwgt,
                    'tocode' => $tocode,
                    'toqty' => $towqty,
                    'towgt' => $towgt,
                    'particular' => '',
                    'tdate' => $dttoday,
                    'ttime' => $ttime,
                    'control' => $gisemi,
                    'fromcost' => $dfromcost,
                    'tocost' => $dtocost,
                    'fromstwgt' => $fromstwgt,
                    'tostwgt' => $tostwgt,
                    'fromstktype' => $sstktype,
                    'tostktype' => $sstktype,
                    'smcode' => $ssmcode,
                    'ic' => $userCode,
                ]));

                $this->adjustItemStock($row['code'], $row['diffqty'], $row['diffwgt'], $row['diffstwgt'], $sstktype, $gisemi);
            }

            $this->setSerialNo($serial);
            DB::commit();

            return response()->json(['success' => true, 'message' => 'Stock updation completed']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    /* ============================================================
     * Edit / Cancel Module
     * ============================================================ */

    public function editCancel(Request $request): View
    {
        abort_unless($request->session()->has('user_code'), 401);

        $mode = str_contains((string) $request->query('module', ''), 'cancel') ? 'cancel' : 'edit';
        $title = (string) $request->query('title', $mode === 'cancel' ? 'Adjustment Cancel' : 'Adjustment Edit');

        $stockTypes = $this->hasTable('stktype')
            ? DB::table('stktype')->orderByDesc('def')->orderBy('code')->get(['code', 'name', 'def'])->toArray()
            : [];

        return view('item-adjustment.edit-cancel', [
            'title' => $title,
            'moduleId' => (string) $request->query('module', 'item-stock-adjustment-' . $mode),
            'mode' => $mode,
            'gisemi' => $this->resolveGisemi($request),
            'today' => date('Y-m-d'),
            'stockTypes' => $stockTypes,
        ]);
    }

    /* ============================================================
     * Item Adjustment Report
     * ============================================================ */

    public function report(Request $request): View
    {
        abort_unless($request->session()->has('user_code'), 401);

        $title    = (string) $request->query('title', 'Item Adjustment');
        $moduleId = (string) $request->query('module', 'item-reports-item-adjustment');

        $today    = date('Y-m-d');
        $dateFrom = $request->query('date1', $today);
        $dateTo   = $request->query('date2', $today);
        $fromcode = strtoupper(trim((string) $request->query('fromcode', '')));
        $tocode   = strtoupper(trim((string) $request->query('tocode', '')));
        $anycode  = strtoupper(trim((string) $request->query('anycode', '')));
        $smcode   = strtoupper(trim((string) $request->query('smcode', '')));
        $fromstktype = strtoupper(trim((string) $request->query('fromstktype', '')));
        $tostktype   = strtoupper(trim((string) $request->query('tostktype', '')));
        $trantype = (string) $request->query('trantype', 'All');
        $reason   = (string) $request->query('reason', 'All');
        $noal     = (bool) $request->query('noal', false);
        $showData = $request->has('show');

        $salesmen   = $this->hasTable('sman')
            ? DB::table('sman')->orderBy('name')->get(['code', 'name'])->toArray()
            : [];
        $stockTypes = $this->hasTable('stktype')
            ? DB::table('stktype')->orderByDesc('def')->orderBy('code')->get(['code', 'name'])->toArray()
            : [];

        $rows   = [];
        $totals = [];

        if ($showData && $this->hasTable('itemadj')) {
            $q = DB::table('itemadj as a')
                ->leftJoin('items as fi', DB::raw('UPPER(TRIM(fi.code))'), '=', DB::raw('UPPER(TRIM(a.fromcode))'))
                ->leftJoin('items as ti', DB::raw('UPPER(TRIM(ti.code))'), '=', DB::raw('UPPER(TRIM(a.tocode))'))
                ->whereBetween('a.tdate', [$dateFrom, $dateTo]);

            if ($fromcode !== '') {
                $q->where(DB::raw('UPPER(TRIM(a.fromcode))'), $fromcode);
            }
            if ($tocode !== '') {
                $q->where(DB::raw('UPPER(TRIM(a.tocode))'), $tocode);
            }
            if ($anycode !== '') {
                $q->where(function ($sub) use ($anycode) {
                    $sub->where(DB::raw('UPPER(TRIM(a.fromcode))'), $anycode)
                        ->orWhere(DB::raw('UPPER(TRIM(a.tocode))'), $anycode);
                });
            }
            if ($smcode !== '') {
                $q->where(DB::raw('UPPER(TRIM(a.smcode))'), $smcode);
            }
            if ($fromstktype !== '') {
                $q->where(DB::raw('UPPER(TRIM(a.fromstktype))'), $fromstktype);
            }
            if ($tostktype !== '') {
                $q->where(DB::raw('UPPER(TRIM(a.tostktype))'), $tostktype);
            }
            if ($noal) {
                $q->where(function ($sub) {
                    $sub->whereNull('a.al')->orWhere('a.al', '<>', 'A');
                });
            }
            if ($reason !== 'All' && $reason !== '') {
                $q->where('a.particular', $reason);
            }
            if ($trantype === 'Stock') {
                $q->where('a.ichange', 0)
                  ->where(function ($sub) { $sub->whereNull('a.al')->orWhere('a.al', '<>', 'A'); });
            } elseif ($trantype === 'Item') {
                $q->where('a.ichange', 1)
                  ->where(function ($sub) { $sub->whereNull('a.al')->orWhere('a.al', '<>', 'A'); });
            } elseif ($trantype === 'Add') {
                $q->where('a.al', 'A');
            }

            $rows = $q->orderBy('a.tdate')->orderBy('a.slno')
                ->get([
                    'a.slno', 'a.tdate', 'a.ttime', 'a.bcode',
                    'a.fromcode', 'fi.name as fromname', 'a.fromqty', 'a.fromwgt', 'a.fromstwgt', 'a.fromstktype',
                    'a.tocode', 'ti.name as toname', 'a.toqty', 'a.towgt', 'a.tostwgt', 'a.tostktype',
                    'a.smcode', 'a.particular', 'a.al', 'a.ichange',
                ])->toArray();

            $totals = [
                'fromqty'  => array_sum(array_column($rows, 'fromqty')),
                'fromwgt'  => array_sum(array_column($rows, 'fromwgt')),
                'fromstwgt'=> array_sum(array_column($rows, 'fromstwgt')),
                'toqty'    => array_sum(array_column($rows, 'toqty')),
                'towgt'    => array_sum(array_column($rows, 'towgt')),
                'tostwgt'  => array_sum(array_column($rows, 'tostwgt')),
            ];
        }

        return view('item-adjustment.report', [
            'title'    => $title, 'moduleId' => $moduleId,
            'dateFrom' => $dateFrom, 'dateTo' => $dateTo,
            'salesmen' => $salesmen, 'stockTypes' => $stockTypes,
            'today'    => date('Y-m-d'),
        ]);
    }

    public function reportData(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);
        $rlevel = max(1,(int)$request->session()->get('gilevel',1));
        $dateFrom    = (string) $request->query('date1', date('Y-m-d'));
        $dateTo      = (string) $request->query('date2', date('Y-m-d'));
        $fromcode    = strtoupper(trim((string) $request->query('fromcode', '')));
        $tocode      = strtoupper(trim((string) $request->query('tocode', '')));
        $anycode     = strtoupper(trim((string) $request->query('anycode', '')));
        $smcode      = strtoupper(trim((string) $request->query('smcode', '')));
        $fromstktype = strtoupper(trim((string) $request->query('fromstktype', '')));
        $tostktype   = strtoupper(trim((string) $request->query('tostktype', '')));
        $trantype    = (string) $request->query('trantype', 'All');
        $reason      = (string) $request->query('reason', '');
        $noal        = (string) $request->query('noal', '') === '1';
        if (!$this->hasTable('itemadj'))
            return response()->json(['ok'=>true,'rows'=>[],'totals'=>[]]);
        $q = DB::table('itemadj as a')
            ->leftJoin('items as fi', DB::raw('UPPER(TRIM(fi.code))'), '=', DB::raw('UPPER(TRIM(a.fromcode))'))
            ->leftJoin('items as ti', DB::raw('UPPER(TRIM(ti.code))'), '=', DB::raw('UPPER(TRIM(a.tocode))'))
            ->whereBetween('a.tdate', [$dateFrom, $dateTo]);
        if ($fromcode !== '') $q->where(DB::raw('UPPER(TRIM(a.fromcode))'), $fromcode);
        if ($tocode   !== '') $q->where(DB::raw('UPPER(TRIM(a.tocode))'), $tocode);
        if ($anycode  !== '') $q->where(function($s) use ($anycode) {
            $s->where(DB::raw('UPPER(TRIM(a.fromcode))'),$anycode)->orWhere(DB::raw('UPPER(TRIM(a.tocode))'),$anycode);
        });
        if ($smcode      !== '') $q->where(DB::raw('UPPER(TRIM(a.smcode))'), $smcode);
        if ($fromstktype !== '') $q->where(DB::raw('UPPER(TRIM(a.fromstktype))'), $fromstktype);
        if ($tostktype   !== '') $q->where(DB::raw('UPPER(TRIM(a.tostktype))'), $tostktype);
        if ($noal) $q->where(function($s){ $s->whereNull('a.al')->orWhere('a.al','<>','A'); });
        if ($reason !== '' && $reason !== 'All') $q->where('a.particular', $reason);
        if ($trantype === 'Stock') $q->where('a.ichange',0)->where(function($s){ $s->whereNull('a.al')->orWhere('a.al','<>','A'); });
        elseif ($trantype === 'Item') $q->where('a.ichange',1)->where(function($s){ $s->whereNull('a.al')->orWhere('a.al','<>','A'); });
        elseif ($trantype === 'Add') $q->where('a.al','A');
        $rows = $q->orderBy('a.tdate')->orderBy('a.slno')
            ->get(['a.slno','a.tdate','a.ttime','a.bcode',
                   'a.fromcode','fi.name as fromname','a.fromqty','a.fromwgt','a.fromstwgt','a.fromstktype',
                   'a.tocode','ti.name as toname','a.toqty','a.towgt','a.tostwgt','a.tostktype',
                   'a.smcode','a.particular','a.al','a.ichange'])
            ->map(fn($r)=>(array)$r)->values()->all();
        $totals = [
            'fromqty'=>array_sum(array_column($rows,'fromqty')),
            'fromwgt'=>round(array_sum(array_column($rows,'fromwgt')),3),
            'fromstwgt'=>round(array_sum(array_column($rows,'fromstwgt')),3),
            'toqty'=>array_sum(array_column($rows,'toqty')),
            'towgt'=>round(array_sum(array_column($rows,'towgt')),3),
            'tostwgt'=>round(array_sum(array_column($rows,'tostwgt')),3),
            'count'=>count($rows),
        ];
        return response()->json(['ok'=>true,'rows'=>$rows,'totals'=>$totals]);
    }

    public function listAdjustments(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);

        if (!$this->hasTable('itemadj')) {
            return response()->json(['success' => false, 'message' => 'itemadj table not found']);
        }

        $dateFrom = $this->normDate((string) $request->query('date_from', ''));
        $dateTo = $this->normDate((string) $request->query('date_to', ''));
        $search = strtoupper(trim((string) $request->query('search', '')));

        $query = DB::table('itemadj as a')
            ->leftJoin('items as fi', DB::raw('UPPER(TRIM(fi.code))'), '=', DB::raw('UPPER(TRIM(a.fromcode))'))
            ->leftJoin('items as ti', DB::raw('UPPER(TRIM(ti.code))'), '=', DB::raw('UPPER(TRIM(a.tocode))'));

        if ($dateFrom && $dateTo) {
            $query->whereBetween('a.tdate', [$dateFrom, $dateTo]);
        } elseif ($dateFrom) {
            $query->where('a.tdate', '>=', $dateFrom);
        } elseif ($dateTo) {
            $query->where('a.tdate', '<=', $dateTo);
        }

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like, $search) {
                $q->where('a.fromcode', 'like', $like)
                  ->orWhere('a.tocode', 'like', $like)
                  ->orWhere('a.particular', 'like', $like);

                if (is_numeric($search)) {
                    $q->orWhere('a.slno', '=', (int) $search);
                }
            });
        }

        $hasWgtrcpt = $this->hasTable('wgtrcptpmnt');

        $rows = $query->orderByDesc('a.slno')->limit(500)->get([
            'a.slno', 'a.tdate', 'a.ttime', 'a.fromcode', 'a.fromqty', 'a.fromwgt', 'a.fromcost',
            'a.tocode', 'a.toqty', 'a.towgt', 'a.tocost', 'a.particular', 'a.control', 'a.smcode',
            'a.fromstwgt', 'a.tostwgt', 'a.fromstktype', 'a.tostktype',
            'a.fromstamt', 'a.tostamt', 'a.bcode', 'a.tobcode',
            'fi.name as from_item_name', 'ti.name as to_item_name',
        ]);

        $result = $rows->map(function ($r) use ($hasWgtrcpt) {
            $slno = (int) ($r->slno ?? 0);
            $hasWgt = false;
            if ($hasWgtrcpt && $slno > 0) {
                $hasWgt = DB::table('wgtrcptpmnt')->where('slno', $slno)->exists();
            }

            return [
                'slno' => $slno,
                'tdate' => (string) ($r->tdate ?? ''),
                'ttime' => (string) ($r->ttime ?? ''),
                'fromcode' => trim((string) ($r->fromcode ?? '')),
                'fromqty' => (int) ($r->fromqty ?? 0),
                'fromwgt' => round((float) ($r->fromwgt ?? 0), 3),
                'fromcost' => round((float) ($r->fromcost ?? 0), 2),
                'tocode' => trim((string) ($r->tocode ?? '')),
                'toqty' => (int) ($r->toqty ?? 0),
                'towgt' => round((float) ($r->towgt ?? 0), 3),
                'tocost' => round((float) ($r->tocost ?? 0), 2),
                'particular' => trim((string) ($r->particular ?? '')),
                'control' => (int) ($r->control ?? 1),
                'smcode' => trim((string) ($r->smcode ?? '')),
                'fromstwgt' => round((float) ($r->fromstwgt ?? 0), 3),
                'tostwgt' => round((float) ($r->tostwgt ?? 0), 3),
                'fromstktype' => trim((string) ($r->fromstktype ?? '')),
                'tostktype' => trim((string) ($r->tostktype ?? '')),
                'fromstamt' => round((float) ($r->fromstamt ?? 0), 2),
                'tostamt' => round((float) ($r->tostamt ?? 0), 2),
                'bcode' => (int) ($r->bcode ?? 0),
                'tobcode' => (int) ($r->tobcode ?? 0),
                'from_item_name' => trim((string) ($r->from_item_name ?? '')),
                'to_item_name' => trim((string) ($r->to_item_name ?? '')),
                'has_wgtrcpt' => $hasWgt,
            ];
        })->values();

        return response()->json(['success' => true, 'rows' => $result]);
    }

    public function updateAdjustment(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);

        $slno = (int) $request->input('slno', 0);
        if ($slno <= 0) {
            return response()->json(['success' => false, 'message' => 'Invalid Sl.No'], 422);
        }

        $old = DB::table('itemadj')->where('slno', $slno)->first();
        if (!$old) {
            return response()->json(['success' => false, 'message' => 'Record not found'], 404);
        }

        $newData = [
            'fromcode' => strtoupper(trim((string) $request->input('fromcode', ''))),
            'tocode' => strtoupper(trim((string) $request->input('tocode', ''))),
            'fromqty' => (int) $request->input('fromqty', 0),
            'toqty' => (int) $request->input('toqty', 0),
            'fromwgt' => (float) $request->input('fromwgt', 0),
            'towgt' => (float) $request->input('towgt', 0),
            'fromstwgt' => (float) $request->input('fromstwgt', 0),
            'tostwgt' => (float) $request->input('tostwgt', 0),
            'fromstamt' => (float) $request->input('fromstamt', 0),
            'tostamt' => (float) $request->input('tostamt', 0),
            'fromstktype' => strtoupper(trim((string) $request->input('fromstktype', ''))),
            'tostktype' => strtoupper(trim((string) $request->input('tostktype', ''))),
            'particular' => mb_substr(trim((string) $request->input('reason', '')), 0, 30),
        ];

        if ($newData['fromcode'] === '' || $newData['tocode'] === '') {
            return response()->json(['success' => false, 'message' => 'From/To item code required'], 422);
        }

        $gisemi = (int) ($old->control ?? $this->resolveGisemi($request));

        DB::beginTransaction();
        try {
            // 1) Reverse old stock changes
            $this->reverseStockForRecord($old, $gisemi);

            // 2) Update the record
            DB::table('itemadj')->where('slno', $slno)->update($this->f('itemadj', $newData));

            // 3) Apply new stock changes
            $this->applyStockForData($newData, (int) ($old->bcode ?? 0), (int) ($old->tobcode ?? 0), $gisemi);

            // 4) Log the edit in delpart
            $this->logDelpart(
                'Adjustment #' . $slno . ' edited (' . $newData['fromcode'] . ' to ' . $newData['tocode'] . ')',
                $gisemi
            );

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Adjustment updated successfully']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function cancelAdjustment(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);

        $slno = (int) $request->input('slno', 0);
        if ($slno <= 0) {
            return response()->json(['success' => false, 'message' => 'Invalid Sl.No'], 422);
        }

        $old = DB::table('itemadj')->where('slno', $slno)->first();
        if (!$old) {
            return response()->json(['success' => false, 'message' => 'Record not found'], 404);
        }

        $gisemi = (int) ($old->control ?? $this->resolveGisemi($request));

        DB::beginTransaction();
        try {
            // 1) Reverse barcode changes
            $frombcode = (int) ($old->bcode ?? 0);
            $tobcode = (int) ($old->tobcode ?? 0);

            if ($frombcode > 0) {
                DB::table('barcode')->where('bcode', $frombcode)->update($this->f('barcode', [
                    'weight' => DB::raw('weight + ' . (float) ($old->fromwgt ?? 0)),
                    'stweight' => DB::raw('stweight + ' . (float) ($old->fromstwgt ?? 0)),
                    'stprice' => DB::raw('stprice + ' . (float) ($old->fromstamt ?? 0)),
                ]));
            }

            if ($tobcode > 0) {
                DB::table('barcode')->where('bcode', $tobcode)->update($this->f('barcode', [
                    'weight' => DB::raw('weight - ' . (float) ($old->towgt ?? 0)),
                    'stweight' => DB::raw('stweight - ' . (float) ($old->tostwgt ?? 0)),
                    'stprice' => DB::raw('stprice - ' . (float) ($old->tostamt ?? 0)),
                ]));
            }

            // 2) Reverse item stock changes
            $this->reverseStockForRecord($old, $gisemi);

            // 3) Delete from itemadj
            DB::table('itemadj')->where('slno', $slno)->delete();

            // 4) Delete from wgtrcptpmnt if exists
            if ($this->hasTable('wgtrcptpmnt')) {
                DB::table('wgtrcptpmnt')->where('slno', $slno)->delete();
            }

            // 5) Log cancellation in delpart
            $fromcode = trim((string) ($old->fromcode ?? ''));
            $tocode = trim((string) ($old->tocode ?? ''));
            $this->logDelpart(
                'Adjustment Entry(' . $fromcode . ' to ' . $tocode . ') Canceled',
                $gisemi
            );

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Adjustment #' . $slno . ' cancelled']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    private function reverseStockForRecord(object $old, int $control): void
    {
        $fromcode = strtoupper(trim((string) ($old->fromcode ?? '')));
        $tocode = strtoupper(trim((string) ($old->tocode ?? '')));
        $fromqty = (int) ($old->fromqty ?? 0);
        $toqty = (int) ($old->toqty ?? 0);
        $fromwgt = (float) ($old->fromwgt ?? 0);
        $towgt = (float) ($old->towgt ?? 0);
        $fromstwgt = (float) ($old->fromstwgt ?? 0);
        $tostwgt = (float) ($old->tostwgt ?? 0);
        $fromstktype = strtoupper(trim((string) ($old->fromstktype ?? '')));
        $tostktype = strtoupper(trim((string) ($old->tostktype ?? '')));

        // Reverse FROM: add back what was subtracted
        if ($fromcode !== '' && $fromcode !== 'AL') {
            $this->adjustItemStock($fromcode, $fromqty, $fromwgt, $fromstwgt, $fromstktype, $control);
        }

        // Reverse TO: subtract what was added
        if ($tocode !== '' && $tocode !== 'AL') {
            $this->adjustItemStock($tocode, -$toqty, -$towgt, -$tostwgt, $tostktype, $control);
        }
    }

    private function applyStockForData(array $data, int $frombcode, int $tobcode, int $control): void
    {
        if ($data['fromcode'] !== '' && $data['fromcode'] !== 'AL') {
            $this->adjustItemStock(
                $data['fromcode'],
                -$data['fromqty'],
                -$data['fromwgt'],
                -$data['fromstwgt'],
                $data['fromstktype'],
                $control
            );
        }

        if ($data['tocode'] !== '' && $data['tocode'] !== 'AL') {
            $this->adjustItemStock(
                $data['tocode'],
                $data['toqty'],
                $data['towgt'],
                $data['tostwgt'],
                $data['tostktype'],
                $control
            );
        }
    }

    private function logDelpart(string $message, int $control): void
    {
        if (!$this->hasTable('delpart')) return;

        DB::table('delpart')->insert($this->f('delpart', [
            'tdate' => date('Y-m-d'),
            'part' => mb_substr($message, 0, 60),
            'control' => $control,
            'updtdate' => date('Y-m-d'),
            'updttime' => date('H:i:s'),
        ]));
    }

    private function itemExists(string $code): bool
    {
        return DB::table('items')
            ->whereRaw('UPPER(TRIM(code)) = ?', [$code])
            ->where('disabled', 0)
            ->exists();
    }

    private function weightedAverageCost(string $code, float $inwardWeight, float $inwardCost): float
    {
        $row = DB::table('items')
            ->whereRaw('UPPER(TRIM(code)) = ?', [$code])
            ->first(['weightb', 'cost']);

        $currentStock = (float) ($row->weightb ?? 0);
        $currentCost = (float) ($row->cost ?? 0);

        if ($currentStock <= 0 || $currentCost == 0.0) {
            return $inwardCost > 0 ? $inwardCost : $currentCost;
        }

        if ($inwardWeight <= 0) {
            return $currentCost;
        }

        return ($currentStock * $currentCost + $inwardWeight * $inwardCost) / ($currentStock + $inwardWeight);
    }

    private function adjustItemStock(
        string $code,
        int $qty,
        float $weight,
        float $stonewgt,
        string $stktype,
        int $control,
        ?float $cost = null
    ): void {
        $updates = $control === 1
            ? [
                'qty' => DB::raw('qty + (' . $qty . ')'),
                'weight' => DB::raw('weight + (' . $weight . ')'),
                'qtyb' => DB::raw('qtyb + (' . $qty . ')'),
                'weightb' => DB::raw('weightb + (' . $weight . ')'),
                'stonewgt' => DB::raw('stonewgt + (' . $stonewgt . ')'),
                'stonewgtb' => DB::raw('stonewgtb + (' . $stonewgt . ')'),
            ]
            : [
                'qtyb' => DB::raw('qtyb + (' . $qty . ')'),
                'weightb' => DB::raw('weightb + (' . $weight . ')'),
                'stonewgtb' => DB::raw('stonewgtb + (' . $stonewgt . ')'),
            ];

        if ($cost !== null) {
            $updates['cost'] = $cost;
        }

        DB::table('items')
            ->whereRaw('UPPER(TRIM(code)) = ?', [$code])
            ->update($this->f('items', $updates));

        if ($stktype === '' || !$this->hasTable('itemsstk')) {
            return;
        }

        $exists = DB::table('itemsstk')
            ->where('code', $code)
            ->where('stktype', $stktype)
            ->exists();

        if (!$exists) {
            DB::table('itemsstk')->insert($this->f('itemsstk', [
                'code' => $code,
                'stktype' => $stktype,
                'qty' => 0,
                'weight' => 0,
                'stonewgt' => 0,
                'qtyb' => 0,
                'weightb' => 0,
                'stonewgtb' => 0,
            ]));
        }

        $stkUpdates = $control === 1
            ? [
                'qty' => DB::raw('qty + (' . $qty . ')'),
                'weight' => DB::raw('weight + (' . $weight . ')'),
                'qtyb' => DB::raw('qtyb + (' . $qty . ')'),
                'weightb' => DB::raw('weightb + (' . $weight . ')'),
                'stonewgt' => DB::raw('stonewgt + (' . $stonewgt . ')'),
                'stonewgtb' => DB::raw('stonewgtb + (' . $stonewgt . ')'),
            ]
            : [
                'qtyb' => DB::raw('qtyb + (' . $qty . ')'),
                'weightb' => DB::raw('weightb + (' . $weight . ')'),
                'stonewgtb' => DB::raw('stonewgtb + (' . $stonewgt . ')'),
            ];

        DB::table('itemsstk')
            ->where('code', $code)
            ->where('stktype', $stktype)
            ->update($this->f('itemsstk', $stkUpdates));
    }

    private function touchCounter(string $code): void
    {
        if (!$this->hasTable('generali')) {
            return;
        }

        if (!DB::table('generali')->where('code', $code)->exists()) {
            DB::table('generali')->insert(['code' => $code, 'cvalue' => 0]);
        }

        DB::table('generali')->where('code', $code)->update(['cvalue' => DB::raw('cvalue + 1')]);
    }

    private function nextSerialNo(): int
    {
        if (!$this->hasTable('generali')) {
            throw new \RuntimeException('generali table not found');
        }

        if (!DB::table('generali')->where('code', 'SERIALNO')->exists()) {
            DB::table('generali')->insert(['code' => 'SERIALNO', 'cvalue' => 0]);
        }

        $current = (int) (DB::table('generali')->where('code', 'SERIALNO')->value('cvalue') ?? 0);
        $maxUsed = 0;
        foreach (['salesm', 'salesrm', 'purchasem', 'purchaserm', 'daybook', 'daybookpart', 'orderm', 'smithm', 'refinerym', 'repairm'] as $table) {
            if ($this->hasTable($table) && Schema::hasColumn($table, 'slno')) {
                $maxUsed = max($maxUsed, (int) (DB::table($table)->max('slno') ?? 0));
            }
        }
        $next = max($current, $maxUsed) + 1;
        DB::table('generali')->where('code', 'SERIALNO')->update(['cvalue' => $next]);

        return $next;
    }

    private function currentSerialNo(): int
    {
        if (!DB::table('generali')->where('code', 'SERIALNO')->exists()) {
            DB::table('generali')->insert(['code' => 'SERIALNO', 'cvalue' => 0]);
        }
        return (int) (DB::table('generali')->where('code', 'SERIALNO')->value('cvalue') ?? 0);
    }

    private function setSerialNo(int $value): void
    {
        if (!DB::table('generali')->where('code', 'SERIALNO')->exists()) {
            DB::table('generali')->insert(['code' => 'SERIALNO', 'cvalue' => $value]);
            return;
        }
        DB::table('generali')->where('code', 'SERIALNO')->update(['cvalue' => $value]);
    }

    private function nextCounterDisplay(string $code, string $prefix): string
    {
        $current = (int) (DB::table('generali')->where('code', $code)->value('cvalue') ?? 0);
        return $prefix . str_pad((string) ($current + 1), 5, '0', STR_PAD_LEFT);
    }

    private function incrementCounterDisplay(string $code, string $prefix): string
    {
        $this->incrementCounterRaw($code);
        $current = (int) (DB::table('generali')->where('code', $code)->value('cvalue') ?? 0);
        return $prefix . str_pad((string) $current, 5, '0', STR_PAD_LEFT);
    }

    private function incrementCounterRaw(string $code): void
    {
        if (!DB::table('generali')->where('code', $code)->exists()) {
            DB::table('generali')->insert(['code' => $code, 'cvalue' => 0]);
        }
        DB::table('generali')->where('code', $code)->update(['cvalue' => DB::raw('cvalue + 1')]);
    }

    private function resolveGisemi(Request $request): int
    {
        return 1;
    }

    private function normDate(string $d): ?string
    {
        $d = trim($d);
        if ($d === '') {
            return null;
        }

        $t = strtotime($d);
        if ($t === false || (int) date('Y', $t) < 1990) {
            return null;
        }

        return date('Y-m-d', $t);
    }

    private function buildAddLessReportTotals(array $rows): array
    {
        $totals = [
            'count' => count($rows),
            'addqty' => 0,
            'addwgt' => 0.0,
            'addstwgt' => 0.0,
            'lessqty' => 0,
            'lesswgt' => 0.0,
            'lessstwgt' => 0.0,
        ];

        foreach ($rows as $row) {
            $totals['addqty'] += (int) ($row['addqty'] ?? 0);
            $totals['addwgt'] += (float) ($row['addwgt'] ?? 0);
            $totals['addstwgt'] += (float) ($row['addstwgt'] ?? 0);
            $totals['lessqty'] += (int) ($row['lessqty'] ?? 0);
            $totals['lesswgt'] += (float) ($row['lesswgt'] ?? 0);
            $totals['lessstwgt'] += (float) ($row['lessstwgt'] ?? 0);
        }

        $totals['addwgt'] = round($totals['addwgt'], 3);
        $totals['addstwgt'] = round($totals['addstwgt'], 3);
        $totals['lesswgt'] = round($totals['lesswgt'], 3);
        $totals['lessstwgt'] = round($totals['lessstwgt'], 3);

        return $totals;
    }

    private function f(string $table, array $data): array
    {
        if (!$this->hasTable($table)) {
            return $data;
        }

        $cols = array_flip(array_map('strtolower', $this->columnList($table)));

        return array_filter(
            $data,
            static fn ($v, $k) => isset($cols[strtolower((string) $k)]),
            ARRAY_FILTER_USE_BOTH
        );
    }
}
