<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderAdvanceAfterController extends Controller
{
    private int    $gilevel    = 1;
    private string $gsincharge = '';

    // ─── View ────────────────────────────────────────────────────────────────

    public function index(Request $request, ?string $mode = null)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $validModes = ['bill', 'edit'];
        $mode = $mode ?? 'bill';
        if (!in_array($mode, $validModes)) $mode = 'bill';

        $this->gsincharge = (string)($request->session()->get('user_code', ''));
        $this->gilevel    = (int)($request->session()->get('gilevel', 1));

        $rates = [
            'gold'     => $this->genDec('GRATE'),
            'silver'   => $this->genDec('SRATE'),
            'platinum' => $this->genDec('PGRATE'),
        ];

        $exchItems = [];
        if ($this->hasTable('items')) {
            $itemCols = $this->getColumns('items');
            $select = ['code', 'name', 'itype'];
            foreach (['touch', 'cost', 'disabled', 'defstktype', 'defquality', 'stkinnos'] as $col) {
                if (in_array($col, $itemCols, true)) {
                    $select[] = $col;
                }
            }
            $q = DB::table('items')->select($select)->orderBy('name');
            if (in_array('disabled', $itemCols, true)) {
                $q->where(function ($qb) {
                    $qb->whereNull('disabled')->orWhere('disabled', '!=', 'Y');
                });
            }
            $exchItems = $q->get()->map(function ($r) {
                return [
                    'code'       => trim($r->code ?? ''),
                    'name'       => trim($r->name ?? ''),
                    'type'       => trim($r->itype ?? ''),
                    'touch'      => (float)($r->touch ?? 0),
                    'cost'       => (float)($r->cost ?? 0),
                    'defstktype' => trim($r->defstktype ?? ''),
                    'defquality' => trim($r->defquality ?? ''),
                ];
            })->toArray();
        }

        return view('order-advance-after.index', [
            'mode'      => $mode,
            'rates'     => $rates,
            'exchItems' => $exchItems,
            'gilevel'   => $this->gilevel,
        ]);
    }

    // ─── API: Order Search ───────────────────────────────────────────────────

    public function orderSearch(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $q = trim((string)$request->query('q', ''));
        if (!$this->hasTable('orderm')) return response()->json(['ok' => true, 'results' => []]);

        $rows = DB::table('orderm')
            ->where('status', 1)
            ->where(function ($qb) use ($q) {
                if ($q !== '') {
                    $qb->where('ordno', 'like', "%$q%")
                       ->orWhere('custname', 'like', "%$q%")
                       ->orWhere('custcode', 'like', "%$q%");
                }
            })
            ->orderByDesc('slno')
            ->limit(30)
            ->get(['ordno', 'tdate', 'custname', 'billamt', 'advance']);

        $results = $rows->map(fn ($r) => [
            'ordno'     => trim($r->ordno ?? ''),
            'date'      => $r->tdate,
            'cust_name' => trim($r->custname ?? ''),
            'bill_amt'  => (float)($r->billamt ?? 0),
            'advance'   => (float)($r->advance ?? 0),
        ])->values();

        return response()->json(['ok' => true, 'results' => $results]);
    }

    // ─── API: Order Lookup ───────────────────────────────────────────────────

    public function orderLookup(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $this->gilevel = (int)($request->session()->get('gilevel', 1));
        $ordno = strtoupper(trim((string)$request->query('ordno', '')));
        if ($ordno === '') return response()->json(['ok' => false, 'message' => 'No order number']);
        if (!$this->hasTable('orderm')) return response()->json(['ok' => false, 'message' => 'Table missing']);

        $m = DB::table('orderm')
            ->where('ordno', $ordno)
            ->where('status', 1)
            ->first();

        if (!$m) {
            return response()->json(['ok' => false, 'message' => 'Invalid order number or order not active']);
        }

        $advance = (float)($m->advance ?? 0);
        $eamt    = (float)($m->eamt ?? 0);
        $sretamt = (float)($m->sretamt ?? 0);

        // Sum existing advance-after amounts
        $advAfterSum = 0;
        if ($this->hasTable('advafter')) {
            $advAfterSum = (float)(DB::table('advafter')->where('ordno', $ordno)->sum('amount') ?? 0);
        }

        $prevAdvance = $advance + $eamt + $sretamt + $advAfterSum;

        return response()->json([
            'ok'           => true,
            'ordno'        => trim($m->ordno ?? ''),
            'custcode'     => trim($m->custcode ?? ''),
            'custname'     => trim($m->custname ?? ''),
            'control'      => (int)($m->control ?? 1),
            'ichadv'       => (int)($m->ichadv ?? 0),
            'prev_advance' => round($prevAdvance, 2),
        ]);
    }

    // ─── API: Item Lookup ────────────────────────────────────────────────────

    public function itemLookup(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $code = strtoupper(trim((string)$request->query('code', '')));
        if ($code === '') return response()->json(['ok' => false, 'message' => 'No code']);
        if (!$this->hasTable('items')) return response()->json(['ok' => false, 'message' => 'Table missing']);

        $cols = $this->getColumns('items');
        $select = ['code', 'name', 'itype'];
        foreach (['cost', 'defstktype', 'defquality'] as $col) {
            if (in_array($col, $cols, true)) $select[] = $col;
        }

        $item = DB::table('items')->where('code', $code)->first($select);
        if (!$item) return response()->json(['ok' => false, 'message' => 'Item not found']);

        return response()->json([
            'ok'         => true,
            'code'       => trim($item->code ?? ''),
            'name'       => trim($item->name ?? ''),
            'type'       => trim($item->itype ?? ''),
            'cost'       => (float)($item->cost ?? 0),
            'defstktype' => trim($item->defstktype ?? ''),
            'defquality' => trim($item->defquality ?? ''),
        ]);
    }

    // ─── API: Item Search ────────────────────────────────────────────────────

    public function itemSearch(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $q = trim((string)$request->query('q', ''));
        if (!$this->hasTable('items')) return response()->json(['ok' => true, 'results' => []]);

        $cols = $this->getColumns('items');
        $select = ['code', 'name', 'itype'];
        foreach (['cost', 'defstktype', 'qty', 'weight'] as $col) {
            if (in_array($col, $cols, true)) $select[] = $col;
        }

        $rows = DB::table('items')
            ->select($select)
            ->where(function ($qb) use ($q) {
                if ($q !== '') {
                    $qb->where('code', 'like', "%$q%")
                       ->orWhere('name', 'like', "%$q%");
                }
            })
            ->orderBy('name')
            ->limit(50)
            ->get();

        $results = $rows->map(fn ($r) => [
            'code'   => trim($r->code ?? ''),
            'name'   => trim($r->name ?? ''),
            'type'   => trim($r->itype ?? ''),
            'cost'   => (float)($r->cost ?? 0),
            'qty'    => (int)($r->qty ?? 0),
            'weight' => (float)($r->weight ?? 0),
        ])->values();

        return response()->json(['ok' => true, 'results' => $results]);
    }

    // ─── API: Save ───────────────────────────────────────────────────────────

    public function save(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $this->gilevel    = (int)($request->session()->get('gilevel', 1));
        $this->gsincharge = (string)($request->session()->get('user_code', ''));

        $ordno     = strtoupper(trim((string)$request->input('ordno', '')));
        $dateRaw   = trim((string)$request->input('date', ''));
        $rate      = (float)$request->input('rate', 0);
        $amount    = (float)$request->input('amount', 0);
        $amttowgt  = strtoupper(trim((string)$request->input('amttowgt', 'N'))) === 'Y' ? 'Y' : 'N';
        $printobcb = strtoupper(trim((string)$request->input('printobcb', 'Y'))) === 'Y' ? 'Y' : 'N';
        $editSlno  = (int)$request->input('edit_slno', 0);
        $items     = $request->input('items', []);

        if ($ordno === '') return response()->json(['ok' => false, 'message' => 'Order number is required']);

        $tdate = $this->parseDate($dateRaw);
        if (!$tdate) $tdate = date('Y-m-d');

        if (!$this->hasTable('orderm')) return response()->json(['ok' => false, 'message' => 'Table missing']);

        // Validate order
        $order = DB::table('orderm')
            ->where('ordno', $ordno)
            ->where('status', 1)
            ->first();

        if (!$order) {
            return response()->json(['ok' => false, 'message' => 'Invalid order number. Order does not exist or is not active.']);
        }

        // Force order advance posting at level 1 (Bill) to keep daybook visibility consistent.
        $icontrol  = 1;
        $custcode  = trim((string)($order->custcode ?? ''));
        $ichadv    = (int)($order->ichadv ?? 0);

        // Determine account code for daybook
        $saccode = 'ADVANCE';
        if ($ichadv === 1 && $custcode !== '') {
            $saccode = $custcode;
        }

        // Calculate cash weight if amttowgt
        $cashwgt = 0;
        if ($amttowgt === 'Y' && $rate > 0) {
            $cashwgt = round($amount / $rate, 2);
        }

        if ($amount == 0 && empty($items)) {
            return response()->json(['ok' => false, 'message' => 'Amount is not entered. You can\'t save.']);
        }

        try {
            DB::beginTransaction();

            // Edit mode: reverse old effects
            $oldVchno = '';
            if ($editSlno > 0) {
                $lslno = $editSlno;

                // Get old voucher number
                if ($this->hasTable('daybookpart')) {
                    $oldVchno = (string)(DB::table('daybookpart')->where('slno', $lslno)->value('vchno') ?? '');
                }

                // Reverse old stock from orderdga
                if ($this->hasTable('orderdga')) {
                    $oldItems = DB::table('orderdga')->where('slno', $lslno)->get();
                    foreach ($oldItems as $oi) {
                        $ocode = strtoupper(trim((string)($oi->code ?? '')));
                        if ($ocode === '') continue;
                        $oqty  = (int)($oi->qty ?? 0);
                        $owgt  = (float)($oi->weight ?? 0);
                        $ostw  = (float)($oi->stonewgt ?? 0);
                        $ostktype = trim((string)($oi->stktype ?? ''));
                        $this->applyItemStockDelta($ocode, -$oqty, -$owgt, -$ostw, $icontrol, $ostktype);
                    }
                    DB::table('orderdga')->where('slno', $lslno)->delete();
                }

                // Delete old records
                if ($this->hasTable('advafter')) {
                    DB::table('advafter')->where('slno', $lslno)->delete();
                }
                if ($this->hasTable('daybook')) {
                    DB::table('daybook')->where('slno', $lslno)->delete();
                }
                if ($this->hasTable('daybookpart')) {
                    DB::table('daybookpart')->where('slno', $lslno)->delete();
                }
            } else {
                // New record: generate serial number
                $lslno = $this->incrementGenInt('SERIALNO');
            }

            // Insert weight items into orderdga + apply stock
            $dtwgt = 0;
            $sno = 0;
            if (!empty($items) && $this->hasTable('orderdga')) {
                $gaCols = $this->getColumns('orderdga');
                foreach ($items as $item) {
                    $scode = strtoupper(trim((string)($item['code'] ?? '')));
                    if ($scode === '') continue;
                    $sno++;
                    $iqty      = (int)($item['qty'] ?? 0);
                    $dwgt      = (float)($item['weight'] ?? 0);
                    $dstwgt    = (float)($item['stonewgt'] ?? 0);
                    $dlessperc = (float)($item['lessperc'] ?? 0);
                    $dlesswgt  = (float)($item['lesswgt'] ?? 0);
                    $dcost     = (float)($item['cost'] ?? 0);
                    $dstktype  = trim((string)($item['stktype'] ?? ''));
                    $diqtype   = trim((string)($item['iqtype'] ?? ''));

                    if ($scode !== '' && $dwgt != 0) {
                        $row = [
                            'slno'     => $lslno,
                            'sno'      => $sno,
                            'code'     => $scode,
                            'qty'      => $iqty,
                            'weight'   => $dwgt,
                            'cost'     => $dcost,
                            'stktype'  => $dstktype,
                            'stonewgt' => $dstwgt,
                            'lessperc' => $dlessperc,
                            'lesswgt'  => $dlesswgt,
                            'iqtype'   => $diqtype,
                            'stktouch' => 100,
                            'tdate'    => $tdate,
                            'control'  => $icontrol,
                        ];
                        DB::table('orderdga')->insert(
                            array_filter($row, fn($k) => in_array($k, $gaCols, true), ARRAY_FILTER_USE_KEY)
                        );

                        $dtwgt += $dwgt - $dstwgt - $dlesswgt;
                        $this->applyItemStockDelta($scode, $iqty, $dwgt, $dstwgt, $icontrol, $dstktype);
                    }
                }
            }

            $dtwgt += $cashwgt;

            // Generate voucher number
            $svchno = $oldVchno;
            if ($editSlno > 0 && $svchno !== '') {
                // Reuse old voucher number on edit
            } else {
                if ($amount > 0 || $dtwgt > 0) {
                    // Receipt voucher
                    if ($icontrol === 1) {
                        $vn = $this->incrementGenInt('VCHNORB');
                        $svchno = 'VRB/' . str_pad($vn, 5, '0', STR_PAD_LEFT);
                    } else {
                        $vn = $this->incrementGenInt('VCHNORE');
                        $svchno = 'VRE/' . str_pad($vn, 5, '0', STR_PAD_LEFT);
                    }
                } else {
                    // Payment/refund voucher
                    if ($icontrol === 1) {
                        $vn = $this->incrementGenInt('VCHNOPB');
                        $svchno = 'VPB/' . str_pad($vn, 5, '0', STR_PAD_LEFT);
                    } else {
                        $vn = $this->incrementGenInt('VCHNOPE');
                        $svchno = 'VPE/' . str_pad($vn, 5, '0', STR_PAD_LEFT);
                    }
                }
            }

            // Insert advafter record
            if (($amount != 0 || $dtwgt != 0) && $this->hasTable('advafter')) {
                $aaCols = $this->getColumns('advafter');
                $aaRow = [
                    'slno'     => $lslno,
                    'tdate'    => $tdate,
                    'docno'    => $svchno,
                    'ordno'    => $ordno,
                    'ttype'    => 'C',
                    'amount'   => $amount,
                    'control'  => $icontrol,
                    'rate'     => $rate,
                    'wgt'      => $dtwgt,
                    'amttowgt' => $amttowgt,
                ];
                DB::table('advafter')->insert(
                    array_filter($aaRow, fn($k) => in_array($k, $aaCols, true), ARRAY_FILTER_USE_KEY)
                );
            }

            // Daybook entries
            if ($amount != 0 || $dtwgt > 0) {
                $particular = ($amount > 0 || $dtwgt > 0)
                    ? 'Advance Against ' . $ordno
                    : 'Refund Against ' . $ordno;

                // Insert daybookpart
                if ($this->hasTable('daybookpart')) {
                    $dpCols = $this->getColumns('daybookpart');
                    $dp = [
                        'slno'       => $lslno,
                        'vchno'      => $svchno,
                        'particular' => $particular,
                        'ic'         => $this->gsincharge,
                        'ttime'      => date('H:i:s'),
                    ];
                    DB::table('daybookpart')->insert(
                        array_filter($dp, fn($k) => in_array($k, $dpCols, true), ARRAY_FILTER_USE_KEY)
                    );
                }

                // Insert daybook double entry
                if ($this->hasTable('daybook')) {
                    $dbCols = $this->getColumns('daybook');

                    $ins = function (string $accode, float $amt, string $opaccode) use ($lslno, $tdate, $icontrol, $dbCols) {
                        if ($accode === '' || abs($amt) < 0.001) return;
                        $row = [
                            'slno'     => $lslno,
                            'tdate'    => $tdate,
                            'accode'   => $accode,
                            'amount'   => round($amt, 2),
                            'control'  => $icontrol,
                            'opaccode' => $opaccode,
                        ];
                        DB::table('daybook')->insert(
                            array_filter($row, fn($k) => in_array($k, $dbCols, true), ARRAY_FILTER_USE_KEY)
                        );
                    };

                    // Entry 1: Debit ADVANCE/Customer account
                    $ins($saccode, $amount, 'CASH');
                    // Entry 2: Credit CASH
                    $ins('CASH', -$amount, $saccode);
                }
            }

            DB::commit();

            return response()->json([
                'ok'      => true,
                'message' => 'Advance saved successfully',
                'slno'    => $lslno,
                'vchno'   => $svchno,
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'message' => 'Save failed: ' . $e->getMessage()]);
        }
    }

    // ─── API: Get ────────────────────────────────────────────────────────────

    public function get(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $slno = (int)$request->query('slno', 0);
        if ($slno <= 0) return response()->json(['ok' => false, 'message' => 'No slno']);
        if (!$this->hasTable('advafter')) return response()->json(['ok' => false, 'message' => 'Table missing']);

        $m = DB::table('advafter')->where('slno', $slno)->first();
        if (!$m) return response()->json(['ok' => false, 'message' => 'Record not found']);

        // Load weight items
        $items = [];
        if ($this->hasTable('orderdga')) {
            $rows = DB::table('orderdga')->where('slno', $slno)->orderBy('sno')->get();
            foreach ($rows as $r) {
                $name = '';
                if ($this->hasTable('items')) {
                    $name = (string)(DB::table('items')->where('code', $r->code ?? '')->value('name') ?? '');
                }
                $items[] = [
                    'code'     => trim($r->code ?? ''),
                    'name'     => trim($name),
                    'qty'      => (int)($r->qty ?? 0),
                    'weight'   => (float)($r->weight ?? 0),
                    'stonewgt' => (float)($r->stonewgt ?? 0),
                    'lessperc' => (float)($r->lessperc ?? 0),
                    'lesswgt'  => (float)($r->lesswgt ?? 0),
                    'cost'     => (float)($r->cost ?? 0),
                    'stktype'  => trim($r->stktype ?? ''),
                    'iqtype'   => trim($r->iqtype ?? ''),
                ];
            }
        }

        // Get order details for prev advance
        $prevAdvance = 0;
        $ordno = trim($m->ordno ?? '');
        if ($ordno !== '' && $this->hasTable('orderm')) {
            $order = DB::table('orderm')->where('ordno', $ordno)->first();
            if ($order) {
                $advance = (float)($order->advance ?? 0);
                $eamt    = (float)($order->eamt ?? 0);
                $sretamt = (float)($order->sretamt ?? 0);
                $advAfterSum = (float)(DB::table('advafter')->where('ordno', $ordno)->sum('amount') ?? 0);
                $prevAdvance = $advance + $eamt + $sretamt + $advAfterSum;
            }
        }

        return response()->json([
            'ok'           => true,
            'slno'         => $slno,
            'ordno'        => $ordno,
            'date'         => $m->tdate ?? '',
            'docno'        => trim($m->docno ?? ''),
            'amount'       => (float)($m->amount ?? 0),
            'rate'         => (float)($m->rate ?? 0),
            'wgt'          => (float)($m->wgt ?? 0),
            'amttowgt'     => trim($m->amttowgt ?? 'N'),
            'control'      => (int)($m->control ?? 1),
            'prev_advance' => round($prevAdvance, 2),
            'items'        => $items,
        ]);
    }

    // ─── API: Search Advances ────────────────────────────────────────────────

    public function search(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $q = trim((string)$request->query('q', ''));
        if (!$this->hasTable('advafter')) return response()->json(['ok' => true, 'results' => []]);

        $rows = DB::table('advafter')
            ->where(function ($qb) use ($q) {
                if ($q !== '') {
                    $qb->where('ordno', 'like', "%$q%")
                       ->orWhere('docno', 'like', "%$q%");
                }
            })
            ->orderByDesc('slno')
            ->limit(30)
            ->get(['slno', 'ordno', 'tdate', 'docno', 'amount', 'wgt']);

        $results = $rows->map(fn ($r) => [
            'slno'   => (int)$r->slno,
            'ordno'  => trim($r->ordno ?? ''),
            'date'   => $r->tdate,
            'docno'  => trim($r->docno ?? ''),
            'amount' => (float)($r->amount ?? 0),
            'wgt'    => (float)($r->wgt ?? 0),
        ])->values();

        return response()->json(['ok' => true, 'results' => $results]);
    }

    // ─── API: Delete ─────────────────────────────────────────────────────────

    public function delete(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $this->gilevel = (int)($request->session()->get('gilevel', 1));
        $slno = (int)$request->input('slno', 0);
        if ($slno <= 0) return response()->json(['ok' => false, 'message' => 'No slno']);

        if (!$this->hasTable('advafter')) return response()->json(['ok' => false, 'message' => 'Table missing']);

        $m = DB::table('advafter')->where('slno', $slno)->first();
        if (!$m) return response()->json(['ok' => false, 'message' => 'Record not found']);

        $icontrol = (int)($m->control ?? 1);

        try {
            DB::beginTransaction();

            // Reverse stock from orderdga
            if ($this->hasTable('orderdga')) {
                $oldItems = DB::table('orderdga')->where('slno', $slno)->get();
                foreach ($oldItems as $oi) {
                    $ocode = strtoupper(trim((string)($oi->code ?? '')));
                    if ($ocode === '') continue;
                    $oqty     = (int)($oi->qty ?? 0);
                    $owgt     = (float)($oi->weight ?? 0);
                    $ostw     = (float)($oi->stonewgt ?? 0);
                    $ostktype = trim((string)($oi->stktype ?? ''));
                    $this->applyItemStockDelta($ocode, -$oqty, -$owgt, -$ostw, $icontrol, $ostktype);
                }
                DB::table('orderdga')->where('slno', $slno)->delete();
            }

            DB::table('advafter')->where('slno', $slno)->delete();

            if ($this->hasTable('daybook')) {
                DB::table('daybook')->where('slno', $slno)->delete();
            }
            if ($this->hasTable('daybookpart')) {
                DB::table('daybookpart')->where('slno', $slno)->delete();
            }

            DB::commit();
            return response()->json(['ok' => true, 'message' => 'Advance deleted successfully']);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'message' => 'Delete failed: ' . $e->getMessage()]);
        }
    }

    // ─── API: Navigation ─────────────────────────────────────────────────────

    public function prevBill(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);
        if (!$this->hasTable('advafter')) return response()->json(['ok' => false, 'message' => 'Table missing']);

        $currentSlno = (int)$request->query('slno', 0);
        $row = DB::table('advafter')
            ->where('slno', '<', $currentSlno > 0 ? $currentSlno : PHP_INT_MAX)
            ->orderByDesc('slno')
            ->first(['slno']);

        if (!$row) return response()->json(['ok' => false, 'message' => 'No previous record']);
        return response()->json(['ok' => true, 'slno' => (int)$row->slno]);
    }

    public function nextBill(Request $request)
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);
        if (!$this->hasTable('advafter')) return response()->json(['ok' => false, 'message' => 'Table missing']);

        $currentSlno = (int)$request->query('slno', 0);
        $row = DB::table('advafter')
            ->where('slno', '>', $currentSlno)
            ->orderBy('slno')
            ->first(['slno']);

        if (!$row) return response()->json(['ok' => false, 'message' => 'No next record']);
        return response()->json(['ok' => true, 'slno' => (int)$row->slno]);
    }

    // ─── Private Helpers ─────────────────────────────────────────────────────

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
            : ['orderm', 'daybook', 'daybookpart'];
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

    private function getColumns(string $table): array
    {
        if (!$this->hasTable($table)) return [];
        return array_map('strtolower', $this->columnList($table));
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

    private function applyItemStockDelta(string $code, int $qtyDelta, float $weightDelta, float $stoneDelta, int $control, string $stktype = ''): void
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
