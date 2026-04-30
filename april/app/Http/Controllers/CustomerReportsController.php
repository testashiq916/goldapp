<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class CustomerReportsController extends Controller
{
    // ── Shared Helpers ────────────────────────────────────────────────────────

    private function auth(Request $request): bool
    {
        return $request->session()->has('user_code');
    }

    private function gilevel(Request $request): int
    {
        return max(1, (int) $request->session()->get('gilevel', 1));
    }

    private function startDate(Request $request): string
    {
        $d = (string) $request->session()->get('gdtstartdate', '');
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : date('Y') . '-04-01';
    }

    private function nd(string $v): string
    {
        $v = trim($v);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return $v;
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $v, $m)) return "$m[3]-$m[2]-$m[1]";
        return date('Y-m-d');
    }

    private function clientNames(): array
    {
        if (!$this->hasTable('clients')) return [];
        return DB::table('clients')
            ->selectRaw('TRIM(code) as code, TRIM(COALESCE(name,"")) as name')
            ->orderBy('name')->limit(2000)->get()
            ->mapWithKeys(fn($r) => [trim($r->code) => trim($r->name)])->all();
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 1. CREDIT BILL DETAILS — outstanding/credit sales bills
    // ══════════════════════════════════════════════════════════════════════════

    public function creditBillIndex(Request $request): View|RedirectResponse
    {
        if (!$this->auth($request)) return redirect('/login');
        return view('customers.credit-bill-details', [
            'title'  => 'Credit Bill Details',
            'date1'  => $this->startDate($request),
            'date2'  => date('Y-m-d'),
            'rlevel' => $this->gilevel($request),
        ]);
    }

    public function creditBillData(Request $request): JsonResponse
    {
        if (!$this->auth($request)) return response()->json(['ok' => false], 401);
        $date1  = $this->nd((string) $request->query('date1', $this->startDate($request)));
        $date2  = $this->nd((string) $request->query('date2', date('Y-m-d')));
        $rl     = $this->gilevel($request);
        $name   = trim((string) $request->query('name', ''));
        $filter = trim((string) $request->query('filter', 'outstanding')); // all|outstanding|paid

        if (!$this->hasTable('salesm')) return response()->json(['ok' => true, 'rows' => []]);

        $q = DB::table('salesm as s')
            ->leftJoin('clients as c', 's.custcode', '=', 'c.code')
            ->selectRaw("
                s.slno, TRIM(s.billno) as billno,
                s.tdate, TRIM(s.custcode) as custcode,
                TRIM(COALESCE(c.name, s.custname,'')) as cname,
                TRIM(COALESCE(c.mobile,'')) as mobile,
                COALESCE(s.netamt,s.billamt,0) as billamt,
                COALESCE(s.ramt,0) as ramt,
                COALESCE(s.bal,0) as bal,
                s.duedate,
                TRIM(COALESCE(s.status,'')) as status
            ")
            ->whereBetween('s.tdate', [$date1, $date2])
            ->where('s.control', '<=', $rl);

        if ($name !== '') {
            $q->where(function ($sub) use ($name) {
                $n = '%' . strtoupper($name) . '%';
                $sub->whereRaw('UPPER(COALESCE(c.name,"")) LIKE ?', [$n])
                    ->orWhereRaw('UPPER(COALESCE(s.custname,"")) LIKE ?', [$n])
                    ->orWhereRaw('UPPER(TRIM(s.custcode)) LIKE ?', [$n]);
            });
        }

        if ($filter === 'outstanding') {
            $q->whereRaw('COALESCE(s.bal,0) > 0.005');
        } elseif ($filter === 'paid') {
            $q->whereRaw('COALESCE(s.bal,0) <= 0.005');
        }

        $rows = $q->orderBy('s.tdate')->orderBy('s.billno')->get()->toArray();

        $totals = ['billamt' => 0.0, 'ramt' => 0.0, 'bal' => 0.0, 'count' => count($rows)];
        foreach ($rows as $r) {
            $totals['billamt'] += (float) $r->billamt;
            $totals['ramt']    += (float) $r->ramt;
            $totals['bal']     += (float) $r->bal;
        }

        return response()->json(['ok' => true, 'rows' => $rows, 'totals' => $totals]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 2. CUSTOMER SUMMARY — per-customer sales totals
    // ══════════════════════════════════════════════════════════════════════════

    public function summaryIndex(Request $request): View|RedirectResponse
    {
        if (!$this->auth($request)) return redirect('/login');
        return view('customers.summary', [
            'title'  => 'Customer Summary',
            'date1'  => $this->startDate($request),
            'date2'  => date('Y-m-d'),
            'rlevel' => $this->gilevel($request),
        ]);
    }

    public function summaryData(Request $request): JsonResponse
    {
        if (!$this->auth($request)) return response()->json(['ok' => false], 401);
        $date1 = $this->nd((string) $request->query('date1', $this->startDate($request)));
        $date2 = $this->nd((string) $request->query('date2', date('Y-m-d')));
        $rl    = $this->gilevel($request);
        $name  = trim((string) $request->query('name', ''));

        if (!$this->hasTable('salesm')) return response()->json(['ok' => true, 'rows' => []]);

        $q = DB::table('salesm as s')
            ->leftJoin('clients as c', 's.custcode', '=', 'c.code')
            ->selectRaw("
                TRIM(s.custcode) as custcode,
                TRIM(COALESCE(c.name, s.custname,'')) as cname,
                TRIM(COALESCE(c.mobile,'')) as mobile,
                TRIM(COALESCE(c.telephone,'')) as telephone,
                COUNT(s.slno) as bill_count,
                COALESCE(SUM(s.netamt),0) as total_amt,
                COALESCE(SUM(s.ramt),0) as total_rcvd,
                COALESCE(SUM(s.bal),0) as total_bal
            ")
            ->whereBetween('s.tdate', [$date1, $date2])
            ->where('s.control', '<=', $rl)
            ->groupBy('s.custcode', 'c.name', 's.custname', 'c.mobile', 'c.telephone');

        if ($name !== '') {
            $q->where(function ($sub) use ($name) {
                $n = '%' . strtoupper($name) . '%';
                $sub->whereRaw('UPPER(COALESCE(c.name,"")) LIKE ?', [$n])
                    ->orWhereRaw('UPPER(COALESCE(s.custname,"")) LIKE ?', [$n]);
            });
        }

        $rows = $q->orderByRaw('COALESCE(c.name, s.custname)')->get()->toArray();

        $totals = ['bill_count' => 0, 'total_amt' => 0.0, 'total_rcvd' => 0.0, 'total_bal' => 0.0];
        foreach ($rows as $r) {
            $totals['bill_count'] += (int) $r->bill_count;
            $totals['total_amt']  += (float) $r->total_amt;
            $totals['total_rcvd'] += (float) $r->total_rcvd;
            $totals['total_bal']  += (float) $r->total_bal;
        }

        return response()->json(['ok' => true, 'rows' => $rows, 'totals' => $totals]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 3. RECEIVED DETAILS — daybook credit entries for customer accounts
    // ══════════════════════════════════════════════════════════════════════════

    public function receivedIndex(Request $request): View|RedirectResponse
    {
        if (!$this->auth($request)) return redirect('/login');
        return view('customers.received-details', [
            'title'  => 'Received Details',
            'date1'  => $this->startDate($request),
            'date2'  => date('Y-m-d'),
            'rlevel' => $this->gilevel($request),
        ]);
    }

    public function receivedData(Request $request): JsonResponse
    {
        if (!$this->auth($request)) return response()->json(['ok' => false], 401);
        $date1 = $this->nd((string) $request->query('date1', $this->startDate($request)));
        $date2 = $this->nd((string) $request->query('date2', date('Y-m-d')));
        $rl    = $this->gilevel($request);
        $name  = trim((string) $request->query('name', ''));

        if (!$this->hasTable('daybook') || !$this->hasTable('accountm')) {
            return response()->json(['ok' => true, 'rows' => []]);
        }

        // Positive daybook amount = credit = payment received from customer
        $q = DB::table('daybook as d')
            ->join('accountm as a', 'd.accode', '=', 'a.accode')
            ->leftJoin('clients as c', 'd.accode', '=', 'c.code')
            ->leftJoin('daybookpart as p', 'd.slno', '=', 'p.slno')
            ->selectRaw("
                d.slno, d.tdate,
                TRIM(d.accode) as accode,
                TRIM(COALESCE(c.name, a.name,'')) as cname,
                ABS(d.amount) as amount,
                TRIM(COALESCE(p.particular,'')) as particular,
                TRIM(COALESCE(d.opaccode,'')) as opaccode,
                d.sno
            ")
            ->whereBetween('d.tdate', [$date1, $date2])
            ->where('d.control', '<=', $rl)
            ->where('d.amount', '>', 0)
            ->where('a.actype2', 'C');

        if ($name !== '') {
            $n = '%' . strtoupper($name) . '%';
            $q->where(function ($sub) use ($n) {
                $sub->whereRaw('UPPER(COALESCE(c.name,"")) LIKE ?', [$n])
                    ->orWhereRaw('UPPER(COALESCE(a.name,"")) LIKE ?', [$n]);
            });
        }

        $rows   = $q->orderBy('d.tdate')->orderBy('d.slno')->get()->toArray();
        $total  = array_sum(array_column($rows, 'amount'));

        return response()->json(['ok' => true, 'rows' => $rows, 'total' => $total]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 4. PAYMENT DETAILS — daybook debit entries for customer accounts
    // ══════════════════════════════════════════════════════════════════════════

    public function paymentIndex(Request $request): View|RedirectResponse
    {
        if (!$this->auth($request)) return redirect('/login');
        return view('customers.payment-details', [
            'title'  => 'Payment Details',
            'date1'  => $this->startDate($request),
            'date2'  => date('Y-m-d'),
            'rlevel' => $this->gilevel($request),
        ]);
    }

    public function paymentData(Request $request): JsonResponse
    {
        if (!$this->auth($request)) return response()->json(['ok' => false], 401);
        $date1 = $this->nd((string) $request->query('date1', $this->startDate($request)));
        $date2 = $this->nd((string) $request->query('date2', date('Y-m-d')));
        $rl    = $this->gilevel($request);
        $name  = trim((string) $request->query('name', ''));

        if (!$this->hasTable('daybook') || !$this->hasTable('accountm')) {
            return response()->json(['ok' => true, 'rows' => []]);
        }

        // Negative daybook amount = debit = amount added to customer outstanding (sales/debit)
        $q = DB::table('daybook as d')
            ->join('accountm as a', 'd.accode', '=', 'a.accode')
            ->leftJoin('clients as c', 'd.accode', '=', 'c.code')
            ->leftJoin('daybookpart as p', 'd.slno', '=', 'p.slno')
            ->selectRaw("
                d.slno, d.tdate,
                TRIM(d.accode) as accode,
                TRIM(COALESCE(c.name, a.name,'')) as cname,
                ABS(d.amount) as amount,
                TRIM(COALESCE(p.particular,'')) as particular,
                TRIM(COALESCE(d.opaccode,'')) as opaccode,
                d.sno
            ")
            ->whereBetween('d.tdate', [$date1, $date2])
            ->where('d.control', '<=', $rl)
            ->where('d.amount', '<', 0)
            ->where('a.actype2', 'C');

        if ($name !== '') {
            $n = '%' . strtoupper($name) . '%';
            $q->where(function ($sub) use ($n) {
                $sub->whereRaw('UPPER(COALESCE(c.name,"")) LIKE ?', [$n])
                    ->orWhereRaw('UPPER(COALESCE(a.name,"")) LIKE ?', [$n]);
            });
        }

        $rows  = $q->orderBy('d.tdate')->orderBy('d.slno')->get()->toArray();
        $total = array_sum(array_column($rows, 'amount'));

        return response()->json(['ok' => true, 'rows' => $rows, 'total' => $total]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 5. CUSTOMER SALES SUMMARY — per-customer sales with weight & item count
    // ══════════════════════════════════════════════════════════════════════════

    public function salesSummaryIndex(Request $request): View|RedirectResponse
    {
        if (!$this->auth($request)) return redirect('/login');
        return view('customers.sales-summary', [
            'title'  => 'Customer Sales Summary',
            'date1'  => $this->startDate($request),
            'date2'  => date('Y-m-d'),
            'rlevel' => $this->gilevel($request),
        ]);
    }

    public function salesSummaryData(Request $request): JsonResponse
    {
        if (!$this->auth($request)) return response()->json(['ok' => false], 401);
        $date1 = $this->nd((string) $request->query('date1', $this->startDate($request)));
        $date2 = $this->nd((string) $request->query('date2', date('Y-m-d')));
        $rl    = $this->gilevel($request);
        $name  = trim((string) $request->query('name', ''));

        if (!$this->hasTable('salesm')) return response()->json(['ok' => true, 'rows' => []]);

        $hasSd = $this->hasTable('salesd');

        $q = DB::table('salesm as s')
            ->leftJoin('clients as c', 's.custcode', '=', 'c.code');

        if ($hasSd) {
            $q->leftJoin('salesd as sd', 's.slno', '=', 'sd.slno')
              ->selectRaw("
                TRIM(s.custcode) as custcode,
                TRIM(COALESCE(c.name, s.custname,'')) as cname,
                TRIM(COALESCE(c.mobile,'')) as mobile,
                COUNT(DISTINCT s.slno) as bill_count,
                COALESCE(SUM(sd.weight),0) as total_wgt,
                COALESCE(SUM(sd.amount),0) as total_item_amt,
                COALESCE(SUM(s.netamt),0) as total_net,
                COALESCE(SUM(s.discount),0) as total_disc,
                COALESCE(SUM(s.ramt),0) as total_rcvd,
                COALESCE(SUM(s.bal),0) as total_bal
              ");
        } else {
            $q->selectRaw("
                TRIM(s.custcode) as custcode,
                TRIM(COALESCE(c.name, s.custname,'')) as cname,
                TRIM(COALESCE(c.mobile,'')) as mobile,
                COUNT(s.slno) as bill_count,
                0 as total_wgt,
                COALESCE(SUM(s.billamt),0) as total_item_amt,
                COALESCE(SUM(s.netamt),0) as total_net,
                COALESCE(SUM(s.discount),0) as total_disc,
                COALESCE(SUM(s.ramt),0) as total_rcvd,
                COALESCE(SUM(s.bal),0) as total_bal
            ");
        }

        $q->whereBetween('s.tdate', [$date1, $date2])
          ->where('s.control', '<=', $rl)
          ->groupBy('s.custcode', 'c.name', 's.custname', 'c.mobile');

        if ($name !== '') {
            $n = '%' . strtoupper($name) . '%';
            $q->where(function ($sub) use ($n) {
                $sub->whereRaw('UPPER(COALESCE(c.name,"")) LIKE ?', [$n])
                    ->orWhereRaw('UPPER(COALESCE(s.custname,"")) LIKE ?', [$n]);
            });
        }

        $rows = $q->orderByRaw('COALESCE(c.name, s.custname)')->get()->toArray();

        $totals = ['bill_count' => 0, 'total_wgt' => 0.0, 'total_net' => 0.0, 'total_rcvd' => 0.0, 'total_bal' => 0.0];
        foreach ($rows as $r) {
            $totals['bill_count'] += (int) $r->bill_count;
            $totals['total_wgt']  += (float) $r->total_wgt;
            $totals['total_net']  += (float) $r->total_net;
            $totals['total_rcvd'] += (float) $r->total_rcvd;
            $totals['total_bal']  += (float) $r->total_bal;
        }

        return response()->json(['ok' => true, 'rows' => $rows, 'totals' => $totals]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 6. BILLWISE DETAILS — individual bills with item lines
    // ══════════════════════════════════════════════════════════════════════════

    public function billwiseDetailsIndex(Request $request): View|RedirectResponse
    {
        if (!$this->auth($request)) return redirect('/login');
        return view('customers.billwise-details', [
            'title'  => 'Billwise Details',
            'date1'  => $this->startDate($request),
            'date2'  => date('Y-m-d'),
            'rlevel' => $this->gilevel($request),
        ]);
    }

    public function billwiseDetailsData(Request $request): JsonResponse
    {
        if (!$this->auth($request)) return response()->json(['ok' => false], 401);
        $date1 = $this->nd((string) $request->query('date1', $this->startDate($request)));
        $date2 = $this->nd((string) $request->query('date2', date('Y-m-d')));
        $rl    = $this->gilevel($request);
        $name  = trim((string) $request->query('name', ''));

        if (!$this->hasTable('salesm')) return response()->json(['ok' => true, 'rows' => []]);

        $q = DB::table('salesm as s')
            ->leftJoin('clients as c', 's.custcode', '=', 'c.code')
            ->selectRaw("
                s.slno, TRIM(s.billno) as billno, s.tdate,
                TRIM(s.custcode) as custcode,
                TRIM(COALESCE(c.name, s.custname,'')) as cname,
                TRIM(COALESCE(c.mobile,'')) as mobile,
                COALESCE(s.billamt,0) as billamt,
                COALESCE(s.discount,0) as discount,
                COALESCE(s.netamt,0) as netamt,
                COALESCE(s.ramt,0) as ramt,
                COALESCE(s.bal,0) as bal,
                s.duedate,
                TRIM(COALESCE(s.status,'')) as bstatus,
                TRIM(COALESCE(s.smcode,'')) as smcode
            ")
            ->whereBetween('s.tdate', [$date1, $date2])
            ->where('s.control', '<=', $rl);

        if ($name !== '') {
            $n = '%' . strtoupper($name) . '%';
            $q->where(function ($sub) use ($n) {
                $sub->whereRaw('UPPER(COALESCE(c.name,"")) LIKE ?', [$n])
                    ->orWhereRaw('UPPER(COALESCE(s.custname,"")) LIKE ?', [$n])
                    ->orWhereRaw('UPPER(TRIM(s.billno)) LIKE ?', [$n]);
            });
        }

        $rows = $q->orderBy('s.tdate')->orderBy('s.billno')->get()->toArray();

        $totals = ['billamt' => 0.0, 'netamt' => 0.0, 'ramt' => 0.0, 'bal' => 0.0, 'count' => count($rows)];
        foreach ($rows as $r) {
            $totals['billamt'] += (float) $r->billamt;
            $totals['netamt']  += (float) $r->netamt;
            $totals['ramt']    += (float) $r->ramt;
            $totals['bal']     += (float) $r->bal;
        }

        return response()->json(['ok' => true, 'rows' => $rows, 'totals' => $totals]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 7. BILL COLLECTION DETAILS — collection table
    // ══════════════════════════════════════════════════════════════════════════

    public function billCollectionIndex(Request $request): View|RedirectResponse
    {
        if (!$this->auth($request)) return redirect('/login');
        return view('customers.bill-collection-details', [
            'title'  => 'Bill Collection Details',
            'date1'  => $this->startDate($request),
            'date2'  => date('Y-m-d'),
            'rlevel' => $this->gilevel($request),
        ]);
    }

    public function billCollectionData(Request $request): JsonResponse
    {
        if (!$this->auth($request)) return response()->json(['ok' => false], 401);
        $date1 = $this->nd((string) $request->query('date1', $this->startDate($request)));
        $date2 = $this->nd((string) $request->query('date2', date('Y-m-d')));
        $rl    = $this->gilevel($request);
        $name  = trim((string) $request->query('name', ''));

        if (!$this->hasTable('collection')) return response()->json(['ok' => true, 'rows' => []]);

        $q = DB::table('collection as col')
            ->leftJoin('clients as c', 'col.code', '=', 'c.code')
            ->selectRaw("
                col.slno, col.tdate,
                TRIM(col.code) as code,
                TRIM(COALESCE(c.name,'')) as cname,
                TRIM(COALESCE(col.billno,'')) as billno,
                COALESCE(col.tranamt,0) as tranamt,
                COALESCE(col.amount,0) as amount,
                COALESCE(col.discount,0) as discount,
                col.duedate,
                TRIM(COALESCE(col.cbcode,'')) as cbcode
            ")
            ->whereBetween('col.tdate', [$date1, $date2])
            ->where('col.control', '<=', $rl);

        if ($name !== '') {
            $n = '%' . strtoupper($name) . '%';
            $q->where(function ($sub) use ($n) {
                $sub->whereRaw('UPPER(COALESCE(c.name,"")) LIKE ?', [$n])
                    ->orWhereRaw('UPPER(TRIM(col.code)) LIKE ?', [$n])
                    ->orWhereRaw('UPPER(TRIM(col.billno)) LIKE ?', [$n]);
            });
        }

        $rows = $q->orderBy('col.tdate')->orderBy('col.slno')->get()->toArray();

        $totals = ['tranamt' => 0.0, 'amount' => 0.0, 'discount' => 0.0, 'count' => count($rows)];
        foreach ($rows as $r) {
            $totals['tranamt']  += (float) $r->tranamt;
            $totals['amount']   += (float) $r->amount;
            $totals['discount'] += (float) $r->discount;
        }

        return response()->json(['ok' => true, 'rows' => $rows, 'totals' => $totals]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 8. DUEDATE REPORT — customers with outstanding balances and due dates
    // ══════════════════════════════════════════════════════════════════════════

    public function duedateIndex(Request $request): View|RedirectResponse
    {
        if (!$this->auth($request)) return redirect('/login');
        return view('customers.duedate-report', [
            'title'  => 'Duedate Report',
            'date1'  => date('Y-m-d'),
            'date2'  => date('Y-m-d', strtotime('+30 days')),
            'rlevel' => $this->gilevel($request),
        ]);
    }

    public function duedateData(Request $request): JsonResponse
    {
        if (!$this->auth($request)) return response()->json(['ok' => false], 401);
        $date1  = $this->nd((string) $request->query('date1', date('Y-m-d')));
        $date2  = $this->nd((string) $request->query('date2', date('Y-m-d', strtotime('+30 days'))));
        $rl     = $this->gilevel($request);
        $name   = trim((string) $request->query('name', ''));
        $filter = trim((string) $request->query('filter', 'overdue_first')); // overdue_first|by_name|by_balance

        $sessionStart = $this->startDate($request);
        $opColumn     = $rl === 1 ? 'opbal' : 'opbalb';

        if (!$this->hasTable('accountm') || !$this->hasTable('clients')) {
            return response()->json(['ok' => true, 'rows' => []]);
        }

        $hasDaybook = $this->hasTable('daybook');
        $hasSalesm  = $this->hasTable('salesm');

        // Get customer accounts with opening balance
        $baseRows = DB::table('accountm as a')
            ->join('clients as c', 'a.accode', '=', 'c.code')
            ->selectRaw("
                TRIM(a.accode) as accode,
                TRIM(COALESCE(c.name,'')) as cname,
                TRIM(COALESCE(c.mobile,'')) as mobile,
                TRIM(COALESCE(c.telephone,'')) as telephone,
                COALESCE(a.$opColumn,0) as opbal,
                c.duedate as client_duedate
            ")
            ->where('a.actype2', 'C')
            ->where('a.control', '<=', $rl)
            ->when($name !== '', function ($q) use ($name) {
                $n = '%' . strtoupper($name) . '%';
                $q->where(function ($sub) use ($n) {
                    $sub->whereRaw('UPPER(COALESCE(c.name,"")) LIKE ?', [$n])
                        ->orWhereRaw('UPPER(TRIM(a.accode)) LIKE ?', [$n]);
                });
            })
            ->get();

        if ($baseRows->isEmpty()) return response()->json(['ok' => true, 'rows' => []]);

        $codes = $baseRows->pluck('accode')->map(fn($x) => trim((string)$x))->filter()->values()->all();

        // Daybook running balance up to date2
        $dbStats = [];
        if ($hasDaybook && count($codes)) {
            $dbStats = DB::table('daybook')
                ->selectRaw('TRIM(accode) as code, COALESCE(SUM(amount),0) as tamt')
                ->whereIn('accode', $codes)
                ->where('tdate', '<=', $date2)
                ->where('control', '<=', $rl)
                ->groupBy('accode')->get()
                ->keyBy(fn($r) => trim($r->code))->all();
        }

        // Max duedate from salesm
        $salesDue = [];
        if ($hasSalesm && count($codes)) {
            $salesDue = DB::table('salesm')
                ->selectRaw('TRIM(custcode) as code, MAX(duedate) as due')
                ->whereIn('custcode', $codes)
                ->groupBy('custcode')->get()
                ->keyBy(fn($r) => trim($r->code))->all();
        }

        $rows = [];
        foreach ($baseRows as $row) {
            $code   = trim((string) $row->accode);
            $tamt   = (float) ($dbStats[$code]->tamt ?? 0);
            $netbal = round((float) $row->opbal + $tamt, 2);

            if (abs($netbal) < 0.01) continue; // skip zero balance

            $saledue = $salesDue[$code]->due ?? null;
            $cldue   = $row->client_duedate;
            $duedate = max(array_filter([$saledue, $cldue])) ?: null;

            $overdue = $duedate && $duedate < date('Y-m-d');

            $rows[] = [
                'accode'    => $code,
                'cname'     => (string) $row->cname,
                'mobile'    => (string) $row->mobile,
                'telephone' => (string) $row->telephone,
                'netbal'    => $netbal,
                'bal_abs'   => abs($netbal),
                'status'    => $netbal > 0 ? 'TG' : 'TR',
                'duedate'   => $duedate ?? '',
                'overdue'   => $overdue,
            ];
        }

        // Filter to duedate range if requested
        if ($request->boolean('range_filter', false)) {
            $rows = array_filter($rows, fn($r) => $r['duedate'] >= $date1 && $r['duedate'] <= $date2);
            $rows = array_values($rows);
        }

        usort($rows, function ($a, $b) use ($filter) {
            if ($filter === 'by_name') return strcmp($a['cname'], $b['cname']);
            if ($filter === 'by_balance') return $b['bal_abs'] <=> $a['bal_abs'];
            // overdue_first: overdue first then by duedate
            if ($a['overdue'] !== $b['overdue']) return $b['overdue'] - $a['overdue'];
            return strcmp($a['duedate'], $b['duedate']) ?: strcmp($a['cname'], $b['cname']);
        });

        $totals = ['tr' => 0.0, 'tg' => 0.0, 'count' => count($rows)];
        foreach ($rows as $r) {
            if ($r['status'] === 'TR') $totals['tr'] += $r['bal_abs'];
            else $totals['tg'] += $r['bal_abs'];
        }

        return response()->json(['ok' => true, 'rows' => $rows, 'totals' => $totals]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 9. PARTY HISTORY — userhist table
    // ══════════════════════════════════════════════════════════════════════════

    public function partyHistoryIndex(Request $request): View|RedirectResponse
    {
        if (!$this->auth($request)) return redirect('/login');
        return view('customers.party-history', [
            'title'  => 'Party History',
            'date1'  => $this->startDate($request),
            'date2'  => date('Y-m-d'),
            'rlevel' => $this->gilevel($request),
        ]);
    }

    public function partyHistoryData(Request $request): JsonResponse
    {
        if (!$this->auth($request)) return response()->json(['ok' => false], 401);
        $date1 = $this->nd((string) $request->query('date1', $this->startDate($request)));
        $date2 = $this->nd((string) $request->query('date2', date('Y-m-d')));
        $name  = trim((string) $request->query('name', ''));

        if (!$this->hasTable('userhist')) return response()->json(['ok' => true, 'rows' => []]);

        $q = DB::table('userhist as u')
            ->leftJoin('clients as c', 'u.code', '=', 'c.code')
            ->selectRaw("
                TRIM(u.code) as code,
                TRIM(COALESCE(c.name,'')) as cname,
                TRIM(COALESCE(c.mobile,'')) as mobile,
                u.tdate,
                TRIM(COALESCE(u.time1,'')) as time1,
                TRIM(COALESCE(u.time2,'')) as time2
            ")
            ->whereBetween('u.tdate', [$date1, $date2]);

        if ($name !== '') {
            $n = '%' . strtoupper($name) . '%';
            $q->where(function ($sub) use ($n) {
                $sub->whereRaw('UPPER(COALESCE(c.name,"")) LIKE ?', [$n])
                    ->orWhereRaw('UPPER(TRIM(u.code)) LIKE ?', [$n]);
            });
        }

        $rows = $q->orderBy('u.tdate')->orderBy('u.code')->get()->toArray();

        return response()->json(['ok' => true, 'rows' => $rows, 'count' => count($rows)]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 10. VISIT REPORT — appoint table
    // ══════════════════════════════════════════════════════════════════════════

    public function visitReportIndex(Request $request): View|RedirectResponse
    {
        if (!$this->auth($request)) return redirect('/login');
        return view('customers.visit-report', [
            'title'  => 'Visit Report',
            'date1'  => $this->startDate($request),
            'date2'  => date('Y-m-d'),
            'rlevel' => $this->gilevel($request),
        ]);
    }

    public function visitReportData(Request $request): JsonResponse
    {
        if (!$this->auth($request)) return response()->json(['ok' => false], 401);
        $date1 = $this->nd((string) $request->query('date1', $this->startDate($request)));
        $date2 = $this->nd((string) $request->query('date2', date('Y-m-d')));
        $search = trim((string) $request->query('search', ''));

        if (!$this->hasTable('appoint')) return response()->json(['ok' => true, 'rows' => []]);

        $q = DB::table('appoint')
            ->selectRaw("slno, DATE(tdatetime) as adate, tdatetime, TRIM(COALESCE(`desc`,'')) as adesc, TRIM(COALESCE(description,'')) as description")
            ->whereRaw('DATE(tdatetime) BETWEEN ? AND ?', [$date1, $date2]);

        if ($search !== '') {
            $s = '%' . $search . '%';
            $q->where(function ($sub) use ($s) {
                $sub->where('desc', 'LIKE', $s)->orWhere('description', 'LIKE', $s);
            });
        }

        $rows = $q->orderBy('tdatetime')->get()->toArray();

        return response()->json(['ok' => true, 'rows' => $rows, 'count' => count($rows)]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 11. CUSTOMER LIST
    // ══════════════════════════════════════════════════════════════════════════

    public function customerListIndex(Request $request): View|RedirectResponse
    {
        if (!$this->auth($request)) return redirect('/login');
        return view('customers.list', [
            'title'  => 'Customer List',
            'rlevel' => $this->gilevel($request),
        ]);
    }

    public function customerListData(Request $request): JsonResponse
    {
        if (!$this->auth($request)) return response()->json(['ok' => false], 401);
        $rl   = $this->gilevel($request);
        $name = trim((string) $request->query('name', ''));
        $grp  = trim((string) $request->query('grp', ''));
        $route = trim((string) $request->query('route', ''));

        if (!$this->hasTable('clients') || !$this->hasTable('accountm')) {
            return response()->json(['ok' => true, 'rows' => []]);
        }

        $opCol = $rl === 1 ? 'opbal' : 'opbalb';
        $hasDaybook = $this->hasTable('daybook');

        $q = DB::table('clients as c')
            ->join('accountm as a', 'c.code', '=', 'a.accode')
            ->selectRaw("
                TRIM(c.code) as code,
                TRIM(COALESCE(c.name,'')) as name,
                TRIM(COALESCE(c.addr1,'')) as addr1,
                TRIM(COALESCE(c.addr2,'')) as addr2,
                TRIM(COALESCE(c.city,'')) as city,
                TRIM(COALESCE(c.mobile,'')) as mobile,
                TRIM(COALESCE(c.telephone,'')) as telephone,
                TRIM(COALESCE(c.grp,'')) as grp,
                TRIM(COALESCE(c.route,'')) as route,
                TRIM(COALESCE(c.carea,'')) as carea,
                COALESCE(c.duedate,'') as duedate,
                COALESCE(a.$opCol,0) as opbal
            ")
            ->where('a.actype2', 'C')
            ->where('a.control', '<=', $rl);

        if ($name !== '') {
            $n = '%' . strtoupper($name) . '%';
            $q->where(function ($sub) use ($n) {
                $sub->whereRaw('UPPER(COALESCE(c.name,"")) LIKE ?', [$n])
                    ->orWhereRaw('UPPER(TRIM(c.code)) LIKE ?', [$n])
                    ->orWhereRaw('UPPER(COALESCE(c.mobile,"")) LIKE ?', [$n]);
            });
        }
        if ($grp !== '')   $q->where('c.grp', $grp);
        if ($route !== '') $q->where('c.route', $route);

        $baseRows = $q->orderBy('c.name')->get();
        if ($baseRows->isEmpty()) return response()->json(['ok' => true, 'rows' => []]);

        $codes = $baseRows->pluck('code')->map(fn($x) => trim((string)$x))->filter()->values()->all();

        $dbStats = [];
        if ($hasDaybook && count($codes)) {
            $dbStats = DB::table('daybook')
                ->selectRaw('TRIM(accode) as code, COALESCE(SUM(amount),0) as tamt')
                ->whereIn('accode', $codes)
                ->where('control', '<=', $rl)
                ->groupBy('accode')->get()
                ->keyBy(fn($r) => trim($r->code))->all();
        }

        $rows = $baseRows->map(function ($row) use ($dbStats) {
            $code   = trim((string) $row->code);
            $tamt   = (float) ($dbStats[$code]->tamt ?? 0);
            $netbal = round((float) $row->opbal + $tamt, 2);
            return [
                'code'      => $code,
                'name'      => (string) $row->name,
                'addr1'     => (string) $row->addr1,
                'addr2'     => (string) $row->addr2,
                'city'      => (string) $row->city,
                'mobile'    => (string) $row->mobile,
                'telephone' => (string) $row->telephone,
                'grp'       => (string) $row->grp,
                'route'     => (string) $row->route,
                'duedate'   => (string) $row->duedate,
                'netbal'    => $netbal,
                'status'    => $netbal < 0 ? 'TR' : ($netbal > 0 ? 'TG' : ''),
            ];
        })->values()->all();

        // Lookups for filters
        $groups = $this->hasTable('clientsgrp')
            ? DB::table('clientsgrp')->selectRaw('TRIM(code) as code, TRIM(name) as name')->orderBy('name')->get()->toArray()
            : [];
        $routes = $this->hasTable('route')
            ? DB::table('route')->selectRaw('TRIM(code) as code, TRIM(name) as name')->orderBy('name')->get()->toArray()
            : [];

        return response()->json(['ok' => true, 'rows' => $rows, 'count' => count($rows), 'groups' => $groups, 'routes' => $routes]);
    }
}
