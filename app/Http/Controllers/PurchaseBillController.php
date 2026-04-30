<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesSecondarySeriesPrefix;
use App\Http\Controllers\Concerns\LogsDelpartAudit;
use App\Support\SecondaryDatabaseSync;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class PurchaseBillController extends Controller
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

        $billTypes = [];
        if ($this->hasTable('salestype')) {
            $stCols    = array_map('strtolower', $this->columnList('salestype'));
            $stSelect  = ['code', 'name'];
            if (in_array('taxperc',  $stCols)) $stSelect[] = 'taxperc';
            if (in_array('pprefix',  $stCols)) $stSelect[] = 'pprefix';
            if (in_array('pstartno', $stCols)) $stSelect[] = 'pstartno';
            $billTypes = DB::table('salestype')->orderBy('code')->get($stSelect)->toArray();
        }

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
            'bill'    => 'Purchase Bill',
            'edit'    => 'Edit Purchase Bill',
            'cancel'  => 'Cancel Purchase Bill',
            'reprint' => 'Reprint Purchase Bill',
        ];

        return view('purchase-bill.index', compact(
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
            'edit' => 'Edit Purchase Bill',
            'cancel' => 'Purchase Cancellation',
            'reprint' => 'Purchase Reprint',
        ];

        return view('purchase-bill.doc-picker', [
            'title' => (string) $request->query('title', $titles[$action]),
            'actionMode' => $action,
            'docType' => 'purchase',
            'searchUrl' => url('/api/purchase-bill/picker-search'),
            'resolveUrl' => url('/api/purchase-bill/picker-resolve'),
            'targetBaseUrl' => url('/purchase-bill'),
            'showViewOption' => $action === 'reprint',
        ]);
    }

    public function pickerSearch(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        if (!$this->hasTable('purchasem')) {
            return response()->json(['ok' => true, 'rows' => []]);
        }

        $q = trim((string) $request->query('q', ''));
        $tdate = $this->parseDate((string) $request->query('tdate', ''));
        $action = strtolower(trim((string) $request->query('action', 'edit')));

        $query = $this->livePurchaseBillsQuery(
            in_array($action, ['bill', 'cancel', 'edit', 'reprint'], true),
            $this->purchaseDocTypesForAction($action)
        )
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('docno', 'like', $q . '%')
                        ->orWhere('name', 'like', '%' . $q . '%');
                });
            })
            ->when($tdate, fn ($query) => $query->whereDate('tdate', $tdate));

        $rows = $query
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
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $docNo = strtoupper(trim((string) $request->input('doc_no', '')));
        $tdate = $this->parseDate((string) $request->input('tdate', ''));
        $action = strtolower(trim((string) $request->input('action', 'edit')));
        $viewOnly = filter_var($request->input('view_only', false), FILTER_VALIDATE_BOOLEAN);

        if ($docNo === '' || !$tdate) {
            return response()->json(['ok' => false, 'message' => 'Doc no and date are required.'], 422);
        }

        if (!$this->hasTable('purchasem')) {
            return response()->json(['ok' => false, 'message' => 'Purchase table not found.'], 404);
        }

        $row = $this->livePurchaseBillsQuery(
            in_array($action, ['bill', 'cancel', 'edit'], true),
            $this->purchaseDocTypesForAction($action)
        )
            ->whereRaw('UPPER(TRIM(docno)) = ?', [$docNo])
            ->whereDate('tdate', $tdate)
            ->orderByDesc('slno')
            ->first(['slno', 'docno', 'tdate', 'status']);

        if (!$row) {
            return response()->json(['ok' => false, 'message' => 'This bill number does not exist...'], 404);
        }

        if ($action === 'reprint') {
            return response()->json([
                'ok' => true,
                'doc_no' => trim((string) ($row->docno ?? '')),
                'url' => url('/purchase-bill-print?' . http_build_query([
                    'slno' => (int) ($row->slno ?? 0),
                ])),
            ]);
        }

        $query = ['doc_no' => trim((string) ($row->docno ?? ''))];

        return response()->json([
            'ok' => true,
            'doc_no' => trim((string) ($row->docno ?? '')),
            'url' => url('/purchase-bill/' . $action . '?' . http_build_query($query)),
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
        $taxBillTypeWise = strtoupper($sw['TAXBILLTYPEWISE'] ?? 'N') === 'Y';

        if ($this->shouldUseSecondaryPrefix('purchase')) {
            $prefix  = $this->secondaryPrefixFor('purchase');
            $current = $this->genInt('PURCHASEB');
            $next    = $current + 1;
            $billNo  = $prefix . str_pad($next, $this->purchaseBillNumberLength(), '0', STR_PAD_LEFT);
            if ($billTypeCode !== '' && $this->hasTable('salestype') && ($taxBillTypeWise || $billTypeWise)) {
                $st = DB::table('salestype')->where('code', $billTypeCode)->first();
                $taxPerc = (float)($st->taxperc ?? $taxPerc);
            }
            return response()->json(['ok' => true, 'bill_no' => $billNo, 'tax_perc' => $taxPerc]);
        }

        if ($billTypeCode !== '' && $this->hasTable('salestype')) {
            $st = DB::table('salestype')->where('code', $billTypeCode)->first();
            if ($taxBillTypeWise || $billTypeWise) {
                $taxPerc = (float)($st->taxperc ?? $taxPerc);
            }
            if ($billTypeWise) {
                $pprefix = trim((string)($st->pprefix ?? ''));
                if ($pprefix !== '') {
                    $current = $this->lastPurchaseBillNumberForPrefix($pprefix);
                    $next    = $current + 1;
                    $billNo  = $pprefix . str_pad($next, $this->purchaseBillNumberLength(), '0', STR_PAD_LEFT);
                    return response()->json(['ok' => true, 'bill_no' => $billNo, 'tax_perc' => $taxPerc]);
                }
            }
        }

        $current = $this->genInt('PURCHASEB');
        $prefix  = $this->genStr('PBPREF');
        if ($prefix === '') {
            $prefix = 'PL/';
        }
        $next    = $current + 1;
        $billNo  = $prefix . str_pad($next, $this->purchaseBillNumberLength(), '0', STR_PAD_LEFT);

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
            $query->where(function ($qb) use ($q, $cols) {
                $qb->where('code', 'like', "%$q%")
                   ->orWhere('name', 'like', "%$q%");
                if (in_array('mobile', $cols, true)) {
                    $qb->orWhere('mobile', 'like', "%$q%");
                }
                if (in_array('tin', $cols, true)) {
                    $qb->orWhere('tin', 'like', "%$q%");
                }
                if (in_array('panadhar', $cols, true)) {
                    $qb->orWhere('panadhar', 'like', "%$q%");
                }
            });
            $query->limit(30);
        } else {
            $query->limit(100);
        }
        $select = ['code', 'name', 'ctype'];
        foreach (['addr1', 'mobile', 'panadhar', 'tin', 'statecode'] as $col) {
            if (in_array($col, $cols, true)) {
                $select[] = $col;
            }
        }
        $rows = $query->get($select);

        // View reads d.results
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

        // Check blocked
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

        // Return flat fields — view reads d.code, d.name, d.address, d.mobile, d.pan, d.old_balance
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
        // stock columns
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

        // Default purchase rate based on item type
        $rate = match ($itype) {
            'G' => $goldRate  ?: $this->genDec('GRATE'),
            'S' => $silvRate  ?: $this->genDec('SRATE'),
            'P' => $platRate  ?: $this->genDec('PGRATE'),
            default => 0,
        };

        // Current stock
        $g    = $this->gilevel;
        $stkq = (int)(($g === 1 ? $item->qty : $item->qtyb) ?? 0);
        $stkw = (float)(($g === 1 ? $item->weight : $item->weightb) ?? 0);

        // Return flat fields — view reads d.code, d.name, d.purity, d.touch, d.rate, d.stktype
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
        // Return flat JSON — view reads d.tax_amt, d.net_total etc. directly
        return response()->json($totals);
    }

    // ─── API: Save Purchase Bill ──────────────────────────────────────────────

    public function save(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $shouldSecondarySync = $request->boolean('secondary_sync');
        if ($shouldSecondarySync && !SecondaryDatabaseSync::userCanUse($request->session()->get('user_code'))) {
            return response()->json(['ok' => false, 'message' => 'You do not have permission for secondary database sync.'], 403);
        }

        $this->gsincharge = (string)($request->session()->get('user_code', ''));
        $g = $this->gilevel;

        // Header fields — key names match what the JS payload sends
        $mode       = trim((string)$request->input('mode', 'bill'));
        $postedSlno = (int)$request->input('slno', 0);
        $docNo      = trim((string)$request->input('doc_no', ''));      // system bill no
        $suppBillNo = trim((string)$request->input('supp_bill_no', ''));// supplier's bill no
        // Parse bill_date (may be dd/mm/yyyy or yyyy-mm-dd)
        $billDate = $this->parseDate($request->input('bill_date')) ?? date('Y-m-d');
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
        $roundAmt   = (float)$request->input('round', 0);
        $hmc        = (float)$request->input('hmc', 0);
        $tcsPerc    = (float)$request->input('tcs_perc', 0);
        $chqAmt     = (float)$request->input('chq_amt', 0);
        $paidAmt    = (float)$request->input('paid_amt', 0);
        $ob         = (float)$request->input('ob', 0);
        $others     = (float)$request->input('others', 0);

        $items    = (array)$request->input('items', []);
        $exchItems = (array)$request->input('exchange_items', []);

        if ($smCode === '') {
            return response()->json(['ok' => false, 'message' => 'Salesman is required.'], 422);
        }

        // Validate items — view sends code/name/lessperc/lesswgt/stwgt/stprice/mcharge
        $validItems = [];
        foreach ($items as $item) {
            $scode = strtoupper(trim($item['code'] ?? $item['item_code'] ?? ''));
            $dwgt  = (float)($item['weight'] ?? 0);
            $iqty  = (int)($item['qty'] ?? 0);
            if ($scode === '' || ($dwgt + $iqty) <= 0) continue;
            if ((float)($item['rate'] ?? 0) <= 0) {
                return response()->json(['ok' => false, 'message' => "Check Rate for $scode"]);
            }
            // Normalize field names
            $item['code']      = $scode;
            $item['item_code'] = $scode;
            $validItems[] = $item;
        }

        if (empty($validItems)) {
            return response()->json(['ok' => false, 'message' => 'No valid items to save']);
        }

        // Compute bill total from validated items
        $billTotal = 0.0;
        foreach ($validItems as $itm) { $billTotal += (float)($itm['amount'] ?? 0); }

        // Compute exchange total from exchange items
        $exchAmtCalc = 0.0;
        foreach ($exchItems as $ei) { $exchAmtCalc += (float)($ei['amount'] ?? 0); }

        // Recalculate totals server-side using PB balcalc formula
        $totals = $this->calcTotals([
            'bill_total'     => $billTotal,
            'exchange_amt'   => $exchAmtCalc,
            'tax_perc'       => $taxPerc,
            'disc_perc'      => $discPerc,
            'discount'       => $discount,
            'round'          => $roundAmt,
            'hmc'            => $hmc,
            'tcs_perc'       => $tcsPerc,
            'paid_amt'       => $paidAmt,
            'ob'             => $ob,
            'external'       => $taxExt,     // field name matches calcTotals()
            'tax_on_mc'      => $taxOnMcOnly,
            'others'         => $others,
        ]);

        $exchAmt    = $totals['exchange_amt'];
        $taxAmt     = $totals['tax_amt'];
        $cessAmt    = $totals['cess'];
        $tcsAmt     = $totals['tcs_amt'];
        $netTotal   = $totals['net_total'];
        $balance    = $totals['balance'];

        $icontrol = 1; // 1 = Bill
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
            if ($mode === 'edit' || $postedSlno > 0) {
                $existing = null;
                if ($postedSlno > 0) {
                    $existing = DB::table('purchasem')
                        ->where('slno', $postedSlno)
                        ->where('pr', 'P')
                        ->first();
                }
                if (!$existing && $docNo !== '') {
                    $existing = DB::table('purchasem')
                        ->whereRaw('UPPER(TRIM(docno)) = ?', [strtoupper($docNo)])
                        ->where('pr', 'P')
                        ->first();
                }
                if ($existing) {
                    $existingSlno = (int)$existing->slno;
                    if ($docNo === '') {
                        $docNo = trim((string)($existing->docno ?? ''));
                    }
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

            // Insert / update purchasem — filter to only columns that exist
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
                'pr'          => 'P',
                'control'     => $icontrol,
                'tdate'       => $billDate,
                'ttime'       => $ttime,
                'duedate'     => $dueDate,
                'rate'        => $goldRate,
                'smcode'      => $smCode,
                'round'       => $roundAmt,
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
            } else {
                DB::table('purchasem')->insert($purchasemData);
            }

            // Insert purchased items + update stock
            $sno = 0;
            foreach ($validItems as $item) {
                $sno++;
                // View sends: code, name, purity, rate, qty, weight, stwgt, stprice, mud, touch, lessperc, lesswgt, mcharge, round, amount, stktype, fr
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
                $dround   = (float)($item['round'] ?? 0);
                $dtouch   = (float)($item['touch'] ?? 0);
                $dstktouch = (float)($item['stktouch'] ?? 0);
                $sstktype = trim($item['stktype'] ?? '');
                $siqtype  = trim($item['purity'] ?? $item['iqtype'] ?? '');
                $smark    = trim($item['mark'] ?? '');
                $sname    = trim($item['name'] ?? $item['item_name'] ?? '');
                $sbatch   = trim($item['batch'] ?? '');

                // Cost per gram
                $dcost = ($dwgt > 0) ? round($damount / $dwgt, 2) : 0;

                // Check if item name matches items.name (store blank if same)
                $realName = (string)(DB::table('items')->where('code', $scode)->value('name') ?? '');
                if (trim($sname) === trim($realName)) $sname = '';

                // Filter to only columns that exist in purchased table
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
                    'round'     => $dround,
                    'stktouch'  => $dstktouch,
                    'touch'     => $dtouch,
                    'batch'     => $sbatch,
                    'fr'        => 0,
                ];
                DB::table('purchased')->insert(
                    array_filter($pdAll, fn($k) => in_array($k, $pdCols), ARRAY_FILTER_USE_KEY)
                );

                // Stock +++ (purchase adds to stock)
                $this->adjustItemStock($scode, $iqty, $dwgt, $dstwgt, $sstktype, '+');
            }

            // Exchange items (old gold received by supplier — decrements stock)
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

                    // Exchange items DECREASE stock (old gold returned by us)
                    $this->adjustItemStock($esc, $eqty, $ewgt, $estwgt, $esstktype, '-');
                }
            }

            // Remove any existing daybook rows for this slno before writing fresh entries.
            // slno comes from the global SERIALNO counter — it is unique per transaction,
            // so deleting by slno alone is safe and avoids missing rows with NULL vtype.
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
                $totals['discount'], $taxAmt, $cessAmt, $hmc, $tcsAmt, $roundAmt, $others,
                $interstate, $taxExt, $icontrol
            );

            DB::commit();
            $this->logDelpart($request, 'Purchase Bill(' . $docno . ') Saved', ['utype' => $existingSlno > 0 ? 'E' : 'A', 'ttype' => 'T', 'slno' => $lslno, 'tdate' => $billDate, 'control' => $icontrol]);

            $message = 'Purchase bill saved successfully';
            $secondarySync = null;
            if ($shouldSecondarySync && $lslno > 0) {
                try {
                    $secondarySync = (new SecondaryDatabaseSync())->sync('purchase', (int) $lslno);
                    $message .= '. Secondary sync completed to ' . ($secondarySync['database'] ?? '');
                } catch (\Throwable $e) {
                    $message .= '. Primary save completed, but secondary sync failed: ' . $e->getMessage();
                }
            }

            return response()->json([
                'ok'      => true,
                'message' => $message,
                'doc_no'  => $docno,
                'slno'    => $lslno,
                'secondary_sync' => $secondarySync,
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
        $billNo = $this->resolveAccessiblePurchaseDocNo($billNo);
        $action = strtolower(trim((string) $request->query('action', 'bill')));
        if ($billNo === '') return response()->json(['ok' => false, 'message' => 'No bill number']);

        if (!$this->hasTable('purchasem')) return response()->json(['ok' => false, 'message' => 'Table missing']);

        $m = $this->livePurchaseBillsQuery(
            in_array($action, ['bill', 'edit', 'cancel'], true),
            $this->purchaseDocTypesForAction($action)
        )
            ->where('docno', $billNo)
            ->first();
        if (!$m) return response()->json(['ok' => false, 'message' => 'Bill not found']);
        if (in_array($action, ['bill', 'edit', 'cancel'], true) && (int)($m->status ?? 1) === 0) {
            return response()->json(['ok' => false, 'message' => 'Bill already cancelled']);
        }

        $items = $this->hasTable('purchased')
            ? DB::table('purchased')->where('slno', $m->slno)->orderBy('sno')->get()->toArray()
            : [];

        $exchItems = $this->hasTable('purchaserd')
            ? DB::table('purchaserd')->where('slno', $m->slno)->orderBy('sno')->get()->toArray()
            : [];

        // Enrich items with real names
        // Map purchased rows to flat item objects matching applyBill() expectations
        $enriched = [];
        foreach ($items as $r) {
            $rn = (string)(DB::table('items')->where('code', $r->code ?? '')->value('name') ?? '');
            $enriched[] = [
                'code'     => $r->code ?? '',
                'name'     => (trim($r->name ?? '') !== '') ? $r->name : $rn,
                'iqtype'   => $r->iqtype ?? '',   // purity
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
                'round'    => (float)($r->round ?? 0),
                'amount'   => (float)($r->amount ?? 0),
                'stktype'  => $r->stktype ?? '',
                'fr'       => $r->fr ?? 'N',
            ];
        }

        // Map purchaserd (exchange) rows
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

        // Return flat JSON — field names match applyBill() in the view
        return response()->json([
            'ok'         => true,
            'slno'       => $m->slno,
            'doc_no'     => $m->docno,        // purchase bill number
            'bill_no'    => $m->billno ?? '',  // supplier bill number
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
            'gold_rate'  => (float)($m->rate ?? 0),
            'note'       => $m->note ?? '',
            'disc_perc'  => (float)($m->discperc ?? 0),
            'discount'   => (float)($m->discount ?? 0),
            'round'      => (float)($m->round ?? 0),
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
        $billNo = $this->resolveAccessiblePurchaseDocNo($billNo);
        $action = strtolower(trim((string) $request->query('action', 'bill')));
        if (!$this->hasTable('purchasem')) return response()->json(['ok' => false, 'message' => 'No table']);

        $current = $billNo !== ''
            ? $this->livePurchaseBillsQuery(
                in_array($action, ['bill', 'cancel', 'edit'], true),
                $this->purchaseDocTypesForAction($action)
            )
                ->where('docno', $billNo)
                ->value('slno')
            : null;

        if ($current) {
            // Navigate to the bill before this one
            $row = $this->livePurchaseBillsQuery(
                in_array($action, ['bill', 'cancel', 'edit'], true),
                $this->purchaseDocTypesForAction($action)
            )
                ->where('slno', '<', $current)
                ->orderByDesc('slno')->first(['docno']);
        } else {
            // No bill loaded — go to the last saved bill
            $row = $this->livePurchaseBillsQuery(
                in_array($action, ['bill', 'cancel', 'edit'], true),
                $this->purchaseDocTypesForAction($action)
            )
                ->orderByDesc('slno')->first(['docno']);
        }

        if (!$row) return response()->json(['ok' => false, 'message' => 'No previous bill']);
        return response()->json(['ok' => true, 'bill_no' => $row->docno]);
    }

    public function nextBill(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $billNo = trim((string)($request->query('doc_no') ?? $request->query('bill_no', '')));
        $billNo = $this->resolveAccessiblePurchaseDocNo($billNo);
        $action = strtolower(trim((string) $request->query('action', 'bill')));
        if (!$this->hasTable('purchasem')) return response()->json(['ok' => false, 'message' => 'No table']);

        $current = $billNo !== ''
            ? $this->livePurchaseBillsQuery(
                in_array($action, ['bill', 'cancel', 'edit'], true),
                $this->purchaseDocTypesForAction($action)
            )
                ->where('docno', $billNo)
                ->value('slno')
            : null;

        if ($current) {
            // Navigate to the bill after this one
            $row = $this->livePurchaseBillsQuery(
                in_array($action, ['bill', 'cancel', 'edit'], true),
                $this->purchaseDocTypesForAction($action)
            )
                ->where('slno', '>', $current)
                ->orderBy('slno')->first(['docno']);
        } else {
            // No bill loaded — go to the last saved bill
            $row = $this->livePurchaseBillsQuery(
                in_array($action, ['bill', 'cancel', 'edit'], true),
                $this->purchaseDocTypesForAction($action)
            )
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
        $action = strtolower(trim((string) $request->query('action', 'bill')));
        if (!$this->hasTable('purchasem')) return response()->json(['ok' => true, 'rows' => []]);

        $rows = $this->livePurchaseBillsQuery(
            in_array($action, ['bill', 'cancel', 'edit'], true),
            $this->purchaseDocTypesForAction($action)
        )
            ->where(function ($qb) use ($q) {
                $qb->where('docno', 'like', "%$q%")
                   ->orWhere('name', 'like', "%$q%")
                   ->orWhere('billno', 'like', "%$q%");
            })
            ->orderByDesc('slno')
            ->limit(20)
            ->get(['docno', 'tdate', 'name', 'netamt', 'status']);

        // Map to field names matching doBillSearch() in view
        $results = $rows->map(fn ($r) => [
            'doc_no'    => $r->docno,
            'date'      => $r->tdate,
            'sup_name'  => $r->name,
            'net_total' => (float)($r->netamt ?? 0),
        ])->values();

        return response()->json(['ok' => true, 'results' => $results]);
    }

    // ─── API: Rebuild Daybook for All Purchase Bills ──────────────────────────

    public function rebuildAllDaybook(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        if (!$this->hasTable('purchasem')) {
            return response()->json(['ok' => false, 'message' => 'purchasem table not found']);
        }

        $this->gsincharge = (string)$request->session()->get('user_code', '');

        $bills = DB::table('purchasem')->where('pr', 'P')->orderBy('slno')->get();

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
                $roundAmt   = (float)($m->round    ?? 0);
                $others     = (float)($m->addamt   ?? 0);
                $interstate = strtoupper(trim($m->cst ?? 'N')) === 'Y';
                $taxExt     = strtoupper(trim($m->taxexternal ?? 'N')) === 'Y';
                $control    = (int)($m->control ?? 1);

                // netamt stored as netTotal+others; recover netTotal
                $storedNet  = (float)($m->netamt ?? 0);
                $netTotal   = $storedNet - $others;

                DB::beginTransaction();

                // Wipe old entries unconditionally
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
                    $discount, $taxAmt, $cessAmt, $hmc, $tcsAmt, $roundAmt, $others,
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
            'message' => "Rebuilt daybook for {$fixed} purchase bill(s)." .
                         (count($errors) ? ' Errors: ' . implode('; ', $errors) : ''),
        ]);
    }

    // ─── API: Cancel Bill ─────────────────────────────────────────────────────

    public function cancelBill(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        // View sends doc_no (purchase bill number)
        $billNo = trim((string)($request->input('doc_no') ?? $request->input('bill_no', '')));
        $reason = trim((string)$request->input('reason', ''));
        if ($billNo === '') return response()->json(['ok' => false, 'message' => 'No bill number']);

        if (!$this->hasTable('purchasem')) return response()->json(['ok' => false]);

        $master = $this->livePurchaseBillsQuery(true, ['P', 'E'])
            ->whereRaw('TRIM(docno)=?', [$billNo])
            ->orderByDesc('slno')
            ->first();

        if (!$master) {
            return response()->json(['ok' => false, 'message' => 'Bill not found']);
        }

        if ((int)($master->status ?? 1) === 0) {
            return response()->json(['ok' => false, 'message' => 'Bill already cancelled']);
        }

        $slno = (int)($master->slno ?? 0);

        DB::transaction(function () use ($slno, $billNo, $reason) {
            if ($slno > 0) {
                $this->reverseEditStock($slno);

                foreach (['purchased', 'purchaserd', 'daybook', 'daybookpart', 'pdclist'] as $tbl) {
                    if ($this->hasTable($tbl)) {
                        DB::table($tbl)->where('slno', $slno)->delete();
                    }
                }
            }

            DB::table('purchasem')->where('slno', $slno)->delete();
        });

        $this->logDelpart($request, 'Purchase Bill(' . $billNo . ') Cancelled', ['utype' => 'D', 'ttype' => 'T']);
        $message = 'Bill cancelled';
        if (SecondaryDatabaseSync::userCanUse((string) $request->session()->get('user_code')) && $slno > 0) {
            try {
                $secondarySync = (new SecondaryDatabaseSync())->sync('purchase', $slno);
                $message .= ' Secondary sync completed to ' . ($secondarySync['database'] ?? '') . '.';
            } catch (\Throwable $e) {
                $message .= ' Primary cancel completed, but secondary sync failed: ' . $e->getMessage();
            }
        }

        return response()->json(['ok' => true, 'message' => $message]);
    }

    // ─── API: Reprint ─────────────────────────────────────────────────────────

    public function reprint(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $billNo = trim((string)$request->input('bill_no', ''));
        // Delegate to get() logic
        $fakeReq = Request::create('/api/purchase-bill/get', 'GET', ['bill_no' => $billNo]);
        $fakeReq->setSession($request->session());
        return $this->get($fakeReq);
    }

    // ─── Private: Totals Calculation ─────────────────────────────────────────

    private function calcTotals(array $data): array
    {
        // View sends bill_total already computed from items
        $billTotal  = (float)($data['bill_total']  ?? 0);
        $exchAmt    = (float)($data['exchange_amt'] ?? 0);
        $discPerc   = (float)($data['disc_perc']   ?? 0);
        $discount   = (float)($data['discount']    ?? 0);
        $roundAmt   = (float)($data['round']       ?? 0);
        $taxPerc    = (float)($data['tax_perc']    ?? 0);
        $hmc        = (float)($data['hmc']         ?? 0);
        $tcsPerc    = (float)($data['tcs_perc']    ?? 0);
        $paidAmt    = (float)($data['paid_amt']    ?? 0);
        $autoPaid   = !empty($data['auto_paid']);
        $ob         = (float)($data['ob']          ?? 0);
        $others     = (float)($data['others']      ?? 0);
        $taxExt     = !empty($data['external']);       // cbx_taxexternal
        $taxDeduct  = !empty($data['tax_deduct_bamt']); // cbx_taxdeduct
        $taxOnMcOnly = !empty($data['tax_on_mc']);     // cbx_taxonmconly

        // Discount: compute from % if only % is given
        if ($discPerc > 0 && $discount == 0.0) {
            $discount = round(($billTotal * $discPerc) / 100, 2);
        }

        // PB balcalc: nettot = billtotal - discount - exchange + hmc
        $netTotal = $billTotal - $discount - $exchAmt + $hmc;

        // Tax
        if ($taxExt) {
            $taxAmt  = 0.0;
            $cessAmt = 0.0;
        } else {
            $taxBase = $taxOnMcOnly ? $hmc : $netTotal;
            $taxAmt  = round(($taxBase * $taxPerc) / 100, 2);
            $cessAmt = 0.0; // cess handled separately when needed
        }

        // Apply tax (deduct or add)
        if ($taxDeduct) {
            $netTotal -= ($taxAmt + $cessAmt);
        } else {
            $netTotal += ($taxAmt + $cessAmt);
        }

        // TCS on net total
        $tcsAmt    = $tcsPerc > 0 ? (float)round(($netTotal * $tcsPerc) / 100, 0) : 0.0;
        $netTotal += $tcsAmt;

        // Bill-level round adjustment
        $netTotal += $roundAmt;

        if ($autoPaid) {
            $paidAmt = $netTotal + $others;
        }

        // Balance = net + others - paid; CB = OB + Balance
        $balance = $netTotal + $others - $paidAmt;
        $cb      = $ob + $balance;

        return [
            'bill_total'   => round($billTotal, 2),
            'exchange_amt' => round($exchAmt, 2),
            'discount'     => round($discount, 2),
            'round'        => round($roundAmt, 2),
            'tax_amt'      => $taxAmt,
            'cess'         => $cessAmt,
            'tcs_amt'      => $tcsAmt,
            'paid_amt'     => round($paidAmt, 2),
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

        // Update itemsstk (stock by type) if it exists
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
        // Reverse purchased items
        if ($this->hasTable('purchased')) {
            $rows = DB::table('purchased')->where('slno', $slno)->get();
            foreach ($rows as $r) {
                $this->adjustItemStock($r->code, (int)($r->qty ?? 0),
                    (float)($r->weight ?? 0), (float)($r->stwgt ?? 0),
                    trim($r->stktype ?? ''), '-');
            }
        }
        // Reverse exchange items (they were subtracted, so add back)
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
            $st = DB::table('salestype')->where('code', $billTypeCode)->first();
            $pprefix = trim((string)($st->pprefix ?? ''));
            if ($pprefix !== '') {
                $next = $this->lastPurchaseBillNumberForPrefix($pprefix) + 1;
                if ($this->hasTable('generali')) {
                    DB::table('generali')->updateOrInsert(
                        ['code' => 'PURCH' . $pprefix],
                        ['cvalue' => $next]
                    );
                }
                return $pprefix . str_pad($next, $this->purchaseBillNumberLength(), '0', STR_PAD_LEFT);
            }
        }

        $next   = $this->incrementGenInt('PURCHASEB');
        $prefix = $this->genStr('PBPREF');
        if ($prefix === '') {
            $prefix = 'PL/';
        }
        return $prefix . str_pad($next, $this->purchaseBillNumberLength(), '0', STR_PAD_LEFT);
    }

    private function lastPurchaseBillNumberForPrefix(string $prefix): int
    {
        $prefix = trim($prefix);
        if ($prefix === '' || !$this->hasTable('purchasem')) {
            return 0;
        }

        $lastDocNo = (string) (DB::table('purchasem')
            ->where('pr', 'P')
            ->where('docno', 'like', $prefix . '%')
            ->orderByDesc('slno')
            ->value('docno') ?? '');

        if ($lastDocNo !== '' && str_starts_with($lastDocNo, $prefix)) {
            $num = (int) preg_replace('/\D+/', '', substr($lastDocNo, strlen($prefix)));
            if ($num > 0) {
                return $num;
            }
        }

        return $this->genInt('PURCH' . $prefix);
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

        // Legacy databases can drift out of sync when SERIALNO is reset or padded with spaces.
        // Keep the shared counter ahead of existing transaction slno values.
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

    private function purchaseBillNumberLength(): int
    {
        $len = (int) trim((string) $this->genStr('PBLEN'));
        return $len > 0 ? $len : 4;
    }

    /**
     * Parse a date string from the form (dd/mm/yyyy or yyyy-mm-dd or '00/00/0000')
     * and return a MySQL-safe yyyy-mm-dd string or null.
     */
    private function parseDate(?string $raw): ?string
    {
        $raw = trim((string)$raw);
        if ($raw === '' || $raw === '00/00/0000' || $raw === '00-00-0000') return null;
        // dd/mm/yyyy
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $raw, $m)) {
            if ($m[1] === '00' || $m[2] === '00') return null;
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        // dd-mm-yyyy
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $raw, $m)) {
            if ($m[1] === '00' || $m[2] === '00') return null;
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        // yyyy-mm-dd already
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) return $raw;
        return null;
    }

    /**
     * Write daybook entries for a purchase bill — matches PowerBuilder save logic exactly.
     *
     * Entry order (opaccode = 'EP' for all except the EP purchase entry):
     *  1. daybookpart  (voucher header / particular)
     *  2. CASH         +cashPaid      (non-cheque portion of payment)
     *  3. CNP/chqBank  +chqAmt        (cheque payment; CNP if PDC)
     *  4. EP           +exchAmt       (exchange old gold)
     *  5. suppcode     +dacamt        (debit supplier = netTotal + others)
     *  6. suppcode     -paidAmt       (credit supplier = amount paid)
     *  7. ADD          -others        (additional amount account)
     *  8. HMC          -hmc           (making charge)
     *  9. TCSAC        -tcsAmt        (TCS)
     * 10. PDISCAC/DISC +discount      (purchase discount received)
     * 11. SGST+CGST    -tax/2 each    (or IGST -tax if interstate)
     * 12. EP           -billTotal     (purchase account credit)
     * 13. ROUND        -roundAmt      (entered bill round-off)
     * 14. ROUND        -sum           (tiny residual balance entry)
     */
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
        float  $roundAmt,
        float  $others,
        bool   $interstate,
        bool   $taxExt,
        int    $control
    ): void {
        if (!$this->hasTable('daybook')) return;

        $dbCols = array_map('strtolower', $this->columnList('daybook'));

        // Account codes from settings (fall back to standard codes)
        $discAc    = $this->genStr('PDISCAC') ?: 'DISC';
        $epAc      = 'EP';        // Exchange/Purchase account
        $cashAc    = 'CASH';
        $cnpAc     = 'CNP';       // Cheque Not Presented
        $addAc     = 'ADD';       // Others/additional amount
        $hmcAc     = 'HMC';
        $tcsAc     = 'TCSAC';
        $sgstAc    = 'SGST';
        $cgstAc    = 'CGST';
        $igstAc    = 'IGST';
        $ptaxExpAc = 'PTAXEXP';
        $roundAc   = 'ROUND';

        $sno = 0;

        // Helper: insert one daybook row (skips zero-amount entries)
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

        // Step 1: daybookpart (voucher header)
        if ($this->hasTable('daybookpart')) {
            $dpCols = array_map('strtolower', $this->columnList('daybookpart'));
            $particular = 'By Purchase - ' . $docno . ' - ' . $suppBillNo . ' From ' . $suppName;
            $dpRow = [];
            if (in_array('slno',       $dpCols)) $dpRow['slno']       = $lslno;
            if (in_array('tdate',      $dpCols)) $dpRow['tdate']      = $billDate;
            if (in_array('particular', $dpCols)) $dpRow['particular'] = mb_substr($particular, 0, 200);
            if (in_array('vchno',      $dpCols)) $dpRow['vchno']      = $lslno;
            if (in_array('control',    $dpCols)) $dpRow['control']    = $control;
            if (in_array('vtype',      $dpCols)) $dpRow['vtype']      = 'PL';
            if (!empty($dpRow)) DB::table('daybookpart')->insert($dpRow);
        }

        // dacamt = total owed to supplier
        $dacamt   = $netTotal + $others;
        $cashPaid = $paidAmt - $chqAmt;  // non-cheque cash portion

        // Step 2: Cash payment (non-cheque)
        if ($cashPaid > 0) {
            $ins($cashAc, $cashPaid, $epAc);
        }

        // Step 3: Cheque payment
        if ($chqAmt > 0) {
            $cbAc = ($chqPdc === 'Y') ? $cnpAc : ($chqBank ?: $cashAc);
            $ins($cbAc, $chqAmt, $epAc);
        }

        // Step 4: Exchange (old gold given by supplier credited to purchase)
        if ($exchAmt > 0) {
            $ins($epAc, $exchAmt, $epAc);
        }

        // Step 5: Supplier debit (amount we owe = netTotal + others)
        if ($dacamt != 0.0) {
            $ins($suppCode ?: $epAc, $dacamt, $epAc);
        }

        // Step 6: Supplier paid (deduct payment from what we owe)
        if ($paidAmt > 0) {
            $ins($suppCode ?: $epAc, -$paidAmt, $epAc);
        }

        // Step 7: Others / additional amount
        if ($others > 0) {
            $ins($addAc, -$others, $epAc);
        }

        // Step 8: Making charge (HMC)
        if ($hmc > 0) {
            $ins($hmcAc, -$hmc, $epAc);
        }

        // Step 9: TCS
        if ($tcsAmt > 0) {
            $ins($tcsAc, -$tcsAmt, $epAc);
        }

        // Step 10: Discount received from supplier
        if ($discount > 0) {
            $ins($discAc, $discount, $epAc);
        }

        // Step 11: Tax (GST)
        $totalTax = $taxAmt + $cessAmt;
        if ($totalTax > 0) {
            if ($taxExt) {
                // Tax paid separately (external)
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

        // Step 12: Purchase account (EP) — credit the purchase total
        if ($billTotal > 0) {
            // opaccode = first payment method, or supplier, or EP
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

        // Step 13: Bill round-off entry
        if ($roundAmt != 0.0) {
            $ins($roundAc, -$roundAmt, $epAc);
        }

        // Step 14: Residual balancing entry to make total exactly zero
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

    private function livePurchaseBillsQuery(bool $onlyActive = true, array $prValues = ['P']): Builder
    {
        $prValues = array_values(array_unique(array_filter(array_map(
            fn ($value) => strtoupper(trim((string) $value)),
            $prValues
        ))));
        if ($prValues === []) {
            $prValues = ['P'];
        }

        $query = DB::table('purchasem')->whereIn('pr', $prValues);

        if ($onlyActive) {
            $query->where('status', '!=', 0);
        }

        if ($this->hasTable('purchased')) {
            $query->whereExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('purchased')
                    ->whereColumn('purchased.slno', 'purchasem.slno');
            });
        }

        return $query;
    }

    private function purchaseDocTypesForAction(string $action): array
    {
        return in_array($action, ['cancel', 'edit'], true) ? ['P', 'E'] : ['P'];
    }

    private function resolveAccessiblePurchaseDocNo(string $billNo): string
    {
        $billNo = trim($billNo);
        if ($billNo === '' || !$this->hasTable('purchasem')) {
            return $billNo;
        }

        $existsLocally = DB::table('purchasem')
            ->whereIn('pr', ['P', 'E'])
            ->where('docno', $billNo)
            ->exists();

        if ($existsLocally) {
            return $billNo;
        }

        $currentDb = (string) config('database.connections.mysql.database', '');
        if ($currentDb === '') {
            return $billNo;
        }

        $map = $this->loadSecondarySyncMap();
        $sourceDb = $this->findSourceDatabaseForTarget($map, $currentDb);
        if ($sourceDb === null) {
            return $billNo;
        }

        $sourceSlno = $this->findSourcePurchaseSlno($sourceDb, $billNo);
        if ($sourceSlno <= 0) {
            return $billNo;
        }

        $mappedDocNo = trim((string) ($map[$sourceDb][$currentDb]['transactions']['purchase'][(string) $sourceSlno]['target_number'] ?? ''));
        return $mappedDocNo !== '' ? $mappedDocNo : $billNo;
    }

    private function loadSecondarySyncMap(): array
    {
        if (!Storage::exists('secondary-sync-map.json')) {
            return [];
        }

        $decoded = json_decode((string) Storage::get('secondary-sync-map.json'), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function findSourceDatabaseForTarget(array $map, string $targetDb): ?string
    {
        foreach ($map as $sourceDb => $targets) {
            if (is_array($targets) && array_key_exists($targetDb, $targets)) {
                return (string) $sourceDb;
            }
        }

        return null;
    }

    private function findSourcePurchaseSlno(string $sourceDb, string $docNo): int
    {
        $connection = 'purchase_sync_source_lookup';
        config(['database.connections.' . $connection => array_merge(
            config('database.connections.mysql', []),
            ['database' => $sourceDb]
        )]);

        try {
            return (int) (DB::connection($connection)
                ->table('purchasem')
                ->where('pr', 'P')
                ->where('docno', $docNo)
                ->value('slno') ?? 0);
        } catch (\Throwable $e) {
            return 0;
        } finally {
            DB::purge($connection);
        }
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
