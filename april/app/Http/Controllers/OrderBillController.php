<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesSecondarySeriesPrefix;
use App\Support\SecondaryDatabaseSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class OrderBillController extends Controller
{
    use HandlesSecondarySeriesPrefix;

    private int    $gilevel    = 1;
    private string $gsincharge = '';

    // ─── Edit Picker ─────────────────────────────────────────────────────────

    public function editPicker(Request $request)
    {
        if (!$request->session()->has('user_code')) return redirect('/login');
        return view('order-bill.edit-picker', [
            'title'          => (string) $request->query('title', 'Order Edit'),
            'actionMode'     => 'edit',
            'showViewOption' => false,
        ]);
    }

    // ─── Edit Picker API: Search ──────────────────────────────────────────────

    public function searchEditOrders(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $q    = trim((string) $request->query('q', ''));
        $date = trim((string) $request->query('tdate', ''));

        // Convert dd/mm/yyyy → Y-m-d
        $dateYmd = null;
        if ($date !== '' && preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $date, $m)) {
            $dateYmd = "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        if (!$this->hasTable('orderm')) {
            return response()->json(['ok' => true, 'rows' => []]);
        }

        $query = DB::table('orderm')
            ->select(['slno', 'ordno', 'tdate', 'custname'])
            ->whereNotNull('ordno')
            ->where('ordno', '<>', '')
            ->where(function ($sub) use ($q) {
                if ($q !== '') {
                    $sub->where('ordno', 'like', $q . '%')
                        ->orWhere('custname', 'like', '%' . $q . '%');
                } else {
                    $sub->whereNotNull('ordno');
                }
            });

        if ($dateYmd) {
            $query->whereDate('tdate', $dateYmd);
        }

        $rows = $query
            ->orderByDesc('tdate')
            ->orderByDesc('slno')
            ->limit(50)
            ->get()
            ->map(fn ($r) => [
                'slno'     => (int) ($r->slno ?? 0),
                'billno'   => trim((string) ($r->ordno ?? '')),
                'tdate'    => $r->tdate ? date('d/m/Y', strtotime($r->tdate)) : '',
                'custname' => trim((string) ($r->custname ?? '')),
            ])
            ->values();

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    // ─── Edit Picker API: Resolve ─────────────────────────────────────────────

    public function resolveOrderAction(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $billNo = strtoupper(trim((string) $request->input('bill_no', '')));
        $date   = trim((string) $request->input('tdate', ''));
        $action = strtolower(trim((string) $request->input('action', 'edit')));

        // Convert dd/mm/yyyy → Y-m-d
        $dateYmd = null;
        if ($date !== '' && preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $date, $m)) {
            $dateYmd = "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        if ($billNo === '' || !$dateYmd) {
            return response()->json(['ok' => false, 'message' => 'Order no and date are required.'], 422);
        }

        if (!$this->hasTable('orderm')) {
            return response()->json(['ok' => false, 'message' => 'Order table not found.'], 404);
        }

        $row = DB::table('orderm')
            ->whereRaw('UPPER(TRIM(ordno)) = ?', [$billNo])
            ->whereDate('tdate', $dateYmd)
            ->where('control', '<=', $this->gilevel)
            ->first();

        if (!$row) {
            return response()->json(['ok' => false, 'message' => 'This Order number does not exist...'], 404);
        }

        if ((int) ($row->status ?? 0) === 2) {
            return response()->json(['ok' => false, 'message' => 'This is not a pending order. You can\'t edit a returned order.'], 422);
        }

        $resolvedNo = trim((string) ($row->ordno ?? ''));

        return response()->json([
            'ok'      => true,
            'bill_no' => $resolvedNo,
            'tdate'   => $row->tdate ? date('d/m/Y', strtotime($row->tdate)) : '',
            'url'     => url('/order-bill/edit?doc=' . urlencode($resolvedNo)),
        ]);
    }

    // ─── View ────────────────────────────────────────────────────────────────

    public function index(Request $request, ?string $mode = null)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $validModes = ['bill', 'edit', 'cancel', 'reprint'];
        $mode = $mode ?? 'bill';
        if (!in_array($mode, $validModes)) $mode = 'bill';

        if ($mode === 'reprint') {
            $doc = trim((string) $request->query('doc', ''));
            if ($doc !== '') {
                return redirect('/order-bill/print?doc=' . urlencode($doc));
            }
        }

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

        $cashBanks = [];
        if ($this->hasTable('accountm')) {
            $cashBanks = DB::table('accountm')
                ->whereIn('actype2', ['H', 'B'])
                ->orderByRaw("CASE WHEN actype2='H' THEN 0 ELSE 1 END, accode")
                ->get(['accode as code', 'name', 'actype2'])
                ->map(fn ($r) => ['code' => trim($r->code), 'name' => trim($r->name ?? ''), 'type' => $r->actype2])
                ->toArray();
        }

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
                    'code' => trim((string)($r->code ?? '')),
                    'name' => trim((string)($r->name ?? '')),
                    'itype' => strtoupper(trim((string)($r->itype ?? 'G'))),
                    'touch' => (float)($r->touch ?? 0),
                    'cost' => (float)($r->cost ?? 0),
                    'disabled' => (int)($r->disabled ?? 0),
                    'defstktype' => trim((string)($r->defstktype ?? '')),
                    'defquality' => trim((string)($r->defquality ?? '')),
                    'stkinnos' => strtoupper(trim((string)($r->stkinnos ?? 'N'))),
                    'ornament' => strtoupper(trim((string)($r->ornament ?? 'N'))),
                ])
                ->values()
                ->all();
        }

        $software = $this->loadSoftwareSettings();

        $titles = [
            'bill'    => 'Order Entry',
            'edit'    => 'Edit Order Entry',
            'cancel'  => 'Cancel Order',
            'reprint' => 'Reprint Order',
        ];

        return view('order-bill.index', compact(
            'mode', 'rates', 'salesmen', 'counters', 'cashBanks', 'software', 'exchItems'
        ) + [
            'title' => $titles[$mode],
            'preloadDoc' => trim((string) $request->query('doc', '')),
        ]);
    }

    public function printView(Request $request): View
    {
        abort_unless($request->session()->has('user_code'), 401);

        $billNo = trim((string) ($request->query('doc') ?? $request->query('bill_no', '')));
        $payload = $this->loadOrderPayload($billNo);
        abort_unless($payload !== null, 404);

        $software = $this->loadSoftwareSettings();
        $company = $this->loadPrintShopInfo($software);

        return view('order-bill.print', [
            'order' => $payload,
            'software' => $software,
            'company' => $company,
        ]);
    }

    // ─── API: Next Bill Number ────────────────────────────────────────────────

    public function nextBillNo(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $billDate = $this->parseDate((string) $request->query('bill_date', '')) ?? date('Y-m-d');
        $billNo   = $this->previewBillNumber($billDate);

        return response()->json(['ok' => true, 'bill_no' => $billNo]);
    }

    // ─── API: Customer Search ─────────────────────────────────────────────────

    public function customerSearch(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $q = trim((string)$request->query('q', ''));

        if (!$this->hasTable('clients')) return response()->json(['ok' => true, 'results' => []]);

        $cols  = array_map('strtolower', $this->columnList('clients'));
        $query = DB::table('clients')->orderBy('name');
        if (in_array('ctype', $cols)) $query->whereIn('ctype', ['C', 'B']); // Customers
        if ($q !== '') {
            $query->where(function ($qb) use ($q) {
                $qb->where('code', 'like', "%$q%")
                   ->orWhere('name', 'like', "%$q%")
                   ->orWhere('mobile', 'like', "%$q%")
                   ->orWhere('telephone', 'like', "%$q%");
            });
            $query->limit(30);
        } else {
            $query->limit(100);
        }
        $select = ['code', 'name'];
        if (in_array('addr1', $cols)) $select[] = 'addr1';
        if (in_array('mobile', $cols)) $select[] = 'mobile';
        if (in_array('telephone', $cols)) $select[] = 'telephone';
        if (in_array('panadhar', $cols)) $select[] = 'panadhar';
        if (in_array('tin', $cols)) $select[] = 'tin';
        $rows = $query->get($select);

        return response()->json(['ok' => true, 'results' => $rows]);
    }

    // ─── API: Customer Details ────────────────────────────────────────────────

    public function customerDetails(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $code = trim((string)$request->query('code', ''));
        if ($code === '') return response()->json(['ok' => false, 'message' => 'No code']);

        if (!$this->hasTable('clients')) return response()->json(['ok' => false, 'message' => 'Invalid customer']);

        $r = DB::table('clients')->where('code', $code)->first();
        if (!$r) return response()->json(['ok' => false, 'invalid' => true, 'message' => 'Invalid Customer']);

        $addr = trim(($r->addr1 ?? '') . ' ' . ($r->addr2 ?? ''));
        $ob   = $this->getCustomerBalance($code);

        $salesReturnSummary = null;
        if ($this->hasTable('salesrm')) {
            $srm = DB::table('salesrm')->where('slno', $m->slno)->first();
            if ($srm) {
                $salesReturnSummary = [
                    'discount' => (float)($srm->discount ?? 0),
                    'taxamt'   => (float)($srm->staxamt ?? 0),
                    'astamt'   => (float)($srm->astamt ?? 0),
                    'pamt'     => (float)($srm->pamt ?? 0),
                    'billamt'  => (float)($srm->billamt ?? 0),
                    'netamt'   => (float)($srm->netamt ?? 0),
                ];
            }
        }

        return response()->json([
            'ok'          => true,
            'code'        => $r->code,
            'name'        => trim($r->name ?? ''),
            'address'     => $addr,
            'mobile'      => trim($r->mobile ?? ''),
            'phone'       => trim($r->phone ?? $r->mobile ?? ''),
            'pan'         => trim($r->panadhar ?? ''),
            'gst_no'      => trim($r->tin ?? ''),
            'old_balance' => $ob,
        ]);
    }

    // ─── API: Item Search ─────────────────────────────────────────────────────

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
        foreach (['purity', 'itype', 'touch', 'defstktype', 'wastage', 'mcharge'] as $c) {
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
                'wastage' => (float)($r->wastage ?? 0),
                'mcharge' => (float)($r->mcharge ?? 0),
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

        if ($code === '' || !$this->hasTable('items')) {
            return response()->json(['ok' => false, 'message' => 'Item not found']);
        }

        $item = DB::table('items')->where('code', $code)->first();
        if (!$item) return response()->json(['ok' => false, 'message' => 'Item not found']);
        if ((int)($item->disabled ?? 0) === 1) return response()->json(['ok' => false, 'message' => 'Item is disabled']);

        $itype = strtoupper(trim($item->itype ?? ''));

        $rate = match ($itype) {
            'G' => $goldRate  ?: $this->genDec('GRATE'),
            'S' => $this->genDec('SRATE'),
            'P' => $this->genDec('PGRATE'),
            default => 0,
        };

        return response()->json([
            'ok'       => true,
            'code'     => $item->code,
            'name'     => trim($item->name ?? ''),
            'purity'   => trim($item->purity ?? ''),
            'itype'    => $itype,
            'rate'     => $rate,
            'touch'    => (float)($item->touch ?? 0),
            'cost'     => (float)($item->cost ?? 0),
            'disabled' => (int)($item->disabled ?? 0),
            'defstktype' => trim((string)($item->defstktype ?? '')),
            'defquality' => trim((string)($item->defquality ?? '')),
            'stkinnos' => strtoupper(trim((string)($item->stkinnos ?? 'N'))),
            'ornament' => strtoupper(trim((string)($item->ornament ?? 'N'))),
            'wastage'  => (float)($item->wastage ?? 0),
            'mcharge'  => (float)($item->mcharge ?? 0),
        ]);
    }

    // ─── API: Recalculate Totals ─────────────────────────────────────────────
    // PB: bal = bamt + tax - advance - exchange - sretamt + refund
    // PB: nettot = bamt + tax - exchange - sretamt
    // PB: cb = ob - advance - exchange - sretamt + refund
    // PB: netbal = cb + bamt + tax

    public function recalc(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $billTotal = (float)($request->input('bill_total', 0));
        $tax       = (float)($request->input('tax', 0));
        $advance   = (float)($request->input('advance', 0));
        $exchange  = (float)($request->input('exchange', 0));
        $sretamt   = (float)($request->input('sretamt', 0));
        $schemeAmt = (float)($request->input('scheme_amt', 0));
        $refund    = (float)($request->input('refund', 0));
        $ob        = (float)($request->input('ob', 0));

        // PB logic:
        // nettot = bamt + tax - advance - exchange - sretamt
        // bal    = bamt + tax - advance - exchange - sretamt + refund (+ rcvd, currently 0)
        $netTotal = round($billTotal + $tax - $advance - $exchange - $sretamt - $schemeAmt, 2);
        $balance  = round($billTotal + $tax - $advance - $exchange - $sretamt - $schemeAmt + $refund, 2);
        $cb       = round($ob - $advance - $exchange - $sretamt - $schemeAmt + $refund, 2);
        $netBal   = round($cb + $billTotal + $tax, 2);

        return response()->json([
            'ok'        => true,
            'net_total' => $netTotal,
            'balance'   => $balance,
            'cb'        => $cb,
            'net_bal'   => $netBal,
        ]);
    }

    // ─── API: Save Order ────────────────────────────────────────────────────

    public function save(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $shouldSecondarySync = $request->boolean('secondary_sync');
        if ($shouldSecondarySync && !SecondaryDatabaseSync::userCanUse($request->session()->get('user_code'))) {
            return response()->json(['ok' => false, 'message' => 'You do not have permission for secondary database sync.'], 403);
        }

        $this->gsincharge = (string)($request->session()->get('user_code', ''));

        $mode       = trim((string)$request->input('mode', 'bill'));
        $reqSlno    = (int)$request->input('slno', 0);
        $docNo      = trim((string)$request->input('doc_no', ''));
        $billDate   = $this->parseDate($request->input('bill_date')) ?? date('Y-m-d');
        $custCode   = trim((string)$request->input('cust_code', ''));
        $custName   = trim((string)$request->input('cust_name', ''));
        $addr       = trim((string)$request->input('address', ''));
        $phone      = trim((string)$request->input('phone', ''));
        $mobile     = trim((string)$request->input('mobile', ''));
        $pan        = trim((string)$request->input('pan', ''));
        $gstNo      = trim((string)$request->input('gst_no', ''));
        $smCode     = trim((string)$request->input('sm_code', ''));
        $counter    = trim((string)$request->input('counter', ''));
        $note       = trim((string)$request->input('note', ''));
        $note       = mb_substr($note, 0, 30);
        $dueDate    = $this->parseDate($request->input('due_date'));
        $cocode     = trim((string)$request->input('cocode', ''));
        $cbcode     = trim((string)$request->input('cbcode', 'CASH'));
        $chqBank    = trim((string)$request->input('chq_bank', ''));
        $chqNo      = trim((string)$request->input('chq_no', ''));
        $chqDate    = $this->parseDate($request->input('chq_date'));
        $chqPdc     = strtoupper((string)$request->input('chq_pdc', 'N')) === 'Y' ? 'Y' : 'N';
        $manualBNo  = !empty($request->input('manual_bill_no'));

        $goldRate   = (float)$request->input('gold_rate', $this->genDec('GRATE'));
        $billTotal  = (float)$request->input('bill_total', 0);
        $exchAmt    = (float)$request->input('exchange', 0);
        $advance    = (float)$request->input('advance', 0);
        $refund     = (float)$request->input('refund', 0);
        $tax        = (float)$request->input('tax', 0);
        $sretamt    = (float)$request->input('sretamt', 0);
        $schemeAmt  = (float)$request->input('scheme_amt', 0);
        $gadvance   = (float)$request->input('gadvance', 0);
        $ob         = (float)$request->input('ob', 0);
        $bcharge    = (float)$request->input('bcharge', 0);
        $ccamt      = (float)$request->input('ccamt', 0);
        $chqAmt     = (float)$request->input('chq_amt', 0);
        $blocked    = strtoupper((string)$request->input('blocked', 'N')) === 'Y' ? 'Y' : 'N';
        $taxable    = strtoupper((string)$request->input('taxable', 'N')) === 'Y' ? 'Y' : 'N';
        $amttowgt   = strtoupper((string)$request->input('amttowgt', 'N')) === 'Y' ? 'Y' : 'N';
        $addbcharge = strtoupper((string)$request->input('addbcharge', 'N')) === 'Y' ? 'Y' : 'N';

        $items = (array)$request->input('items', []);
        $exchangeItems = (array)$request->input('exchange_items', []);
        $salesReturnItems = (array)$request->input('sales_return_items', []);
        $goldAdvanceItems = (array)$request->input('gold_advance_items', []);
        $modelItems = (array)$request->input('model_items', []);
        $software = $this->loadSoftwareSettings();
        $sw = static fn (string $k, string $d = ''): string => (string)($software[strtoupper($k)] ?? $d);

        // PB-style prechecks
        if ($docNo === '' && !$manualBNo) {
            return response()->json(['ok' => false, 'message' => 'Order No. empty. You cannot save.']);
        }
        if ($smCode === '' && strtoupper($sw('SMCompulsary', 'N')) === 'Y') {
            return response()->json(['ok' => false, 'message' => 'SM Code empty. You cannot save.']);
        }
        if ($exchAmt > 0 && strtoupper($sw('OrderExToCust', 'Y')) === 'Y' && $custCode === '') {
            return response()->json(['ok' => false, 'message' => 'Please enter customer code for exchange amount.']);
        }
        if ($advance > 0 && strtoupper($sw('OrderCAToCust', 'N')) === 'Y' && $custCode === '') {
            return response()->json(['ok' => false, 'message' => 'Please enter customer code for cash advance.']);
        }
        if ($custName === '' && strtoupper($sw('ExchForm', '')) === 'SALEENA') {
            return response()->json(['ok' => false, 'message' => 'Customer Name empty. You cannot save.']);
        }
        if (strtoupper($sw('OrderDelDateCompulsary', 'Y')) === 'Y') {
            if (!$dueDate) {
                return response()->json(['ok' => false, 'message' => 'Please enter delivery date (Due Date).']);
            }
            $dueTs = strtotime((string)$dueDate);
            $todayY = (int)date('Y');
            if ($dueTs !== false && (int)date('Y', $dueTs) < $todayY) {
                return response()->json(['ok' => false, 'message' => 'Due Date is invalid.']);
            }
        }
        if ($custCode !== '' && $this->hasTable('clients')) {
            $existsCust = DB::table('clients')->where('code', $custCode)->exists();
            if (!$existsCust) {
                return response()->json(['ok' => false, 'message' => 'Invalid Customer Code.']);
            }
        }

        // Validate items
        $validItems = [];
        foreach ($items as $idx => $item) {
            $scode = strtoupper(trim($item['code'] ?? $item['itemcode'] ?? ''));
            if ($scode === '') continue;
            $item['code'] = $scode;
            $dweight = (float)($item['weight'] ?? 0);
            $drate   = (float)($item['rate'] ?? 0);
            $dwastage = (float)($item['wastage'] ?? 0);
            $dmcharge = (float)($item['mcharge'] ?? 0);
            if ($dweight <= 0) {
                return response()->json(['ok' => false, 'message' => "Check Weight ($scode). You cannot save."]);
            }
            if ($drate <= 0) {
                return response()->json(['ok' => false, 'message' => "Check Rate ($scode). You cannot save."]);
            }
            if ($dwastage > 0 && $dmcharge <= 0 && strtoupper($sw('StrictChk', 'N')) === 'Y') {
                return response()->json(['ok' => false, 'message' => "Check Wastage and MC ($scode)."]);
            }
            $validItems[] = $item;
        }

        if (empty($validItems)) {
            return response()->json(['ok' => false, 'message' => 'No valid items to save']);
        }

        // Recalculate bill total from items
        $calcBillTotal = 0.0;
        foreach ($validItems as $itm) { $calcBillTotal += (float)($itm['amount'] ?? 0); }
        $billTotal = $calcBillTotal;

        // Force all order-bill postings at level 1 so daybook always shows them.
        $icontrol = 1;
        $status   = 1; // Active
        $closed   = 0;

        try {
            DB::beginTransaction();

            // Detect existing bill to avoid duplicate save on "previous -> save"
            $existingSlno = 0;
            $existingRow = null;
            if ($reqSlno > 0) {
                $existingSlno = $reqSlno;
                if ($this->hasTable('orderm')) {
                    $existingRow = DB::table('orderm')->where('slno', $reqSlno)->first();
                }
            } elseif ($docNo !== '') {
                $existing = DB::table('orderm')->where('ordno', $docNo)->first();
                if ($existing) {
                    $existingSlno = (int)$existing->slno;
                    $existingRow = $existing;
                }
            }

            // Generate serial number and order number
            if ($existingSlno > 0) {
                $lslno = $existingSlno;
                $ordno = $docNo;
            } else {
                $lslno = $this->incrementGenInt('SERIALNO');
                if ($manualBNo && $docNo !== '') {
                    $ordno = $docNo;
                } else {
                    $ordno = $this->generateBillNumber($billDate);
                }
            }

            // Insert / update orderm
            $pmCols = array_map('strtolower', $this->columnList('orderm'));
            $ordermAll = [
                'slno'        => $lslno,
                'ordno'       => $ordno,
                'custcode'    => $custCode,
                'custname'    => $custName,
                'billamt'     => $billTotal,
                'eamt'        => $exchAmt,
                'advance'     => $advance,
                'gadvance'    => $gadvance,
                'sretamt'     => $sretamt,
                'refund'      => $refund,
                'tax'         => $tax,
                'status'      => $status,
                'control'     => $icontrol,
                'tdate'       => $billDate,
                'duedate'     => $dueDate,
                'duedate_org' => $dueDate,
                'rate'        => $goldRate,
                'smcode'      => $smCode,
                'ob'          => $ob,
                'addr'        => $addr,
                'phone'       => $phone,
                'note'        => $note,
                'ic'          => $this->gsincharge,
                'blocked'     => $blocked,
                'counter'     => $counter,
                'taxable'     => $taxable,
                'amttowgt'    => $amttowgt,
                'closed'      => $closed,
                'cbcode'      => $cbcode,
                'bcharge'     => $bcharge,
                'addbcharge'  => $addbcharge,
                'ccamt'       => $ccamt,
                'chqbank'     => $chqBank,
                'chqamt'      => $chqAmt,
                'chqno'       => $chqNo,
                'chqdate'     => $chqDate,
                'chqpdc'      => $chqPdc,
                'cocode'      => in_array(strtoupper($cocode), ['APP', 'SCHMAMT'], true) ? strtoupper($cocode) : 'APP',
                'jewlcode'    => '',
            ];
            $ordermData = array_filter($ordermAll, fn($k) => in_array($k, $pmCols), ARRAY_FILTER_USE_KEY);

            if ($existingSlno > 0) {
                $oldControl = (int)($existingRow->control ?? $icontrol);
                $oldOrdno   = trim((string)($existingRow->ordno ?? $docNo));
                $this->rollbackExistingOrderEffects($lslno, $oldControl, $oldOrdno, strtoupper($sw('CalcWACost', 'Y')) === 'Y');

                DB::table('orderm')->where('slno', $lslno)->update($ordermData);
                DB::table('orderd')->where('slno', $lslno)->delete();
                // Delete exchange items from purchased for this slno
                if ($this->hasTable('purchased')) {
                    DB::table('purchased')->where('slno', $lslno)->delete();
                }
                if ($this->hasTable('purchasem')) {
                    DB::table('purchasem')->where('slno', $lslno)->delete();
                }
                if ($this->hasTable('salesrd')) {
                    DB::table('salesrd')->where('slno', $lslno)->delete();
                }
                if ($this->hasTable('salesrm')) {
                    DB::table('salesrm')->where('slno', $lslno)->delete();
                }
                if ($this->hasTable('orderdga')) {
                    DB::table('orderdga')->where('slno', $lslno)->delete();
                }
                if ($this->hasTable('ordermodel')) {
                    DB::table('ordermodel')->where('slno', $lslno)->delete();
                }
                if ($this->hasTable('stkandprofit')) {
                    DB::table('stkandprofit')->where('slno', $lslno)->delete();
                }
                if ($this->hasTable('oglist')) {
                    DB::table('oglist')->where('slno', $lslno)->delete();
                }
                if ($this->hasTable('pdclist')) {
                    DB::table('pdclist')->where('slno', $lslno)->delete();
                }
                if ($this->hasTable('smithnewwrk')) {
                    DB::table('smithnewwrk')->where('ordno', $ordno)->delete();
                }
                // Rebuild daybook lines on edit
                if ($this->hasTable('daybook')) {
                    DB::table('daybook')->where('slno', $lslno)->delete();
                }
                if ($this->hasTable('daybookpart')) {
                    DB::table('daybookpart')->where('slno', $lslno)->delete();
                }
            } else {
                DB::table('orderm')->insert($ordermData);
            }

            // Insert orderd items
            $sno = 0;
            $odCols = array_map('strtolower', $this->columnList('orderd'));
            foreach ($validItems as $item) {
                $sno++;
                $scode    = strtoupper(trim($item['code'] ?? ''));
                $iqty     = (int)($item['qty'] ?? 0);
                $dwgt     = (float)($item['weight'] ?? 0);
                $drate    = (float)($item['rate'] ?? 0);
                $damount  = (float)($item['amount'] ?? 0);
                $dstwgt   = (float)($item['stwgt'] ?? $item['stonewgt'] ?? 0);
                $dstprice = (float)($item['stprice'] ?? $item['stoneprice'] ?? 0);
                $dmc      = (float)($item['mcharge'] ?? 0);
                $dwastage = (float)($item['wastage'] ?? 0);
                $dcost    = ($dwgt > 0) ? round($damount / $dwgt, 2) : 0;
                $spart    = trim($item['narration'] ?? $item['part'] ?? '');
                $siqtype  = trim($item['purity'] ?? $item['iqtype'] ?? '');
                $ssmith   = trim($item['smith'] ?? '');
                $sstage   = (int)($item['stage'] ?? 1);

                $odAll = [
                    'slno'      => $lslno,
                    'sno'       => $sno,
                    'code'      => $scode,
                    'qty'       => $iqty,
                    'weight'    => $dwgt,
                    'rate'      => $drate,
                    'stonewgt'  => $dstwgt,
                    'stoneprice'=> $dstprice,
                    'mcharge'   => $dmc,
                    'wastage'   => $dwastage,
                    'amount'    => $damount,
                    'cost'      => $dcost,
                    'part'      => $spart,
                    'iqtype'    => $siqtype,
                    'smith'     => $ssmith,
                    'stage'     => $sstage,
                ];
                DB::table('orderd')->insert(
                    array_filter($odAll, fn($k) => in_array($k, $odCols), ARRAY_FILTER_USE_KEY)
                );
            }

            // Exchange master/detail + stock effect
            if ($exchAmt > 0 && !empty($exchangeItems)) {
                if ($this->hasTable('purchasem')) {
                    $pmCols2 = $this->getColumns('purchasem');
                    $purchasem = [
                        'slno'     => $lslno,
                        'billno'   => $ordno,
                        'docno'    => $ordno,
                        'suppcode' => $custCode,
                        'name'     => $custName,
                        'billamt'  => $exchAmt,
                        'pamt'     => $exchAmt,
                        'status'   => 3,
                        'pr'       => 'E',
                        'control'  => $icontrol,
                        'tdate'    => $billDate,
                        'ttime'    => date('H:i:s'),
                        'rate'     => $goldRate,
                        'smcode'   => $smCode,
                        'ic'       => $this->gsincharge,
                        'counter'  => $counter,
                    ];
                    DB::table('purchasem')->insert(array_filter($purchasem, fn($k) => in_array($k, $pmCols2, true), ARRAY_FILTER_USE_KEY));
                }

                if ($this->hasTable('purchased')) {
                    $pdCols = $this->getColumns('purchased');
                    $esno = 0;
                    foreach ($exchangeItems as $ei) {
                        $esc = strtoupper(trim((string)($ei['code'] ?? '')));
                        if ($esc === '') continue;
                        $esno++;
                        $eqty = (int)($ei['qty'] ?? 0);
                        $ewgt = (float)($ei['weight'] ?? 0);
                        $estw = (float)($ei['stwgt'] ?? $ei['stonewgt'] ?? 0);
                        $eamt = (float)($ei['amount'] ?? 0);
                        $ecost = ($ewgt > 0) ? round($eamt / $ewgt, 2) : (float)($ei['cost'] ?? 0);
                        $stktype = trim((string)($ei['stktype'] ?? ''));

                        $newCost = $this->computeWeightedCostForAdd($esc, $ewgt, $ecost, $icontrol, strtoupper($sw('CalcWACost', 'Y')) === 'Y');
                        $this->applyItemStockDelta($esc, $eqty, $ewgt, $estw, $icontrol, $stktype, $newCost);

                        $pdAll = [
                            'slno'     => $lslno,
                            'sno'      => $esno,
                            'code'     => $esc,
                            'name'     => trim((string)($ei['name'] ?? '')),
                            'qty'      => $eqty,
                            'weight'   => $ewgt,
                            'rate'     => (float)($ei['rate'] ?? 0),
                            'lesswgt'  => (float)($ei['lesswgt'] ?? 0),
                            'lessperc' => (float)($ei['lessperc'] ?? 0),
                            'amount'   => $eamt,
                            'cost'     => $ecost,
                            'stwgt'    => $estw,
                            'stprice'  => (float)($ei['stprice'] ?? $ei['stoneprice'] ?? 0),
                            'mud'      => (float)($ei['mud'] ?? 0),
                            'stktype'  => $stktype,
                            'iqtype'   => trim((string)($ei['iqtype'] ?? '')),
                            'stktouch' => (float)($ei['stktouch'] ?? 0),
                            'rate2'    => (float)($ei['rate2'] ?? 0),
                            'batch'    => trim((string)($ei['batch'] ?? '')),
                            'touch'    => (float)($ei['touch'] ?? 0),
                        ];
                        DB::table('purchased')->insert(array_filter($pdAll, fn($k) => in_array($k, $pdCols, true), ARRAY_FILTER_USE_KEY));
                    }
                }
            }

            // Gold advance detail + stock effect
            if (!empty($goldAdvanceItems) && $this->hasTable('orderdga')) {
                $gaCols = $this->getColumns('orderdga');
                $gSno = 0;
                foreach ($goldAdvanceItems as $ga) {
                    $gcode = strtoupper(trim((string)($ga['code'] ?? $ga['itemcode'] ?? '')));
                    if ($gcode === '') continue;
                    $gSno++;
                    $gqty = (int)($ga['qty'] ?? 0);
                    $gw = (float)($ga['weight'] ?? 0);
                    $gstw = (float)($ga['stonewgt'] ?? 0);
                    $gstktype = trim((string)($ga['stktype'] ?? ''));
                    $this->applyItemStockDelta($gcode, $gqty, $gw, $gstw, $icontrol, $gstktype, null);

                    $row = [
                        'slno' => $lslno, 'sno' => $gSno, 'code' => $gcode, 'qty' => $gqty, 'weight' => $gw,
                        'cost' => (float)($ga['cost'] ?? 0), 'stktype' => $gstktype, 'stonewgt' => $gstw,
                        'lessperc' => (float)($ga['lessperc'] ?? 0), 'lesswgt' => (float)($ga['lesswgt'] ?? 0),
                        'iqtype' => trim((string)($ga['iqtype'] ?? '')), 'stktouch' => (float)($ga['stktouch'] ?? 0),
                        'tdate' => $billDate, 'control' => $icontrol,
                    ];
                    DB::table('orderdga')->insert(array_filter($row, fn($k) => in_array($k, $gaCols, true), ARRAY_FILTER_USE_KEY));
                }
            }

            // Sales return master/detail + stock effect
            if ($sretamt > 0 && !empty($salesReturnItems) && $this->hasTable('salesrd')) {
                $srCols = $this->getColumns('salesrd');
                $srSno = 0;
                $retDisc = (float)($salesReturnItems[0]['wgtamt'] ?? 0);
                $retTax  = (float)($salesReturnItems[0]['taxamt'] ?? 0);
                $retAst  = (float)($salesReturnItems[0]['ast'] ?? 0);

                foreach ($salesReturnItems as $sr) {
                    $srcode = strtoupper(trim((string)($sr['code'] ?? $sr['itemcode'] ?? '')));
                    if ($srcode === '') continue;
                    $srSno++;
                    $srqty = (int)($sr['qty'] ?? 0);
                    $srw = (float)($sr['weight'] ?? 0);
                    $srstw = (float)($sr['stonewgt'] ?? $sr['stwgt'] ?? 0);
                    $srstktype = trim((string)($sr['stktype'] ?? ''));
                    $this->applyItemStockDelta($srcode, $srqty, $srw, $srstw, $icontrol, $srstktype, null);

                    $row = [
                        'slno' => $lslno, 'sno' => $srSno, 'code' => $srcode, 'name' => trim((string)($sr['name'] ?? $sr['itemname'] ?? '')),
                        'qty' => $srqty, 'weight' => $srw, 'stonewgt' => $srstw,
                        'stoneprice' => (float)($sr['stoneprice'] ?? $sr['stprice'] ?? 0),
                        'mcharge' => (float)($sr['mcharge'] ?? $sr['mc'] ?? 0),
                        'wastage' => (float)($sr['wastage'] ?? 0), 'rate' => (float)($sr['rate'] ?? 0),
                        'amount' => (float)($sr['amount'] ?? 0), 'cost' => (float)($sr['cost'] ?? 0),
                        'stktype' => $srstktype, 'iqtype' => trim((string)($sr['iqtype'] ?? '')),
                        'stktouch' => (float)($sr['stktouch'] ?? 0),
                    ];
                    DB::table('salesrd')->insert(array_filter($row, fn($k) => in_array($k, $srCols, true), ARRAY_FILTER_USE_KEY));
                }

                if ($this->hasTable('salesrm')) {
                    $srmCols = $this->getColumns('salesrm');
                    $billAmtForSr = $sretamt + $retDisc - $retTax - $retAst;
                    $srm = [
                        'slno' => $lslno, 'billno' => $ordno, 'custcode' => $custCode, 'custname' => $custName,
                        'billamt' => $billAmtForSr, 'discount' => $retDisc, 'pamt' => $sretamt, 'status' => 1,
                        'sr' => 'E', 'grate' => $goldRate, 'control' => $icontrol, 'tdate' => $billDate,
                        'ttime' => date('H:i:s'), 'srate' => $this->genDec('SRATE'), 'ob' => 0, 'smcode' => $smCode,
                        'netamt' => $sretamt, 'ic' => $this->gsincharge, 'staxamt' => $retTax, 'astamt' => $retAst,
                    ];
                    DB::table('salesrm')->insert(array_filter($srm, fn($k) => in_array($k, $srmCols, true), ARRAY_FILTER_USE_KEY));
                }
            }

            // Model rows
            if (!empty($modelItems) && $this->hasTable('ordermodel')) {
                $mdCols = $this->getColumns('ordermodel');
                foreach ($modelItems as $mri) {
                    $mcode = strtoupper(trim((string)($mri['code'] ?? $mri['itemcode'] ?? '')));
                    if ($mcode === '') continue;
                    $row = [
                        'slno' => $lslno,
                        'code' => $mcode,
                        'qty' => (int)($mri['qty'] ?? 0),
                        'weight' => (float)($mri['weight'] ?? 0),
                        'part' => trim((string)($mri['part'] ?? $mri['iqtype'] ?? '')),
                    ];
                    DB::table('ordermodel')->insert(array_filter($row, fn($k) => in_array($k, $mdCols, true), ARRAY_FILTER_USE_KEY));
                }
            }

            // Daybook posting (core lines: advance/exchange/sales return)
            $this->postOrderDaybook($lslno, [
                'doc_no'    => $ordno,
                'cust_code' => $custCode,
                'cust_name' => $custName,
                'tdate'     => $billDate,
                'control'   => $icontrol,
                'rate'      => $goldRate,
                'advance'   => $advance,
                'refund'    => $refund,
                'ccamt'     => $ccamt,
                'chq_amt'   => $chqAmt,
                'cbcode'    => $cbcode,
                'chq_bank'  => $chqBank,
                'chq_pdc'   => $chqPdc,
                'chq_no'    => $chqNo,
                'chq_date'  => $chqDate,
                'exchange'  => $exchAmt,
                'sretamt'   => $sretamt,
                'scheme_amt'=> $schemeAmt,
                'scheme_ledger' => in_array(strtoupper($cocode), ['APP', 'SCHMAMT'], true) ? strtoupper($cocode) : 'APP',
                'addbcharge'=> $addbcharge,
                'bcharge'   => $bcharge,
                'net_total' => $billTotal + $tax - $advance - $exchAmt - $sretamt - $schemeAmt,
            ]);

            // Increment bill counter only on new bill
            if ($existingSlno === 0 && !$manualBNo) {
                // Counter was already incremented in generateBillNumber()
            }

            DB::commit();

            $message = 'Order saved successfully';
            $secondarySync = null;
            if ($shouldSecondarySync && $lslno > 0) {
                try {
                    $secondarySync = (new SecondaryDatabaseSync())->sync('order', (int) $lslno);
                    $message .= '. Secondary sync completed to ' . ($secondarySync['database'] ?? '');
                } catch (\Throwable $e) {
                    $message .= '. Primary save completed, but secondary sync failed: ' . $e->getMessage();
                }
            }

            return response()->json([
                'ok'      => true,
                'message' => $message,
                'doc_no'  => $ordno,
                'slno'    => $lslno,
                'secondary_sync' => $secondarySync,
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'message' => 'Save failed: ' . $e->getMessage()]);
        }
    }

    // ─── API: Get Order ──────────────────────────────────────────────────────

    public function get(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $billNo = trim((string)$request->query('bill_no', ''));
        if ($billNo === '') return response()->json(['ok' => false, 'message' => 'No bill number']);

        $payload = $this->loadOrderPayload($billNo);
        if ($payload === null) {
            $exists = $this->hasTable('orderm')
                ? DB::table('orderm')->where('ordno', $billNo)->exists()
                : false;
            return response()->json([
                'ok' => false,
                'message' => $exists ? 'This order is cancelled.' : 'Order not found',
            ]);
        }

        return response()->json($payload + ['ok' => true]);
    }

    // ─── API: Navigate ───────────────────────────────────────────────────────

    public function prevBill(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $billNo = trim((string)($request->query('doc_no') ?? $request->query('bill_no', '')));
        if (!$this->hasTable('orderm')) return response()->json(['ok' => false, 'message' => 'No table']);

        $current = $billNo !== ''
            ? DB::table('orderm')
                ->where('ordno', $billNo)
                ->whereNotNull('ordno')
                ->where('ordno', '<>', '')
                ->where('control', '<=', $this->gilevel)
                ->where(function ($q) {
                    $q->whereNull('status')->orWhere('status', '<>', 0);
                })
                ->value('slno')
            : null;

        if ($current) {
            $row = DB::table('orderm')
                ->whereNotNull('ordno')
                ->where('ordno', '<>', '')
                ->where('control', '<=', $this->gilevel)
                ->where(function ($q) {
                    $q->whereNull('status')->orWhere('status', '<>', 0);
                })
                ->where('slno', '<', $current)
                ->orderByDesc('slno')->first(['ordno']);
        } else {
            $row = DB::table('orderm')
                ->whereNotNull('ordno')
                ->where('ordno', '<>', '')
                ->where('control', '<=', $this->gilevel)
                ->where(function ($q) {
                    $q->whereNull('status')->orWhere('status', '<>', 0);
                })
                ->orderByDesc('slno')->first(['ordno']);
        }

        if (!$row) return response()->json(['ok' => false, 'message' => 'No previous order']);
        return response()->json(['ok' => true, 'bill_no' => $row->ordno]);
    }

    public function nextBill(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $billNo = trim((string)($request->query('doc_no') ?? $request->query('bill_no', '')));
        if (!$this->hasTable('orderm')) return response()->json(['ok' => false, 'message' => 'No table']);

        $current = $billNo !== ''
            ? DB::table('orderm')
                ->where('ordno', $billNo)
                ->whereNotNull('ordno')
                ->where('ordno', '<>', '')
                ->where('control', '<=', $this->gilevel)
                ->where(function ($q) {
                    $q->whereNull('status')->orWhere('status', '<>', 0);
                })
                ->value('slno')
            : null;

        if ($current) {
            $row = DB::table('orderm')
                ->whereNotNull('ordno')
                ->where('ordno', '<>', '')
                ->where('control', '<=', $this->gilevel)
                ->where(function ($q) {
                    $q->whereNull('status')->orWhere('status', '<>', 0);
                })
                ->where('slno', '>', $current)
                ->orderBy('slno')->first(['ordno']);
        } else {
            $row = DB::table('orderm')
                ->whereNotNull('ordno')
                ->where('ordno', '<>', '')
                ->where('control', '<=', $this->gilevel)
                ->where(function ($q) {
                    $q->whereNull('status')->orWhere('status', '<>', 0);
                })
                ->orderByDesc('slno')->first(['ordno']);
        }

        if (!$row) return response()->json(['ok' => false, 'message' => 'No next order']);
        return response()->json(['ok' => true, 'bill_no' => $row->ordno]);
    }

    // ─── API: Search ─────────────────────────────────────────────────────────

    public function search(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $q = trim((string)$request->query('q', ''));
        if (!$this->hasTable('orderm')) return response()->json(['ok' => true, 'results' => []]);

        $rows = DB::table('orderm')
            ->where(function ($qb) {
                $qb->whereNull('status')->orWhere('status', '<>', 0);
            })
            ->where(function ($qb) use ($q) {
                $qb->where('ordno', 'like', "%$q%")
                   ->orWhere('custname', 'like', "%$q%")
                   ->orWhere('custcode', 'like', "%$q%");
            })
            ->orderByDesc('slno')
            ->limit(20)
            ->get(['ordno', 'tdate', 'custname', 'billamt', 'status']);

        $results = $rows->map(fn ($r) => [
            'doc_no'     => $r->ordno,
            'date'       => $r->tdate,
            'cust_name'  => $r->custname,
            'bill_total' => (float)($r->billamt ?? 0),
            'status'     => (int)($r->status ?? 1),
        ])->values();

        return response()->json(['ok' => true, 'results' => $results]);
    }

    // ─── API: Cancel ─────────────────────────────────────────────────────────

    public function cancelBill(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $billNo    = trim((string)($request->input('doc_no') ?? ''));
        $reason    = trim((string)$request->input('reason', ''));
        $gilevel   = (int)($request->session()->get('gilevel', 1));
        $gisemi    = (int)($request->session()->get('semi', $gilevel));
        $gsuserid  = (string)($request->session()->get('user_code', ''));
        $gsincharge = (string)($request->session()->get('user_code', ''));

        if ($billNo === '') return response()->json(['ok' => false, 'message' => 'No bill number']);
        if (!$this->hasTable('orderm')) return response()->json(['ok' => false]);

        // Strip prefix if present (PB: sfc2 = DP prefix)
        $dpPrefix  = strtoupper(trim($this->genStr('DP')));
        $searchNo  = $billNo;
        if ($dpPrefix !== '' && strpos(strtoupper($billNo), $dpPrefix) === 0 && $gisemi > 1) {
            $searchNo = substr($billNo, strlen($dpPrefix));
        }

        // Lookup order
        $controlLimit = ($dpPrefix !== '' && strpos(strtoupper($billNo), $dpPrefix) === 0 && $gisemi > 1) ? $gisemi : $gilevel;
        $order = DB::table('orderm')
            ->where(DB::raw('TRIM(ordno)'), $searchNo)
            ->where('control', '<=', $controlLimit)
            ->first();

        if (!$order) return response()->json(['ok' => false, 'message' => 'Order does not exist']);
        if ((int) ($order->status ?? 1) === 0) {
            return response()->json(['ok' => false, 'message' => 'This order is already cancelled.']);
        }

        $lslno   = (int)$order->slno;
        $control = (int)($order->control ?? 1);
        $dtdate  = $order->tdate ?? date('Y-m-d');

        // PB: Check if a sales bill exists against this order
        $saleBillNo = trim((string)($order->salebill ?? ''));
        if ($saleBillNo !== '' && $this->hasTable('salesm')) {
            $salesSlno = (int)(DB::table('salesm')->where('billno', $saleBillNo)->max('slno') ?? 0);
            if ($salesSlno > 0) {
                return response()->json([
                    'ok'      => false,
                    'message' => "You have to cancel sales against this order first.\nYou can't cancel this order entry.",
                ]);
            }
        }

        // Calculate WA cost setting
        $calcWACost = strtoupper($this->genStr('CalcWACost') ?: 'Y') === 'Y';

        try {
            DB::beginTransaction();

            // ── Reverse stock from purchased items ──
            $this->rollbackExistingOrderEffects($lslno, $control, trim($order->ordno ?? ''), $calcWACost);

            // ── Delete from all related tables (PB order) ──
            $deleteTables = [
                'orderd', 'purchasem', 'purchased',
                'salesrm', 'salesrd', 'orderdga', 'orderdmodel',
                'daybook', 'daybookpart', 'stkandprofit', 'oglist',
            ];
            foreach ($deleteTables as $tbl) {
                if ($this->hasTable($tbl)) {
                    DB::table($tbl)->where('slno', $lslno)->delete();
                }
            }

            $ordermCols = array_map('strtolower', $this->columnList('orderm'));
            $ordermUpdate = [];
            if (in_array('status', $ordermCols, true)) {
                $ordermUpdate['status'] = 0;
            }
            if (in_array('salebill', $ordermCols, true)) {
                $ordermUpdate['salebill'] = '';
            }
            if (in_array('blocked', $ordermCols, true)) {
                $ordermUpdate['blocked'] = 'N';
            }
            if (in_array('note', $ordermCols, true)) {
                $prevNote = trim((string) ($order->note ?? ''));
                $cancelNote = 'CANCELLED' . ($reason !== '' ? ': ' . $reason : '');
                $mergedNote = trim($prevNote !== '' ? ($prevNote . ' | ' . $cancelNote) : $cancelNote);
                $noteLimit = 0;
                try {
                    $noteMeta = DB::selectOne(
                        "SELECT CHARACTER_MAXIMUM_LENGTH AS max_len
                         FROM information_schema.COLUMNS
                         WHERE TABLE_SCHEMA = DATABASE()
                           AND TABLE_NAME = ?
                           AND COLUMN_NAME = ?",
                        ['orderm', 'note']
                    );
                    $noteLimit = (int) ($noteMeta->max_len ?? 0);
                } catch (\Throwable $e) {
                    $noteLimit = 0;
                }
                if ($noteLimit <= 0) {
                    $noteLimit = 40;
                }
                $ordermUpdate['note'] = mb_substr($mergedNote, 0, $noteLimit);
            }
            if ($ordermUpdate !== []) {
                DB::table('orderm')->where('slno', $lslno)->update($ordermUpdate);
            }

            // ── Log cancellation in delpart (PB audit trail) ──
            if ($this->hasTable('delpart')) {
                $dpCols = array_map('strtolower', $this->columnList('delpart'));
                $logData = [
                    'tdate'    => $dtdate,
                    'part'     => 'Order Entry(' . trim($searchNo) . ') Canceled -' . $dtdate
                                  . ($reason !== '' ? ' [' . $reason . ']' : ''),
                    'control'  => $gisemi,
                    'slno'     => $lslno,
                    'utype'    => 'C',
                    'ttype'    => 'O',
                    'updtdate' => date('Y-m-d'),
                    'updttime' => date('H:i:s'),
                    'uid'      => $gsuserid,
                    'ic'       => $gsincharge,
                ];
                $logData = array_filter($logData, fn($k) => in_array($k, $dpCols), ARRAY_FILTER_USE_KEY);
                DB::table('delpart')->insert($logData);
            }

            DB::commit();

            $message = 'Order cancelled successfully';
            if (SecondaryDatabaseSync::userCanUse((string) $request->session()->get('user_code')) && $lslno > 0) {
                try {
                    $secondarySync = (new SecondaryDatabaseSync())->sync('order', $lslno);
                    $message .= ' Secondary sync completed to ' . ($secondarySync['database'] ?? '') . '.';
                } catch (\Throwable $e) {
                    $message .= ' Primary cancel completed, but secondary sync failed: ' . $e->getMessage();
                }
            }

            return response()->json(['ok' => true, 'message' => $message]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'message' => 'Cancel failed: ' . $e->getMessage()]);
        }
    }

    // ─── Private Helpers ─────────────────────────────────────────────────────

    private function generateBillNumber(?string $billDate = null): string
    {
        $billDate = $billDate ?: date('Y-m-d');
        if ($this->shouldUseDateBasedBillNo()) {
            return $this->makeDateBasedBillNumber($billDate);
        }

        if ($this->shouldUseSecondaryPrefix('order')) {
            $prefix = $this->secondaryPrefixFor('order');
            $len    = 4;
            $next   = $this->reserveNextOrderNumber($prefix);
            return $prefix . str_pad($next, $len, '0', STR_PAD_LEFT);
        }

        $prefix = $this->genStr('ORDBPREF') ?: 'OR/';
        $lenStr = $this->genStr('ORDBLEN') ?: '4';
        $len    = (int)$lenStr ?: 4;
        $next   = $this->reserveNextOrderNumber($prefix);
        return $prefix . str_pad($next, $len, '0', STR_PAD_LEFT);
    }

    private function previewBillNumber(?string $billDate = null): string
    {
        $billDate = $billDate ?: date('Y-m-d');
        if ($this->shouldUseDateBasedBillNo()) {
            return $this->makeDateBasedBillNumber($billDate);
        }

        if ($this->shouldUseSecondaryPrefix('order')) {
            $prefix  = $this->secondaryPrefixFor('order');
            $len     = 4;
            $current = $this->genInt('ORDERB');
            $maxUsed = $this->lastOrderNumberForPrefix($prefix);
            $next    = max($current, $maxUsed) + 1;
            return $prefix . str_pad($next, $len, '0', STR_PAD_LEFT);
        }

        $prefix  = $this->genStr('ORDBPREF') ?: 'OR/';
        $lenStr  = $this->genStr('ORDBLEN') ?: '4';
        $len     = (int)$lenStr ?: 4;
        $current = $this->genInt('ORDERB');
        $maxUsed = $this->lastOrderNumberForPrefix($prefix);
        $next    = max($current, $maxUsed) + 1;
        return $prefix . str_pad($next, $len, '0', STR_PAD_LEFT);
    }

    private function shouldUseDateBasedBillNo(): bool
    {
        $flag = strtoupper($this->genStr('OrderNoDateBased'));
        if ($flag === 'Y') return true;
        if ($flag === 'N') return false;

        if (!$this->hasTable('orderm')) return false;

        $latest = trim((string) (DB::table('orderm')->orderByDesc('slno')->value('ordno') ?? ''));
        return $latest !== '' && (bool) preg_match('/^\d{2}\/\d{4}\/\d{2,}$/', $latest);
    }

    private function makeDateBasedBillNumber(string $billDate): string
    {
        $ts = strtotime($billDate);
        if ($ts === false) {
            $ts = time();
        }

        $prefix = date('y/md', $ts);
        $running = $this->hasTable('orderm')
            ? (int) DB::table('orderm')->whereDate('tdate', $billDate)->count()
            : 0;

        do {
            $running++;
            $billNo = $prefix . '/' . str_pad((string) $running, 2, '0', STR_PAD_LEFT);
            $exists = $this->hasTable('orderm')
                ? DB::table('orderm')->where('ordno', $billNo)->exists()
                : false;
        } while ($exists);

        return $billNo;
    }

    private function genInt(string $code): int
    {
        if (!$this->hasTable('generali')) return 0;
        return (int)(DB::table('generali')->where('code', $code)->value('cvalue') ?? 0);
    }

    private function incrementGenInt(string $code): int
    {
        if (!$this->hasTable('generali')) return 1;
        $current = $this->genInt($code);
        $maxUsed = 0;
        $tables = $code === 'SERIALNO'
            ? ['salesm', 'salesrm', 'purchasem', 'purchaserm', 'daybook', 'daybookpart', 'orderm', 'smithm', 'refinerym', 'repairm']
            : ['orderm'];
        foreach ($tables as $table) {
            if ($this->hasTable($table) && Schema::hasColumn($table, 'slno')) {
                $maxUsed = max($maxUsed, (int) (DB::table($table)->max('slno') ?? 0));
            }
        }
        $next = max($current, $maxUsed) + 1;
        $updated = DB::table('generali')->where('code', $code)->update(['cvalue' => $next]);
        if ($updated === 0) {
            DB::table('generali')->insert(['code' => $code, 'cvalue' => $next]);
        }
        return $next;
    }

    private function lastOrderNumberForPrefix(string $prefix): int
    {
        if (!$this->hasTable('orderm')) {
            return 0;
        }

        $maxNo = 0;
        $rows = DB::table('orderm')
            ->whereNotNull('ordno')
            ->where('ordno', 'like', $prefix . '%')
            ->get(['ordno']);

        foreach ($rows as $row) {
            $value = trim((string) ($row->ordno ?? ''));
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

    private function reserveNextOrderNumber(string $prefix): int
    {
        $current = $this->genInt('ORDERB');
        $maxUsed = $this->lastOrderNumberForPrefix($prefix);
        $next = max($current, $maxUsed) + 1;

        if ($this->hasTable('generali')) {
            $updated = DB::table('generali')->where('code', 'ORDERB')->update(['cvalue' => $next]);
            if ($updated === 0) {
                DB::table('generali')->insert(['code' => 'ORDERB', 'cvalue' => $next]);
            }
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

    private function getCustomerBalance(string $code): float
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

    private function loadPrintShopInfo(array $software = []): array
    {
        $logoFile = $this->genStr('SHOPLOGO');
        $logoUrl = '';
        if ($logoFile !== '') {
            $path = public_path('uploads/logo/' . $logoFile);
            if (file_exists($path)) {
                $logoUrl = url('uploads/logo/' . $logoFile);
            }
        }

        return [
            'name' => $this->genStr('SHOPNM') ?: $this->genStr('Name'),
            'addr' => $this->genStr('SHOPADDR') ?: $this->genStr('Addr'),
            'phone' => $this->genStr('SHOPPHONE') ?: $this->genStr('Phone'),
            'mobile' => $this->genStr('Mobile'),
            'gstin' => trim((string) ($software['KGST'] ?? '')),
            'logo_url' => $logoUrl,
        ];
    }

    private function loadOrderPayload(string $billNo): ?array
    {
        $billNo = trim($billNo);
        if ($billNo === '' || !$this->hasTable('orderm')) return null;

        $m = DB::table('orderm')
            ->where('ordno', $billNo)
            ->whereNotNull('ordno')
            ->where('ordno', '<>', '')
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '<>', 0);
            })
            ->first();
        if (!$m) return null;

        $items = $this->hasTable('orderd')
            ? DB::table('orderd')->where('slno', $m->slno)->orderBy('sno')->get()->toArray()
            : [];

        $enriched = [];
        foreach ($items as $r) {
            $rn = (string) (DB::table('items')->where('code', $r->code ?? '')->value('name') ?? '');
            $enriched[] = [
                'code' => $r->code ?? '',
                'name' => $rn,
                'rate' => (float) ($r->rate ?? 0),
                'qty' => (int) ($r->qty ?? 0),
                'weight' => (float) ($r->weight ?? 0),
                'stwgt' => (float) ($r->stonewgt ?? 0),
                'stprice' => (float) ($r->stoneprice ?? 0),
                'mcharge' => (float) ($r->mcharge ?? 0),
                'wastage' => (float) ($r->wastage ?? 0),
                'amount' => (float) ($r->amount ?? 0),
                'narration' => trim($r->part ?? ''),
                'model' => trim($r->part ?? ''),
                'iqtype' => trim($r->iqtype ?? ''),
                'smith' => trim($r->smith ?? ''),
                'stage' => trim((string) ($r->stage ?? '')),
                'cost' => (float) ($r->cost ?? 0),
            ];
        }

        $exchItems = $this->hasTable('purchased')
            ? DB::table('purchased')->where('slno', $m->slno)->orderBy('sno')->get()->toArray()
            : [];

        $enrichedExch = [];
        foreach ($exchItems as $r) {
            $rn = (string) (DB::table('items')->where('code', $r->code ?? '')->value('name') ?? '');
            $enrichedExch[] = [
                'code' => $r->code ?? '',
                'name' => (trim($r->name ?? '') !== '') ? $r->name : $rn,
                'qty' => (int) ($r->qty ?? 0),
                'weight' => (float) ($r->weight ?? 0),
                'rate' => (float) ($r->rate ?? 0),
                'lesswgt' => (float) ($r->lesswgt ?? 0),
                'lessperc' => (float) ($r->lessperc ?? 0),
                'amount' => (float) ($r->amount ?? 0),
                'stwgt' => (float) ($r->stwgt ?? 0),
                'stprice' => (float) ($r->stprice ?? 0),
                'mud' => (float) ($r->mud ?? 0),
                'touch' => (float) ($r->touch ?? 0),
                'rate2' => (float) ($r->rate2 ?? 0),
                'stktype' => trim((string) ($r->stktype ?? '')),
                'iqtype' => trim((string) ($r->iqtype ?? '')),
                'stktouch' => (float) ($r->stktouch ?? 0),
                'stkinnos' => trim((string) ($r->stkinnos ?? 'N')),
                'ornament' => trim((string) ($r->ornament ?? 'N')),
                'cost' => (float) ($r->cost ?? 0),
                'itemtype' => trim((string) ($r->itemtype ?? '')),
                'stkfd' => trim((string) ($r->stkfd ?? '')),
                'batch' => trim((string) ($r->batch ?? '')),
            ];
        }

        $salesReturnSummary = null;
        if ($this->hasTable('salesrm')) {
            $srm = DB::table('salesrm')->where('slno', $m->slno)->first();
            if ($srm) {
                $salesReturnSummary = [
                    'discount' => (float) ($srm->discount ?? 0),
                    'taxamt' => (float) ($srm->staxamt ?? 0),
                    'astamt' => (float) ($srm->astamt ?? 0),
                    'pamt' => (float) ($srm->pamt ?? 0),
                    'billamt' => (float) ($srm->billamt ?? 0),
                    'netamt' => (float) ($srm->netamt ?? 0),
                ];
            }
        }

        $salesReturnItems = $this->hasTable('salesrd')
            ? DB::table('salesrd')->where('slno', $m->slno)->orderBy('sno')->get()->toArray()
            : [];
        $goldAdvanceItems = $this->hasTable('orderdga')
            ? DB::table('orderdga')->where('slno', $m->slno)->orderBy('sno')->get()->toArray()
            : [];
        $modelItems = $this->hasTable('ordermodel')
            ? DB::table('ordermodel')->where('slno', $m->slno)->get()->toArray()
            : [];

        $salesmanName = '';
        if ($this->hasTable('sman') && trim((string) ($m->smcode ?? '')) !== '') {
            $salesmanName = trim((string) (DB::table('sman')->where('code', $m->smcode)->value('name') ?? ''));
        }

        $mobile = '';
        $pan = '';
        $gstNo = '';
        if ($this->hasTable('clients') && trim((string) ($m->custcode ?? '')) !== '') {
            $client = DB::table('clients')->where('code', $m->custcode)->first();
            $mobile = trim((string) ($client->mobile ?? ''));
            $pan = trim((string) ($client->panadhar ?? ''));
            $gstNo = trim((string) ($client->tin ?? ''));
        }

        $advAfter = 0.0;
        if ($this->hasTable('advafter') && trim((string) ($m->ordno ?? '')) !== '') {
            $advAfter = (float) (DB::table('advafter')->where('ordno', $m->ordno)->sum('amount') ?? 0);
        }

        $ob = (float) ($m->ob ?? 0);
        $advance = (float) ($m->advance ?? 0);
        $exchAmt = (float) ($m->eamt ?? 0);
        $sretamt = (float) ($m->sretamt ?? 0);
        $refund = (float) ($m->refund ?? 0);
        $billAmt = (float) ($m->billamt ?? 0);
        $tax = (float) ($m->tax ?? 0);
        $schemeLedger = strtoupper(trim((string) ($m->cocode ?? 'APP')));
        if (!in_array($schemeLedger, ['APP', 'SCHMAMT'], true)) {
            $schemeLedger = 'APP';
        }
        $schemeAmt = 0.0;
        if ($this->hasTable('daybook')) {
            $schemeEntry = DB::table('daybook')
                ->where('slno', $m->slno)
                ->whereIn('accode', ['APP', 'SCHMAMT'])
                ->whereRaw('amount < 0')
                ->orderByRaw('ABS(amount) DESC')
                ->first(['accode', 'amount']);
            if ($schemeEntry) {
                $schemeAmt = abs((float) ($schemeEntry->amount ?? 0));
                $entryLedger = strtoupper(trim((string) ($schemeEntry->accode ?? '')));
                if (in_array($entryLedger, ['APP', 'SCHMAMT'], true)) {
                    $schemeLedger = $entryLedger;
                }
            }
        }
        $netTotal = $billAmt + $tax - $advance - $exchAmt - $sretamt - $schemeAmt;
        $balance = $billAmt + $tax - $advance - $exchAmt - $sretamt - $schemeAmt + $refund;
        $cb = $ob - $advance - $exchAmt - $sretamt - $schemeAmt + $refund;
        $netBal = $cb + $billAmt + $tax;

        return [
            'slno' => $m->slno,
            'doc_no' => $m->ordno,
            'date' => $m->tdate,
            'cust_code' => trim($m->custcode ?? ''),
            'cust_name' => trim($m->custname ?? ''),
            'address' => trim($m->addr ?? ''),
            'phone' => trim($m->phone ?? ''),
            'mobile' => $mobile,
            'pan' => $pan,
            'gst_no' => $gstNo,
            'salesman' => trim($m->smcode ?? ''),
            'salesman_name' => $salesmanName,
            'counter' => trim($m->counter ?? ''),
            'gold_rate' => (float) ($m->rate ?? 0),
            'note' => trim($m->note ?? ''),
            'due_date' => $m->duedate ?? '',
            'bill_total' => $billAmt,
            'exchange' => $exchAmt,
            'advance' => $advance,
            'gadvance' => (float) ($m->gadvance ?? 0),
            'refund' => $refund,
            'tax' => $tax,
            'sretamt' => $sretamt,
            'scheme_amt' => round($schemeAmt, 2),
            'scheme_ledger' => $schemeLedger,
            'net_total' => round($netTotal, 2),
            'balance' => round($balance, 2),
            'ob' => $ob,
            'cb' => round($cb, 2),
            'net_bal' => round($netBal, 2),
            'blocked' => trim($m->blocked ?? 'N'),
            'taxable' => trim($m->taxable ?? 'N'),
            'amttowgt' => trim($m->amttowgt ?? 'N'),
            'cbcode' => trim($m->cbcode ?? 'CASH'),
            'bcharge' => (float) ($m->bcharge ?? 0),
            'ccamt' => (float) ($m->ccamt ?? 0),
            'addbcharge' => trim($m->addbcharge ?? 'N'),
            'chq_bank' => trim($m->chqbank ?? ''),
            'chq_amt' => (float) ($m->chqamt ?? 0),
            'chq_no' => trim($m->chqno ?? ''),
            'chq_date' => $m->chqdate ?? '',
            'chq_pdc' => trim($m->chqpdc ?? 'N'),
            'cocode' => trim($m->cocode ?? ''),
            'status' => (int) ($m->status ?? 1),
            'closed' => (int) ($m->closed ?? 0),
            'adv_after' => $advAfter,
            'items' => $enriched,
            'exch_items' => $enrichedExch,
            'sales_return_items' => $salesReturnItems,
            'sales_return_summary' => $salesReturnSummary,
            'gold_advance_items' => $goldAdvanceItems,
            'model_items' => $modelItems,
        ];
    }

    private function getColumns(string $table): array
    {
        if (!$this->hasTable($table)) return [];
        return array_map('strtolower', $this->columnList($table));
    }

    private function postOrderDaybook(int $slno, array $ctx): void
    {
        if (!$this->hasTable('daybook')) return;

        $dbCols = array_map('strtolower', $this->columnList('daybook'));
        $dpCols = $this->hasTable('daybookpart')
            ? array_map('strtolower', $this->columnList('daybookpart'))
            : [];

        $docNo    = trim((string)($ctx['doc_no'] ?? ''));
        $custCode = trim((string)($ctx['cust_code'] ?? ''));
        $custName = trim((string)($ctx['cust_name'] ?? ''));
        $tdate    = (string)($ctx['tdate'] ?? date('Y-m-d'));
        $control  = (int)($ctx['control'] ?? 1);
        $rate     = (float)($ctx['rate'] ?? 0);

        $advance  = (float)($ctx['advance'] ?? 0);
        $refund   = (float)($ctx['refund'] ?? 0);
        $ccamt    = (float)($ctx['ccamt'] ?? 0);
        $chqAmt   = (float)($ctx['chq_amt'] ?? 0);
        $cbcode   = trim((string)($ctx['cbcode'] ?? 'CASH'));
        $chqBank  = trim((string)($ctx['chq_bank'] ?? ''));
        $chqPdc   = strtoupper(trim((string)($ctx['chq_pdc'] ?? 'N'))) === 'Y' ? 'Y' : 'N';
        $exchAmt  = (float)($ctx['exchange'] ?? 0);
        $sretamt  = (float)($ctx['sretamt'] ?? 0);
        $schemeAmt = (float)($ctx['scheme_amt'] ?? 0);
        $schemeLedger = strtoupper(trim((string)($ctx['scheme_ledger'] ?? 'APP')));
        if (!in_array($schemeLedger, ['APP', 'SCHMAMT'], true)) {
            $schemeLedger = 'APP';
        }
        $addBc    = strtoupper(trim((string)($ctx['addbcharge'] ?? 'N'))) === 'Y';
        $bcharge  = (float)($ctx['bcharge'] ?? 0);
        $netTotal = (float)($ctx['net_total'] ?? 0);

        $opCust = $custCode !== '' ? $custCode : 'ADVANCE';
        $particular = 'By Order - ' . $docNo . ($custName !== '' ? ' From ' . $custName : '');
        $particular = substr($particular, 0, 40);

        if (!empty($dpCols)) {
            $dp = [
                'slno'       => $slno,
                'particular' => $particular,
                'vchno'      => '',
                'ic'         => $this->gsincharge,
                'uid'        => $this->gsincharge,
                'ttime'      => date('H:i:s'),
                'rate'       => $rate,
            ];
            DB::table('daybookpart')->insert(array_filter($dp, fn ($k) => in_array($k, $dpCols, true), ARRAY_FILTER_USE_KEY));
        }

        $ins = function (string $accode, float $amount, string $opaccode = '') use ($slno, $tdate, $control, $dbCols): void {
            if ($accode === '' || abs($amount) < 0.000001) return;
            $row = [
                'slno'    => $slno,
                'accode'  => $accode,
                'amount'  => round($amount, 2),
                'control' => $control,
                'tdate'   => $tdate,
                'opaccode'=> $opaccode,
            ];
            DB::table('daybook')->insert(array_filter($row, fn ($k) => in_array($k, $dbCols, true), ARRAY_FILTER_USE_KEY));
        };

        $effectiveAdvance = round($advance - $refund, 2);
        if ($effectiveAdvance > 0) {
            $cashAcc = $cbcode !== '' ? $cbcode : 'CASH';
            $cashPart = round($effectiveAdvance - $ccamt - $chqAmt, 2);
            if ($cashPart > 0) {
                $ins($cashAcc, -$cashPart, $opCust);
            }
            if ($ccamt > 0) {
                $ins($cashAcc, -$ccamt, $opCust);
            }
            if ($chqAmt > 0) {
                $chqAcc = $chqPdc === 'Y' ? 'CNC' : ($chqBank !== '' ? $chqBank : 'CASH');
                $ins($chqAcc, -$chqAmt, $opCust);
            }
            $ins($opCust, $effectiveAdvance, 'CASH');
        }

        // Bank commission + commission tax (PB parity approximation)
        if ($effectiveAdvance > 0 && strtoupper($cbcode) !== 'CASH') {
            $dbComn = $this->genDec('BANKCOMN');
            $dbComnTax = $this->genDec('BCOMNTAX');
            $baseForComn = ($ccamt > 0) ? $ccamt : ($addBc ? max($netTotal - $bcharge, 0) : $effectiveAdvance);
            $comn = round(($baseForComn * $dbComn) / 100, 1);
            $comnTax = round(($comn * $dbComnTax) / 100, 1);
            if ($comn !== 0.0) {
                $ins($cbcode, $comn, $opCust);
                if (!$addBc) $ins('BCOMN', -$comn, $opCust);
            }
            if ($comnTax !== 0.0) {
                $ins($cbcode, $comnTax, $opCust);
                if (!$addBc) $ins('BCOMNTAX', -$comnTax, $opCust);
            }
        }

        // PDC list posting
        if ($chqPdc === 'Y' && $chqAmt > 0 && $this->hasTable('pdclist')) {
            $pcCols = $this->getColumns('pdclist');
            $pd = [
                'slno'        => $slno,
                'tdate'       => $tdate,
                'docno'       => $docNo,
                'bank'        => $chqBank,
                'code'        => 'CNC',
                'chqno'       => trim((string)($ctx['chq_no'] ?? '')),
                'chqdate'     => (string)($ctx['chq_date'] ?? null),
                'amount'      => $chqAmt,
                'particulars' => $particular,
                'rp'          => 'R',
                'pend'        => 'Y',
                'control'     => $control,
            ];
            DB::table('pdclist')->insert(array_filter($pd, fn ($k) => in_array($k, $pcCols, true), ARRAY_FILTER_USE_KEY));
        }

        if ($exchAmt > 0) {
            $opEx = $custCode !== '' ? $custCode : 'ORDEREXC';
            $ins('EP', -$exchAmt, $opCust);
            $ins($opEx, $exchAmt, $opCust);
        }

        if ($sretamt > 0) {
            $opSr = $custCode !== '' ? $custCode : 'ORDERSR';
            $ins('ESR', -$sretamt, $opCust);
            $ins($opSr, $sretamt, $opCust);
        }

        if ($schemeAmt > 0) {
            $opScheme = $custCode !== '' ? $custCode : 'ORDERSCHM';
            $ins($schemeLedger, -$schemeAmt, $opCust);
            $ins($opScheme, $schemeAmt, $opCust);
        }
    }

    private function rollbackExistingOrderEffects(int $slno, int $control, string $ordno, bool $calcWACost): void
    {
        // rollback purchased effect
        if ($this->hasTable('purchased')) {
            $rows = DB::table('purchased')->where('slno', $slno)->get();
            foreach ($rows as $r) {
                $code = strtoupper(trim((string)($r->code ?? '')));
                if ($code === '') continue;
                $qty = (int)($r->qty ?? 0);
                $wgt = (float)($r->weight ?? 0);
                $stw = (float)($r->stwgt ?? $r->stonewgt ?? 0);
                $stktype = trim((string)($r->stktype ?? ''));
                $cost = (float)($r->cost ?? 0);
                $newCost = $this->computeWeightedCostForRemove($code, $wgt, $cost, $control, $calcWACost);
                $this->applyItemStockDelta($code, -$qty, -$wgt, -$stw, $control, $stktype, $newCost);
            }
        }

        // rollback orderdga effect
        if ($this->hasTable('orderdga')) {
            $rows = DB::table('orderdga')->where('slno', $slno)->get();
            foreach ($rows as $r) {
                $code = strtoupper(trim((string)($r->code ?? '')));
                if ($code === '') continue;
                $qty = (int)($r->qty ?? 0);
                $wgt = (float)($r->weight ?? 0);
                $stw = (float)($r->stonewgt ?? 0);
                $stktype = trim((string)($r->stktype ?? ''));
                $this->applyItemStockDelta($code, -$qty, -$wgt, -$stw, $control, $stktype, null);
            }
        }

        // rollback sales return effect
        if ($this->hasTable('salesrd')) {
            $rows = DB::table('salesrd')->where('slno', $slno)->get();
            foreach ($rows as $r) {
                $code = strtoupper(trim((string)($r->code ?? '')));
                if ($code === '') continue;
                $qty = (int)($r->qty ?? 0);
                $wgt = (float)($r->weight ?? 0);
                $stw = (float)($r->stonewgt ?? 0);
                $stktype = trim((string)($r->stktype ?? ''));
                $this->applyItemStockDelta($code, -$qty, -$wgt, -$stw, $control, $stktype, null);
            }
        }
    }

    private function computeWeightedCostForRemove(string $code, float $removeWgt, float $removeCost, int $control, bool $calcWACost): ?float
    {
        if (!$this->hasTable('items')) return null;
        $cols = $this->getColumns('items');
        $wCol = ($control === 1 && in_array('weight', $cols, true)) ? 'weight' : (in_array('weightb', $cols, true) ? 'weightb' : (in_array('weight', $cols, true) ? 'weight' : ''));
        if ($wCol === '') return null;
        $row = DB::table('items')->where('code', $code)->first([$wCol, 'cost']);
        if (!$row) return null;
        $curW = (float)($row->{$wCol} ?? 0);
        $curCost = (float)($row->cost ?? 0);
        if (!$calcWACost || ($curW - $removeWgt) <= 0) return $curCost;
        return round((($curW * $curCost) - ($removeWgt * $removeCost)) / max(($curW - $removeWgt), 0.000001), 2);
    }

    private function computeWeightedCostForAdd(string $code, float $addWgt, float $addCost, int $control, bool $calcWACost): ?float
    {
        if (!$this->hasTable('items')) return null;
        $cols = $this->getColumns('items');
        $wCol = ($control === 1 && in_array('weight', $cols, true)) ? 'weight' : (in_array('weightb', $cols, true) ? 'weightb' : (in_array('weight', $cols, true) ? 'weight' : ''));
        if ($wCol === '') return null;
        $row = DB::table('items')->where('code', $code)->first([$wCol, 'cost']);
        if (!$row) return round($addCost, 2);
        $curW = (float)($row->{$wCol} ?? 0);
        $curCost = (float)($row->cost ?? 0);
        if (!$calcWACost || ($curW + $addWgt) <= 0 || $curW <= 0) return round($addCost, 2);
        return round((($curW * $curCost) + ($addWgt * $addCost)) / max(($curW + $addWgt), 0.000001), 2);
    }

    private function applyItemStockDelta(string $code, int $qtyDelta, float $weightDelta, float $stoneDelta, int $control, string $stktype = '', ?float $newCost = null): void
    {
        if (!$this->hasTable('items') || $code === '') return;
        $cols = $this->getColumns('items');
        $upd = [];
        $add = static fn (string $col, float $delta) => DB::raw($col . ' + (' . (float)$delta . ')');

        if ($control === 1) {
            foreach (['qty' => $qtyDelta, 'qtyb' => $qtyDelta, 'weight' => $weightDelta, 'weightb' => $weightDelta, 'stonewgt' => $stoneDelta, 'stonewgtb' => $stoneDelta] as $c => $v) {
                if (in_array($c, $cols, true)) $upd[$c] = $add($c, (float)$v);
            }
        } else {
            foreach (['qtyb' => $qtyDelta, 'weightb' => $weightDelta, 'stonewgtb' => $stoneDelta] as $c => $v) {
                if (in_array($c, $cols, true)) $upd[$c] = $add($c, (float)$v);
            }
        }
        if ($newCost !== null && in_array('cost', $cols, true)) {
            $upd['cost'] = round($newCost, 2);
        }
        if (!empty($upd)) DB::table('items')->where('code', $code)->update($upd);

        if ($stktype !== '' && $this->hasTable('itemsstk')) {
            $sCols = $this->getColumns('itemsstk');
            $where = ['code' => $code, 'stktype' => $stktype];
            $exists = DB::table('itemsstk')->where($where)->exists();
            if (!$exists) {
                DB::table('itemsstk')->insert(array_intersect_key($where, array_flip($sCols)));
            }
            $sUpd = [];
            $addS = static fn (string $col, float $delta) => DB::raw($col . ' + (' . (float)$delta . ')');
            if ($control === 1) {
                foreach (['qty' => $qtyDelta, 'qtyb' => $qtyDelta, 'weight' => $weightDelta, 'weightb' => $weightDelta, 'stonewgt' => $stoneDelta, 'stonewgtb' => $stoneDelta] as $c => $v) {
                    if (in_array($c, $sCols, true)) $sUpd[$c] = $addS($c, (float)$v);
                }
            } else {
                foreach (['qtyb' => $qtyDelta, 'weightb' => $weightDelta, 'stonewgtb' => $stoneDelta] as $c => $v) {
                    if (in_array($c, $sCols, true)) $sUpd[$c] = $addS($c, (float)$v);
                }
            }
            if (!empty($sUpd)) DB::table('itemsstk')->where($where)->update($sUpd);
        }
    }
}
