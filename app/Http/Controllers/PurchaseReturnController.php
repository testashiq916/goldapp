<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesSecondarySeriesPrefix;
use App\Http\Controllers\Concerns\LogsDelpartAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurchaseReturnController extends Controller
{
    use HandlesSecondarySeriesPrefix;
    use LogsDelpartAudit;

    private int    $gilevel    = 1;
    private string $gsincharge = '';

    // ─── View ────────────────────────────────────────────────────────────────

    public function index(Request $request, ?string $mode = null)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $validModes = ['bill', 'edit', 'cancel', 'reprint'];
        $mode = $mode ?? 'bill';
        if (!in_array($mode, $validModes)) $mode = 'bill';

        $this->gsincharge = (string)($request->session()->get('user_code', ''));

        $rates = [
            'gold'     => $this->genDec('GRATE'),
            'silver'   => $this->genDec('SRATE'),
            'platinum' => $this->genDec('PGRATE'),
        ];

        $salesmen = $this->hasTable('sman')
            ? DB::table('sman')->orderBy('name')->get(['code', 'name'])->toArray()
            : [];

        $counters = $this->hasTable('counter')
            ? DB::table('counter')->orderBy('name')->get(['code', 'name'])->toArray()
            : [];

        $billTypes = $this->hasTable('salestype')
            ? DB::table('salestype')->orderBy('code')->get(['code', 'name'])->toArray()
            : [];

        $cashBanks = [];
        if ($this->hasTable('accountm')) {
            $cashBanks = DB::table('accountm')
                ->whereIn('actype2', ['H', 'B'])
                ->orderByRaw("CASE WHEN actype2='H' THEN 0 ELSE 1 END, accode")
                ->get(['accode as code', 'name', 'actype2'])
                ->map(fn ($r) => ['code' => trim($r->code), 'name' => trim($r->name ?? ''), 'type' => $r->actype2])
                ->toArray();
        }

        $states = [];
        if ($this->hasTable('statestate')) {
            $states = DB::table('statestate')->orderBy('name')->get(['code', 'name'])->toArray();
        } elseif ($this->hasTable('state')) {
            $states = DB::table('state')->orderBy('name')->get(['code', 'name'])->toArray();
        }

        $software = $this->loadSoftwareSettings();

        $titles = [
            'bill'    => 'Purchase Return Bill',
            'edit'    => 'Edit Purchase Return Bill',
            'cancel'  => 'Cancel Purchase Return Bill',
            'reprint' => 'Reprint Purchase Return Bill',
        ];

        return view('purchase-return.index', compact(
            'mode', 'rates', 'salesmen', 'counters', 'billTypes',
            'cashBanks', 'states', 'software'
        ) + ['title' => $titles[$mode]]);
    }

    public function picker(Request $request, string $action = 'edit')
    {
        $action = strtolower($action);
        if (!in_array($action, ['edit', 'cancel', 'reprint'], true)) {
            $action = 'edit';
        }

        $titles = [
            'edit' => 'Edit Purchase Return',
            'cancel' => 'Cancel Purchase Return',
            'reprint' => 'Purchase Return Reprint',
        ];

        return view('purchase-bill.doc-picker', [
            'title' => (string) $request->query('title', $titles[$action]),
            'actionMode' => $action,
            'docType' => 'purchase-return',
            'searchUrl' => url('/api/purchase-return/picker-search'),
            'resolveUrl' => url('/api/purchase-return/picker-resolve'),
            'targetBaseUrl' => url('/purchase-return'),
            'showViewOption' => $action === 'reprint',
        ]);
    }

    public function pickerSearch(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        if (!$this->hasTable('purchaserm')) {
            return response()->json(['ok' => true, 'rows' => []]);
        }

        $q = trim((string) $request->query('q', ''));
        $tdate = $this->parseDate((string) $request->query('tdate', ''));
        $action = strtolower(trim((string) $request->query('action', 'edit')));

        $rows = DB::table('purchaserm')
            ->where('pr', 'R')
            ->when($action === 'cancel', fn ($query) => $query->whereRaw('COALESCE(status,1) <> 0'))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('docno', 'like', $q . '%')
                        ->orWhere('name', 'like', '%' . $q . '%');
                });
            })
            ->when($tdate, fn ($query) => $query->whereDate('tdate', $tdate))
            ->orderByDesc('tdate')
            ->orderByDesc('slno')
            ->get(['slno', 'docno', 'tdate', 'name'])
            ->map(fn ($r) => [
                'slno' => (int) ($r->slno ?? 0),
                'doc_no' => trim((string) ($r->docno ?? '')),
                'tdate' => !empty($r->tdate) ? date('d/m/Y', strtotime((string) $r->tdate)) : '',
                'party_name' => trim((string) ($r->name ?? '')),
            ])
            ->values();

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    public function pickerResolve(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $docNo = strtoupper(trim((string) $request->input('doc_no', '')));
        $tdate = $this->parseDate((string) $request->input('tdate', ''));
        $action = strtolower(trim((string) $request->input('action', 'edit')));
        $viewOnly = filter_var($request->input('view_only', false), FILTER_VALIDATE_BOOLEAN);

        if ($docNo === '') {
            return response()->json(['ok' => false, 'message' => 'Doc no is required.'], 422);
        }

        if (!$this->hasTable('purchaserm')) {
            return response()->json(['ok' => false, 'message' => 'Purchase return table not found.'], 404);
        }

        $query = DB::table('purchaserm')
            ->where('pr', 'R')
            ->whereRaw('UPPER(TRIM(docno)) = ?', [$docNo]);

        if ($tdate) {
            $query->whereDate('tdate', $tdate);
        }
        if ($action === 'cancel') {
            $query->whereRaw('COALESCE(status,1) <> 0');
        }

        $row = $query->orderByDesc('slno')->first(['docno', 'tdate']);

        if (!$row) {
            return response()->json(['ok' => false, 'message' => 'This bill number does not exist...'], 404);
        }

        $queryArgs = ['doc_no' => trim((string) ($row->docno ?? ''))];
        if ($action === 'reprint' && !$viewOnly) {
            $queryArgs['autoprint'] = '1';
        }

        return response()->json([
            'ok' => true,
            'doc_no' => trim((string) ($row->docno ?? '')),
            'url' => url('/purchase-return/' . $action . '?' . http_build_query($queryArgs)),
        ]);
    }

    // ─── API: Next Bill Number ────────────────────────────────────────────────

    public function nextBillNo(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $billTypeCode = trim((string)$request->query('bill_type', ''));
        $taxPerc      = $this->genDec('PTAX');

        $sw = $this->loadSoftwareSettings();
        $billTypeWise = strtoupper($sw['BILLTYPEWISEBILLNO'] ?? 'N') === 'Y';

        if ($this->shouldUseSecondaryPrefix('purchasereturn')) {
            $secPrefix = $this->secondaryPrefixFor('purchasereturn');
            $current = $this->genInt('PRETURNB');
            $next    = $current + 1;
            $billNo  = $secPrefix . str_pad($next, 5, '0', STR_PAD_LEFT);
            return response()->json(['ok' => true, 'bill_no' => $billNo, 'tax_perc' => $taxPerc]);
        }

        if ($billTypeWise && $billTypeCode !== '' && $this->hasTable('salestype')) {
            $st       = DB::table('salestype')->where('code', $billTypeCode)->first();
            $prprefix = trim((string)($st->prprefix ?? ''));
            if ($prprefix !== '') {
                $current = $this->genInt('PRET' . $prprefix);
                $next    = $current + 1;
                $billNo  = $prprefix . str_pad($next, 5, '0', STR_PAD_LEFT);
                return response()->json(['ok' => true, 'bill_no' => $billNo, 'tax_perc' => $taxPerc]);
            }
        }

        $current = $this->genInt('PRETURNB');
        $prefix  = $this->genStr('PRBPREF') ?: 'PNB/';
        $next    = $current + 1;
        $billNo  = $prefix . str_pad($next, 5, '0', STR_PAD_LEFT);

        return response()->json(['ok' => true, 'bill_no' => $billNo, 'tax_perc' => $taxPerc]);
    }

    // ─── API: Supplier Search ─────────────────────────────────────────────────

    public function supplierSearch(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $q = trim((string)$request->query('q', ''));

        if (!$this->hasTable('clients')) return response()->json(['ok' => true, 'results' => []]);

        $cols  = array_map('strtolower', $this->columnList('clients'));
        $query = DB::table('clients')->orderBy('name');
        if (in_array('ctype', $cols)) $query->where('ctype', 'S');
        if ($q !== '') {
            $query->where(function ($qb) use ($q) {
                $qb->where('code', 'like', "%$q%")
                   ->orWhere('name', 'like', "%$q%")
                   ->orWhere('mobile', 'like', "%$q%");
            });
            $query->limit(30);
        } else {
            $query->limit(100);
        }
        $rows = $query->get(['code', 'name', 'addr1', 'mobile', 'panadhar', 'ctype']);

        return response()->json(['ok' => true, 'results' => $rows]);
    }

    // ─── API: Supplier Details ────────────────────────────────────────────────

    public function supplierDetails(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $code = trim((string)$request->query('code', ''));
        if ($code === '') return response()->json(['ok' => false, 'message' => 'No code']);

        if ($this->hasTable('accountm')) {
            $blocked = DB::table('accountm')->where('accode', $code)->value('blocked');
            if ($blocked === 'Y') {
                return response()->json(['ok' => false, 'blocked' => true, 'message' => 'Party is blocked']);
            }
        }

        if (!$this->hasTable('clients')) return response()->json(['ok' => false, 'message' => 'Invalid supplier']);

        $r = DB::table('clients')->where('code', $code)->first();
        if (!$r) return response()->json(['ok' => false, 'invalid' => true, 'message' => 'Invalid Supplier']);

        $addr = trim(($r->addr1 ?? '') . ' ' . ($r->addr2 ?? ''));
        $ob   = $this->getSupplierBalance($code);

        return response()->json([
            'ok'          => true,
            'code'        => $r->code,
            'name'        => trim($r->name ?? ''),
            'address'     => $addr,
            'mobile'      => trim($r->mobile ?? ''),
            'pan'         => trim($r->panadhar ?? ''),
            'gst_no'      => trim($r->tin ?? ''),
            'state_code'  => trim($r->statecode ?? ''),
            'old_balance' => $ob,
        ]);
    }

    // ─── API: Item Search (popup list) ────────────────────────────────────────

    public function itemSearch(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        if (!$this->hasTable('items')) return response()->json(['ok' => true, 'results' => []]);

        $q    = trim((string)$request->query('q', ''));
        $cols = array_map('strtolower', $this->columnList('items'));

        $query = DB::table('items')
            ->where(function ($qb) use ($q, $cols) {
                if ($q !== '') {
                    $qb->where('code', 'like', "%$q%")
                       ->orWhere('name', 'like', "%$q%");
                    if (in_array('purity', $cols)) $qb->orWhere('purity', 'like', "%$q%");
                }
            })
            ->where('disabled', '!=', 1)
            ->orderBy('name')
            ->limit(200);

        $select = ['code', 'name'];
        foreach (['purity', 'itype', 'touch', 'defstktype'] as $c) {
            if (in_array($c, $cols)) $select[] = $c;
        }
        $g = $this->gilevel;
        foreach (($g === 1 ? ['qty', 'weight'] : ['qtyb', 'weightb']) as $c) {
            if (in_array($c, $cols)) $select[] = $c;
        }

        $rows = $query->get($select)->map(function ($r) use ($g) {
            return [
                'code'    => $r->code,
                'name'    => trim($r->name ?? ''),
                'purity'  => trim($r->purity  ?? ''),
                'itype'   => strtoupper(trim($r->itype   ?? '')),
                'touch'   => (float)($r->touch   ?? 0),
                'stktype' => trim($r->defstktype ?? ''),
                'qty'     => (int)(($g === 1 ? ($r->qty     ?? 0) : ($r->qtyb    ?? 0))),
                'weight'  => (float)(($g === 1 ? ($r->weight  ?? 0) : ($r->weightb ?? 0))),
            ];
        });

        return response()->json(['ok' => true, 'results' => $rows]);
    }

    // ─── API: Item Lookup ─────────────────────────────────────────────────────

    public function itemLookup(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $code      = strtoupper(trim((string)$request->query('code', '')));
        $goldRate  = (float)$request->query('gold_rate', 0);
        $silvRate  = (float)$request->query('silver_rate', 0);
        $platRate  = (float)$request->query('platinum_rate', 0);

        if ($code === '' || !$this->hasTable('items')) {
            return response()->json(['ok' => false, 'message' => 'Item not found']);
        }

        $item = DB::table('items')->where('code', $code)->first();
        if (!$item) return response()->json(['ok' => false, 'message' => 'Item not found']);
        if ((int)($item->disabled ?? 0) === 1) return response()->json(['ok' => false, 'message' => 'Item is disabled']);

        $itype    = strtoupper(trim($item->itype ?? ''));
        $stkinnos = trim($item->stkinnos ?? 'N');
        $stktype  = trim($item->defstktype ?? '');

        $rate = match ($itype) {
            'G' => $goldRate  ?: $this->genDec('GRATE'),
            'S' => $silvRate  ?: $this->genDec('SRATE'),
            'P' => $platRate  ?: $this->genDec('PGRATE'),
            default => 0,
        };

        $g    = $this->gilevel;
        $stkq = (int)(($g === 1 ? $item->qty : $item->qtyb) ?? 0);
        $stkw = (float)(($g === 1 ? $item->weight : $item->weightb) ?? 0);

        return response()->json([
            'ok'        => true,
            'code'      => $item->code,
            'name'      => trim($item->name ?? ''),
            'purity'    => trim($item->purity ?? ''),
            'touch'     => (float)($item->touch ?? 0),
            'itype'     => $itype,
            'rate'      => $rate,
            'stkinnos'  => $stkinnos,
            'stktype'   => $stktype,
            'stock_qty' => $stkq,
            'stock_wgt' => $stkw,
            'cost'      => (float)($item->cost ?? 0),
        ]);
    }

    // ─── API: Recalculate Totals ─────────────────────────────────────────────

    public function recalc(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $data   = $request->all();
        $totals = $this->calcTotals($data);
        return response()->json($totals);
    }

    // ─── API: Save Purchase Return ──────────────────────────────────────────

    public function save(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $this->gsincharge = (string)($request->session()->get('user_code', ''));
        $g = $this->gilevel;

        $mode       = trim((string)$request->input('mode', 'bill'));
        $docNo      = trim((string)$request->input('doc_no', ''));
        $suppBillNo = trim((string)$request->input('supp_bill_no', ''));
        $billDate   = $this->parseDate($request->input('bill_date')) ?? date('Y-m-d');
        $suppCode   = trim((string)$request->input('sup_code', ''));
        $suppName   = trim((string)$request->input('sup_name', ''));
        $addr       = trim((string)$request->input('address', ''));
        $mobile     = trim((string)$request->input('mobile', ''));
        $pan        = trim((string)$request->input('pan', ''));
        $gstNo      = trim((string)$request->input('gst_no', ''));
        $stateCode  = trim((string)$request->input('state_code', ''));
        $smCode     = trim((string)$request->input('sm_code', ''));
        $counter    = trim((string)$request->input('counter', ''));
        $billType   = trim((string)$request->input('btype', ''));
        $note       = trim((string)$request->input('note', ''));
        $chqBank    = trim((string)$request->input('chq_bank', ''));
        $chqNo      = trim((string)$request->input('chq_no', ''));
        $chqDate    = $this->parseDate($request->input('chq_date'));
        $chqPdc     = strtoupper((string)$request->input('chq_pdc', 'N')) === 'Y' ? 'Y' : 'N';
        $interstate  = strtoupper((string)$request->input('interstate', 'N')) === 'Y';
        $taxExt      = strtoupper((string)$request->input('tax_external', 'N')) === 'Y';
        $manualBNo   = !empty($request->input('manual_bill_no'));

        $goldRate   = (float)$request->input('gold_rate', $this->genDec('GRATE'));
        $taxPerc    = (float)$request->input('tax_perc', 0);
        $chqAmt     = (float)$request->input('chq_amt', 0);
        $paidAmt    = (float)$request->input('paid_amt', 0);
        $ob         = (float)$request->input('ob', 0);
        $others     = (float)$request->input('others', 0);

        $items    = (array)$request->input('items', []);

        // Validate items
        $validItems = [];
        foreach ($items as $item) {
            $scode = strtoupper(trim($item['code'] ?? $item['item_code'] ?? ''));
            $dwgt  = (float)($item['weight'] ?? 0);
            $iqty  = (int)($item['qty'] ?? 0);
            if ($scode === '' || ($dwgt + $iqty) <= 0) continue;
            if ((float)($item['rate'] ?? 0) <= 0) {
                return response()->json(['ok' => false, 'message' => "Check Rate for $scode"]);
            }
            $item['code']      = $scode;
            $item['item_code'] = $scode;
            $validItems[] = $item;
        }

        if (empty($validItems)) {
            return response()->json(['ok' => false, 'message' => 'No valid items to save']);
        }

        $billTotal = 0.0;
        foreach ($validItems as $itm) { $billTotal += (float)($itm['amount'] ?? 0); }

        // Purchase return totals: nettotal = billtotal + others + tax
        $totals = $this->calcTotals([
            'bill_total'     => $billTotal,
            'tax_perc'       => $taxPerc,
            'paid_amt'       => $paidAmt,
            'ob'             => $ob,
            'external'       => $taxExt,
            'others'         => $others,
        ]);

        $taxAmt     = $totals['tax_amt'];
        $netTotal   = $totals['net_total'];
        $balance    = $totals['balance'];

        $icontrol = 1;
        $status = ($balance == 0) ? 3 : (($paidAmt > 0) ? 2 : 1);
        $ttime  = date('H:i:s');

        // GST split
        $sgst = $cgst = $igst = 0.0;
        if (!$taxExt) {
            if ($interstate) {
                $igst = $taxAmt;
            } else {
                $sgst = round($taxAmt / 2, 2);
                $cgst = round($taxAmt / 2, 2);
            }
        }

        try {
            DB::beginTransaction();

            // Edit mode: reverse existing then re-insert
            $existingSlno = 0;
            if ($mode === 'edit' && $docNo !== '') {
                $existing = DB::table('purchaserm')->where('docno', $docNo)->where('pr', 'R')->first();
                if ($existing) {
                    $existingSlno = (int)$existing->slno;
                    $this->reverseEditStock($existingSlno);
                }
            }

            // Generate serial number and bill number
            if ($existingSlno > 0) {
                $lslno = $existingSlno;
                $docno = $docNo;
            } else {
                $lslno = $this->incrementGenInt('SERIALNO');
                if ($manualBNo && $docNo !== '') {
                    $docno = $docNo;
                } else {
                    $docno = $this->generateBillNumber($billType);
                }
            }

            // Insert / update purchaserm (pr='R' for purchase return)
            $pmCols = array_map('strtolower', $this->columnList('purchaserm'));
            $purchasermAll = [
                'slno'        => $lslno,
                'docno'       => $docno,
                'billno'      => $suppBillNo,
                'suppcode'    => $suppCode,
                'name'        => $suppName,
                'billamt'     => $billTotal,
                'eamt'        => 0,
                'pamt'        => $paidAmt,
                'addamt'      => $others,
                'status'      => $status,
                'pr'          => 'R',
                'control'     => $icontrol,
                'tdate'       => $billDate,
                'ttime'       => $ttime,
                'rate'        => $goldRate,
                'smcode'      => $smCode,
                'round'       => 0,
                'taxamt'      => $taxAmt,
                'taxperc'     => $taxPerc,
                'netamt'      => $netTotal + $others,
                'ob'          => $ob,
                'ic'          => $this->gsincharge,
                'taxexternal' => $taxExt ? 'Y' : 'N',
                'billtype'    => $billType,
                'addr'        => $addr,
                'note'        => $note,
                'fr'          => 'N',
                'chqbank'     => $chqBank,
                'chqamt'      => $chqAmt,
                'chqno'       => $chqNo,
                'chqdate'     => $chqDate,
                'chqpdc'      => $chqPdc,
                'pan'         => $pan,
                'statecode'   => $stateCode,
                'sgst'        => $sgst,
                'cgst'        => $cgst,
                'igst'        => $igst,
                'mobile'      => $mobile,
                'counter'     => $counter,
                'cst'         => $interstate ? 'Y' : 'N',
            ];
            $purchasermData = array_filter($purchasermAll, fn($k) => in_array($k, $pmCols), ARRAY_FILTER_USE_KEY);

            if ($existingSlno > 0) {
                DB::table('purchaserm')->where('slno', $lslno)->where('pr', 'R')->update($purchasermData);
                DB::table('purchaserd')->where('slno', $lslno)->delete();
            } else {
                DB::table('purchaserm')->insert($purchasermData);
            }

            // Insert purchaserd items + update stock
            $sno = 0;
            foreach ($validItems as $item) {
                $sno++;
                $scode    = strtoupper(trim($item['code'] ?? $item['item_code'] ?? ''));
                $iqty     = (int)($item['qty'] ?? 0);
                $dwgt     = (float)($item['weight'] ?? 0);
                $dlesswgt = (float)($item['lesswgt'] ?? $item['less_wgt'] ?? 0);
                $dlessp   = (float)($item['lessperc'] ?? $item['less_perc'] ?? 0);
                $dstwgt   = (float)($item['stwgt'] ?? $item['stone_wgt'] ?? 0);
                $dstprice = (float)($item['stprice'] ?? $item['stone_price'] ?? 0);
                $drate    = (float)($item['rate'] ?? 0);
                $damount  = (float)($item['amount'] ?? 0);
                $dmud     = (float)($item['mud'] ?? 0);
                $dmc      = (float)($item['mcharge'] ?? 0);
                $dtouch   = (float)($item['touch'] ?? 0);
                $dstktouch = (float)($item['stktouch'] ?? 0);
                $sstktype = trim($item['stktype'] ?? '');
                $siqtype  = trim($item['purity'] ?? $item['iqtype'] ?? '');
                $sname    = trim($item['name'] ?? $item['item_name'] ?? '');

                $dcost = ($dwgt > 0) ? round($damount / $dwgt, 2) : 0;

                $realName = (string)(DB::table('items')->where('code', $scode)->value('name') ?? '');
                if (trim($sname) === trim($realName)) $sname = '';

                static $pdCols = null;
                if ($pdCols === null) $pdCols = array_map('strtolower', $this->columnList('purchaserd'));
                $pdAll = [
                    'slno'      => $lslno,
                    'code'      => $scode,
                    'qty'       => $iqty,
                    'weight'    => $dwgt,
                    'rate'      => $drate,
                    'lesswgt'   => $dlesswgt,
                    'lessperc'  => $dlessp,
                    'amount'    => $damount,
                    'cost'      => $dcost,
                    'stwgt'     => $dstwgt,
                    'stprice'   => $dstprice,
                    'mud'       => $dmud,
                    'name'      => $sname,
                    'sno'       => $sno,
                    'stktype'   => $sstktype,
                    'iqtype'    => $siqtype,
                    'mcharge'   => $dmc,
                    'stktouch'  => $dstktouch,
                    'touch'     => $dtouch,
                    'fr'        => 0,
                ];
                DB::table('purchaserd')->insert(
                    array_filter($pdAll, fn($k) => in_array($k, $pdCols), ARRAY_FILTER_USE_KEY)
                );

                // Purchase return DECREASES stock (returning items to supplier)
                $this->adjustItemStock($scode, $iqty, $dwgt, $dstwgt, $sstktype, '-');
            }

            // Daybook entries
            if ($this->hasTable('daybook')) {
                DB::table('daybook')->where('slno', $lslno)->delete();
            }
            if ($this->hasTable('daybookpart')) {
                DB::table('daybookpart')->where('slno', $lslno)->delete();
            }
            $this->writePurchaseReturnDaybook(
                $lslno, $billDate,
                $suppCode, $suppName, $docno, $suppBillNo,
                $billTotal, $netTotal,
                $paidAmt, $chqAmt, $chqBank, $chqPdc,
                $taxAmt, $others,
                $interstate, $taxExt, $icontrol
            );

            DB::commit();
            $this->logDelpart($request, 'Purchase Return(' . $docno . ') Saved', ['utype' => $mode === 'edit' ? 'E' : 'A', 'ttype' => 'T', 'slno' => $lslno, 'tdate' => $billDate, 'control' => $icontrol]);

            return response()->json([
                'ok'      => true,
                'message' => 'Purchase return saved successfully',
                'doc_no'  => $docno,
                'slno'    => $lslno,
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'message' => 'Save failed: ' . $e->getMessage()]);
        }
    }

    // ─── API: Get Bill ────────────────────────────────────────────────────────

    public function get(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $billNo = trim((string)$request->query('bill_no', ''));
        if ($billNo === '') return response()->json(['ok' => false, 'message' => 'No bill number']);

        if (!$this->hasTable('purchaserm')) return response()->json(['ok' => false, 'message' => 'Table missing']);

        $m = DB::table('purchaserm')->where('docno', $billNo)->where('pr', 'R')->first();
        if (!$m) return response()->json(['ok' => false, 'message' => 'Bill not found']);

        $items = $this->hasTable('purchaserd')
            ? DB::table('purchaserd')->where('slno', $m->slno)->orderBy('sno')->get()->toArray()
            : [];

        $enriched = [];
        foreach ($items as $r) {
            $rn = (string)(DB::table('items')->where('code', $r->code ?? '')->value('name') ?? '');
            $enriched[] = [
                'code'     => $r->code ?? '',
                'name'     => (trim($r->name ?? '') !== '') ? $r->name : $rn,
                'iqtype'   => $r->iqtype ?? '',
                'rate'     => (float)($r->rate ?? 0),
                'qty'      => (int)($r->qty ?? 0),
                'weight'   => (float)($r->weight ?? 0),
                'stwgt'    => (float)($r->stwgt ?? 0),
                'stprice'  => (float)($r->stprice ?? 0),
                'mud'      => (float)($r->mud ?? 0),
                'touch'    => (float)($r->touch ?? 0),
                'lessperc' => (float)($r->lessperc ?? 0),
                'lesswgt'  => (float)($r->lesswgt ?? 0),
                'mcharge'  => (float)($r->mcharge ?? 0),
                'amount'   => (float)($r->amount ?? 0),
                'stktype'  => $r->stktype ?? '',
                'fr'       => $r->fr ?? 'N',
            ];
        }

        $ob      = (float)($m->ob ?? 0);
        $balance = (float)($m->netamt ?? 0) - (float)($m->pamt ?? 0);
        // Purchase return: cb = ob - balance (opposite of purchase)
        $cb      = $ob - $balance;

        return response()->json([
            'ok'         => true,
            'slno'       => $m->slno,
            'doc_no'     => $m->docno,
            'bill_no'    => $m->billno ?? '',
            'date'       => $m->tdate,
            'sup_code'   => $m->suppcode ?? '',
            'sup_name'   => $m->name ?? '',
            'address'    => $m->addr ?? '',
            'mobile'     => $m->mobile ?? '',
            'pan'        => $m->pan ?? '',
            'gst_no'     => $m->gstno ?? '',
            'state_code' => $m->statecode ?? '',
            'salesman'   => $m->smcode ?? '',
            'counter'    => $m->counter ?? '',
            'btype'      => $m->billtype ?? 'Gold',
            'note'       => $m->note ?? '',
            'tax_perc'   => (float)($m->taxperc ?? 0),
            'tax_amt'    => (float)($m->taxamt ?? 0),
            'paid_amt'   => (float)($m->pamt ?? 0),
            'ob'         => $ob,
            'cb'         => $cb,
            'others'     => (float)($m->addamt ?? 0),
            'interstate' => ($m->cst ?? '') === 'Y' ? 'Y' : 'N',
            'external'   => ($m->taxexternal ?? '') === 'Y' ? 'Y' : 'N',
            'status'     => $m->status ?? 1,
            'bill_total' => (float)($m->billamt ?? 0),
            'net_total'  => (float)($m->netamt ?? 0),
            'balance'    => $balance,
            'items'      => $enriched,
        ]);
    }

    // ─── API: Navigate Bills ─────────────────────────────────────────────────

    public function prevBill(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $billNo = trim((string)($request->query('doc_no') ?? $request->query('bill_no', '')));
        if (!$this->hasTable('purchaserm')) return response()->json(['ok' => false, 'message' => 'No table']);

        $current = $billNo !== ''
            ? DB::table('purchaserm')->where('docno', $billNo)->where('pr', 'R')->value('slno')
            : null;

        if ($current) {
            $row = DB::table('purchaserm')
                ->where('pr', 'R')->where('slno', '<', $current)
                ->orderByDesc('slno')->first(['docno']);
        } else {
            $row = DB::table('purchaserm')
                ->where('pr', 'R')
                ->orderByDesc('slno')->first(['docno']);
        }

        if (!$row) return response()->json(['ok' => false, 'message' => 'No previous bill']);
        return response()->json(['ok' => true, 'bill_no' => $row->docno]);
    }

    public function nextBill(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $billNo = trim((string)($request->query('doc_no') ?? $request->query('bill_no', '')));
        if (!$this->hasTable('purchaserm')) return response()->json(['ok' => false, 'message' => 'No table']);

        $current = $billNo !== ''
            ? DB::table('purchaserm')->where('docno', $billNo)->where('pr', 'R')->value('slno')
            : null;

        if ($current) {
            $row = DB::table('purchaserm')
                ->where('pr', 'R')->where('slno', '>', $current)
                ->orderBy('slno')->first(['docno']);
        } else {
            $row = DB::table('purchaserm')
                ->where('pr', 'R')
                ->orderByDesc('slno')->first(['docno']);
        }

        if (!$row) return response()->json(['ok' => false, 'message' => 'No next bill']);
        return response()->json(['ok' => true, 'bill_no' => $row->docno]);
    }

    // ─── API: Search Bills ────────────────────────────────────────────────────

    public function search(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $q = trim((string)$request->query('q', ''));
        if (!$this->hasTable('purchaserm')) return response()->json(['ok' => true, 'rows' => []]);

        $rows = DB::table('purchaserm')
            ->where('pr', 'R')
            ->where(function ($qb) use ($q) {
                $qb->where('docno', 'like', "%$q%")
                   ->orWhere('name', 'like', "%$q%")
                   ->orWhere('billno', 'like', "%$q%");
            })
            ->orderByDesc('slno')
            ->limit(20)
            ->get(['docno', 'tdate', 'name', 'netamt', 'status']);

        $results = $rows->map(fn ($r) => [
            'doc_no'    => $r->docno,
            'date'      => $r->tdate,
            'sup_name'  => $r->name,
            'net_total' => (float)($r->netamt ?? 0),
        ])->values();

        return response()->json(['ok' => true, 'results' => $results]);
    }

    // ─── API: Cancel Bill ─────────────────────────────────────────────────────

    public function cancelBill(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $billNo = trim((string)($request->input('doc_no') ?? $request->input('bill_no', '')));
        $reason = trim((string)$request->input('reason', ''));
        if ($billNo === '') return response()->json(['ok' => false, 'message' => 'No bill number']);

        if (!$this->hasTable('purchaserm')) return response()->json(['ok' => false]);

        $bill = DB::table('purchaserm')
            ->where('docno', $billNo)
            ->where('pr', 'R')
            ->first(['slno', 'status']);

        if (!$bill) return response()->json(['ok' => false, 'message' => 'Bill not found']);
        if ((int)($bill->status ?? 1) === 0) {
            return response()->json(['ok' => false, 'message' => 'Bill already cancelled']);
        }

        $slno = (int) ($bill->slno ?? 0);

        DB::transaction(function () use ($slno, $billNo, $reason) {
            if ($slno > 0) {
                $this->reverseEditStock($slno);

                foreach (['daybook', 'daybookpart', 'pdclist'] as $tbl) {
                    if ($this->hasTable($tbl)) {
                        DB::table($tbl)->where('slno', $slno)->delete();
                    }
                }
            }

            $pmCols = array_map('strtolower', $this->columnList('purchaserm'));
            $cancelData = ['status' => 0];
            if (in_array('note', $pmCols, true)) {
                $cancelData['note'] = ($reason !== '' ? 'CANCELLED: ' . $reason : 'CANCELLED');
            }

            DB::table('purchaserm')
                ->where('docno', $billNo)
                ->where('pr', 'R')
                ->update($cancelData);
        });

        $this->logDelpart($request, 'Purchase Return(' . $billNo . ') Cancelled', ['utype' => 'D', 'ttype' => 'T', 'slno' => $slno]);
        return response()->json(['ok' => true, 'message' => 'Bill cancelled']);
    }

    // ─── API: Reprint ─────────────────────────────────────────────────────────

    public function reprint(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $billNo = trim((string)$request->input('bill_no', ''));
        $fakeReq = Request::create('/api/purchase-return/get', 'GET', ['bill_no' => $billNo]);
        $fakeReq->setSession($request->session());
        return $this->get($fakeReq);
    }

    // ─── Private: Totals Calculation ─────────────────────────────────────────
    // Purchase return: nettotal = billtotal + others + tax; balance = nettotal - paidamt; cb = ob - balance

    private function calcTotals(array $data): array
    {
        $billTotal  = (float)($data['bill_total']  ?? 0);
        $taxPerc    = (float)($data['tax_perc']    ?? 0);
        $paidAmt    = (float)($data['paid_amt']    ?? 0);
        $ob         = (float)($data['ob']          ?? 0);
        $others     = (float)($data['others']      ?? 0);
        $taxExt     = !empty($data['external']);

        // Tax
        if ($taxExt) {
            $taxAmt = 0.0;
        } else {
            $taxAmt = round(($billTotal * $taxPerc) / 100, 2);
        }

        // nettotal = billtotal + tax
        $netTotal = $billTotal + $taxAmt;

        // balance = nettotal + others - paidamt
        $balance = $netTotal + $others - $paidAmt;

        // cb = ob - balance (purchase return reduces what we owe)
        $cb = $ob - $balance;

        return [
            'bill_total'   => round($billTotal, 2),
            'tax_amt'      => $taxAmt,
            'net_total'    => round($netTotal, 2),
            'balance'      => round($balance, 2),
            'cb'           => round($cb, 2),
        ];
    }

    // ─── Private: Stock Update ────────────────────────────────────────────────

    private function adjustItemStock(string $code, int $qty, float $wgt, float $stwgt,
                                     string $stktype, string $direction): void
    {
        if (!$this->hasTable('items')) return;
        $g = $this->gilevel;

        $qtyCol = $g === 1 ? 'qty'    : 'qtyb';
        $wgtCol = $g === 1 ? 'weight' : 'weightb';

        if ($direction === '+') {
            DB::table('items')->where('code', $code)
                ->increment($qtyCol, $qty, [$wgtCol => DB::raw("$wgtCol + $wgt")]);
        } else {
            DB::table('items')->where('code', $code)
                ->decrement($qtyCol, $qty, [$wgtCol => DB::raw("$wgtCol - $wgt")]);
        }

        if ($stktype !== '' && $this->hasTable('itemsstk')) {
            $exists = DB::table('itemsstk')
                ->where('code', $code)->where('stktype', $stktype)->exists();
            if ($exists) {
                if ($direction === '+') {
                    DB::table('itemsstk')
                        ->where('code', $code)->where('stktype', $stktype)
                        ->increment('weight', $wgt, ['qty' => DB::raw("qty + $qty")]);
                } else {
                    DB::table('itemsstk')
                        ->where('code', $code)->where('stktype', $stktype)
                        ->decrement('weight', $wgt, ['qty' => DB::raw("qty - $qty")]);
                }
            }
        }
    }

    // ─── Private: Reverse Edit Stock ─────────────────────────────────────────

    private function reverseEditStock(int $slno): void
    {
        // Purchase return items were subtracted, so add them back
        if ($this->hasTable('purchaserd')) {
            $rows = DB::table('purchaserd')->where('slno', $slno)->get();
            foreach ($rows as $r) {
                $this->adjustItemStock($r->code, (int)($r->qty ?? 0),
                    (float)($r->weight ?? 0), (float)($r->stwgt ?? 0),
                    trim($r->stktype ?? ''), '+');
            }
        }
    }

    // ─── Private: Bill Number Generation ─────────────────────────────────────

    private function generateBillNumber(string $billTypeCode = ''): string
    {
        $sw = $this->loadSoftwareSettings();
        $billTypeWise = strtoupper($sw['BILLTYPEWISEBILLNO'] ?? 'N') === 'Y';

        if ($billTypeWise && $billTypeCode !== '' && $this->hasTable('salestype')) {
            $st       = DB::table('salestype')->where('code', $billTypeCode)->first();
            $prprefix = trim((string)($st->prprefix ?? ''));
            if ($prprefix !== '') {
                $next = $this->incrementGenInt('PRET' . $prprefix);
                return $prprefix . str_pad($next, 5, '0', STR_PAD_LEFT);
            }
        }

        $next   = $this->incrementGenInt('PRETURNB');
        $prefix = $this->genStr('PRBPREF') ?: 'PNB/';
        return $prefix . str_pad($next, 5, '0', STR_PAD_LEFT);
    }

    // ─── Private: generali helpers ────────────────────────────────────────────

    private function genInt(string $code): int
    {
        if (!$this->hasTable('generali')) return 0;
        return (int)(DB::table('generali')->whereRaw('TRIM(code)=?', [$code])->value('cvalue') ?? 0);
    }

    private function incrementGenInt(string $code): int
    {
        if (!$this->hasTable('generali')) return 1;
        $current = $this->genInt($code);
        $maxUsed = 0;
        $tables = $code === 'SERIALNO'
            ? ['salesm', 'salesrm', 'purchasem', 'purchaserm', 'daybook', 'daybookpart', 'orderm', 'smithm', 'refinerym', 'repairm']
            : ['purchasem', 'purchaserm'];
        foreach ($tables as $table) {
            if ($this->hasTable($table) && Schema::hasColumn($table, 'slno')) {
                $maxUsed = max($maxUsed, (int) (DB::table($table)->max('slno') ?? 0));
            }
        }

        $next = max($current, $maxUsed) + 1;
        $updated = DB::table('generali')->whereRaw('TRIM(code)=?', [$code])->update(['cvalue' => $next]);
        if ($updated === 0) {
            DB::table('generali')->insert(['code' => $code, 'cvalue' => $next]);
        }
        return $next;
    }

    private function genDec(string $code): float
    {
        if (!$this->hasTable('generald')) return 0.0;
        return (float)(DB::table('generald')->where('code', $code)->value('cvalue') ?? 0);
    }

    private function genStr(string $code): string
    {
        if (!$this->hasTable('generals')) return '';
        return trim((string)(DB::table('generals')->where('code', $code)->value('cvalue') ?? ''));
    }

    private function parseDate(?string $raw): ?string
    {
        $raw = trim((string)$raw);
        if ($raw === '' || $raw === '00/00/0000' || $raw === '00-00-0000') return null;
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $raw, $m)) {
            if ($m[1] === '00' || $m[2] === '00') return null;
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $raw, $m)) {
            if ($m[1] === '00' || $m[2] === '00') return null;
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) return $raw;
        return null;
    }

    /**
     * Write daybook entries for a purchase return bill.
     * Purchase return is the opposite of purchase:
     *  - Supplier is credited (we return goods, so supplier owes us less)
     *  - Stock decreases
     *  - Purchase account (EP) is debited
     */
    private function writePurchaseReturnDaybook(
        int    $lslno,
        string $billDate,
        string $suppCode,
        string $suppName,
        string $docno,
        string $suppBillNo,
        float  $billTotal,
        float  $netTotal,
        float  $paidAmt,
        float  $chqAmt,
        string $chqBank,
        string $chqPdc,
        float  $taxAmt,
        float  $others,
        bool   $interstate,
        bool   $taxExt,
        int    $control
    ): void {
        if (!$this->hasTable('daybook')) return;

        $dbCols = array_map('strtolower', $this->columnList('daybook'));

        $epAc      = 'EP';
        $cashAc    = 'CASH';
        $cnpAc     = 'CNP';
        $addAc     = 'ADD';
        $sgstAc    = 'SGST';
        $cgstAc    = 'CGST';
        $igstAc    = 'IGST';
        $roundAc   = 'ROUND';

        $sno = 0;

        $ins = function (string $accode, float $amount, string $opaccode) use (
            $lslno, $billDate, $control, $dbCols, &$sno
        ) {
            if (round($amount, 2) == 0.0) return;
            $sno++;
            $row = [];
            if (in_array('slno',     $dbCols)) $row['slno']     = $lslno;
            if (in_array('sno',      $dbCols)) $row['sno']      = $sno;
            if (in_array('tdate',    $dbCols)) $row['tdate']     = $billDate;
            if (in_array('ddate',    $dbCols)) $row['ddate']     = $billDate;
            if (in_array('accode',   $dbCols)) $row['accode']    = mb_substr($accode, 0, 20);
            if (in_array('amount',   $dbCols)) $row['amount']    = round($amount, 2);
            if (in_array('control',  $dbCols)) $row['control']   = $control;
            if (in_array('opaccode', $dbCols)) $row['opaccode']  = mb_substr($opaccode, 0, 20);
            if (in_array('vtype',    $dbCols)) $row['vtype']     = 'PR';
            if (in_array('vno',      $dbCols)) $row['vno']       = $lslno;
            if (in_array('userid',   $dbCols)) $row['userid']    = '';
            DB::table('daybook')->insert($row);
        };

        // Step 1: daybookpart (voucher header)
        if ($this->hasTable('daybookpart')) {
            $dpCols = array_map('strtolower', $this->columnList('daybookpart'));
            $particular = 'By Purchase Return - ' . $docno . ' - ' . $suppBillNo . ' From ' . $suppName;
            $dpRow = [];
            if (in_array('slno',       $dpCols)) $dpRow['slno']       = $lslno;
            if (in_array('tdate',      $dpCols)) $dpRow['tdate']      = $billDate;
            if (in_array('particular', $dpCols)) $dpRow['particular'] = mb_substr($particular, 0, 200);
            if (in_array('vchno',      $dpCols)) $dpRow['vchno']      = $lslno;
            if (in_array('control',    $dpCols)) $dpRow['control']    = $control;
            if (in_array('vtype',      $dpCols)) $dpRow['vtype']      = 'PR';
            if (!empty($dpRow)) DB::table('daybookpart')->insert($dpRow);
        }

        $dacamt   = $netTotal + $others;
        $cashPaid = $paidAmt - $chqAmt;

        // Purchase return entries are opposite of purchase:
        // EP (purchase account) is debited (+billTotal)
        // Supplier is credited (-dacamt, i.e. reducing what we owe)
        // Cash/Cheque is credited if received back

        // Step 2: Purchase account debit (returning goods)
        if ($billTotal > 0) {
            $ins($epAc, $billTotal, $suppCode ?: $epAc);
        }

        // Step 3: Supplier credit (reduce what we owe)
        if ($dacamt != 0.0) {
            $ins($suppCode ?: $epAc, -$dacamt, $epAc);
        }

        // Step 4: Supplier paid back (amount received from supplier)
        if ($paidAmt > 0) {
            $ins($suppCode ?: $epAc, $paidAmt, $epAc);
        }

        // Step 5: Cash received
        if ($cashPaid > 0) {
            $ins($cashAc, -$cashPaid, $epAc);
        }

        // Step 6: Cheque received
        if ($chqAmt > 0) {
            $cbAc = ($chqPdc === 'Y') ? $cnpAc : ($chqBank ?: $cashAc);
            $ins($cbAc, -$chqAmt, $epAc);
        }

        // Step 7: Others / additional amount
        if ($others > 0) {
            $ins($addAc, $others, $epAc);
        }

        // Step 8: Tax (GST) — purchase return reverses input tax credit
        if ($taxAmt > 0 && !$taxExt) {
            if ($interstate) {
                $ins($igstAc, $taxAmt, $epAc);
            } else {
                $half = round($taxAmt / 2, 2);
                $ins($sgstAc, $half, $epAc);
                $ins($cgstAc, $half, $epAc);
            }
        }

        // Step 9: Round (balance entry to make total exactly zero)
        $sum = round((float)(DB::table('daybook')->where('slno', $lslno)->sum('amount') ?? 0), 2);
        if ($sum != 0.0) {
            $sno++;
            $row = [];
            if (in_array('slno',     $dbCols)) $row['slno']     = $lslno;
            if (in_array('sno',      $dbCols)) $row['sno']      = $sno;
            if (in_array('tdate',    $dbCols)) $row['tdate']     = $billDate;
            if (in_array('accode',   $dbCols)) $row['accode']    = $roundAc;
            if (in_array('amount',   $dbCols)) $row['amount']    = -$sum;
            if (in_array('control',  $dbCols)) $row['control']   = $control;
            if (in_array('opaccode', $dbCols)) $row['opaccode']  = $epAc;
            if (in_array('vtype',    $dbCols)) $row['vtype']     = 'PR';
            if (in_array('vno',      $dbCols)) $row['vno']       = $lslno;
            DB::table('daybook')->insert($row);
        }
    }

    private function getSupplierBalance(string $code): float
    {
        if (!$this->hasTable('daybook')) return 0.0;
        return (float)(DB::table('daybook')->where('accode', $code)->sum('amount') ?? 0);
    }

    private function loadSoftwareSettings(): array
    {
        $iniPath = storage_path('app/software-settings.ini');
        $settings = [];
        if (!file_exists($iniPath)) return $settings;
        $raw = file_get_contents($iniPath);
        $section = '';
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === ';' || $line[0] === '#') continue;
            if (preg_match('/^\[(.+)\]$/', $line, $m)) { $section = trim($m[1]); continue; }
            if ($section === 'Software' && str_contains($line, '=')) {
                [$k, $v] = explode('=', $line, 2);
                $settings[strtoupper(trim($k))] = trim($v);
            }
        }
        return $settings;
    }
}
