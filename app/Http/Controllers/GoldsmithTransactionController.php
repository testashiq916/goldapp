<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\LogsDelpartAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class GoldsmithTransactionController extends Controller
{
    use LogsDelpartAudit;

    public function picker(Request $request, string $action = 'edit')
    {
        abort_unless($request->session()->has('user_code'), 401);

        $action = strtolower($action);
        if (!in_array($action, ['edit', 'cancel', 'reprint'], true)) {
            $action = 'edit';
        }

        $title = (string) $request->query('title', 'Transaction');
        $module = (string) $request->query('module', 'goldsmith-bill-edit');
        $type = strtoupper((string) $request->query('type', 'G'));
        $eb = strtoupper((string) $request->query('eb', 'B'));

        return view('purchase-bill.doc-picker', [
            'title' => $title,
            'actionMode' => $action,
            'docType' => 'goldsmith-transaction',
            'searchUrl' => url('/api/goldsmith-transactions/picker-search') . '?' . http_build_query([
                'module' => $module,
                'type' => $type,
                'eb' => $eb,
            ]),
            'resolveUrl' => url('/api/goldsmith-transactions/picker-resolve') . '?' . http_build_query([
                'module' => $module,
                'title' => $title,
                'type' => $type,
                'eb' => $eb,
            ]),
            'targetBaseUrl' => url('/goldsmith-transactions'),
            'showViewOption' => $action === 'reprint',
        ]);
    }

    public function index(Request $request, ?string $mode = null): View
    {
        abort_unless($request->session()->has('user_code'), 401);
        $type = strtoupper((string) $request->query('type', 'G'));
        $eb = strtoupper((string) $request->query('eb', 'B'));
        $mode = $mode ?: (string) $request->query('mode', 'bill');
        $module = (string) $request->query('module', 'goldsmith-bill');
        $title = (string) $request->query('title', 'Goldsmith Transactions');

        if ($mode === 'new-work-note') {
            return view('goldsmith-transactions.new-work-note', [
                'moduleId' => $module,
                'mode' => $mode,
                'title' => $title,
                'type' => in_array($type, ['G', 'J', 'C', 'R'], true) ? $type : 'G',
            ]);
        }

        if ($mode === 'interest-posting') {
            return view('goldsmith-transactions.interest-posting', [
                'moduleId' => $module,
                'mode' => $mode,
                'title' => $title,
                'type' => in_array($type, ['G', 'J', 'C', 'R'], true) ? $type : 'G',
                'salesmen' => $this->hasTable('sman')
                    ? DB::table('sman')->orderBy('name')->get(['code', 'name'])->toArray()
                    : [],
            ]);
        }

        $salesmen = $this->hasTable('sman')
            ? DB::table('sman')->orderBy('name')->get(['code', 'name'])->toArray()
            : [];

        $cashBanks = [];
        if ($this->hasTable('accountm')) {
            $cashBanks = DB::table('accountm')
                ->whereIn('actype2', ['H', 'B'])
                ->orderByRaw("CASE WHEN actype2='H' THEN 0 ELSE 1 END, accode")
                ->get(['accode as code', 'name', 'actype2'])
                ->map(fn ($r) => ['code' => trim((string) $r->code), 'name' => trim((string) ($r->name ?? '')), 'type' => trim((string) ($r->actype2 ?? ''))])
                ->toArray();
        }
        if ($cashBanks === []) {
            $cashBanks[] = ['code' => 'CASH', 'name' => 'CASH', 'type' => 'H'];
        }

        $goldRate = $this->hasTable('generald')
            ? (float) (DB::table('generald')->where('code', 'THRATE')->value('cvalue') ?? 0)
            : 0.0;

        // Smith software settings from generals table
        $smithCfg = [];
        if ($this->hasTable('generals')) {
            $keys = ['SmithAddWastageToMCAmt', 'SmithMcOnNetWgt', 'SmithIssueWastage',
                     'SmithDefStkType', 'SmithDefStkType2', 'SmithMcOnTruncateWgt',
                     'TouchToSmithEntry', 'CreateBCodeInSmithEntry'];
            $smithCfg = DB::table('generals')->whereIn('code', $keys)
                ->get(['code', 'cvalue'])->pluck('cvalue', 'code')->toArray();
        }
        // Global smith wastage % from generald
        $smithWastagePerc = $this->hasTable('generald')
            ? (float) (DB::table('generald')->where('code', 'GSWASTAGE')->value('cvalue') ?? 0)
            : 0.0;

        return view('goldsmith-transactions.index', [
            'moduleId' => $module,
            'title' => $title,
            'type' => in_array($type, ['G', 'J', 'C', 'R'], true) ? $type : 'G',
            'mode' => $mode,
            'billNo' => $this->nextDocNo($type, $eb, false, $module),
            'salesmen' => $salesmen,
            'cashBanks' => $cashBanks,
            'goldRate' => $goldRate,
            'smithCfg' => $smithCfg,
            'smithWastagePerc' => $smithWastagePerc,
        ]);
    }

    public function pickerSearch(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);
        if (!$this->hasTable('smithm')) {
            return response()->json(['ok' => true, 'rows' => []]);
        }

        $q = trim((string) $request->query('q', ''));
        $tdate = $this->normDate((string) $request->query('tdate', ''));
        $type = strtoupper((string) $request->query('type', 'G'));
        $module = (string) $request->query('module', '');

        $query = DB::table('smithm as sm')
            ->leftJoin('clients as c', 'c.code', '=', 'sm.smithcode')
            ->selectRaw('sm.slno, sm.docno, sm.tdate, COALESCE(c.name, sm.smithcode) as party_name');

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('sm.docno', 'like', $q . '%')
                  ->orWhere('sm.smithcode', 'like', '%' . $q . '%')
                  ->orWhere('c.name', 'like', '%' . $q . '%');
            });
        }
        if ($tdate) {
            $query->whereDate('sm.tdate', $tdate);
        }

        $prefixes = $this->pickerDocPrefixes($type, $module);
        if ($prefixes !== []) {
            $query->where(function ($w) use ($prefixes) {
                foreach ($prefixes as $prefix) {
                    $w->orWhere('sm.docno', 'like', $prefix . '%');
                }
            });
        }

        $rows = $query
            ->orderByDesc('sm.tdate')
            ->orderByDesc('sm.slno')
            ->get()
            ->map(fn ($r) => [
                'slno' => (int) ($r->slno ?? 0),
                'doc_no' => trim((string) ($r->docno ?? '')),
                'tdate' => !empty($r->tdate) ? date('d/m/Y', strtotime((string) $r->tdate)) : '',
                'party_name' => trim((string) ($r->party_name ?? '')),
            ])->values();

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    public function pickerResolve(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);
        if (!$this->hasTable('smithm')) {
            return response()->json(['ok' => false, 'message' => 'Transaction table not found.'], 404);
        }

        $docNo = strtoupper(trim((string) $request->input('doc_no', '')));
        $tdate = $this->normDate((string) $request->input('tdate', ''));
        $action = strtolower(trim((string) $request->input('action', 'edit')));
        $module = (string) $request->query('module', 'goldsmith-bill-edit');
        $title = (string) $request->query('title', 'Transaction');
        $type = strtoupper((string) $request->query('type', 'G'));
        $eb = strtoupper((string) $request->query('eb', 'B'));

        if ($docNo === '') {
            return response()->json(['ok' => false, 'message' => 'Doc no is required.'], 422);
        }

        $query = DB::table('smithm')->whereRaw('UPPER(TRIM(docno)) = ?', [$docNo]);
        if ($tdate) {
            $query->whereDate('tdate', $tdate);
        }
        $row = $query->orderByDesc('slno')->first(['slno', 'docno']);

        if (!$row) {
            return response()->json(['ok' => false, 'message' => 'This bill number does not exist...'], 404);
        }

        $targetUrl = $action === 'reprint'
            ? url('/goldsmith-transactions-print?' . http_build_query([
                'slno' => (int) ($row->slno ?? 0),
                'type' => $type,
                'module' => $module,
            ]))
            : url('/goldsmith-transactions?' . http_build_query([
                'module' => $module,
                'title' => $title,
                'type' => $type,
                'mode' => $action,
                'eb' => $eb,
                'slno' => (int) ($row->slno ?? 0),
            ]));

        return response()->json([
            'ok' => true,
            'doc_no' => trim((string) ($row->docno ?? '')),
            'url' => $targetUrl,
        ]);
    }

    public function nextNumber(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);
        $type = strtoupper((string) $request->query('type', 'G'));
        $eb = strtoupper((string) $request->query('eb', 'B'));
        $module = (string) $request->query('module', 'goldsmith-bill');
        $isReceipt = $request->boolean('receipt');
        return response()->json(['success' => true, 'billNo' => $this->nextDocNo($type, $eb, false, $module, $isReceipt)]);
    }

    public function get(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);
        $slno = (int) $request->query('slno', 0);
        $type = strtoupper((string) $request->query('type', 'G'));
        $module = (string) $request->query('module', 'goldsmith-bill');
        $gilevel = max(1, (int) $request->session()->get('gilevel', 1));
        if ($slno <= 0) return response()->json(['success' => false, 'message' => 'slno required'], 422);

        $masterQuery = DB::table('smithm as sm')
            ->leftJoin('clients as c', 'c.code', '=', 'sm.smithcode')
            ->where('sm.slno', $slno)
            ->where('sm.control', '<=', $gilevel)
            ->selectRaw('sm.*, c.name, c.mobile');
        if ($this->hasTable('sman')) {
            $masterQuery->leftJoin('sman as s', 's.code', '=', 'sm.smcode')
                ->addSelect(DB::raw('s.name as sman_name'));
        }
        $this->applyTransactionScope($masterQuery, 'sm.docno', 'c.ctype', 'sm.control', $type, $module, $gilevel);
        $master = $masterQuery->first();
        if (!$master) return response()->json(['success' => false, 'message' => 'Transaction not found'], 404);
        $master->cbcode = $this->daybookCashBankCode($slno, trim((string) ($master->smithcode ?? '')));

        $details = DB::table('smithd')->where('slno', $slno)->orderBy('sno')->get()->map(function ($r) {
            $bcode = (int) ($r->bcode ?? 0);
            $bcstk = 'Y';
            if ($bcode > 0 && $this->hasTable('barcode')) {
                $bcstk = (string) (DB::table('barcode')->where('bcode', $bcode)->value('stk') ?: 'Y');
            }
            return [
                'itemcode' => (string) ($r->code ?? ''),
                'itemname' => (string) ($r->name ?? ''),
                'qty' => (float) ($r->qty ?? 0),
                'weight' => (float) ($r->weight ?? 0),
                'stonewgt' => (float) ($r->stonewgt ?? 0),
                'mcharge' => (float) ($r->mcharge ?? 0),
                'wastage' => (float) ($r->wastage ?? 0),
                'givrec' => (string) ($r->givrec ?? ''),
                'stoneprice' => (float) ($r->stoneprice ?? 0),
                'wgtamt' => (float) ($r->wgtamt ?? 0),
                'touch' => (float) ($r->touch ?? 0),
                'ordno' => (string) ($r->ordno ?? ''),
                'orditem' => (string) ($r->orditem ?? ''),
                'stktype' => (string) ($r->stktype ?? ''),
                'touchnote' => (string) ($r->touchnote ?? ''),
                'bcode' => $bcode,
                'smithmc' => (float) ($r->smithmc ?? 0),
                'netwgt' => (float) ($r->netwgt ?? 0),
                'stktouch' => (float) ($r->stktouch ?? 100),
                'hmc' => (float) ($r->hmc ?? 0),
                'mud' => (float) ($r->mud ?? 0),
                'tp' => (float) ($r->tp ?? 0),
                'sva' => (float) ($r->sva ?? 0),
                'sstprice' => (float) ($r->sstprice ?? 0),
                'model' => (string) ($r->model ?? ''),
                'remark' => (string) ($r->remark ?? ''),
                'bcstk' => $bcstk,
            ];
        });

        return response()->json(['success' => true, 'master' => $master, 'details' => $details, 'eb' => ((int) ($master->control ?? 1) === 1 ? 'B' : 'E')]);
    }

    public function printView(Request $request): View|\Symfony\Component\HttpFoundation\Response
    {
        abort_unless($request->session()->has('user_code'), 401);

        $slno = (int) $request->query('slno', 0);
        if ($slno <= 0) {
            return response('<p style="padding:20px;font-family:Arial,sans-serif">No transaction selected.</p>', 400);
        }

        $type = strtoupper((string) $request->query('type', 'G'));
        $module = (string) $request->query('module', 'goldsmith-bill');
        $gilevel = max(1, (int) $request->session()->get('gilevel', 1));

        $masterQuery = DB::table('smithm as sm')
            ->leftJoin('clients as c', 'c.code', '=', 'sm.smithcode')
            ->where('sm.slno', $slno)
            ->where('sm.control', '<=', $gilevel)
            ->selectRaw('sm.*, c.name as party_name, c.addr1, c.addr2, c.addr3, c.city, c.mobile, c.tin, c.ctype');
        if ($this->hasTable('sman')) {
            $masterQuery->leftJoin('sman as s', 's.code', '=', 'sm.smcode')
                ->addSelect(DB::raw('s.name as sman_name'));
        }
        if ($this->hasTable('state')) {
            $masterQuery->leftJoin('state as st', 'st.code', '=', 'sm.statecode')
                ->addSelect(DB::raw('st.name as state_name'));
        }
        $this->applyTransactionScope($masterQuery, 'sm.docno', 'c.ctype', 'sm.control', $type, $module, $gilevel);
        $master = $masterQuery->first();

        if (!$master) {
            return response('<p style="padding:20px;font-family:Arial,sans-serif">Transaction not found.</p>', 404);
        }

        $rows = DB::table('smithd as d')
            ->leftJoin('items as i', 'i.code', '=', 'd.code')
            ->where('d.slno', $slno)
            ->orderBy('d.sno')
            ->get([
                'd.sno', 'd.code', 'd.name', 'd.qty', 'd.weight', 'd.stonewgt', 'd.givrec', 'd.mcharge',
                'd.stoneprice', 'd.touch', 'd.netwgt', 'd.hmc', 'd.model', 'd.remark', 'i.name as item_name', 'i.vatcode as hsn',
            ])
            ->map(function ($r) {
                $name = trim((string) ($r->name ?? ''));
                if ($name === '') {
                    $name = trim((string) ($r->item_name ?? ''));
                }

                return [
                    'sno' => (int) ($r->sno ?? 0),
                    'item' => $name,
                    'code' => trim((string) ($r->code ?? '')),
                    'hsn' => trim((string) ($r->hsn ?? '')),
                    'qty' => (float) ($r->qty ?? 0),
                    'issue_wgt' => strtoupper(trim((string) ($r->givrec ?? ''))) === 'G' ? (float) ($r->weight ?? 0) : 0.0,
                    'recv_wgt' => strtoupper(trim((string) ($r->givrec ?? ''))) === 'R' ? (float) ($r->weight ?? 0) : 0.0,
                    'stone_wgt' => (float) ($r->stonewgt ?? 0),
                    'net_wgt' => max(0.0, (float) ($r->weight ?? 0) - (float) ($r->stonewgt ?? 0)),
                    'touch' => (float) ($r->touch ?? 0),
                    'mc' => (float) ($r->mcharge ?? 0),
                    'stone_price' => (float) ($r->stoneprice ?? 0),
                    'hmc' => (float) ($r->hmc ?? 0),
                    'issue_net' => strtoupper(trim((string) ($r->givrec ?? ''))) === 'G' ? (float) ($r->netwgt ?? 0) : 0.0,
                    'recv_net' => strtoupper(trim((string) ($r->givrec ?? ''))) === 'R' ? (float) ($r->netwgt ?? 0) : 0.0,
                    'model' => trim((string) ($r->model ?? '')),
                    'remark' => trim((string) ($r->remark ?? '')),
                ];
            })
            ->values();

        $issuedWgt = (float) $rows->sum('issue_net');
        $receivedWgt = (float) $rows->sum('recv_net');
        $closingWgt = (float) ($master->opwgt ?? 0) - $issuedWgt + $receivedWgt;
        $grossAmount = (float) $rows->sum(fn ($row) => (float) ($row['mc'] ?? 0) + (float) ($row['stone_price'] ?? 0) + (float) ($row['hmc'] ?? 0));

        $dbAmt = 0.0;
        $crAmt = 0.0;
        $closingAmt = (float) ($master->opamt ?? 0);
        if ($this->hasTable('daybook')) {
            $dbAmt = (float) (DB::table('daybook')->where('slno', $slno)->where('accode', $master->smithcode)->where('amount', '<', 0)->sum(DB::raw('ABS(amount)')) ?? 0);
            $crAmt = (float) (DB::table('daybook')->where('slno', $slno)->where('accode', $master->smithcode)->where('amount', '>', 0)->sum('amount') ?? 0);
            $closingAmt += (float) (DB::table('daybook')->where('slno', $slno)->where('accode', $master->smithcode)->sum('amount') ?? 0);
        }

        $company = [];
        if ($this->hasTable('generals')) {
            $company = DB::table('generals')
                ->whereIn('code', ['SHOPNM', 'SHOPADDR', 'SHOPPHONE', 'Mobile', 'GSTIN', 'GSTNO', 'KGST'])
                ->get(['code', 'cvalue'])
                ->mapWithKeys(fn ($r) => [trim((string) $r->code) => trim((string) ($r->cvalue ?? ''))])
                ->toArray();
        }

        $addressParts = array_filter([
            trim((string) ($master->addr1 ?? '')),
            trim((string) ($master->addr2 ?? '')),
            trim((string) ($master->addr3 ?? '')),
            trim((string) ($master->city ?? '')),
        ], fn ($v) => $v !== '');

        $docTitle = match (strtoupper(trim((string) ($master->ctype ?? $type)))) {
            'J' => 'Jewellery Transaction',
            'C' => 'Party Weight Deposit Transaction',
            'R' => 'Remake Transaction',
            default => 'Goldsmith Transaction',
        };

        $taxAmt = (float) ($master->taxamt ?? 0);
        $taxPerc = (float) ($master->taxperc ?? 0);
        $isInterstate = strtoupper(trim((string) ($master->interstate ?? 'N'))) === 'Y';
        $igstAmount = $isInterstate ? $taxAmt : 0.0;
        $cgstAmount = $isInterstate ? 0.0 : round($taxAmt / 2, 2);
        $sgstAmount = $isInterstate ? 0.0 : round($taxAmt / 2, 2);
        $igstLabel = 'IGST (' . number_format($taxPerc, 2) . '%)';
        $cgstLabel = 'CGST (' . number_format($taxPerc / 2, 2) . '%)';
        $sgstLabel = 'SGST (' . number_format($taxPerc / 2, 2) . '%)';
        $finalAmount = $grossAmount
            + (float) ($master->acidcharge ?? 0)
            + (float) ($master->tcsamt ?? 0)
            + $taxAmt
            - (float) ($master->discount ?? 0)
            - (float) ($master->tdsamt ?? 0);

        return view('goldsmith-transactions.print', [
            'master' => $master,
            'rows' => $rows,
            'docTitle' => $docTitle,
            'company' => $company,
            'address' => implode(', ', $addressParts),
            'issuedWgt' => $issuedWgt,
            'receivedWgt' => $receivedWgt,
            'closingWgt' => $closingWgt,
            'dbAmt' => $dbAmt,
            'crAmt' => $crAmt,
            'closingAmt' => $closingAmt,
            'grossAmount' => $grossAmount,
            'igstAmount' => $igstAmount,
            'cgstAmount' => $cgstAmount,
            'sgstAmount' => $sgstAmount,
            'igstLabel' => $igstLabel,
            'cgstLabel' => $cgstLabel,
            'sgstLabel' => $sgstLabel,
            'finalAmount' => $finalAmount,
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);
        $in = $request->all();
        $rawRows = $in['rows'] ?? [];
        $rows = $rawRows;
        if (is_string($rows)) $rows = json_decode($rows, true) ?: [];
        if (!is_array($rows)) $rows = [];

        Log::info('goldsmith_save_buffer_received', $this->goldsmithSaveLogContext($request, $in, $rows));

        try {
            $rows = $this->normalizeRows($rows);
        } catch (\Throwable $e) {
            Log::warning('goldsmith_save_buffer_invalid', $this->goldsmithSaveLogContext($request, $in, $rows, [
                'error' => $e->getMessage(),
            ]));

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        if (!$rows) {
            Log::warning('goldsmith_save_buffer_rejected', $this->goldsmithSaveLogContext($request, $in, $rows, [
                'reason' => 'No valid rows to save',
                'raw_rows_type' => get_debug_type($rawRows),
            ]));

            return response()->json(['success' => false, 'message' => 'No valid rows to save'], 422);
        }

        $saded = strtoupper((string) ($in['saded'] ?? 'A'));
        $type = strtoupper((string) ($in['istype'] ?? 'G'));
        $eb = strtoupper((string) ($in['eb'] ?? 'B'));
        $module = (string) ($in['module'] ?? 'goldsmith-bill');
        $icontrol = $eb === 'B' ? 1 : 2;
        $slno = (int) ($in['slno'] ?? 0);
        $docno = trim((string) ($in['docno'] ?? ''));
        $smithCode = trim((string) ($in['smithcode'] ?? ''));
        $smithName = trim((string) ($in['smithname'] ?? ''));
        if ($smithCode === '') {
            Log::warning('goldsmith_save_buffer_rejected', $this->goldsmithSaveLogContext($request, $in, $rows, [
                'reason' => 'Enter goldsmith code',
            ]));

            return response()->json(['success' => false, 'message' => 'Enter goldsmith code'], 422);
        }

        $createbc = strtoupper(trim((string) ($in['createbc'] ?? 'N'))) === 'Y';

        $tot = $this->computeTotals($rows);
        $hasTransaction =
            $tot['itqty'] !== 0 ||
            abs((float) $tot['dtwgtissued']) > 0.0001 ||
            abs((float) $tot['dtwgtrcvd']) > 0.0001 ||
            abs((float) $tot['dtmcharge']) > 0.0001 ||
            abs((float) $tot['dttcamt']) > 0.0001 ||
            abs((float) $tot['dthmc']) > 0.0001;
        if (!$hasTransaction) {
            Log::warning('goldsmith_save_buffer_rejected', $this->goldsmithSaveLogContext($request, $in, $rows, [
                'reason' => 'Incomplete transaction. Nothing to save.',
                'totals' => $tot,
            ]));

            return response()->json(['success' => false, 'message' => 'Incomplete transaction. Nothing to save.'], 422);
        }

        $paid = (float) ($in['paid'] ?? 0);
        $pm = strtoupper((string) ($in['pmntrcpt'] ?? 'PAID'));
        $signedPaid = $pm === 'PAID' ? $paid : -$paid;
        $tdate = (string) ($in['tdate'] ?? date('Y-m-d'));
        $ttime = date('H:i:s');

        DB::beginTransaction();
        try {
            if ($saded === 'E' && $slno > 0) {
                $oldControl = (int) (DB::table('smithm')->where('slno', $slno)->value('control') ?? $icontrol);
                $this->reverseStockForSlno($slno, $oldControl === 1 ? 'B' : 'E');
                DB::table('smithd')->where('slno', $slno)->delete();
                DB::table('smithm')->where('slno', $slno)->delete();
                DB::table('daybook')->where('slno', $slno)->delete();
                DB::table('daybookpart')->where('slno', $slno)->delete();
                if ($this->hasTable('itemadj')) DB::table('itemadj')->where('slno', $slno)->delete();
            } else {
                $slno = $this->nextSerialNo();
                $docno = $this->nextDocNo($type, $eb, true, $module, $this->usesReceiptDocNo($type, $rows));
            }

            DB::table('smithm')->insert($this->f('smithm', [
                'slno' => $slno, 'docno' => $docno, 'tdate' => $tdate, 'ttime' => $ttime, 'smithcode' => $smithCode,
                'tmcharge' => $tot['dtmcharge'], 'pamt' => $signedPaid, 'status' => 1, 'control' => $icontrol,
                'rate' => (float) ($in['rate'] ?? 0), 'rmno' => trim((string) ($in['rmno'] ?? '')), 'smcode' => trim((string) ($in['smcode'] ?? '')),
                'person' => trim((string) ($in['person'] ?? '')), 'ic' => 1, 'opwgt' => (float) ($in['opwgt'] ?? 0), 'opamt' => (float) ($in['opamt'] ?? 0),
                'tdsperc' => (float) ($in['tdsperc'] ?? 0), 'tdsamt' => (float) ($in['tdsamt'] ?? 0), 'acidcharge' => (float) ($in['acidcharge'] ?? 0),
                'discount' => (float) ($in['discount'] ?? 0), 'lotno' => trim((string) ($in['lotno'] ?? '')), 'taxperc' => (float) ($in['taxperc'] ?? 0),
                'taxamt' => (float) ($in['taxamt'] ?? 0), 'interstate' => strtoupper((string) ($in['interstate'] ?? 'N')) === 'Y' ? 'Y' : 'N',
                'taxreverse' => strtoupper((string) ($in['taxreverse'] ?? 'N')) === 'Y' ? 'Y' : 'N', 'statecode' => trim((string) ($in['statecode'] ?? '')),
                'placeos' => trim((string) ($in['placeos'] ?? '')), 'refno' => trim((string) ($in['refno'] ?? '')),
                'doctype' => $this->mapDoctype((string) ($in['doctype'] ?? 'Normal')), 'duedate' => $this->normDate((string) ($in['duedate'] ?? '')),
                'transportmode' => trim((string) ($in['transportmode'] ?? '')), 'vehno' => trim((string) ($in['vehno'] ?? '')),
                'purpose' => trim((string) ($in['purpose'] ?? '')), 'note' => trim((string) ($in['note'] ?? '')),
                'tcsperc' => (float) ($in['tcsperc'] ?? 0), 'tcsamt' => (float) ($in['tcsamt'] ?? 0),
            ]));

            $sno = 1;
            foreach ($rows as $r) {
                if (!in_array($r['itemcode'], ['TC', 'INT'], true)) $this->applyItemStock($r['itemcode'], $r['stktype'], $r['givrec'], $r['weight'], $r['stonewgt'], $r['qty'], $eb);
                $touchWgt = 0.0;
                if ($r['touch'] > 0) {
                    $base = max(0, $r['weight'] - $r['stonewgt']);
                    $touchWgt = ($base * $r['touch']) / 100.0;
                }
                $itemName = (string) (DB::table('items')->where('code', $r['itemcode'])->value('name') ?? '');
                $storeName = trim($itemName) === trim($r['itemname']) ? '' : $r['itemname'];

                DB::table('smithd')->insert($this->f('smithd', [
                    'slno' => $slno, 'sno' => $sno++, 'code' => $r['itemcode'], 'name' => $storeName, 'qty' => $r['qty'],
                    'weight' => $r['weight'], 'stonewgt' => $r['stonewgt'], 'stoneprice' => $r['stoneprice'], 'mcharge' => $r['mcharge'],
                    'wastage' => $r['wastage'], 'cost' => $r['cost'], 'givrec' => $r['givrec'], 'wgtamt' => $r['wgtamt'], 'touch' => $r['touch'],
                    'touchwgt' => $touchWgt, 'ordno' => $r['ordno'], 'orditem' => $r['orditem'], 'stktype' => $r['stktype'],
                    'touchnote' => $r['touchnote'], 'bcode' => $r['bcode'], 'smithmc' => $r['smithmc'], 'netwgt' => $r['netwgt'],
                    'stktouch' => $r['stktouch'], 'hmc' => $r['hmc'], 'mud' => $r['mud'], 'tp' => $r['tp'], 'sva' => $r['sva'],
                    'sstprice' => $r['sstprice'], 'model' => $r['model'], 'remark' => $r['remark'],
                ]));

                if ($r['bcode'] > 0 && $this->hasTable('barcode')) {
                    DB::table('barcode')->where('bcode', $r['bcode'])->update($this->f('barcode', [
                        'stk' => $r['givrec'] === 'G' ? 'N' : $r['bcstk'],
                        'islno' => $r['givrec'] === 'G' ? $slno : 0,
                    ]));
                } elseif ($createbc && $r['bcode'] == 0 && $this->hasTable('barcode') && !in_array($r['itemcode'], ['TC', 'INT'], true)) {
                    $newBcode = $this->nextBarcodeNumber();
                    DB::table('barcode')->insert($this->f('barcode', [
                        'bcode' => $newBcode,
                        'icode' => $r['itemcode'],
                        'qty' => $r['qty'],
                        'weight' => $r['weight'],
                        'stweight' => $r['stonewgt'],
                        'stprice' => $r['stoneprice'],
                        'wastage' => $r['wastage'],
                        'mcrate' => $r['smithmc'],
                        'mc' => abs($r['mcharge']),
                        'smithmcrate' => $r['smithmc'],
                        'rate' => (float) ($in['rate'] ?? 0),
                        'stktouch' => $r['stktouch'],
                        'smithcode' => $smithCode,
                        'tdate' => $tdate,
                        'docno' => $docno,
                        'stk' => $r['givrec'] === 'G' ? 'N' : 'Y',
                        'islno' => $r['givrec'] === 'G' ? $slno : 0,
                    ]));
                    DB::table('smithd')->where('slno', $slno)->where('sno', $sno - 1)->update($this->f('smithd', ['bcode' => $newBcode]));
                }
            }

            DB::table('daybookpart')->insert($this->f('daybookpart', [
                'slno' => $slno, 'particular' => $this->particular($type, $docno, $smithName), 'vchno' => '',
                'ic' => 1, 'uid' => 1, 'ttime' => $ttime, 'rate' => (float) ($in['rate'] ?? 0),
            ]));

            $this->writeDaybook($slno, $tdate, $icontrol, $smithCode, $type, $tot, [
                'acid' => (float) ($in['acidcharge'] ?? 0), 'disc' => (float) ($in['discount'] ?? 0), 'tds' => (float) ($in['tdsamt'] ?? 0),
                'tax' => (float) ($in['taxamt'] ?? 0), 'tcs' => (float) ($in['tcsamt'] ?? 0), 'paid' => $signedPaid,
                'cbcode' => trim((string) ($in['cbcode'] ?? 'CASH')) ?: 'CASH',
                'interstate' => strtoupper((string) ($in['interstate'] ?? 'N')) === 'Y' ? 'Y' : 'N',
                'taxreverse' => strtoupper((string) ($in['taxreverse'] ?? 'N')) === 'Y' ? 'Y' : 'N',
            ]);

            if ($this->hasTable('daybook')) {
                $round = (float) DB::table('daybook')->where('slno', $slno)->sum('amount');
                if (abs($round) > 0.0001) {
                    DB::table('daybook')->insert($this->f('daybook', ['slno' => $slno, 'accode' => 'ROUND', 'amount' => -$round, 'control' => $icontrol, 'tdate' => $tdate, 'opaccode' => 'MC']));
                }
            }

            DB::commit();
            $this->logDelpart($request, 'Goldsmith(' . $docno . ') ' . ($saded === 'E' ? 'Updated' : 'Saved'), ['utype' => $saded === 'E' ? 'E' : 'A', 'ttype' => 'T', 'slno' => $slno, 'tdate' => $tdate, 'control' => $icontrol]);
            Log::info('goldsmith_save_buffer_saved', $this->goldsmithSaveLogContext($request, $in, $rows, [
                'docno' => $docno,
                'slno' => $slno,
                'totals' => $tot,
            ]));

            return response()->json(['success' => true, 'message' => 'Saved successfully', 'docno' => $docno, 'slno' => $slno]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('goldsmith_save_buffer_error', $this->goldsmithSaveLogContext($request, $in, $rows, [
                'docno' => $docno,
                'slno' => $slno,
                'error' => $e->getMessage(),
            ]));

            return response()->json(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    private function goldsmithSaveLogContext(Request $request, array $input, array $rows, array $extra = []): array
    {
        return array_merge([
            'module' => (string) ($input['module'] ?? 'goldsmith-bill'),
            'mode' => strtoupper((string) ($input['saded'] ?? 'A')),
            'type' => strtoupper((string) ($input['istype'] ?? 'G')),
            'eb' => strtoupper((string) ($input['eb'] ?? 'B')),
            'slno' => (int) ($input['slno'] ?? 0),
            'docno' => trim((string) ($input['docno'] ?? '')),
            'smithcode' => trim((string) ($input['smithcode'] ?? '')),
            'smithname' => trim((string) ($input['smithname'] ?? '')),
            'tdate' => (string) ($input['tdate'] ?? ''),
            'rate' => (string) ($input['rate'] ?? ''),
            'paid' => (string) ($input['paid'] ?? ''),
            'row_count' => count($rows),
            'rows' => $rows,
            'user_code' => (string) $request->session()->get('user_code', ''),
            'ip' => $request->ip(),
        ], $extra);
    }

    public function prev(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);
        $slno = (int) $request->query('slno', 0);
        $type = strtoupper((string) $request->query('type', 'G'));
        $module = (string) $request->query('module', 'goldsmith-bill');
        $gilevel = max(1, (int) $request->session()->get('gilevel', 1));

        $query = DB::table('smithm')
            ->leftJoin('clients', 'clients.code', '=', 'smithm.smithcode');
        $this->applyTransactionScope($query, 'smithm.docno', 'clients.ctype', 'smithm.control', $type, $module, $gilevel);
        if ($slno > 0) {
            $query->where('smithm.slno', '<', $slno);
        }
        $row = $query->orderByDesc('smithm.slno')->first(['smithm.slno', 'smithm.docno']);

        if (!$row) return response()->json(['success' => false, 'message' => 'No previous record']);
        return response()->json(['success' => true, 'slno' => $row->slno, 'docno' => $row->docno]);
    }

    public function next(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);
        $slno = (int) $request->query('slno', 0);
        $type = strtoupper((string) $request->query('type', 'G'));
        $module = (string) $request->query('module', 'goldsmith-bill');
        $gilevel = max(1, (int) $request->session()->get('gilevel', 1));

        $query = DB::table('smithm')
            ->leftJoin('clients', 'clients.code', '=', 'smithm.smithcode');
        $this->applyTransactionScope($query, 'smithm.docno', 'clients.ctype', 'smithm.control', $type, $module, $gilevel);
        if ($slno > 0) {
            $query->where('smithm.slno', '>', $slno)->orderBy('smithm.slno');
        } else {
            $query->orderByDesc('smithm.slno');
        }
        $row = $query->first(['smithm.slno', 'smithm.docno']);

        if (!$row) return response()->json(['success' => false, 'message' => 'No next record']);
        return response()->json(['success' => true, 'slno' => $row->slno, 'docno' => $row->docno]);
    }

    public function delete(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);
        $slno = (int) $request->input('slno', 0);
        if ($slno <= 0) return response()->json(['success' => false, 'message' => 'Invalid slno'], 422);
        $master = DB::table('smithm')->where('slno', $slno)->first(['docno', 'tdate', 'control']);
        $control = (int) ($master->control ?? 0);
        if ($control === 0) return response()->json(['success' => false, 'message' => 'Transaction not found'], 404);

        DB::beginTransaction();
        try {
            $this->reverseStockForSlno($slno, $control === 1 ? 'B' : 'E');
            DB::table('smithd')->where('slno', $slno)->delete();
            DB::table('smithm')->where('slno', $slno)->delete();
            DB::table('daybook')->where('slno', $slno)->delete();
            DB::table('daybookpart')->where('slno', $slno)->delete();
            if ($this->hasTable('itemadj')) DB::table('itemadj')->where('slno', $slno)->delete();
            DB::commit();
            $this->logDelpart($request, 'Goldsmith(' . trim((string) ($master->docno ?? $slno)) . ') Deleted', ['utype' => 'D', 'ttype' => 'T', 'slno' => $slno, 'tdate' => (string) ($master->tdate ?? date('Y-m-d')), 'control' => $control]);
            return response()->json(['success' => true, 'message' => 'Deleted']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function balance(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);
        $code = trim((string) $request->query('code', ''));
        if ($code === '') return response()->json(['success' => false], 422);
        $tdate = (string) $request->query('tdate', date('Y-m-d'));
        $slno = (int) $request->query('slno', 0);
        $saded = strtoupper((string) $request->query('saded', 'A'));
        $gilevel = (int) $request->query('semi', 1);
        if (!in_array($gilevel, [1, 2], true)) $gilevel = 1;

        if ($this->hasTable('accountm')) {
            $blocked = (string) (DB::table('accountm')->where('accode', $code)->value('blocked') ?? 'N');
            if ($blocked === 'Y') return response()->json(['success' => false, 'message' => 'Customer is blocked']);
        }

        $client = DB::table('clients')->where('code', $code)->first();
        if (!$client) return response()->json(['success' => false, 'message' => 'Invalid code']);

        $opwgt = 0.0; $opbalBase = 0.0;
        if ($this->hasTable('clientsgs')) {
            $opwgtCol = $gilevel === 1 ? 'opweight' : 'opweightb';
            $opbalCol = $gilevel === 1 ? 'opbalance' : 'opbalanceb';
            $op = DB::table('clients as c')->join('clientsgs as cgs', 'cgs.code', '=', 'c.code')
                ->whereRaw('TRIM(c.code)=?', [$code])
                ->selectRaw("COALESCE(cgs.$opwgtCol,0) as opwgt, COALESCE(c.$opbalCol,0) as opbal")
                ->first();
            $opwgt = (float) ($op->opwgt ?? 0); $opbalBase = (float) ($op->opbal ?? 0);
        }

        $aq = DB::table('daybook')->where('accode', $code)->whereDate('tdate', '<=', $tdate)->where('control', '<=', $gilevel);
        if ($saded === 'E' && $slno > 0) $aq->where('slno', '<>', $slno);
        $opamt = (float) $aq->sum('amount') + $opbalBase;
        $opwgt = $this->smithWgtBal($code, $tdate, $gilevel, ($saded === 'E' ? $slno : null), $opwgt);

        $mcrate = 0.0; $convtouch = 100.0; $decround = 3; $mcdecround = 2; $deftouch = 0.0; $smithWastage = 0.0;
        if ($this->hasTable('clientsgs')) {
            $cgs = DB::table('clientsgs')->where('code', $code)->first();
            $mcrate = (float) ($cgs->mcrate ?? 0); $convtouch = (float) ($cgs->convtouch ?? 100);
            $decround = (int) ($cgs->decround ?? 3); $mcdecround = (int) ($cgs->mcdecround ?? 2); $deftouch = (float) ($cgs->deftouch ?? 0);
            $smithWastage = (float) ($cgs->wastage ?? 0);
            if ($convtouch <= 0) $convtouch = 100;
        }

        return response()->json([
            'success' => true, 'name' => (string) ($client->name ?? ''), 'mobile' => (string) ($client->mobile ?? ''),
            'opwgt' => round($opwgt, 3), 'opamt' => round($opamt, 2), 'opbal_label' => number_format(round($opwgt, 3), 3) . ' / ' . number_format(round($opamt, 2), 2),
            'mcrate' => $mcrate, 'idecround' => $decround, 'imcdecround' => $mcdecround,
            'diconvtouch' => $convtouch, 'dideftouch' => $deftouch, 'smithWastage' => $smithWastage,
        ]);
    }

    public function itemHelp(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);
        $q = '%' . trim((string) $request->query('q', '')) . '%';
        $rows = DB::table('items')->where(function ($b) use ($q) { $b->where('code', 'like', $q)->orWhere('name', 'like', $q); })->limit(50)->get(['code', 'name']);
        return response()->json($rows);
    }

    public function itemInfo(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);
        $code = strtoupper(trim((string) $request->query('code', '')));
        if ($code === '') return response()->json(['success' => false]);

        $cols = $this->columnList('items');
        $want = ['code', 'name', 'cost', 'disabled', 'defstktype', 'touch', 'defquality',
                 'smithmc', 'jewlmc', 'ornament', 'stktouch', 'jewltouch'];
        $sel = array_values(array_intersect($want, $cols));
        if (!in_array('code', $sel, true)) $sel[] = 'code';

        $item = DB::table('items')->where('code', $code)->first($sel);
        if (!$item) return response()->json(['success' => false, 'message' => 'Item not found']);

        return response()->json(['success' => true, 'item' => (array) $item]);
    }

    public function clientHelp(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);
        $q = '%' . trim((string) $request->query('q', '')) . '%';
        $ctype = strtoupper((string) $request->query('ctype', 'G'));
        $types = [$ctype];
        if ($ctype === 'J' && !DB::table('clients')->where('ctype', 'J')->exists()) {
            $types = ['J', 'S', 'G', 'F', 'C'];
        }

        $rows = DB::table('clients')
            ->whereIn('ctype', $types)
            ->where(function ($b) use ($q) {
                $b->where('code', 'like', $q)->orWhere('name', 'like', $q);
            })
            ->orderByRaw("CASE ctype WHEN 'J' THEN 0 WHEN 'S' THEN 1 WHEN 'G' THEN 2 WHEN 'F' THEN 3 ELSE 4 END")
            ->orderBy('code')
            ->limit(50)
            ->get(['code', 'name', 'mobile', 'ctype']);
        return response()->json($rows);
    }

    public function barcodeInfo(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);
        $bcode = (int) $request->query('bcode', 0);
        if ($bcode <= 0) return response()->json(['success' => false]);
        $row = DB::table('barcode')->where('bcode', $bcode)->first();
        if (!$row) return response()->json(['success' => false]);
        return response()->json([
            'success' => true, 'itemcode' => (string) ($row->icode ?? ''), 'qty' => (float) ($row->qty ?? 0), 'weight' => (float) ($row->weight ?? 0),
            'stonewgt' => (float) ($row->stweight ?? 0), 'stoneprice' => (float) ($row->stprice ?? 0), 'mcrate' => (float) ($row->mcrate ?? 0),
            'mcharge' => (float) ($row->dmdamt ?? 0), 'smithmc' => (float) ($row->smithmcrate ?? 0), 'touch' => (float) ($row->stktouch ?? 0),
            'stk' => (string) ($row->stk ?? 'Y'), 'rate' => (float) ($row->rate ?? 0),
        ]);
    }

    public function interestPostingShow(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);

        $smcode = strtoupper(trim((string) $request->query('smcode', '')));
        $code = strtoupper(trim((string) $request->query('code', '')));

        $rows = DB::table('clients as c')
            ->leftJoin('clientsgs as cg', 'cg.code', '=', 'c.code')
            ->when($code !== '', fn ($q) => $q->where('c.code', $code))
            ->when($code === '' && $smcode !== '', fn ($q) => $q->whereRaw("TRIM(COALESCE(c.smcode, '')) = ?", [$smcode]))
            ->where(function ($q) use ($code) {
                if ($code !== '') {
                    $q->whereRaw("TRIM(COALESCE(c.grp, '')) = 'KC'");
                    return;
                }

                $q->whereRaw("TRIM(COALESCE(c.grp, '')) = 'KC'")
                    ->orWhere('cg.intwgt', '<>', 0)
                    ->orWhere('cg.intamt', '<>', 0);
            })
            ->orderBy('c.name')
            ->get([
                'c.code',
                'c.name',
                DB::raw('COALESCE(cg.intwgt, 0) as intwgt'),
                DB::raw('COALESCE(cg.intamt, 0) as intamt'),
                'c.smcode',
            ])
            ->map(fn ($row) => [
                'code' => (string) ($row->code ?? ''),
                'name' => (string) ($row->name ?? ''),
                'intwgt' => round((float) ($row->intwgt ?? 0), 3),
                'intamt' => round((float) ($row->intamt ?? 0), 2),
                'sel' => ((float) ($row->intwgt ?? 0) != 0.0 || (float) ($row->intamt ?? 0) != 0.0) ? 1 : 0,
                'smcode' => (string) ($row->smcode ?? ''),
            ])
            ->values();

        return response()->json(['success' => true, 'rows' => $rows]);
    }

    public function interestPostingSave(Request $request): JsonResponse
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
        $gilevel = (int) $request->input('gilevel', 1);
        if (!in_array($gilevel, [1, 2], true)) {
            $gilevel = 1;
        }

        $icontrol = $gilevel === 1 ? 1 : 2;
        $seb = $gilevel === 1 ? 'E' : 'B';
        $snote = 'Interest upto ' . date('d/m/Y', strtotime($dtdate));
        $ssmcode = strtoupper(trim((string) $request->input('smcode', '')));
        $saved = 0;

        DB::beginTransaction();

        try {
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $scode = strtoupper(trim((string) ($row['code'] ?? '')));
                $sname = trim((string) ($row['name'] ?? ''));
                $dwgt = round((float) ($row['intwgt'] ?? 0), 3);
                $damt = round((float) ($row['intamt'] ?? 0), 2);
                $isel = (int) ($row['sel'] ?? 0);

                if ($scode === '' || $isel !== 1 || ($dwgt == 0.0 && $damt == 0.0)) {
                    continue;
                }

                $clientExists = DB::table('clients')
                    ->where('code', $scode)
                    ->where('grp', 'KC')
                    ->exists();

                if (!$clientExists) {
                    throw new \RuntimeException("Invalid depositor code: {$scode}");
                }

                $lslno = $this->nextSerialNo();
                $sdocno = $this->nextInterestPostingDocNo($seb);
                $ttime = date('H:i:s');

                DB::table('smithm')->insert($this->f('smithm', [
                    'slno' => $lslno,
                    'docno' => $sdocno,
                    'tdate' => $dtdate,
                    'ttime' => $ttime,
                    'smithcode' => $scode,
                    'tmcharge' => $damt,
                    'pamt' => 0,
                    'status' => 1,
                    'control' => $icontrol,
                    'rate' => 0,
                    'rmno' => '',
                    'smcode' => $ssmcode,
                    'person' => '',
                    'jewlcode' => '',
                    'ic' => 1,
                    'opwgt' => 0,
                    'opamt' => 0,
                    'tdsperc' => 0,
                    'tdsamt' => 0,
                    'acidcharge' => 0,
                    'discount' => 0,
                    'lotno' => '',
                    'taxperc' => 0,
                    'taxamt' => 0,
                    'interstate' => 'N',
                    'taxreverse' => 'N',
                    'statecode' => '',
                    'placeos' => '',
                    'refno' => '',
                    'doctype' => 'N',
                    'duedate' => null,
                    'transportmode' => '',
                    'vehno' => '',
                    'purpose' => '',
                    'note' => $snote,
                    'tcsperc' => 0,
                    'tcsamt' => 0,
                ]));

                DB::table('smithd')->insert($this->f('smithd', [
                    'slno' => $lslno,
                    'code' => 'INT',
                    'qty' => 0,
                    'weight' => $dwgt,
                    'stonewgt' => 0,
                    'stoneprice' => 0,
                    'mcharge' => $damt,
                    'wastage' => 0,
                    'cost' => 0,
                    'givrec' => 'R',
                    'wgtamt' => 0,
                    'touch' => 100,
                    'touchwgt' => 0,
                    'sno' => 1,
                    'name' => '',
                    'ordno' => '',
                    'orditem' => '',
                    'stktype' => '',
                    'touchnote' => '',
                    'bcode' => 0,
                    'smithmc' => 0,
                    'netwgt' => $dwgt,
                    'stktouch' => 0,
                    'hmc' => 0,
                    'mud' => 0,
                    'tp' => 0,
                    'sva' => 0,
                    'sstprice' => 0,
                    'model' => '',
                    'remark' => 'Interest',
                ]));

                $sacpart = 'By Interest Post -' . trim($sdocno);
                if ($sname !== '') {
                    $sacpart .= '-' . $sname;
                }

                DB::table('daybookpart')->insert($this->f('daybookpart', [
                    'slno' => $lslno,
                    'particular' => mb_substr($sacpart, 0, 40),
                    'vchno' => '',
                    'ic' => 1,
                    'uid' => 1,
                    'ttime' => $ttime,
                    'rate' => 0,
                ]));

                if ($damt > 0) {
                    DB::table('daybook')->insert($this->f('daybook', [
                        'slno' => $lslno,
                        'tdate' => $dtdate,
                        'accode' => $scode,
                        'amount' => $damt,
                        'control' => $icontrol,
                        'opaccode' => 'INTEXP',
                    ]));

                    DB::table('daybook')->insert($this->f('daybook', [
                        'slno' => $lslno,
                        'tdate' => $dtdate,
                        'accode' => 'INTEXP',
                        'amount' => -$damt,
                        'control' => $icontrol,
                        'opaccode' => $scode,
                    ]));
                }

                $saved++;
            }

            if ($saved === 0) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'No selected rows with interest amount/weight to save',
                ], 422);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Saved {$saved} interest posting entr" . ($saved === 1 ? 'y' : 'ies'),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function normalizeRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            if (!is_array($r)) continue;
            $code = strtoupper(trim((string) ($r['itemcode'] ?? '')));
            if ($code === '') continue;
            $gr = strtoupper(trim((string) ($r['givrec'] ?? '')));
            if (!in_array($gr, ['G', 'R'], true)) throw new \RuntimeException('Invalid Give/Receive for item ' . $code);
            $x = [
                'itemcode' => $code, 'itemname' => trim((string) ($r['itemname'] ?? '')), 'model' => trim((string) ($r['model'] ?? '')),
                'qty' => (float) ($r['qty'] ?? 0), 'weight' => (float) ($r['weight'] ?? 0), 'stonewgt' => (float) ($r['stonewgt'] ?? 0), 'givrec' => $gr,
                'touch' => (float) ($r['touch'] ?? 0), 'wastage' => (float) ($r['wastage'] ?? 0), 'netwgt' => (float) ($r['netwgt'] ?? 0),
                'stoneprice' => (float) ($r['stoneprice'] ?? 0), 'mcharge' => (float) ($r['mcharge'] ?? 0), 'hmc' => (float) ($r['hmc'] ?? 0),
                'tp' => (float) ($r['tp'] ?? 0), 'mud' => (float) ($r['mud'] ?? 0), 'ordno' => trim((string) ($r['ordno'] ?? '')),
                'orditem' => trim((string) ($r['orditem'] ?? '')), 'stktype' => trim((string) ($r['stktype'] ?? '')), 'touchnote' => trim((string) ($r['touchnote'] ?? '')),
                'bcode' => (int) ($r['bcode'] ?? 0), 'smithmc' => (float) ($r['smithmc'] ?? ($r['mcrate'] ?? 0)), 'stktouch' => (float) ($r['stktouch'] ?? 100),
                'wgtamt' => (float) ($r['wgtamt'] ?? 0), 'sva' => (float) ($r['sva'] ?? 0), 'sstprice' => (float) ($r['sstprice'] ?? 0),
                'cost' => (float) ($r['cost'] ?? 0), 'bcstk' => strtoupper((string) ($r['bcstk'] ?? 'Y')) === 'N' ? 'N' : 'Y', 'remark' => trim((string) ($r['remark'] ?? '')),
            ];
            if ($x['givrec'] === 'G') {
                if ($x['mcharge'] > 0) $x['mcharge'] = -$x['mcharge'];
                if ($x['stoneprice'] > 0) $x['stoneprice'] = -$x['stoneprice'];
                if ($x['hmc'] > 0) $x['hmc'] = -$x['hmc'];
            }
            $out[] = $x;
        }
        return $out;
    }

    private function computeTotals(array $rows): array
    {
        $iss = 0.0; $rcv = 0.0; $tmc = 0.0; $tc = 0.0; $h = 0.0; $q = 0;
        foreach ($rows as $r) {
            $isTc = in_array($r['itemcode'], ['TC', 'INT'], true);
            $sum = (float) $r['mcharge'] + (float) $r['stoneprice'] + (float) $r['hmc'];
            if ($r['givrec'] === 'G') { $iss += (float) $r['weight']; $tmc += $isTc ? $sum : -abs($sum); } else { $rcv += (float) $r['weight']; $tmc += $isTc ? -abs($sum) : abs($sum); }
            if ($isTc) $tc += (float) $r['mcharge'];
            $h += (float) $r['hmc']; $q += (int) round((float) $r['qty']);
        }
        return ['dtwgtissued' => round($iss, 3), 'dtwgtrcvd' => round($rcv, 3), 'dtmcharge' => round($tmc, 2), 'dttcamt' => round($tc, 2), 'dthmc' => round($h, 2), 'itqty' => $q];
    }

    private function writeDaybook(int $slno, string $tdate, int $control, string $party, string $type, array $tot, array $c): void
    {
        $mcAc = $type === 'J' ? $this->general('JMCAC', 'JMC') : $this->general('SMCAC', 'MC');
        $mc = (-(float) $tot['dtmcharge']) + (float) $tot['dttcamt'] + (float) $tot['dthmc'];
        if (abs($mc) > 0.0001) $this->dbl($slno, $tdate, $control, $mcAc, $mc, $party, $party, -$mc, $mcAc);
        if (abs((float) $c['acid']) > 0.0001) $this->dbl($slno, $tdate, $control, 'ACCH', (float) $c['acid'], $party, $party, -(float) $c['acid'], 'ACCH');
        if (abs((float) $c['disc']) > 0.0001) { $d = $this->general('GSDISCAC', 'DISC'); $this->dbl($slno, $tdate, $control, $d, -(float) $c['disc'], $party, $party, (float) $c['disc'], $d); }
        if (abs((float) $c['tds']) > 0.0001) { $td = $type === 'J' ? 'TDSE' : 'TDSL'; $this->dbl($slno, $tdate, $control, $party, (float) $c['tds'], $td, $td, -(float) $c['tds'], $party); }
        if (abs((float) $c['tax']) > 0.0001) {
            $p = (float) $tot['dtwgtissued'] > 0 ? -(float) $c['tax'] : (float) $c['tax'];
            if ((string) $c['interstate'] === 'Y') {
                $this->db($slno, $tdate, $control, 'IGST', -$p, $party);
            } else {
                $half = $p / 2;
                if ((string) $c['taxreverse'] === 'Y' && (float) $tot['dtwgtissued'] == 0.0) $half = -$half;
                $this->db($slno, $tdate, $control, 'SGST', -$half, $party);
                $this->db($slno, $tdate, $control, 'CGST', -$half, $party);
            }
            $ta = (string) $c['taxreverse'] === 'Y' ? 'GSTEXP' : $party;
            $tv = -$p; if ((string) $c['taxreverse'] === 'Y' && (float) $tot['dtwgtissued'] == 0.0) $tv = -$tv;
            $this->db($slno, $tdate, $control, $ta, $tv, 'GST');
        }
        if (abs((float) $c['tcs']) > 0.0001) {
            $ta = $this->general('GSTCSAC', 'TCSAC');
            $v = (float) $tot['dtwgtissued'] > 0 ? (float) $c['tcs'] : -(float) $c['tcs'];
            $this->db($slno, $tdate, $control, $ta, $v, $party);
            $this->db($slno, $tdate, $control, $party, -$v, $ta);
        }
        if (abs((float) $c['paid']) > 0.0001) {
            $cbcode = trim((string) ($c['cbcode'] ?? 'CASH')) ?: 'CASH';
            $this->dbl($slno, $tdate, $control, $cbcode, (float) $c['paid'], $party, $party, -(float) $c['paid'], $cbcode);
        }
    }

    private function daybookCashBankCode(int $slno, string $party): string
    {
        if (!$this->hasTable('daybook') || $slno <= 0) {
            return 'CASH';
        }

        $query = DB::table('daybook')
            ->where('slno', $slno)
            ->whereRaw('TRIM(daybook.accode) <> ?', [$party])
            ->whereRaw('TRIM(daybook.opaccode) = ?', [$party])
            ->orderByRaw("CASE WHEN TRIM(daybook.accode)='CASH' THEN 0 ELSE 1 END");

        if ($this->hasTable('accountm')) {
            $query->join('accountm', DB::raw('TRIM(accountm.accode)'), '=', DB::raw('TRIM(daybook.accode)'))
                ->whereIn('accountm.actype2', ['H', 'B']);
        }

        $row = $query->first(['daybook.accode']);

        return trim((string) ($row->accode ?? 'CASH')) ?: 'CASH';
    }

    private function dbl(int $slno, string $tdate, int $control, string $a1, float $v1, string $o1, string $a2, float $v2, string $o2): void
    {
        $this->db($slno, $tdate, $control, $a1, $v1, $o1);
        $this->db($slno, $tdate, $control, $a2, $v2, $o2);
    }

    private function db(int $slno, string $tdate, int $control, string $ac, float $amt, string $op): void
    {
        if (!$this->hasTable('daybook')) return;
        DB::table('daybook')->insert($this->f('daybook', ['slno' => $slno, 'tdate' => $tdate, 'accode' => $ac, 'amount' => $amt, 'control' => $control, 'opaccode' => $op]));
    }

    private function particular(string $type, string $docno, string $name): string
    {
        $p = $type === 'J' ? 'By Jewellery Entry-' : ($type === 'C' ? 'By Deposit Entry-' : 'By Smith Entry-');
        $x = $p . trim($docno); if (trim($name) !== '') $x .= '-' . trim($name);
        return mb_substr($x, 0, 40);
    }

    private function mapDoctype(string $x): string
    {
        $x = strtoupper(trim($x));
        return match ($x) { 'U', 'UNFIXING' => 'U', 'F', 'FIXING' => 'F', default => 'N' };
    }

    private function normDate(string $d): ?string
    {
        $d = trim($d); if ($d === '') return null;
        $t = strtotime($d); if ($t === false || (int) date('Y', $t) < 1990) return null;
        return date('Y-m-d', $t);
    }

    private function nextDocNo(string $type, string $eb, bool $increment = false, string $module = '', bool $isReceipt = false): string
    {
        $eb = in_array($eb, ['B', 'E'], true) ? $eb : 'B';
        $module = strtolower(trim($module));
        $type = strtoupper($type);

        if ($type === 'J' && $isReceipt) {
            $this->ensureCounter('JEWLRB');
            $counter = (int) (DB::table('generali')->whereRaw('TRIM(code)=?', ['JEWLRB'])->value('cvalue') ?? 0);
            $pref = $this->general('JRBPREF', 'JR/');
            $len = (int) $this->general('JRBLEN', '5');
            if ($len <= 0) $len = 5;
            $run = $this->nextRunNumberFromExistingDocs('JEWLRB', $counter, $pref, $increment);
            return $pref . str_pad((string) $run, $len, '0', STR_PAD_LEFT);
        }

        $base = 'SMITH';
        if ($type === 'J') $base = 'JEWL';
        elseif ($type === 'C') $base = 'PDEP';
        elseif ($type === 'R') $base = str_contains($module, 'rcpt-memo-from-smith') ? 'RM3' : 'RM2';

        $code = $base . $eb;
        $this->ensureCounter($code);
        $counter = (int) (DB::table('generali')->whereRaw('TRIM(code)=?', [$code])->value('cvalue') ?? 0);

        if ($type === 'R') {
            $pref = $base === 'RM3' ? ($eb === 'B' ? 'RM3/' : 'RM3 ') : ($eb === 'B' ? 'RM2/' : 'RM2 ');
            $run = $this->nextRunNumberFromExistingDocs($code, $counter, $pref, $increment);
            return $pref . str_pad((string) $run, 5, '0', STR_PAD_LEFT);
        }

        $pc = $type === 'J' ? ($eb === 'B' ? 'JBPREF' : 'JEPREF') : ($type === 'C' ? ($eb === 'B' ? 'DBPREF' : 'DEPREF') : ($eb === 'B' ? 'GSBPREF' : 'GSEPREF'));
        $lc = $type === 'J' ? ($eb === 'B' ? 'JBLEN' : 'JELEN') : ($type === 'C' ? ($eb === 'B' ? 'DBLEN' : 'DELEN') : ($eb === 'B' ? 'GSBLEN' : 'GSELEN'));
        $dp = $type === 'J' ? ($eb === 'B' ? 'JLB/' : 'JLE/') : ($type === 'C' ? ($eb === 'B' ? 'DPB/' : 'DPE/') : ($eb === 'B' ? 'GSB/' : 'GSE/'));
        $pref = $this->general($pc, $dp); $len = (int) $this->general($lc, '5'); if ($len <= 0) $len = 5;
        $run = $this->nextRunNumberFromExistingDocs($code, $counter, $pref, $increment);
        return $pref . str_pad((string) $run, $len, '0', STR_PAD_LEFT);
    }

    private function usesReceiptDocNo(string $type, array $rows): bool
    {
        if (strtoupper($type) !== 'J') {
            return false;
        }

        foreach ($rows as $row) {
            if (strtoupper(trim((string) ($row['givrec'] ?? ''))) === 'R') {
                return true;
            }
        }

        return false;
    }

    private function nextRunNumberFromExistingDocs(string $counterCode, int $counter, string $prefix, bool $increment): int
    {
        $maxExisting = $this->maxDocSerialForPrefix($prefix);
        $current = max($counter, $maxExisting);
        $run = $current + 1;

        if ($this->hasTable('generali') && ($increment || $counter < $maxExisting)) {
            DB::table('generali')->whereRaw('TRIM(code)=?', [$counterCode])->update(['cvalue' => $increment ? $run : $maxExisting]);
        }

        return $run;
    }

    private function maxDocSerialForPrefix(string $prefix): int
    {
        $prefix = trim($prefix);
        if ($prefix === '' || !$this->hasTable('smithm') || !Schema::hasColumn('smithm', 'docno')) {
            return 0;
        }

        return DB::table('smithm')
            ->where('docno', 'like', $prefix . '%')
            ->pluck('docno')
            ->reduce(function (int $max, $docno): int {
                return preg_match('/(\d+)\s*$/', trim((string) $docno), $m)
                    ? max($max, (int) $m[1])
                    : $max;
            }, 0);
    }

    private function pickerDocPrefixes(string $type, string $module): array
    {
        $defaults = match ($type) {
            'J' => ['JLB/', 'JLE/', 'JRB/', 'JI/', 'JR/'],
            'C' => ['DPB/', 'DPE/'],
            'R' => str_contains($module, 'rcpt-memo-from-smith') ? ['RM3/'] : ['RM2/'],
            default => ['GSB/', 'GSE/', 'GSR/', 'GI/', 'GR/'],
        };

        $configured = match ($type) {
            'J' => [$this->general('JBPREF', ''), $this->general('JEPREF', ''), $this->general('JRBPREF', '')],
            'C' => [$this->general('DBPREF', ''), $this->general('DEPREF', '')],
            'S', 'G' => [$this->general('GSBPREF', ''), $this->general('GSEPREF', ''), $this->general('GSRBPREF', '')],
            default => [],
        };

        $all = array_merge($defaults, $configured);
        return array_values(array_unique(array_filter(array_map('trim', $all), fn ($v) => $v !== '')));
    }

    private function applyDocPrefixFilter($query, string $column, string $type, string $module): void
    {
        $prefixes = $this->pickerDocPrefixes($type, $module);
        if ($prefixes === []) {
            return;
        }

        $query->where(function ($w) use ($column, $prefixes) {
            foreach ($prefixes as $prefix) {
                $w->orWhere($column, 'like', $prefix . '%');
            }
        });
    }

    private function applyTransactionScope($query, string $docColumn, string $ctypeColumn, string $controlColumn, string $type, string $module, int $gilevel): void
    {
        $type = strtoupper($type);
        $query->where($controlColumn, '<=', max(1, $gilevel));

        if ($type === 'R') {
            $this->applyDocPrefixFilter($query, $docColumn, $type, $module);
            return;
        }

        $prefixes = $this->pickerDocPrefixes($type, $module);
        $query->where(function ($w) use ($ctypeColumn, $type, $docColumn, $prefixes) {
            $w->where($ctypeColumn, $type);
            foreach ($prefixes as $prefix) {
                $w->orWhere($docColumn, 'like', $prefix . '%');
            }
        });
    }

    private function nextInterestPostingDocNo(string $suffix): string
    {
        $suffix = strtoupper(trim($suffix)) === 'B' ? 'B' : 'E';
        $code = 'DINT' . $suffix;
        $this->ensureCounter($code);
        DB::table('generali')->whereRaw('TRIM(code)=?', [$code])->update(['cvalue' => DB::raw('cvalue + 1')]);
        $run = (int) (DB::table('generali')->whereRaw('TRIM(code)=?', [$code])->value('cvalue') ?? 0);

        return 'DIN' . $suffix . str_pad((string) $run, 5, '0', STR_PAD_LEFT);
    }

    private function ensureCounter(string $code): void
    {
        if (!$this->hasTable('generali')) return;
        if (!DB::table('generali')->whereRaw('TRIM(code)=?', [$code])->exists()) DB::table('generali')->insert(['code' => $code, 'cvalue' => 0]);
    }

    private function nextSerialNo(): int
    {
        $this->ensureCounter('SERIALNO');
        $current = (int) (DB::table('generali')->whereRaw('TRIM(code)=?', ['SERIALNO'])->value('cvalue') ?? 0);
        $maxUsed = 0;
        foreach (['salesm', 'salesrm', 'purchasem', 'purchaserm', 'daybook', 'daybookpart', 'orderm', 'smithm', 'refinerym', 'repairm'] as $table) {
            if ($this->hasTable($table) && Schema::hasColumn($table, 'slno')) {
                $maxUsed = max($maxUsed, (int) (DB::table($table)->max('slno') ?? 0));
            }
        }
        $next = max($current, $maxUsed) + 1;
        DB::table('generali')->whereRaw('TRIM(code)=?', ['SERIALNO'])->update(['cvalue' => $next]);
        return $next;
    }

    private function general(string $code, string $default = ''): string
    {
        if (!$this->hasTable('generals')) return $default;
        $v = DB::table('generals')->where('code', $code)->value('cvalue');
        if ($v === null || trim((string) $v) === '') {
            if (!DB::table('generals')->where('code', $code)->exists()) DB::table('generals')->insert($this->f('generals', ['code' => $code, 'cvalue' => $default]));
            return $default;
        }
        return trim((string) $v);
    }

    private function f(string $table, array $data): array
    {
        if (!$this->hasTable($table)) return $data;
        $cols = array_flip(array_map('strtolower', $this->columnList($table)));
        return array_filter($data, static fn ($v, $k) => isset($cols[strtolower((string) $k)]), ARRAY_FILTER_USE_BOTH);
    }

    private function smithWgtBal(string $rcode, string $rdate, int $gilevel = 1, ?int $excludeSlno = null, ?float $opWeight = null): float
    {
        $dtopbalwgt = $opWeight ?? 0.0;
        $qG = DB::table('smithd')->join('smithm', 'smithm.slno', '=', 'smithd.slno')
            ->whereRaw('TRIM(smithm.smithcode)=?', [$rcode])->where('smithd.givrec', 'G')->where('smithm.control', '<=', $gilevel)->whereDate('smithm.tdate', '<=', $rdate);
        if ($excludeSlno) $qG->where('smithm.slno', '<>', $excludeSlno);
        $g = (float) $qG->sum('smithd.netwgt');

        $qR = DB::table('smithd')->join('smithm', 'smithm.slno', '=', 'smithd.slno')
            ->whereRaw('TRIM(smithm.smithcode)=?', [$rcode])->where('smithd.givrec', 'R')->where('smithm.control', '<=', $gilevel)->whereDate('smithm.tdate', '<=', $rdate);
        if ($excludeSlno) $qR->where('smithm.slno', '<>', $excludeSlno);
        $r = (float) $qR->sum('smithd.netwgt');

        return $dtopbalwgt - $g + $r;
    }

    private function reverseStockForSlno(int $slno, string $eb): void
    {
        $rows = DB::table('smithd')->where('slno', $slno)->get(['code', 'qty', 'weight', 'stonewgt', 'givrec', 'stktype', 'bcode']);
        foreach ($rows as $r) {
            $code = trim((string) ($r->code ?? '')); $qty = (float) ($r->qty ?? 0); $w = (float) ($r->weight ?? 0); $sw = (float) ($r->stonewgt ?? 0);
            $gr = strtoupper((string) ($r->givrec ?? 'R')); $st = trim((string) ($r->stktype ?? '')); $bc = (int) ($r->bcode ?? 0);
            if (!in_array($code, ['TC', 'INT'], true)) $this->applyItemStock($code, $st, $gr === 'G' ? 'R' : 'G', $w, $sw, $qty, $eb);
            if ($bc > 0 && $this->hasTable('barcode')) DB::table('barcode')->where('bcode', $bc)->update($this->f('barcode', ['stk' => $gr === 'G' ? 'Y' : 'N', 'islno' => 0]));
        }
    }

    private function applyItemStock(string $code, string $stktype, string $givrec, float $weight, float $stonewgt, float $qty, string $eb): void
    {
        $sign = $givrec === 'G' ? -1 : 1; $w = $sign * $weight; $q = $sign * $qty; $sw = $sign * $stonewgt;
        if ($eb === 'B') {
            DB::table('items')->whereRaw('TRIM(code)=?', [$code])->update($this->f('items', [
                'weight' => DB::raw("weight + ($w)"), 'qty' => DB::raw("qty + ($q)"), 'weightb' => DB::raw("weightb + ($w)"),
                'qtyb' => DB::raw("qtyb + ($q)"), 'stonewgt' => DB::raw("stonewgt + ($sw)"), 'stonewgtb' => DB::raw("stonewgtb + ($sw)"),
            ]));
        } else {
            DB::table('items')->whereRaw('TRIM(code)=?', [$code])->update($this->f('items', [
                'weightb' => DB::raw("weightb + ($w)"), 'qtyb' => DB::raw("qtyb + ($q)"), 'stonewgtb' => DB::raw("stonewgtb + ($sw)"),
            ]));
        }

        if ($stktype !== '' && $this->hasTable('itemsstk')) {
            if (!DB::table('itemsstk')->where('code', $code)->where('stktype', $stktype)->exists()) {
                DB::table('itemsstk')->insert($this->f('itemsstk', ['code' => $code, 'stktype' => $stktype, 'weight' => 0, 'qty' => 0, 'weightb' => 0, 'qtyb' => 0, 'stonewgt' => 0, 'stonewgtb' => 0]));
            }
            if ($eb === 'B') {
                DB::table('itemsstk')->where('code', $code)->where('stktype', $stktype)->update($this->f('itemsstk', [
                    'weight' => DB::raw("weight + ($w)"), 'qty' => DB::raw("qty + ($q)"), 'weightb' => DB::raw("weightb + ($w)"),
                    'qtyb' => DB::raw("qtyb + ($q)"), 'stonewgt' => DB::raw("stonewgt + ($sw)"), 'stonewgtb' => DB::raw("stonewgtb + ($sw)"),
                ]));
            } else {
                DB::table('itemsstk')->where('code', $code)->where('stktype', $stktype)->update($this->f('itemsstk', [
                    'weightb' => DB::raw("weightb + ($w)"), 'qtyb' => DB::raw("qtyb + ($q)"), 'stonewgtb' => DB::raw("stonewgtb + ($sw)"),
                ]));
            }
        }
    }

    private function nextBarcodeNumber(): int
    {
        $bcMaxNo = 'Y';
        if ($this->hasTable('generals')) {
            $v = DB::table('generals')->where('code', 'BCMaxNo')->value('cvalue');
            if ($v !== null && trim((string) $v) !== '') $bcMaxNo = strtoupper(trim((string) $v));
        }

        $next = 0;
        if ($bcMaxNo === 'Y') {
            if ($this->hasTable('barcode')) {
                $next = (int) (DB::table('barcode')->max('bcode') ?? 0);
            }
        } else {
            $this->ensureCounter('BCNO');
            DB::table('generali')->whereRaw('TRIM(code)=?', ['BCNO'])->update(['cvalue' => DB::raw('cvalue + 1')]);
            $next = (int) (DB::table('generali')->whereRaw('TRIM(code)=?', ['BCNO'])->value('cvalue') ?? 0);
            return $next > 0 ? $next : 100001;
        }

        return $next > 0 ? $next + 1 : 100001;
    }
}
