<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DiamondPurchaseBillController extends Controller
{
    private int    $gilevel    = 1;
    private string $gsincharge = '';

    private const COUNTER_KEY = 'DPURCHASEB';
    private const PREFIX_KEY  = 'DPBPREF';
    private const DMD_FLAG    = 'Y';
    private const PR_FLAG     = 'P';

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

        $mcRows = [];
        if ($this->hasTable('mctable')) {
            $mcCols = array_map('strtolower', $this->columnList('mctable'));
            $mcQ = DB::table('mctable');
            if (in_array('fromwgt', $mcCols)) $mcQ->orderBy('fromwgt');
            $mcRows = $mcQ->get()->toArray();
        }

        $software = $this->loadSoftwareSettings();

        $titles = [
            'bill'    => 'Diamond Purchase Bill',
            'edit'    => 'Edit Diamond Purchase Bill',
            'cancel'  => 'Cancel Diamond Purchase Bill',
            'reprint' => 'Reprint Diamond Purchase Bill',
        ];

        return view('diamond-purchase.index', compact(
            'mode', 'rates', 'salesmen', 'counters', 'billTypes',
            'cashBanks', 'states', 'mcRows', 'software'
        ) + ['title' => $titles[$mode]]);
    }

    public function picker(Request $request, string $action = 'edit')
    {
        $action = strtolower($action);
        if (!in_array($action, ['edit', 'cancel', 'reprint'], true)) {
            $action = 'edit';
        }

        $titles = [
            'edit' => 'Edit Diamond Purchase Bill',
            'cancel' => 'Diamond Purchase Cancellation',
            'reprint' => 'Diamond Purchase Reprint',
        ];

        return view('purchase-bill.doc-picker', [
            'title' => (string) $request->query('title', $titles[$action]),
            'actionMode' => $action,
            'docType' => 'diamond-purchase',
            'searchUrl' => url('/api/diamond-purchase/picker-search'),
            'resolveUrl' => url('/api/diamond-purchase/picker-resolve'),
            'targetBaseUrl' => url('/diamond-purchase'),
            'showViewOption' => $action === 'reprint',
        ]);
    }

    public function pickerSearch(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);
        if (!$this->hasTable('purchasem')) return response()->json(['ok' => true, 'rows' => []]);

        $q = trim((string) $request->query('q', ''));
        $tdate = $this->parsePickerDate((string) $request->query('tdate', ''));

        $rows = DB::table('purchasem')
            ->where('pr', self::PR_FLAG)
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
        if (!$this->hasTable('purchasem')) {
            return response()->json(['ok' => false, 'message' => 'Diamond purchase table not found.'], 404);
        }

        $docNo = strtoupper(trim((string) $request->input('doc_no', '')));
        $tdate = $this->parsePickerDate((string) $request->input('tdate', ''));
        $action = strtolower(trim((string) $request->input('action', 'edit')));
        $viewOnly = filter_var($request->input('view_only', false), FILTER_VALIDATE_BOOLEAN);

        if ($docNo === '') {
            return response()->json(['ok' => false, 'message' => 'Doc no is required.'], 422);
        }

        $query = DB::table('purchasem')
            ->where('pr', self::PR_FLAG)
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
            'url' => url('/diamond-purchase/' . $action . '?' . http_build_query($queryArgs)),
        ]);
    }

    // ─── API: Next Bill Number ────────────────────────────────────────────────

    public function nextBillNo(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $current = $this->genInt(self::COUNTER_KEY);
        $prefix  = $this->genStr(self::PREFIX_KEY);
        $next    = $current + 1;
        $billNo  = $prefix . str_pad($next, 5, '0', STR_PAD_LEFT);

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

    // ─── API: Create Supplier ─────────────────────────────────────────────────

    public function createSupplier(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $code = strtoupper(trim($request->input('code', '')));
        $name = trim($request->input('name', ''));

        if (!$code || !$name) {
            return response()->json(['ok' => false, 'error' => 'Code and Name are required']);
        }
        if (!$this->hasTable('clients')) {
            return response()->json(['ok' => false, 'error' => 'clients table not found']);
        }

        if (DB::table('clients')->where('code', $code)->exists()) {
            return response()->json(['ok' => false, 'error' => 'Supplier code already exists']);
        }

        $cols = array_map('strtolower', $this->columnList('clients'));
        $data = ['code' => $code, 'name' => $name];
        if (in_array('ctype',    $cols)) $data['ctype']    = 'S';
        if (in_array('addr1',    $cols)) $data['addr1']    = trim($request->input('address', ''));
        if (in_array('mobile',   $cols)) $data['mobile']   = trim($request->input('mobile', ''));
        if (in_array('panadhar', $cols)) $data['panadhar'] = trim($request->input('pan', ''));
        if (in_array('tin',      $cols)) $data['tin']      = trim($request->input('gst_no', ''));

        DB::table('clients')->insert($data);

        return response()->json(['ok' => true, 'code' => $code, 'name' => $name]);
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
            'prate'     => (float)($item->prate ?? 0),
            'defqty'    => (int)($item->defqty ?? 0),
            'iqtype'    => trim($item->defquality ?? ''),
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

    // ─── API: Save Diamond Purchase Bill ──────────────────────────────────────

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
        $dueDate    = $this->parseDate($request->input('due_date'));
        $chqBank    = trim((string)$request->input('chq_bank', ''));
        $chqNo      = trim((string)$request->input('chq_no', ''));
        $chqDate    = $this->parseDate($request->input('chq_date'));
        $chqPdc     = strtoupper((string)$request->input('chq_pdc', 'N')) === 'Y' ? 'Y' : 'N';
        $interstate  = strtoupper((string)$request->input('interstate', 'N')) === 'Y';
        $taxExt      = strtoupper((string)$request->input('tax_external', 'N')) === 'Y';
        $taxOnMcOnly = strtoupper((string)$request->input('tax_on_mc', 'N')) === 'Y';
        $manualBNo   = !empty($request->input('manual_bill_no'));

        $goldRate   = (float)$request->input('gold_rate', $this->genDec('GRATE'));
        $taxPerc    = (float)$request->input('tax_perc', 0);
        $discPerc   = (float)$request->input('disc_perc', 0);
        $discount   = (float)$request->input('discount', 0);
        $hmc        = (float)$request->input('hmc', 0);
        $tcsPerc    = (float)$request->input('tcs_perc', 0);
        $chqAmt     = (float)$request->input('chq_amt', 0);
        $paidAmt    = (float)$request->input('paid_amt', 0);
        $ob         = (float)$request->input('ob', 0);
        $others     = (float)$request->input('others', 0);

        $items      = (array)$request->input('items', []);
        $exchItems  = (array)$request->input('exchange_items', []);
        $dmdDetails = (array)$request->input('dmd_details', []);

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

        $exchAmtCalc = 0.0;
        foreach ($exchItems as $ei) { $exchAmtCalc += (float)($ei['amount'] ?? 0); }

        $totals = $this->calcTotals([
            'bill_total'     => $billTotal,
            'exchange_amt'   => $exchAmtCalc,
            'tax_perc'       => $taxPerc,
            'disc_perc'      => $discPerc,
            'discount'       => $discount,
            'hmc'            => $hmc,
            'tcs_perc'       => $tcsPerc,
            'paid_amt'       => $paidAmt,
            'ob'             => $ob,
            'external'       => $taxExt,
            'tax_on_mc'      => $taxOnMcOnly,
            'others'         => $others,
        ]);

        $exchAmt    = $totals['exchange_amt'];
        $taxAmt     = $totals['tax_amt'];
        $cessAmt    = $totals['cess'];
        $tcsAmt     = $totals['tcs_amt'];
        $netTotal   = $totals['net_total'];
        $balance    = $totals['balance'];

        $icontrol = 1;
        $status = ($balance == 0) ? 3 : (($paidAmt > 0) ? 2 : 1);
        $ttime  = date('H:i:s');

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

            $existingSlno = 0;
            if ($mode === 'edit' && $docNo !== '') {
                $existing = DB::table('purchasem')
                    ->where('docno', $docNo)->where('pr', self::PR_FLAG)->where('dmd', self::DMD_FLAG)
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
                if ($manualBNo && $docNo !== '') {
                    $docno = $docNo;
                } else {
                    $docno = $this->generateBillNumber();
                }
            }

            $pmCols = array_map('strtolower', $this->columnList('purchasem'));
            $purchasemAll = [
                'slno'        => $lslno,
                'docno'       => $docno,
                'billno'      => $suppBillNo,
                'suppcode'    => $suppCode,
                'name'        => $suppName,
                'billamt'     => $billTotal,
                'eamt'        => $exchAmt,
                'pamt'        => $paidAmt,
                'addamt'      => $others,
                'status'      => $status,
                'pr'          => self::PR_FLAG,
                'dmd'         => self::DMD_FLAG,
                'control'     => $icontrol,
                'tdate'       => $billDate,
                'ttime'       => $ttime,
                'duedate'     => $dueDate,
                'rate'        => $goldRate,
                'smcode'      => $smCode,
                'round'       => 0,
                'taxamt'      => $taxAmt,
                'taxperc'     => $taxPerc,
                'netamt'      => $netTotal + $others,
                'ob'          => $ob,
                'astamt'      => $cessAmt,
                'ic'          => $this->gsincharge,
                'taxexternal' => $taxExt ? 'Y' : 'N',
                'billtype'    => $billType,
                'discperc'    => $discPerc,
                'discount'    => $discount,
                'addr'        => $addr,
                'note'        => $note,
                'exchslno'    => 0,
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
                'tcsperc'     => $tcsPerc,
                'tcsamt'      => $tcsAmt,
                'hmc'         => $hmc,
                'taxonmconly' => $taxOnMcOnly ? 'Y' : 'N',
            ];
            $purchasemData = array_filter($purchasemAll, fn($k) => in_array($k, $pmCols), ARRAY_FILTER_USE_KEY);

            if ($existingSlno > 0) {
                DB::table('purchasem')->where('slno', $lslno)->update($purchasemData);
                DB::table('purchased')->where('slno', $lslno)->delete();
                DB::table('purchaserm')->where('slno', $lslno)->delete();
                DB::table('purchaserd')->where('slno', $lslno)->delete();
                if ($this->hasTable('purchased_dmddet')) {
                    DB::table('purchased_dmddet')->where('slno', $lslno)->delete();
                }
            } else {
                DB::table('purchasem')->insert($purchasemData);
            }

            // Insert purchased items + diamond details + update stock
            $dmdCols = null;
            if ($this->hasTable('purchased_dmddet')) {
                $dmdCols = array_map('strtolower', $this->columnList('purchased_dmddet'));
            }

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
                $sbatch   = trim($item['batch'] ?? '');

                // Diamond-specific fields
                $dwastage  = (float)($item['wastage']  ?? 0);
                $dmperc    = (float)($item['mperc']    ?? 0);
                $dstkinnos = trim($item['stkinnos']    ?? 'N');
                $ddmdamt   = (float)($item['dmdamt']   ?? 0);
                $ddmdwgt   = (float)($item['dmdwgt']   ?? 0);
                $dsstprice = (float)($item['sstprice'] ?? 0);
                $dsmcharge = (float)($item['smcharge'] ?? 0);
                $dbarcode  = trim($item['barcode']     ?? '');

                $dcost = ($dwgt > 0) ? round($damount / $dwgt, 2) : 0;

                $realName = (string)(DB::table('items')->where('code', $scode)->value('name') ?? '');
                if (trim($sname) === trim($realName)) $sname = '';

                static $pdCols = null;
                if ($pdCols === null) $pdCols = array_map('strtolower', $this->columnList('purchased'));
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
                    'mark'      => $smark,
                    'stktype'   => $sstktype,
                    'iqtype'    => $siqtype,
                    'mcharge'   => $dmc,
                    'stktouch'  => $dstktouch,
                    'touch'     => $dtouch,
                    'batch'     => $sbatch,
                    'fr'        => 0,
                    'wastage'   => $dwastage,
                    'mperc'     => $dmperc,
                    'stkinnos'  => $dstkinnos,
                    'dmdamt'    => $ddmdamt,
                    'dmdwgt'    => $ddmdwgt,
                    'sstprice'  => $dsstprice,
                    'smcharge'  => $dsmcharge,
                    'bcode'     => $dbarcode,
                ];
                DB::table('purchased')->insert(
                    array_filter($pdAll, fn($k) => in_array($k, $pdCols), ARRAY_FILTER_USE_KEY)
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

                // Stock +++
                $this->adjustItemStock($scode, $iqty, $dwgt, $dstwgt, $sstktype, '+');
            }

            // Exchange items
            if ($exchAmt > 0 && !empty($exchItems)) {
                $prmCols = array_map('strtolower', $this->columnList('purchaserm'));
                $prmAll  = [
                    'slno'     => $lslno,
                    'docno'    => $docno,
                    'billno'   => $suppBillNo,
                    'suppcode' => $suppCode,
                    'name'     => $suppName,
                    'billamt'  => $exchAmt,
                    'ramt'     => $exchAmt,
                    'addamt'   => 0,
                    'lessamt'  => 0,
                    'status'   => 3,
                    'pr'       => 'E',
                    'control'  => $icontrol,
                    'tdate'    => $billDate,
                    'ttime'    => $ttime,
                    'rate'     => $goldRate,
                    'smcode'   => $smCode,
                    'round'    => 0,
                    'netamt'   => $exchAmt,
                    'ic'       => $this->gsincharge,
                ];
                DB::table('purchaserm')->insert(
                    array_filter($prmAll, fn($k) => in_array($k, $prmCols), ARRAY_FILTER_USE_KEY)
                );

                $prdCols = array_map('strtolower', $this->columnList('purchaserd'));
                $esno = 0;
                foreach ($exchItems as $ei) {
                    $esno++;
                    $esc   = strtoupper(trim($ei['code'] ?? $ei['item_code'] ?? ''));
                    if ($esc === '') continue;
                    $eqty  = (int)($ei['qty'] ?? 0);
                    $ewgt  = round((float)($ei['weight'] ?? 0), 3);
                    $eless = (float)($ei['lesswgt'] ?? $ei['less_wgt'] ?? 0);
                    $elessp = (float)($ei['lessperc'] ?? $ei['less_perc'] ?? 0);
                    $estwgt = (float)($ei['stwgt'] ?? $ei['stone_wgt'] ?? 0);
                    $estprice = (float)($ei['stprice'] ?? $ei['stone_price'] ?? 0);
                    $erate = (float)($ei['rate'] ?? 0);
                    $eamt  = round((float)($ei['amount'] ?? 0), 2);
                    $emud  = (float)($ei['mud'] ?? 0);
                    $esstktype = trim($ei['stktype'] ?? '');
                    $esname = trim($ei['name'] ?? $ei['item_name'] ?? '');
                    $ecost  = (float)($ei['cost'] ?? 0);

                    $realName = (string)(DB::table('items')->where('code', $esc)->value('name') ?? '');
                    if (trim($esname) === trim($realName)) $esname = '';

                    $prdAll = [
                        'slno'     => $lslno,
                        'code'     => $esc,
                        'qty'      => $eqty,
                        'weight'   => $ewgt,
                        'lesswgt'  => $eless,
                        'lessperc' => $elessp,
                        'rate'     => $erate,
                        'amount'   => $eamt,
                        'cost'     => $ecost,
                        'stwgt'    => $estwgt,
                        'stprice'  => $estprice,
                        'name'     => $esname,
                        'sno'      => $esno,
                        'mud'      => $emud,
                        'mark'     => '',
                        'stktype'  => $esstktype,
                        'stktouch' => 0,
                    ];
                    DB::table('purchaserd')->insert(
                        array_filter($prdAll, fn($k) => in_array($k, $prdCols), ARRAY_FILTER_USE_KEY)
                    );

                    $this->adjustItemStock($esc, $eqty, $ewgt, $estwgt, $esstktype, '-');
                }
            }

            // Daybook
            if ($this->hasTable('daybook')) {
                DB::table('daybook')->where('slno', $lslno)->delete();
            }
            if ($this->hasTable('daybookpart')) {
                DB::table('daybookpart')->where('slno', $lslno)->delete();
            }
            $this->writePurchaseDaybook(
                $lslno, $billDate,
                $suppCode, $suppName, $docno, $suppBillNo,
                $billTotal, $netTotal, $exchAmt,
                $paidAmt, $chqAmt, $chqBank, $chqPdc,
                $totals['discount'], $taxAmt, $cessAmt, $hmc, $tcsAmt, $others,
                $interstate, $taxExt, $icontrol
            );

            DB::commit();

            return response()->json([
                'ok'      => true,
                'message' => 'Diamond purchase bill saved successfully',
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

        if (!$this->hasTable('purchasem')) return response()->json(['ok' => false, 'message' => 'Table missing']);

        $m = DB::table('purchasem')
            ->where('docno', $billNo)->where('pr', self::PR_FLAG)->where('dmd', self::DMD_FLAG)
            ->first();
        if (!$m) return response()->json(['ok' => false, 'message' => 'Bill not found']);

        $items = $this->hasTable('purchased')
            ? DB::table('purchased')->where('slno', $m->slno)->orderBy('sno')->get()->toArray()
            : [];

        $exchItems = $this->hasTable('purchaserd')
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
                    'stcode'    => trim($dr->code    ?? ''),
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

        $enriched = [];
        $snoIdx = 0;
        $hasBarcodeTable = $this->hasTable('barcode');
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
                'wastage'  => (float)($r->wastage  ?? 0),
                'mperc'    => (float)($r->mperc    ?? 0),
                'stkinnos' => trim($r->stkinnos    ?? 'N'),
                'dmdamt'   => (float)($r->dmdamt   ?? 0),
                'dmdwgt'   => (float)($r->dmdwgt   ?? 0),
                'sstprice' => (float)($r->sstprice ?? 0),
                'smcharge' => (float)($r->smcharge ?? 0),
                'barcode'  => $bcode,
                'svaperc'  => $svaperc,
                'salesamt' => $salesamt,
                'huid'     => $huid,
                'dmd_rows' => $dmdRowsMap[$snoIdx] ?? [],
            ];
        }

        $enrichedExch = [];
        foreach ($exchItems as $r) {
            $rn = (string)(DB::table('items')->where('code', $r->code ?? '')->value('name') ?? '');
            $enrichedExch[] = [
                'code'     => $r->code ?? '',
                'name'     => (trim($r->name ?? '') !== '') ? $r->name : $rn,
                'qty'      => (int)($r->qty ?? 0),
                'weight'   => (float)($r->weight ?? 0),
                'lessperc' => (float)($r->lessperc ?? 0),
                'lesswgt'  => (float)($r->lesswgt ?? 0),
                'rate'     => (float)($r->rate ?? 0),
                'stprice'  => (float)($r->stprice ?? 0),
                'amount'   => (float)($r->amount ?? 0),
                'stktype'  => $r->stktype ?? '',
            ];
        }

        $ob      = (float)($m->ob ?? 0);
        $balance = (float)($m->netamt ?? 0) - (float)($m->pamt ?? 0);
        $cb      = $ob + $balance;

        return response()->json([
            'ok'         => true,
            'slno'       => $m->slno,
            'doc_no'     => $m->docno,
            'bill_no'    => $m->billno ?? '',
            'date'       => $m->tdate,
            'order_no'   => '',
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
            'disc_perc'  => (float)($m->discperc ?? 0),
            'discount'   => (float)($m->discount ?? 0),
            'tax_perc'   => (float)($m->taxperc ?? 0),
            'tax_amt'    => (float)($m->taxamt ?? 0),
            'cess'       => (float)($m->astamt ?? 0),
            'hmc'        => (float)($m->hmc ?? 0),
            'tcs_perc'   => (float)($m->tcsperc ?? 0),
            'tcs_amt'    => (float)($m->tcsamt ?? 0),
            'paid_amt'   => (float)($m->pamt ?? 0),
            'ob'         => $ob,
            'cb'         => $cb,
            'others'     => (float)($m->addamt ?? 0),
            'due_date'   => $m->duedate ?? '',
            'chq_bank'   => $m->chqbank ?? '',
            'chq_no'     => $m->chqno ?? '',
            'chq_date'   => $m->chqdate ?? '',
            'chq_amt'    => (float)($m->chqamt ?? 0),
            'interstate' => ($m->cst ?? '') === 'Y' ? 'Y' : 'N',
            'external'   => ($m->taxexternal ?? '') === 'Y' ? 'Y' : 'N',
            'status'     => $m->status ?? 1,
            'bill_total' => (float)($m->billamt ?? 0),
            'exch_amt'   => (float)($m->eamt ?? 0),
            'net_total'  => (float)($m->netamt ?? 0),
            'balance'    => $balance,
            'p_return'   => 0,
            'items'      => $enriched,
            'exch_items' => $enrichedExch,
        ]);
    }

    // ─── API: Navigate Bills ─────────────────────────────────────────────────

    public function prevBill(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $billNo = trim((string)($request->query('doc_no') ?? $request->query('bill_no', '')));
        if (!$this->hasTable('purchasem')) return response()->json(['ok' => false, 'message' => 'No table']);

        $current = $billNo !== ''
            ? DB::table('purchasem')->where('docno', $billNo)->where('pr', self::PR_FLAG)->where('dmd', self::DMD_FLAG)->value('slno')
            : null;

        if ($current) {
            $row = DB::table('purchasem')
                ->where('pr', self::PR_FLAG)->where('dmd', self::DMD_FLAG)->where('slno', '<', $current)
                ->orderByDesc('slno')->first(['docno']);
        } else {
            $row = DB::table('purchasem')
                ->where('pr', self::PR_FLAG)->where('dmd', self::DMD_FLAG)
                ->orderByDesc('slno')->first(['docno']);
        }

        if (!$row) return response()->json(['ok' => false, 'message' => 'No previous bill']);
        return response()->json(['ok' => true, 'bill_no' => $row->docno]);
    }

    public function nextBill(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $billNo = trim((string)($request->query('doc_no') ?? $request->query('bill_no', '')));
        if (!$this->hasTable('purchasem')) return response()->json(['ok' => false, 'message' => 'No table']);

        $current = $billNo !== ''
            ? DB::table('purchasem')->where('docno', $billNo)->where('pr', self::PR_FLAG)->where('dmd', self::DMD_FLAG)->value('slno')
            : null;

        if ($current) {
            $row = DB::table('purchasem')
                ->where('pr', self::PR_FLAG)->where('dmd', self::DMD_FLAG)->where('slno', '>', $current)
                ->orderBy('slno')->first(['docno']);
        } else {
            $row = DB::table('purchasem')
                ->where('pr', self::PR_FLAG)->where('dmd', self::DMD_FLAG)
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
        if (!$this->hasTable('purchasem')) return response()->json(['ok' => true, 'rows' => []]);

        $rows = DB::table('purchasem')
            ->where('pr', self::PR_FLAG)->where('dmd', self::DMD_FLAG)
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

        if (!$this->hasTable('purchasem')) return response()->json(['ok' => false]);

        $affected = DB::table('purchasem')
            ->where('docno', $billNo)->where('pr', self::PR_FLAG)->where('dmd', self::DMD_FLAG)
            ->update(['status' => 0, 'note' => ($reason !== '' ? 'CANCELLED: ' . $reason : 'CANCELLED')]);

        if ($affected === 0) return response()->json(['ok' => false, 'message' => 'Bill not found']);
        return response()->json(['ok' => true, 'message' => 'Bill cancelled']);
    }

    // ─── API: Reprint ─────────────────────────────────────────────────────────

    public function reprint(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $billNo = trim((string)$request->input('bill_no', ''));
        $fakeReq = Request::create('/api/diamond-purchase/get', 'GET', ['bill_no' => $billNo]);
        $fakeReq->setSession($request->session());
        return $this->get($fakeReq);
    }

    // ─── API: Rebuild All Daybook ─────────────────────────────────────────────

    public function rebuildAllDaybook(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        if (!$this->hasTable('purchasem')) {
            return response()->json(['ok' => false, 'message' => 'purchasem table not found']);
        }

        $this->gsincharge = (string)$request->session()->get('user_code', '');

        $bills = DB::table('purchasem')
            ->where('pr', self::PR_FLAG)->where('dmd', self::DMD_FLAG)
            ->orderBy('slno')->get();

        $fixed = 0;
        $errors = [];

        foreach ($bills as $m) {
            try {
                $lslno      = (int)$m->slno;
                $billDate   = $m->tdate ?? date('Y-m-d');
                $suppCode   = trim($m->suppcode ?? '');
                $suppName   = trim($m->name ?? '');
                $docno      = trim($m->docno ?? '');
                $suppBillNo = trim($m->billno ?? '');
                $billTotal  = (float)($m->billamt ?? 0);
                $exchAmt    = (float)($m->eamt    ?? 0);
                $paidAmt    = (float)($m->pamt    ?? 0);
                $chqAmt     = (float)($m->chqamt  ?? 0);
                $chqBank    = trim($m->chqbank ?? '');
                $chqPdc     = strtoupper(trim($m->chqpdc ?? 'N')) === 'Y' ? 'Y' : 'N';
                $discount   = (float)($m->discount ?? 0);
                $taxAmt     = (float)($m->taxamt   ?? 0);
                $cessAmt    = (float)($m->astamt   ?? 0);
                $hmc        = (float)($m->hmc      ?? 0);
                $tcsAmt     = (float)($m->tcsamt   ?? 0);
                $others     = (float)($m->addamt   ?? 0);
                $interstate = strtoupper(trim($m->cst ?? 'N')) === 'Y';
                $taxExt     = strtoupper(trim($m->taxexternal ?? 'N')) === 'Y';
                $control    = (int)($m->control ?? 1);

                $storedNet  = (float)($m->netamt ?? 0);
                $netTotal   = $storedNet - $others;

                DB::beginTransaction();

                if ($this->hasTable('daybook')) {
                    DB::table('daybook')->where('slno', $lslno)->delete();
                }
                if ($this->hasTable('daybookpart')) {
                    DB::table('daybookpart')->where('slno', $lslno)->delete();
                }

                $this->writePurchaseDaybook(
                    $lslno, $billDate,
                    $suppCode, $suppName, $docno, $suppBillNo,
                    $billTotal, $netTotal, $exchAmt,
                    $paidAmt, $chqAmt, $chqBank, $chqPdc,
                    $discount, $taxAmt, $cessAmt, $hmc, $tcsAmt, $others,
                    $interstate, $taxExt, $control
                );

                DB::commit();
                $fixed++;
            } catch (\Throwable $e) {
                DB::rollBack();
                $errors[] = ($m->docno ?? $m->slno) . ': ' . $e->getMessage();
            }
        }

        return response()->json([
            'ok'     => true,
            'fixed'  => $fixed,
            'errors' => $errors,
            'message' => "Rebuilt daybook for {$fixed} diamond purchase bill(s)." .
                         (count($errors) ? ' Errors: ' . implode('; ', $errors) : ''),
        ]);
    }

    // ─── Private: Totals Calculation ─────────────────────────────────────────

    private function calcTotals(array $data): array
    {
        $billTotal  = (float)($data['bill_total']  ?? 0);
        $exchAmt    = (float)($data['exchange_amt'] ?? 0);
        $discPerc   = (float)($data['disc_perc']   ?? 0);
        $discount   = (float)($data['discount']    ?? 0);
        $taxPerc    = (float)($data['tax_perc']    ?? 0);
        $hmc        = (float)($data['hmc']         ?? 0);
        $tcsPerc    = (float)($data['tcs_perc']    ?? 0);
        $paidAmt    = (float)($data['paid_amt']    ?? 0);
        $ob         = (float)($data['ob']          ?? 0);
        $others     = (float)($data['others']      ?? 0);
        $taxExt     = !empty($data['external']);
        $taxDeduct  = !empty($data['tax_deduct_bamt']);
        $taxOnMcOnly = !empty($data['tax_on_mc']);

        if ($discPerc > 0 && $discount == 0.0) {
            $discount = round(($billTotal * $discPerc) / 100, 2);
        }

        $netTotal = $billTotal - $discount - $exchAmt + $hmc;

        if ($taxExt) {
            $taxAmt  = 0.0;
            $cessAmt = 0.0;
        } else {
            $taxBase = $taxOnMcOnly ? $hmc : $netTotal;
            $taxAmt  = round(($taxBase * $taxPerc) / 100, 2);
            $cessAmt = 0.0;
        }

        if ($taxDeduct) {
            $netTotal -= ($taxAmt + $cessAmt);
        } else {
            $netTotal += ($taxAmt + $cessAmt);
        }

        $tcsAmt    = $tcsPerc > 0 ? (float)round(($netTotal * $tcsPerc) / 100, 0) : 0.0;
        $netTotal += $tcsAmt;

        $balance = $netTotal + $others - $paidAmt;
        $cb      = $ob + $balance;

        return [
            'bill_total'   => round($billTotal, 2),
            'exchange_amt' => round($exchAmt, 2),
            'discount'     => round($discount, 2),
            'tax_amt'      => $taxAmt,
            'cess'         => $cessAmt,
            'tcs_amt'      => $tcsAmt,
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
            } else {
                if ($direction === '+') {
                    DB::table('itemsstk')->insert([
                        'code' => $code, 'stktype' => $stktype,
                        'qty' => $qty, 'weight' => $wgt,
                    ]);
                }
            }
        }
    }

    // ─── Private: Reverse Edit Stock ─────────────────────────────────────────

    private function reverseEditStock(int $slno): void
    {
        if ($this->hasTable('purchased')) {
            $rows = DB::table('purchased')->where('slno', $slno)->get();
            foreach ($rows as $r) {
                $this->adjustItemStock($r->code, (int)($r->qty ?? 0),
                    (float)($r->weight ?? 0), (float)($r->stwgt ?? 0),
                    trim($r->stktype ?? ''), '-');
            }
        }
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

    private function generateBillNumber(): string
    {
        $next   = $this->incrementGenInt(self::COUNTER_KEY);
        $prefix = $this->genStr(self::PREFIX_KEY);
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

    private function writePurchaseDaybook(
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
        float  $chqAmt,
        string $chqBank,
        string $chqPdc,
        float  $discount,
        float  $taxAmt,
        float  $cessAmt,
        float  $hmc,
        float  $tcsAmt,
        float  $others,
        bool   $interstate,
        bool   $taxExt,
        int    $control
    ): void {
        if (!$this->hasTable('daybook')) return;

        $dbCols = array_map('strtolower', $this->columnList('daybook'));

        $discAc    = $this->genStr('PDISCAC') ?: 'DISC';
        $epAc      = 'EP';
        $cashAc    = 'CASH';
        $cnpAc     = 'CNP';
        $addAc     = 'ADD';
        $hmcAc     = 'HMC';
        $tcsAc     = 'TCSAC';
        $sgstAc    = 'SGST';
        $cgstAc    = 'CGST';
        $igstAc    = 'IGST';
        $ptaxExpAc = 'PTAXEXP';
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
            if (in_array('tdate',    $dbCols)) $row['tdate']    = $billDate;
            if (in_array('ddate',    $dbCols)) $row['ddate']    = $billDate;
            if (in_array('accode',   $dbCols)) $row['accode']   = mb_substr($accode, 0, 20);
            if (in_array('amount',   $dbCols)) $row['amount']   = round($amount, 2);
            if (in_array('control',  $dbCols)) $row['control']  = $control;
            if (in_array('opaccode', $dbCols)) $row['opaccode'] = mb_substr($opaccode, 0, 20);
            if (in_array('vtype',    $dbCols)) $row['vtype']    = 'PL';
            if (in_array('vno',      $dbCols)) $row['vno']      = $lslno;
            if (in_array('userid',   $dbCols)) $row['userid']   = '';
            DB::table('daybook')->insert($row);
        };

        if ($this->hasTable('daybookpart')) {
            $dpCols = array_map('strtolower', $this->columnList('daybookpart'));
            $particular = 'By Diamond Purchase - ' . $docno . ' - ' . $suppBillNo . ' From ' . $suppName;
            $dpRow = [];
            if (in_array('slno',       $dpCols)) $dpRow['slno']       = $lslno;
            if (in_array('tdate',      $dpCols)) $dpRow['tdate']      = $billDate;
            if (in_array('particular', $dpCols)) $dpRow['particular'] = mb_substr($particular, 0, 200);
            if (in_array('vchno',      $dpCols)) $dpRow['vchno']      = $lslno;
            if (in_array('control',    $dpCols)) $dpRow['control']    = $control;
            if (in_array('vtype',      $dpCols)) $dpRow['vtype']      = 'PL';
            if (!empty($dpRow)) DB::table('daybookpart')->insert($dpRow);
        }

        $dacamt   = $netTotal + $others;
        $cashPaid = $paidAmt - $chqAmt;

        if ($cashPaid > 0) $ins($cashAc, $cashPaid, $epAc);
        if ($chqAmt > 0) {
            $cbAc = ($chqPdc === 'Y') ? $cnpAc : ($chqBank ?: $cashAc);
            $ins($cbAc, $chqAmt, $epAc);
        }
        if ($exchAmt > 0) $ins($epAc, $exchAmt, $epAc);
        if ($dacamt != 0.0) $ins($suppCode ?: $epAc, $dacamt, $epAc);
        if ($paidAmt > 0) $ins($suppCode ?: $epAc, -$paidAmt, $epAc);
        if ($others > 0) $ins($addAc, -$others, $epAc);
        if ($hmc > 0) $ins($hmcAc, -$hmc, $epAc);
        if ($tcsAmt > 0) $ins($tcsAc, -$tcsAmt, $epAc);
        if ($discount > 0) $ins($discAc, $discount, $epAc);

        $totalTax = $taxAmt + $cessAmt;
        if ($totalTax > 0) {
            if ($taxExt) {
                $ins($ptaxExpAc, -$totalTax, $epAc);
            } else {
                if ($interstate) {
                    $ins($igstAc, -$totalTax, $epAc);
                } else {
                    $half = round($totalTax / 2, 2);
                    $ins($sgstAc, -$half, $epAc);
                    $ins($cgstAc, -$half, $epAc);
                }
            }
        }

        if ($billTotal > 0) {
            $epOpAc = $epAc;
            if ($cashPaid > 0) {
                $epOpAc = $cashAc;
            } elseif ($chqAmt > 0) {
                $epOpAc = $chqBank ?: $cashAc;
            } elseif ($suppCode !== '') {
                $epOpAc = $suppCode;
            }
            $ins($epAc, -$billTotal, $epOpAc);
        }

        $sum = round((float)(DB::table('daybook')->where('slno', $lslno)->sum('amount') ?? 0), 2);
        if ($sum != 0.0) {
            $sno++;
            $row = [];
            if (in_array('slno',     $dbCols)) $row['slno']     = $lslno;
            if (in_array('sno',      $dbCols)) $row['sno']      = $sno;
            if (in_array('tdate',    $dbCols)) $row['tdate']    = $billDate;
            if (in_array('accode',   $dbCols)) $row['accode']   = $roundAc;
            if (in_array('amount',   $dbCols)) $row['amount']   = -$sum;
            if (in_array('control',  $dbCols)) $row['control']  = $control;
            if (in_array('opaccode', $dbCols)) $row['opaccode'] = $epAc;
            if (in_array('vtype',    $dbCols)) $row['vtype']    = 'PL';
            if (in_array('vno',      $dbCols)) $row['vno']      = $lslno;
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
        $settings = [];
        if (!$this->hasTable('generals')) return $settings;
        $rows = DB::table('generals')->get(['code', 'cvalue']);
        foreach ($rows as $r) {
            $settings[strtoupper(trim($r->code))] = trim($r->cvalue ?? '');
        }
        return $settings;
    }

    // ─── Barcode Lookup ────────────────────────────────────────────────────
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

        return response()->json([
            'ok'       => true,
            'bcode'    => $bcode,
            'icode'    => trim($bc->icode    ?? ''),
            'weight'   => (float)($bc->weight   ?? 0),
            'stweight' => (float)($bc->stweight ?? 0),
            'stprice'  => (float)($bc->stprice  ?? 0),
            'mc'       => (float)($bc->mc       ?? 0),
            'wastage'  => (float)($bc->wastage  ?? 0),
            'dmdamt'   => (float)($bc->dmdamt   ?? 0),
            'dmdwgt'   => (float)($bc->dmdwgt   ?? 0),
            'rate'     => (float)($bc->rate      ?? 0),
            'vap'      => (float)($bc->vap       ?? 0),
            'tamt'     => (float)($bc->tamt      ?? 0),
            'huid'     => trim($bc->huid         ?? ''),
            'stkinnos' => trim($bc->stkinnos     ?? 'N'),
        ]);
    }

    // ─── Next Barcode Number ───────────────────────────────────────────────
    public function nextBarcode(Request $request)
    {
        $lbcode = 0;

        // Get max bcode from barcode table
        if ($this->hasTable('barcode')) {
            $lbcode = (int)(DB::table('barcode')->max('bcode') ?? 0);
        }

        // Also check generali BCNO
        if ($this->hasTable('generali')) {
            $bcno = (int)(DB::table('generali')->where('code', 'BCNO')->value('cvalue') ?? 0);
            if ($bcno > $lbcode) $lbcode = $bcno;
        }

        if ($lbcode == 0) $lbcode = 1000000;

        $lbcode++;

        return response()->json(['ok' => true, 'bcode' => $lbcode]);
    }
}
