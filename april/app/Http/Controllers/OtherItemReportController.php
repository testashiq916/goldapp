<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OtherItemReportController extends Controller
{
    /* ────────────────────────────────────────────────────────────────
     |  PAGE METHODS
     * ──────────────────────────────────────────────────────────────── */

    public function salesBook(Request $request)
    {
        if (!$request->session()->has('user_code')) return redirect('/login');
        return view('other-item-reports.sales-book', [
            'title'    => 'Other Item Sales Book',
            'moduleId' => (string) $request->query('module', 'other-item-reports-sales-book'),
            'today'    => date('Y-m-d'),
        ]);
    }

    public function salesRegister(Request $request)
    {
        if (!$request->session()->has('user_code')) return redirect('/login');
        return view('other-item-reports.sales-register', [
            'title'    => 'Other Item Sales Register',
            'moduleId' => (string) $request->query('module', 'other-item-reports-sales-register'),
            'today'    => date('Y-m-d'),
        ]);
    }

    public function purchaseBook(Request $request)
    {
        if (!$request->session()->has('user_code')) return redirect('/login');
        return view('other-item-reports.purchase-book', [
            'title'    => 'Other Item Purchase Book',
            'moduleId' => (string) $request->query('module', 'other-item-reports-purchase-book'),
            'today'    => date('Y-m-d'),
        ]);
    }

    public function purchaseRegister(Request $request)
    {
        if (!$request->session()->has('user_code')) return redirect('/login');
        return view('other-item-reports.purchase-register', [
            'title'    => 'Other Item Purchase Register',
            'moduleId' => (string) $request->query('module', 'other-item-reports-purchase-register'),
            'today'    => date('Y-m-d'),
        ]);
    }

    public function stockLedger(Request $request)
    {
        if (!$request->session()->has('user_code')) return redirect('/login');
        return view('other-item-reports.stock-ledger', [
            'title'    => 'Other Item Stock Ledger',
            'moduleId' => (string) $request->query('module', 'other-item-reports-stock-ledger'),
            'today'    => date('Y-m-d'),
        ]);
    }

    public function stockList(Request $request)
    {
        if (!$request->session()->has('user_code')) return redirect('/login');
        return view('other-item-reports.stock-list', [
            'title'    => 'Other Item Stock List',
            'moduleId' => (string) $request->query('module', 'other-item-reports-stock-list'),
            'today'    => date('Y-m-d'),
        ]);
    }

    /* ────────────────────────────────────────────────────────────────
     |  API DATA METHODS
     * ──────────────────────────────────────────────────────────────── */

    public function salesBookData(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        if (!$this->hasTable('oitemtranm')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => $this->zeroTotals(['billamt', 'lessamt', 'addamt', 'ramt', 'balance'])]);
        }

        $date1 = $this->date($request, 'date1');
        $date2 = $this->date($request, 'date2');
        $pcode = trim((string) $request->query('pcode', ''));

        $q = DB::table('oitemtranm as m')
            ->where('m.sp', 'S')
            ->whereBetween('m.tdate', [$date1, $date2])
            ->select('m.slno', 'm.tdate', 'm.docno', 'm.pcode', 'm.pname',
                     'm.billamt', 'm.lessamt', 'm.addamt', 'm.ramt');

        if ($pcode !== '') $q->where('m.pcode', $pcode);

        if ($this->hasTable('oitemtrand')) {
            $q->selectRaw('(SELECT COUNT(*) FROM oitemtrand d2 WHERE d2.slno = m.slno) as item_count');
            $q->selectRaw('(SELECT COALESCE(SUM(d2.qty),0) FROM oitemtrand d2 WHERE d2.slno = m.slno) as total_qty');
            $q->selectRaw('(SELECT COALESCE(SUM(d2.amount),0) FROM oitemtrand d2 WHERE d2.slno = m.slno) as item_total');
        } else {
            $q->selectRaw('0 as item_count, 0 as total_qty, 0 as item_total');
        }

        $rows = $q->orderBy('m.tdate')->orderBy('m.slno')->get()
            ->map(function ($r) {
                $row = (array) $r;
                $row['balance'] = (float)$row['billamt'] - (float)$row['ramt'];
                return $row;
            })->values()->all();

        $totals = $this->sumTotals($rows, ['billamt', 'lessamt', 'addamt', 'ramt', 'balance', 'total_qty', 'item_total']);

        return response()->json(['ok' => true, 'rows' => $rows, 'totals' => $totals]);
    }

    public function salesRegisterData(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        if (!$this->hasTable('oitemtranm') || !$this->hasTable('oitemtrand')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => $this->zeroTotals(['qty', 'amount', 'billamt'])]);
        }

        $date1  = $this->date($request, 'date1');
        $date2  = $this->date($request, 'date2');
        $pcode  = trim((string) $request->query('pcode', ''));
        $icode  = trim((string) $request->query('icode', ''));

        $q = DB::table('oitemtranm as m')
            ->join('oitemtrand as d', 'd.slno', '=', 'm.slno')
            ->where('m.sp', 'S')
            ->whereBetween('m.tdate', [$date1, $date2])
            ->select([
                'm.slno', 'm.tdate', 'm.docno', 'm.pcode', 'm.pname',
                'm.billamt', 'm.lessamt', 'm.addamt', 'm.ramt',
                'd.sno', 'd.code as icode', 'd.name as iname',
                'd.qty', 'd.rate', 'd.amount',
            ]);

        if ($pcode !== '') $q->where('m.pcode', $pcode);
        if ($icode !== '') $q->where('d.code', $icode);

        $rows = $q->orderBy('m.tdate')->orderBy('m.slno')->orderBy('d.sno')->get()
            ->map(fn($r) => (array) $r)->values()->all();

        // Totals — billamt only once per unique bill
        $seen = [];
        $totBillamt = $totRamt = 0;
        foreach ($rows as $r) {
            if (!isset($seen[$r['slno']])) {
                $seen[$r['slno']] = true;
                $totBillamt += (float)$r['billamt'];
                $totRamt    += (float)$r['ramt'];
            }
        }
        $totals = [
            'qty'     => array_sum(array_column($rows, 'qty')),
            'amount'  => array_sum(array_column($rows, 'amount')),
            'billamt' => $totBillamt,
            'ramt'    => $totRamt,
            'balance' => $totBillamt - $totRamt,
            'bills'   => count($seen),
        ];

        return response()->json(['ok' => true, 'rows' => $rows, 'totals' => $totals]);
    }

    public function purchaseBookData(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        if (!$this->hasTable('oitemtranm')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => $this->zeroTotals(['billamt', 'lessamt', 'addamt', 'ramt', 'balance'])]);
        }

        $date1 = $this->date($request, 'date1');
        $date2 = $this->date($request, 'date2');
        $pcode = trim((string) $request->query('pcode', ''));

        $q = DB::table('oitemtranm as m')
            ->where('m.sp', 'P')
            ->whereBetween('m.tdate', [$date1, $date2])
            ->select('m.slno', 'm.tdate', 'm.docno', 'm.pcode', 'm.pname',
                     'm.billamt', 'm.lessamt', 'm.addamt', 'm.ramt');

        if ($pcode !== '') $q->where('m.pcode', $pcode);

        // Item count subquery
        if ($this->hasTable('oitemtrand')) {
            $q->selectRaw('(SELECT COUNT(*) FROM oitemtrand d2 WHERE d2.slno = m.slno) as item_count');
            $q->selectRaw('(SELECT COALESCE(SUM(d2.qty),0) FROM oitemtrand d2 WHERE d2.slno = m.slno) as total_qty');
        } else {
            $q->selectRaw('0 as item_count, 0 as total_qty');
        }

        $rows = $q->orderBy('m.tdate')->orderBy('m.slno')->get()
            ->map(function ($r) {
                $row = (array) $r;
                $row['balance'] = (float)$row['billamt'] - (float)$row['ramt'];
                return $row;
            })->values()->all();

        $totals = $this->sumTotals($rows, ['billamt', 'lessamt', 'addamt', 'ramt', 'balance', 'total_qty']);

        return response()->json(['ok' => true, 'rows' => $rows, 'totals' => $totals]);
    }

    public function purchaseRegisterData(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        if (!$this->hasTable('oitemtranm') || !$this->hasTable('oitemtrand')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => $this->zeroTotals(['qty', 'amount', 'billamt'])]);
        }

        $date1 = $this->date($request, 'date1');
        $date2 = $this->date($request, 'date2');
        $pcode = trim((string) $request->query('pcode', ''));
        $icode = trim((string) $request->query('icode', ''));

        $q = DB::table('oitemtranm as m')
            ->join('oitemtrand as d', 'd.slno', '=', 'm.slno')
            ->where('m.sp', 'P')
            ->whereBetween('m.tdate', [$date1, $date2])
            ->select([
                'm.tdate', 'm.docno', 'm.pcode', 'm.pname',
                'd.code as icode', 'd.name as iname',
                'd.qty', 'd.rate', 'd.amount', 'd.cost',
                'm.billamt', 'm.lessamt', 'm.addamt', 'm.ramt',
            ]);

        if ($pcode !== '') $q->where('m.pcode', $pcode);
        if ($icode !== '') $q->where('d.code', $icode);

        $rows = $q->orderBy('m.tdate')->orderBy('m.slno')->orderBy('d.sno')->get()
            ->map(fn($r) => (array) $r)->values()->all();

        $seen = [];
        $totBillamt = 0;
        foreach ($rows as $r) {
            if (!isset($seen[$r['docno']])) {
                $seen[$r['docno']] = true;
                $totBillamt += (float)$r['billamt'];
            }
        }
        $totals = $this->sumTotals($rows, ['qty', 'amount']);
        $totals['billamt'] = $totBillamt;

        return response()->json(['ok' => true, 'rows' => $rows, 'totals' => $totals]);
    }

    public function stockLedgerData(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $icode = trim((string) $request->query('icode', ''));
        if ($icode === '') {
            return response()->json(['ok' => false, 'message' => 'Select an item to view ledger']);
        }

        $date1 = $this->date($request, 'date1');
        $date2 = $this->date($request, 'date2');

        // Opening stock: purchases - sales before date1
        $opStock = 0.0;
        if ($this->hasTable('oitemtranm') && $this->hasTable('oitemtrand')) {
            $opStock = (float) DB::table('oitemtranm as m')
                ->join('oitemtrand as d', 'd.slno', '=', 'm.slno')
                ->where('d.code', $icode)
                ->where('m.tdate', '<', $date1)
                ->selectRaw("COALESCE(SUM(CASE WHEN UPPER(TRIM(m.sp))='P' THEN d.qty ELSE -d.qty END),0) as bal")
                ->value('bal');
        } elseif ($this->hasTable('itemsothers')) {
            $item = DB::table('itemsothers')->where('code', $icode)->first();
            $opStock = $item ? (float)($item->opstock ?? 0) : 0;
        }

        $rows = [];
        $iname = '';

        if ($this->hasTable('oitemtranm') && $this->hasTable('oitemtrand')) {
            // Get item name
            if ($this->hasTable('itemsothers')) {
                $item = DB::table('itemsothers')->where('code', $icode)->first();
                $iname = $item ? trim($item->name) : $icode;
            }

            $txns = DB::table('oitemtranm as m')
                ->join('oitemtrand as d', 'd.slno', '=', 'm.slno')
                ->where('d.code', $icode)
                ->whereBetween('m.tdate', [$date1, $date2])
                ->select('m.tdate', 'm.docno', 'm.pname', 'm.sp', 'd.qty', 'd.rate', 'd.amount')
                ->orderBy('m.tdate')->orderBy('m.slno')->get();

            $bal = $opStock;
            foreach ($txns as $t) {
                $isSale = strtoupper(trim($t->sp)) === 'S';
                $inQty  = $isSale ? 0 : (float)$t->qty;
                $outQty = $isSale ? (float)$t->qty : 0;
                $bal   += $inQty - $outQty;
                $rows[] = [
                    'tdate'   => $t->tdate,
                    'docno'   => trim($t->docno),
                    'pname'   => trim($t->pname),
                    'sp'      => trim($t->sp),
                    'in_qty'  => $inQty,
                    'out_qty' => $outQty,
                    'balance' => $bal,
                    'rate'    => (float)$t->rate,
                    'amount'  => (float)$t->amount,
                ];
            }
        }

        $totals = [
            'in_qty'  => array_sum(array_column($rows, 'in_qty')),
            'out_qty' => array_sum(array_column($rows, 'out_qty')),
            'amount'  => array_sum(array_column($rows, 'amount')),
        ];

        return response()->json([
            'ok'      => true,
            'iname'   => $iname,
            'icode'   => $icode,
            'op_stock' => $opStock,
            'rows'    => $rows,
            'totals'  => $totals,
        ]);
    }

    public function stockListData(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        if (!$this->hasTable('itemsothers')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => []]);
        }

        $grp  = trim((string) $request->query('grp', ''));
        $zero = $request->query('zero', '1');  // 1 = include zero stock, 0 = hide

        $q = DB::table('itemsothers')
            ->select('code', 'name', 'grp', 'stock', 'srate', 'prate', 'cost', 'opcost', 'opstock', 'keepstk');

        if ($grp !== '') $q->where('grp', $grp);
        if ($zero === '0') $q->where('stock', '!=', 0);

        $rows = $q->orderBy('name')->get()
            ->map(function ($r) {
                $row = (array) $r;
                $row['value'] = (float)($r->stock ?? 0) * (float)($r->cost ?? 0);
                return $row;
            })->values()->all();

        $totals = [
            'stock' => array_sum(array_column($rows, 'stock')),
            'value' => array_sum(array_column($rows, 'value')),
        ];

        // Groups for dropdown
        $groups = DB::table('itemsothers')->whereNotNull('grp')->where('grp', '!=', '')
            ->distinct()->orderBy('grp')->pluck('grp');

        return response()->json(['ok' => true, 'rows' => $rows, 'totals' => $totals, 'groups' => $groups]);
    }

    /* ────────────────────────────────────────────────────────────────
     |  Item lookup for autocomplete
     * ──────────────────────────────────────────────────────────────── */
    public function itemsLookup(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $items = [];
        if ($this->hasTable('itemsothers')) {
            $items = DB::table('itemsothers')
                ->select('code', 'name', 'stock')
                ->orderBy('name')
                ->get()
                ->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name), 'stock' => (float)$r->stock])
                ->values()->all();
        }
        return response()->json(['ok' => true, 'items' => $items]);
    }

    /* ────────────────────────────────────────────────────────────────
     |  Helpers
     * ──────────────────────────────────────────────────────────────── */

    private function date(Request $request, string $key): string
    {
        $v = (string) $request->query($key, date('Y-m-d'));
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : date('Y-m-d');
    }

    private function zeroTotals(array $keys): array
    {
        return array_fill_keys($keys, 0.0);
    }

    private function sumTotals(array $rows, array $keys): array
    {
        $totals = $this->zeroTotals($keys);
        foreach ($rows as $row) {
            foreach ($keys as $k) {
                $totals[$k] += (float)($row[$k] ?? 0);
            }
        }
        return $totals;
    }
}
