<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DiamondPurchaseReturnController extends Controller
{
    private int    $gilevel    = 1;
    private string $gsincharge = '';

    private const COUNTER_KEY = 'DPRETURNB';
    private const PREFIX_KEY  = 'DPRBPREF';
    private const DMD_FLAG    = 'Y';

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
            'gold18'   => $this->genDec('G18RATE'),
            'silver'   => $this->genDec('SRATE'),
            'platinum' => $this->genDec('PGRATE'),
        ];

        $salesmen = $this->hasTable('sman')
            ? DB::table('sman')->orderBy('name')->get(['code', 'name'])->toArray()
            : [];

        $software = $this->loadSoftwareSettings();

        $titles = [
            'bill'    => 'Diamond Purchase Return',
            'edit'    => 'Edit Diamond Purchase Return',
            'cancel'  => 'Cancel Diamond Purchase Return',
            'reprint' => 'Reprint Diamond Purchase Return',
        ];

        return view('diamond-purchase-return.index', compact(
            'mode', 'rates', 'salesmen', 'software'
        ) + ['title' => $titles[$mode]]);
    }

    public function picker(Request $request, string $action = 'edit')
    {
        $action = strtolower($action);
        if (!in_array($action, ['edit', 'cancel', 'reprint'], true)) {
            $action = 'edit';
        }

        $titles = [
            'edit' => 'Edit Diamond Purchase Return',
            'cancel' => 'Diamond Purchase Return Cancellation',
            'reprint' => 'Diamond Purchase Return Reprint',
        ];

        return view('purchase-bill.doc-picker', [
            'title' => (string) $request->query('title', $titles[$action]),
            'actionMode' => $action,
            'docType' => 'diamond-purchase-return',
            'searchUrl' => url('/api/diamond-purchase-return/picker-search'),
            'resolveUrl' => url('/api/diamond-purchase-return/picker-resolve'),
            'targetBaseUrl' => url('/diamond-purchase-return'),
            'showViewOption' => $action === 'reprint',
        ]);
    }

    public function pickerSearch(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);
        if (!$this->hasTable('purchaserm')) return response()->json(['ok' => true, 'rows' => []]);

        $q = trim((string) $request->query('q', ''));
        $tdate = $this->parsePickerDate((string) $request->query('tdate', ''));

        $rows = DB::table('purchaserm')
            ->where('dmd', self::DMD_FLAG)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('docno', 'like', $q . '%')
                        ->orWhere('name', 'like', '%' . $q . '%');
                });
            })
            ->when($tdate, fn ($query) => $query->whereDate('tdate', $tdate))
            ->orderByDesc('tdate')
            ->orderByDesc('slno')
            ->limit(50)
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
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);
        if (!$this->hasTable('purchaserm')) {
            return response()->json(['ok' => false, 'message' => 'Diamond purchase return table not found.'], 404);
        }

        $docNo = strtoupper(trim((string) $request->input('doc_no', '')));
        $tdate = $this->parsePickerDate((string) $request->input('tdate', ''));
        $action = strtolower(trim((string) $request->input('action', 'edit')));
        $viewOnly = filter_var($request->input('view_only', false), FILTER_VALIDATE_BOOLEAN);

        if ($docNo === '') {
            return response()->json(['ok' => false, 'message' => 'Doc no is required.'], 422);
        }

        $query = DB::table('purchaserm')
            ->where('dmd', self::DMD_FLAG)
            ->whereRaw('UPPER(TRIM(docno)) = ?', [$docNo]);
        if ($tdate) {
            $query->whereDate('tdate', $tdate);
        }

        $row = $query->orderByDesc('slno')->first(['docno']);
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
            'url' => url('/diamond-purchase-return/' . $action . '?' . http_build_query($queryArgs)),
        ]);
    }

    // ─── API: Next Bill Number ────────────────────────────────────────────────

    public function nextBillNo(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $current = $this->genInt(self::COUNTER_KEY);
        $prefix  = $this->genStr(self::PREFIX_KEY) ?: 'DRB';
        $next    = $current + 1;
        $billNo  = $prefix . '/' . str_pad($next, 5, '0', STR_PAD_LEFT);

        $taxPerc = $this->genDec('PTAX');

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

    // ─── API: Item Search ────────────────────────────────────────────────────

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
            'prate'     => (float)($item->prate ?? 0),
            'defqty'    => (int)($item->defqty ?? 0),
            'iqtype'    => trim($item->defquality ?? ''),
        ]);
    }

    // ─── API: Barcode Lookup ──────────────────────────────────────────────────

    public function barcodeLookup(Request $request)
    {
        $bcode = trim($request->input('bcode', ''));
        if ($bcode === '' || !$this->hasTable('barcode')) {
            return response()->json(['ok' => false]);
        }

        $bc = DB::table('barcode')->where('bcode', $bcode)->first();
        if (!$bc) {
            return response()->json(['ok' => false, 'message' => 'Barcode not found']);
        }

        // From PB bcodechk: also fetch item details + diamond details
        $icode = trim($bc->icode ?? '');
        $itemName = '';
        $itype = '';
        if ($icode !== '' && $this->hasTable('items')) {
            $item = DB::table('items')->where('code', $icode)->first();
            if ($item) {
                $itemName = trim($item->name ?? '');
                $itype = strtoupper(trim($item->itype ?? ''));
            }
        }

        // Diamond sub-details from barcode_dmddet
        $dmdRows = [];
        if ($this->hasTable('barcode_dmddet')) {
            $dmdRows = DB::table('barcode_dmddet')
                ->where('bcode', $bcode)
                ->orderBy('sno')
                ->get()
                ->map(fn($d) => [
                    'stcode'    => trim($d->stcode    ?? ''),
                    'sttype'    => trim($d->sttype    ?? ''),
                    'stcolor'   => trim($d->stcolor   ?? ''),
                    'stsize'    => trim($d->stsize    ?? ''),
                    'stcut'     => trim($d->stcut     ?? ''),
                    'stsettype' => trim($d->stsettype ?? ''),
                    'pcs'       => (int)($d->pcs      ?? 0),
                    'carats'    => (float)($d->carats  ?? 0),
                    'wgt'       => (float)($d->wgt     ?? 0),
                    'rate'      => (float)($d->prate   ?? $d->rate ?? 0),
                    'amount'    => (float)($d->pamt    ?? $d->amount ?? 0),
                    'samount'   => (float)($d->amount  ?? 0),
                ])->toArray();
        }

        // Diamond totals from barcodedmd
        $ddmdamt = 0; $ddmdwgt = 0;
        if ($this->hasTable('barcodedmd')) {
            $bDmd = DB::table('barcodedmd')->where('bcode', $bcode)->first();
            if ($bDmd) {
                $ddmdwgt = (float)($bDmd->dmdwgt ?? 0);
            }
        }
        // Sum dmd amounts from barcode_dmddet
        if ($this->hasTable('barcode_dmddet')) {
            $ddmdamt = (float)(DB::table('barcode_dmddet')->where('bcode', $bcode)->sum('pamt') ?? 0);
        }

        // Check if barcode has purchase reference (rslno)
        $rslno = (int)($bc->rslno ?? 0);
        $purchRate = 0; $purchStprice = 0; $purchMcharge = 0; $purchAmount = 0; $purchPurity = '';
        if ($rslno > 0 && $this->hasTable('purchased')) {
            $pRow = DB::table('purchased')
                ->where('slno', $rslno)->where('code', $icode)->where('bcode', $bcode)
                ->first();
            if ($pRow) {
                $purchRate    = (float)($pRow->rate    ?? 0);
                $purchStprice = (float)($pRow->stprice ?? 0);
                $purchMcharge = (float)($pRow->mcharge ?? 0);
                $purchAmount  = (float)($pRow->amount  ?? 0);
                $purchPurity  = trim($pRow->purity     ?? '');
            }
        }

        return response()->json([
            'ok'        => true,
            'bcode'     => $bcode,
            'icode'     => $icode,
            'name'      => $itemName,
            'itype'     => $itype,
            'qty'       => (int)($bc->qty       ?? 0),
            'weight'    => (float)($bc->weight   ?? 0),
            'stweight'  => (float)($bc->stweight ?? 0),
            'stprice'   => $purchStprice > 0 ? $purchStprice : (float)($bc->stprice  ?? 0),
            'wastage'   => (float)($bc->wastage  ?? 0),
            'mcrate'    => (float)($bc->mcrate   ?? 0),
            'costmc'    => (float)($bc->costmc   ?? 0),
            'rate'      => $purchRate > 0 ? $purchRate : (float)($bc->rate ?? 0),
            'vap'       => (float)($bc->vap      ?? 0),
            'tamt'      => (float)($bc->tamt     ?? 0),
            'huid'      => trim($bc->huid        ?? ''),
            'stkinnos'  => trim($bc->stkinnos    ?? 'N'),
            'stktouch'  => (float)($bc->stktouch ?? 0),
            'cost'      => (float)($bc->cost     ?? 0),
            'stk'       => trim($bc->stk         ?? ''),
            'part'      => trim($bc->note        ?? $bc->part ?? ''),
            'qtype'     => trim($bc->qtype       ?? ''),
            'transtouch' => (float)($bc->transtouch ?? 0),
            'smithcode' => trim($bc->smithcode   ?? ''),
            'dmdamt'    => $ddmdamt,
            'dmdwgt'    => $ddmdwgt,
            'mcharge'   => $purchMcharge > 0 ? $purchMcharge : (float)($bc->costmc ?? 0),
            'amount'    => $purchAmount,
            'purity'    => $purchPurity ?: trim($bc->qtype ?? ''),
            'rslno'     => $rslno,
            'dmd_rows'  => $dmdRows,
        ]);
    }

    // ─── API: Next Barcode Number ─────────────────────────────────────────────

    public function nextBarcode(Request $request)
    {
        $lbcode = 0;
        if ($this->hasTable('barcode')) {
            $lbcode = (int)(DB::table('barcode')->max('bcode') ?? 0);
        }
        if ($this->hasTable('generali')) {
            $bcno = (int)(DB::table('generali')->where('code', 'BCNO')->value('cvalue') ?? 0);
            if ($bcno > $lbcode) $lbcode = $bcno;
        }
        if ($lbcode == 0) $lbcode = 1000000;
        $lbcode++;

        return response()->json(['ok' => true, 'bcode' => $lbcode]);
    }

    // ─── API: Save Diamond Purchase Return ───────────────────────────────────

    public function save(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $this->gsincharge = (string)($request->session()->get('user_code', ''));

        $mode       = trim((string)$request->input('mode', 'bill'));
        $docNo      = trim((string)$request->input('doc_no', ''));
        $suppBillNo = trim((string)$request->input('supp_bill_no', ''));
        $billDate   = $this->parseDate($request->input('bill_date')) ?? date('Y-m-d');
        $suppCode   = trim((string)$request->input('sup_code', ''));
        $suppName   = trim((string)$request->input('sup_name', ''));
        $smCode     = trim((string)$request->input('sm_code', ''));
        $dueDate    = $this->parseDate($request->input('due_date'));
        $billType   = trim((string)$request->input('btype', ''));
        $taxExt     = strtoupper((string)$request->input('tax_external', 'N')) === 'Y';
        $cst        = strtoupper((string)$request->input('cst', 'N')) === 'Y';

        $goldRate   = (float)$request->input('gold_rate', $this->genDec('GRATE'));
        $taxPerc    = (float)$request->input('tax_perc', 0);
        $paidAmt    = (float)$request->input('paid_amt', 0);
        $ob         = (float)$request->input('ob', 0);
        $others     = (float)$request->input('others', 0);
        $exchAmt    = (float)$request->input('exchange_amt', 0);

        $items      = (array)$request->input('items', []);
        $dmdDetails = (array)$request->input('dmd_details', []);

        // Validate items
        $validItems = [];
        foreach ($items as $item) {
            $scode = strtoupper(trim($item['code'] ?? $item['item_code'] ?? ''));
            $dwgt  = (float)($item['weight'] ?? 0);
            $iqty  = (int)($item['qty'] ?? 0);
            if ($scode === '' || ($dwgt + $iqty) <= 0) continue;
            $item['code']      = $scode;
            $item['item_code'] = $scode;
            $validItems[] = $item;
        }

        if (empty($validItems)) {
            return response()->json(['ok' => false, 'message' => 'No valid items to save']);
        }

        $billTotal = 0.0;
        foreach ($validItems as $itm) { $billTotal += (float)($itm['amount'] ?? 0); }

        // Balance calc per PB: nettot = billtotal - exchange + tax
        $taxAmt = 0.0;
        if (!$taxExt) {
            $taxAmt = round(($billTotal * $taxPerc) / 100, 2);
        }
        $netTotal = $billTotal - $exchAmt + $taxAmt;
        $netWithOthers = $netTotal + $others;

        // On new bill, paid = net+others (PB logic: rparm=1 and saded=A)
        if ($mode === 'bill' && $paidAmt == 0) {
            $paidAmt = $netWithOthers;
        }

        $balance = $netWithOthers - $paidAmt;
        $cb      = $ob - $balance;

        $icontrol = 1;
        $status   = ($balance == 0) ? 3 : (($paidAmt > 0) ? 2 : 1);
        $ttime    = date('H:i:s');

        // Tax breakdown
        $sgst = $cgst = $igst = 0.0;
        if (!$taxExt && $taxAmt > 0) {
            if ($cst) {
                $igst = $taxAmt;
            } else {
                $sgst = round($taxAmt / 2, 2);
                $cgst = round($taxAmt / 2, 2);
            }
        }

        try {
            DB::beginTransaction();

            $existingSlno = 0;
            if ($mode === 'edit' && $docNo !== '') {
                $existing = DB::table('purchaserm')
                    ->where('docno', $docNo)->where('dmd', self::DMD_FLAG)
                    ->first();
                if ($existing) {
                    $existingSlno = (int)$existing->slno;
                    $this->reverseEditStock($existingSlno);
                }
            }

            if ($existingSlno > 0) {
                $lslno = $existingSlno;
                $docno = $docNo;
            } else {
                $lslno = $this->incrementGenInt('SERIALNO');
                $docno = $this->generateBillNumber();
            }

            $prmCols = array_map('strtolower', $this->columnList('purchaserm'));
            $purchasermAll = [
                'slno'        => $lslno,
                'docno'       => $docno,
                'billno'      => $suppBillNo,
                'suppcode'    => $suppCode,
                'name'        => $suppName,
                'billamt'     => $billTotal,
                'ramt'        => $paidAmt,
                'addamt'      => $others,
                'lessamt'     => $exchAmt,
                'status'      => $status,
                'pr'          => 'R',
                'dmd'         => self::DMD_FLAG,
                'control'     => $icontrol,
                'tdate'       => $billDate,
                'ttime'       => $ttime,
                'rate'        => $goldRate,
                'smcode'      => $smCode,
                'ob'          => $ob,
                'round'       => 0,
                'netamt'      => $netWithOthers,
                'taxamt'      => $taxAmt,
                'taxperc'     => $taxPerc,
                'astamt'      => 0,
                'ic'          => $this->gsincharge,
                'taxexternal' => $taxExt ? 'Y' : 'N',
                'billtype'    => $billType,
                'sgst'        => $sgst,
                'cgst'        => $cgst,
                'igst'        => $igst,
                'cst'         => $cst ? 'Y' : 'N',
            ];
            $purchasermData = array_filter($purchasermAll, fn($k) => in_array($k, $prmCols), ARRAY_FILTER_USE_KEY);

            if ($existingSlno > 0) {
                DB::table('purchaserm')->where('slno', $lslno)->update($purchasermData);
                DB::table('purchaserd')->where('slno', $lslno)->delete();
                if ($this->hasTable('purchased_dmddet')) {
                    DB::table('purchased_dmddet')->where('slno', $lslno)->delete();
                }
            } else {
                DB::table('purchaserm')->insert($purchasermData);
            }

            // Insert purchaserd items + diamond details
            $dmdCols = null;
            if ($this->hasTable('purchased_dmddet')) {
                $dmdCols = array_map('strtolower', $this->columnList('purchased_dmddet'));
            }

            $prdCols = array_map('strtolower', $this->columnList('purchaserd'));
            $sno = 0;
            foreach ($validItems as $idx => $item) {
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
                $smark    = trim($item['mark'] ?? '');
                $sname    = trim($item['name'] ?? $item['item_name'] ?? '');
                $ddmdamt  = (float)($item['dmdamt']   ?? 0);
                $ddmdwgt  = (float)($item['dmdwgt']   ?? 0);
                $dstkinnos = trim($item['stkinnos']   ?? 'N');
                $dbarcode = trim($item['barcode']     ?? '');

                $dcost = ($dwgt > 0) ? round($damount / $dwgt, 2) : 0;

                $realName = (string)(DB::table('items')->where('code', $scode)->value('name') ?? '');
                if (trim($sname) === trim($realName)) $sname = '';

                $prdAll = [
                    'slno'     => $lslno,
                    'code'     => $scode,
                    'qty'      => $iqty,
                    'weight'   => $dwgt,
                    'rate'     => $drate,
                    'lesswgt'  => $dlesswgt,
                    'lessperc' => $dlessp,
                    'amount'   => $damount,
                    'cost'     => $dcost,
                    'stwgt'    => $dstwgt,
                    'stprice'  => $dstprice,
                    'mud'      => $dmud,
                    'name'     => $sname,
                    'sno'      => $sno,
                    'mark'     => $smark,
                    'stktype'  => $sstktype,
                    'iqtype'   => $siqtype,
                    'mcharge'  => $dmc,
                    'stktouch' => $dstktouch,
                    'touch'    => $dtouch,
                    'fr'       => 0,
                    'bcode'    => $dbarcode,
                    'dmdamt'   => $ddmdamt,
                    'dmdwgt'   => $ddmdwgt,
                    'purity'   => $siqtype,
                    'stkinnos' => $dstkinnos,
                ];
                DB::table('purchaserd')->insert(
                    array_filter($prdAll, fn($k) => in_array($k, $prdCols), ARRAY_FILTER_USE_KEY)
                );

                // Save diamond sub-rows to purchased_dmddet
                if ($dmdCols !== null) {
                    $subRows = $dmdDetails[$idx] ?? [];
                    $dSno = 0;
                    foreach ($subRows as $dr) {
                        if (empty($dr['pcs']) && empty($dr['carats'])) continue;
                        $dSno++;
                        $dAll = [
                            'slno'      => $lslno,
                            'prow'      => $sno,
                            'sno'       => $dSno,
                            'sttype'    => trim($dr['sttype']    ?? ''),
                            'stcolor'   => trim($dr['stcolor']   ?? ''),
                            'stsize'    => trim($dr['stsize']    ?? ''),
                            'stcut'     => trim($dr['stcut']     ?? ''),
                            'stsettype' => trim($dr['stsettype'] ?? ''),
                            'pcs'       => (int)($dr['pcs']      ?? 0),
                            'carats'    => (float)($dr['carats'] ?? 0),
                            'rate'      => (float)($dr['rate']   ?? 0),
                            'amount'    => (float)($dr['amount'] ?? 0),
                        ];
                        if (in_array('code', $dmdCols)) $dAll['code'] = trim($dr['stcode'] ?? '');

                        DB::table('purchased_dmddet')->insert(
                            array_filter($dAll, fn($k) => in_array($k, $dmdCols), ARRAY_FILTER_USE_KEY)
                        );
                    }
                }

                // Stock: Return means items go back to supplier → decrease stock
                $this->adjustItemStock($scode, $iqty, $dwgt, $dstwgt, $sstktype, '-');
            }

            // Daybook entry
            if ($this->hasTable('daybook')) {
                DB::table('daybook')->where('slno', $lslno)->delete();
            }
            if ($this->hasTable('daybookpart')) {
                DB::table('daybookpart')->where('slno', $lslno)->delete();
            }
            $this->writeReturnDaybook(
                $lslno, $billDate, $suppCode, $suppName, $docno, $suppBillNo,
                $billTotal, $netTotal, $exchAmt, $paidAmt, $taxAmt,
                $others, $cst, $taxExt, $icontrol
            );

            DB::commit();

            return response()->json([
                'ok'      => true,
                'message' => 'Diamond purchase return saved successfully',
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

        $m = DB::table('purchaserm')
            ->where('docno', $billNo)->where('dmd', self::DMD_FLAG)
            ->first();
        if (!$m) return response()->json(['ok' => false, 'message' => 'Bill not found']);

        $items = $this->hasTable('purchaserd')
            ? DB::table('purchaserd')->where('slno', $m->slno)->orderBy('sno')->get()->toArray()
            : [];

        // Load diamond sub-rows grouped by prow
        $dmdRowsMap = [];
        if ($this->hasTable('purchased_dmddet')) {
            $allDmd = DB::table('purchased_dmddet')
                ->where('slno', $m->slno)
                ->orderBy('prow')->orderBy('sno')
                ->get()->toArray();
            foreach ($allDmd as $dr) {
                $prow = (int)($dr->prow ?? 0);
                $dmdRowsMap[$prow][] = [
                    'stcode'    => trim($dr->code     ?? ''),
                    'sttype'    => trim($dr->sttype    ?? ''),
                    'stcolor'   => trim($dr->stcolor   ?? ''),
                    'stsize'    => trim($dr->stsize    ?? ''),
                    'stcut'     => trim($dr->stcut     ?? ''),
                    'stsettype' => trim($dr->stsettype ?? ''),
                    'pcs'       => (int)($dr->pcs      ?? 0),
                    'carats'    => (float)($dr->carats  ?? 0),
                    'rate'      => (float)($dr->rate    ?? 0),
                    'amount'    => (float)($dr->amount  ?? 0),
                ];
            }
        }

        $hasBarcodeTable = $this->hasTable('barcode');
        $enriched = [];
        $snoIdx = 0;
        foreach ($items as $r) {
            $snoIdx++;
            $rn = (string)(DB::table('items')->where('code', $r->code ?? '')->value('name') ?? '');

            // Fetch svaperc, salesamt, huid from barcode table using bcode
            $bcode = trim($r->bcode ?? '');
            $svaperc = 0; $salesamt = 0; $huid = '';
            if ($bcode !== '' && $hasBarcodeTable) {
                $bc = DB::table('barcode')->where('bcode', $bcode)->first();
                if ($bc) {
                    $svaperc  = (float)($bc->vap   ?? 0);
                    $salesamt = (float)($bc->tamt   ?? 0);
                    $huid     = trim($bc->huid      ?? '');
                }
            }

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
                'stkinnos' => trim($r->stkinnos ?? 'N'),
                'dmdamt'   => (float)($r->dmdamt ?? 0),
                'dmdwgt'   => (float)($r->dmdwgt ?? 0),
                'barcode'  => $bcode,
                'svaperc'  => $svaperc,
                'salesamt' => $salesamt,
                'huid'     => $huid,
                'purity'   => trim($r->purity ?? $r->iqtype ?? ''),
                'dmd_rows' => $dmdRowsMap[$snoIdx] ?? [],
            ];
        }

        $ob      = (float)($m->ob ?? 0);
        $balance = (float)($m->netamt ?? 0) - (float)($m->ramt ?? 0);
        $cb      = $ob - $balance;

        return response()->json([
            'ok'         => true,
            'slno'       => $m->slno,
            'doc_no'     => $m->docno,
            'bill_no'    => $m->billno ?? '',
            'date'       => $m->tdate,
            'sup_code'   => $m->suppcode ?? '',
            'sup_name'   => $m->name ?? '',
            'salesman'   => $m->smcode ?? '',
            'btype'      => $m->billtype ?? '',
            'tax_perc'   => (float)($m->taxperc ?? 0),
            'tax_amt'    => (float)($m->taxamt ?? 0),
            'paid_amt'   => (float)($m->ramt ?? 0),
            'ob'         => $ob,
            'cb'         => $cb,
            'others'     => (float)($m->addamt ?? 0),
            'exchange_amt' => (float)($m->lessamt ?? 0),
            'external'   => ($m->taxexternal ?? '') === 'Y' ? 'Y' : 'N',
            'cst'        => ($m->cst ?? '') === 'Y' ? 'Y' : 'N',
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
            ? DB::table('purchaserm')->where('docno', $billNo)->where('dmd', self::DMD_FLAG)->value('slno')
            : null;

        if ($current) {
            $row = DB::table('purchaserm')
                ->where('dmd', self::DMD_FLAG)->where('slno', '<', $current)
                ->orderByDesc('slno')->first(['docno']);
        } else {
            $row = DB::table('purchaserm')
                ->where('dmd', self::DMD_FLAG)
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
            ? DB::table('purchaserm')->where('docno', $billNo)->where('dmd', self::DMD_FLAG)->value('slno')
            : null;

        if ($current) {
            $row = DB::table('purchaserm')
                ->where('dmd', self::DMD_FLAG)->where('slno', '>', $current)
                ->orderBy('slno')->first(['docno']);
        } else {
            $row = DB::table('purchaserm')
                ->where('dmd', self::DMD_FLAG)
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
            ->where('dmd', self::DMD_FLAG)
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

        $affected = DB::table('purchaserm')
            ->where('docno', $billNo)->where('dmd', self::DMD_FLAG)
            ->update(['status' => 0]);

        if ($affected === 0) return response()->json(['ok' => false, 'message' => 'Bill not found']);
        return response()->json(['ok' => true, 'message' => 'Bill cancelled']);
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
        if ($this->hasTable('purchaserd')) {
            $rows = DB::table('purchaserd')->where('slno', $slno)->get();
            foreach ($rows as $r) {
                // Reverse the return: return decreased stock, so reverse = increase
                $this->adjustItemStock($r->code, (int)($r->qty ?? 0),
                    (float)($r->weight ?? 0), (float)($r->stwgt ?? 0),
                    trim($r->stktype ?? ''), '+');
            }
        }
    }

    // ─── Private: Bill Number Generation ─────────────────────────────────────

    private function generateBillNumber(): string
    {
        $next   = $this->incrementGenInt(self::COUNTER_KEY);
        $prefix = $this->genStr(self::PREFIX_KEY) ?: 'DRB';
        return $prefix . '/' . str_pad($next, 5, '0', STR_PAD_LEFT);
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
        $tables = $code === 'SERIALNO'
            ? ['salesm', 'salesrm', 'purchasem', 'purchaserm', 'daybook', 'daybookpart', 'orderm', 'smithm', 'refinerym', 'repairm']
            : ['purchasem', 'purchaserm'];

        $maxUsed = 0;
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

    private function getSupplierBalance(string $code): float
    {
        if (!$this->hasTable('daybook')) return 0.0;
        return (float)(DB::table('daybook')->where('accode', $code)->sum('amount') ?? 0);
    }

    private function loadSoftwareSettings(): array
    {
        $settings = [];
        if (!$this->hasTable('generals')) return $settings;
        $rows = DB::table('generals')->get(['code', 'cvalue']);
        foreach ($rows as $r) {
            $settings[strtoupper(trim($r->code))] = trim($r->cvalue ?? '');
        }
        return $settings;
    }

    // ─── Private: Return Daybook ─────────────────────────────────────────────

    private function writeReturnDaybook(
        int    $lslno,
        string $billDate,
        string $suppCode,
        string $suppName,
        string $docno,
        string $suppBillNo,
        float  $billTotal,
        float  $netTotal,
        float  $exchAmt,
        float  $paidAmt,
        float  $taxAmt,
        float  $others,
        bool   $cst,
        bool   $taxExt,
        int    $control
    ): void {
        if (!$this->hasTable('daybook')) return;

        $dbCols = array_map('strtolower', $this->columnList('daybook'));

        $epAc    = 'EP';
        $cashAc  = 'CASH';
        $sgstAc  = 'SGST';
        $cgstAc  = 'CGST';
        $igstAc  = 'IGST';
        $addAc   = 'ADD';
        $roundAc = 'ROUND';

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
            DB::table('daybook')->insert($row);
        };

        if ($this->hasTable('daybookpart')) {
            $dpCols = array_map('strtolower', $this->columnList('daybookpart'));
            $particular = 'By Diamond Purchase Return - ' . $docno . ' - ' . $suppBillNo . ' From ' . $suppName;
            $dpRow = [];
            if (in_array('slno',       $dpCols)) $dpRow['slno']       = $lslno;
            if (in_array('tdate',      $dpCols)) $dpRow['tdate']      = $billDate;
            if (in_array('particular', $dpCols)) $dpRow['particular'] = mb_substr($particular, 0, 200);
            if (in_array('vchno',      $dpCols)) $dpRow['vchno']      = $lslno;
            if (in_array('control',    $dpCols)) $dpRow['control']    = $control;
            if (in_array('vtype',      $dpCols)) $dpRow['vtype']      = 'PR';
            if (!empty($dpRow)) DB::table('daybookpart')->insert($dpRow);
        }

        $dacamt = $netTotal + $others;

        // Return daybook is reverse of purchase
        if ($paidAmt > 0) $ins($cashAc, -$paidAmt, $epAc);
        if ($exchAmt > 0) $ins($epAc, -$exchAmt, $epAc);
        if ($dacamt != 0.0) $ins($suppCode ?: $epAc, -$dacamt, $epAc);
        if ($paidAmt > 0) $ins($suppCode ?: $epAc, $paidAmt, $epAc);
        if ($others > 0) $ins($addAc, $others, $epAc);

        $totalTax = $taxAmt;
        if ($totalTax > 0 && !$taxExt) {
            if ($cst) {
                $ins($igstAc, $totalTax, $epAc);
            } else {
                $half = round($totalTax / 2, 2);
                $ins($sgstAc, $half, $epAc);
                $ins($cgstAc, $half, $epAc);
            }
        }

        if ($billTotal > 0) {
            $ins($epAc, $billTotal, $suppCode ?: $epAc);
        }

        // Rounding entry
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
}
