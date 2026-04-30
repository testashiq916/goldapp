<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StaffReportsController extends Controller
{
    private function guard(Request $r): bool
    {
        return $r->session()->has('user_code');
    }

    private function relaxGroupBy(): void
    {
        DB::statement("SET SESSION sql_mode = (SELECT REPLACE(@@SESSION.sql_mode, 'ONLY_FULL_GROUP_BY', ''))");
    }

    /* ── Page ─────────────────────────────────────────────────────── */
    public function index(Request $request)
    {
        if (!$this->guard($request)) return redirect('/login');
        $sub = $request->query('sub', 'salesman-book');
        return view('staff-reports.index', [
            'title'    => 'Staff Reports',
            'moduleId' => (string) $request->query('module', 'staff-reports'),
            'sub'      => $sub,
            'today'    => date('Y-m-d'),
            'firstDay' => date('Y-m-01'),
        ]);
    }

    /* ══════════════════════════════════════════════════════════════
       HELPERS
       ══════════════════════════════════════════════════════════════ */
    private function dateRange(Request $r): array
    {
        $from = $r->query('from', date('Y-m-01'));
        $to   = $r->query('to',   date('Y-m-d'));
        return [substr($from, 0, 10), substr($to, 0, 10)];
    }

    private function smanList(): array
    {
        if (!$this->hasTable('sman')) return [];
        return DB::table('sman')
            ->select('code', 'name', 'comn', 'cat', 'active')
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    /* ══════════════════════════════════════════════════════════════
       1. SALESMAN SALES BOOK  — bill-level detail per salesman
       ══════════════════════════════════════════════════════════════ */
    public function salesmanBook(Request $request): JsonResponse
    {
        if (!$this->guard($request)) return response()->json(['ok' => false], 401);
        $this->relaxGroupBy();

        [$from, $to] = $this->dateRange($request);
        $smcode = trim($request->query('smcode', ''));

        $q = DB::table('salesm as m')
            ->leftJoin('sman as s', DB::raw('TRIM(s.code)'), '=', DB::raw('TRIM(m.smcode)'))
            ->select(
                DB::raw('TRIM(m.smcode) as smcode'),
                DB::raw('COALESCE(TRIM(s.name), TRIM(m.smcode), "—") as smname'),
                'm.billno', 'm.tdate',
                DB::raw('TRIM(m.custname) as custname'),
                'm.billamt', 'm.discount', 'm.netamt',
                DB::raw('COALESCE(m.sgst,0)+COALESCE(m.cgst,0)+COALESCE(m.igst,0) as taxamt'),
                DB::raw('COALESCE(m.tcsamt,0) as tcsamt'),
                DB::raw("CASE WHEN UPPER(TRIM(m.status))='C' THEN 'Cancelled' ELSE 'Active' END as status")
            )
            ->whereBetween('m.tdate', [$from, $to])
            ->whereRaw("UPPER(TRIM(COALESCE(m.status,''))) <> 'X'");

        if ($smcode !== '') {
            $q->whereRaw('TRIM(m.smcode) = ?', [$smcode]);
        }

        $rows = $q->orderBy('m.tdate')->orderBy('smcode')->orderBy('m.billno')->get();

        // Group by salesman
        $grouped = [];
        foreach ($rows as $r) {
            $key = $r->smcode ?: '—';
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'smcode'   => $r->smcode,
                    'smname'   => $r->smname,
                    'bills'    => [],
                    'total_amt'=> 0,
                    'total_net'=> 0,
                    'total_disc'=> 0,
                    'bill_count'=> 0,
                    'cancelled' => 0,
                ];
            }
            $grouped[$key]['bills'][] = $r;
            if ($r->status !== 'Cancelled') {
                $grouped[$key]['total_amt']  += (float)$r->billamt;
                $grouped[$key]['total_net']  += (float)$r->netamt;
                $grouped[$key]['total_disc'] += (float)$r->discount;
                $grouped[$key]['bill_count']++;
            } else {
                $grouped[$key]['cancelled']++;
            }
        }

        $grandAmt  = array_sum(array_column($grouped, 'total_amt'));
        $grandNet  = array_sum(array_column($grouped, 'total_net'));
        $grandDisc = array_sum(array_column($grouped, 'total_disc'));
        $grandBills= array_sum(array_column($grouped, 'bill_count'));

        return response()->json([
            'ok'        => true,
            'from'      => $from, 'to' => $to,
            'grouped'   => array_values($grouped),
            'grand'     => compact('grandAmt','grandNet','grandDisc','grandBills'),
            'sman_list' => $this->smanList(),
        ]);
    }

    /* ══════════════════════════════════════════════════════════════
       2. SALESMAN SUMMARY  — one row per salesman with totals
       ══════════════════════════════════════════════════════════════ */
    public function salesmanSummary(Request $request): JsonResponse
    {
        if (!$this->guard($request)) return response()->json(['ok' => false], 401);
        $this->relaxGroupBy();

        [$from, $to] = $this->dateRange($request);

        $rows = DB::table('salesm as m')
            ->leftJoin('sman as s', DB::raw('TRIM(s.code)'), '=', DB::raw('TRIM(m.smcode)'))
            ->select(
                DB::raw('TRIM(m.smcode) as smcode'),
                DB::raw('COALESCE(MAX(TRIM(s.name)), TRIM(m.smcode), "No Salesman") as smname'),
                DB::raw('MAX(COALESCE(s.comn, 0)) as comn_pct'),
                DB::raw('COUNT(*) as total_bills'),
                DB::raw('SUM(CASE WHEN UPPER(TRIM(COALESCE(m.status,"")))="C" THEN 1 ELSE 0 END) as cancelled'),
                DB::raw('SUM(CASE WHEN UPPER(TRIM(COALESCE(m.status,"")))<>"C" THEN 1 ELSE 0 END) as active_bills'),
                DB::raw('SUM(CASE WHEN UPPER(TRIM(COALESCE(m.status,"")))<>"C" THEN m.billamt ELSE 0 END) as total_amt'),
                DB::raw('SUM(CASE WHEN UPPER(TRIM(COALESCE(m.status,"")))<>"C" THEN m.netamt ELSE 0 END) as net_amt'),
                DB::raw('SUM(CASE WHEN UPPER(TRIM(COALESCE(m.status,"")))<>"C" THEN m.discount ELSE 0 END) as total_disc'),
                DB::raw('AVG(CASE WHEN UPPER(TRIM(COALESCE(m.status,"")))<>"C" THEN m.billamt ELSE NULL END) as avg_bill'),
                DB::raw('MAX(CASE WHEN UPPER(TRIM(COALESCE(m.status,"")))<>"C" THEN m.billamt ELSE NULL END) as max_bill'),
                DB::raw('SUM(CASE WHEN UPPER(TRIM(COALESCE(m.status,"")))<>"C" THEN m.billamt ELSE 0 END)*MAX(COALESCE(s.comn,0))/100 as commission')
            )
            ->whereBetween('m.tdate', [$from, $to])
            ->whereRaw("UPPER(TRIM(COALESCE(m.status,''))) <> 'X'")
            ->groupBy(DB::raw('TRIM(m.smcode)'))
            ->orderByDesc('total_amt')
            ->get();

        $grand = [
            'total_bills'  => $rows->sum('total_bills'),
            'active_bills' => $rows->sum('active_bills'),
            'cancelled'    => $rows->sum('cancelled'),
            'total_amt'    => $rows->sum('total_amt'),
            'net_amt'      => $rows->sum('net_amt'),
            'total_disc'   => $rows->sum('total_disc'),
            'commission'   => $rows->sum('commission'),
        ];

        return response()->json([
            'ok'    => true,
            'from'  => $from, 'to' => $to,
            'rows'  => $rows,
            'grand' => $grand,
        ]);
    }

    /* ══════════════════════════════════════════════════════════════
       3. COUNTER SALES REPORT
       ══════════════════════════════════════════════════════════════ */
    public function counterSales(Request $request): JsonResponse
    {
        if (!$this->guard($request)) return response()->json(['ok' => false], 401);
        $this->relaxGroupBy();

        [$from, $to] = $this->dateRange($request);

        $counters = $this->hasTable('counter')
            ? DB::table('counter')->select('code', 'name')->orderBy('name')->get()->keyBy('code')
            : collect();

        $rows = DB::table('salesm as m')
            ->select(
                DB::raw('TRIM(COALESCE(m.counter,"")) as counter_code'),
                DB::raw('COUNT(*) as total_bills'),
                DB::raw('SUM(CASE WHEN UPPER(TRIM(COALESCE(m.status,"")))<>"C" THEN 1 ELSE 0 END) as active_bills'),
                DB::raw('SUM(CASE WHEN UPPER(TRIM(COALESCE(m.status,"")))<>"C" THEN m.billamt ELSE 0 END) as total_amt'),
                DB::raw('SUM(CASE WHEN UPPER(TRIM(COALESCE(m.status,"")))<>"C" THEN m.netamt ELSE 0 END) as net_amt'),
                DB::raw('SUM(CASE WHEN UPPER(TRIM(COALESCE(m.status,"")))<>"C" THEN m.discount ELSE 0 END) as total_disc'),
                DB::raw('AVG(CASE WHEN UPPER(TRIM(COALESCE(m.status,"")))<>"C" THEN m.billamt ELSE NULL END) as avg_bill'),
                DB::raw('COUNT(DISTINCT DATE(m.tdate)) as working_days')
            )
            ->whereBetween('m.tdate', [$from, $to])
            ->whereRaw("UPPER(TRIM(COALESCE(m.status,''))) <> 'X'")
            ->groupBy(DB::raw('TRIM(COALESCE(m.counter,""))'))
            ->orderByDesc('total_amt')
            ->get()
            ->map(function($r) use ($counters) {
                $r->counter_name = isset($counters[$r->counter_code])
                    ? $counters[$r->counter_code]->name
                    : ($r->counter_code ?: 'No Counter');
                return $r;
            });

        $grand = [
            'total_bills'  => $rows->sum('total_bills'),
            'active_bills' => $rows->sum('active_bills'),
            'total_amt'    => $rows->sum('total_amt'),
            'net_amt'      => $rows->sum('net_amt'),
            'total_disc'   => $rows->sum('total_disc'),
        ];

        // Daily breakdown per counter for trend
        $daily = DB::table('salesm as m')
            ->select(
                'm.tdate',
                DB::raw('TRIM(COALESCE(m.counter,"")) as counter_code'),
                DB::raw('COUNT(*) as bills'),
                DB::raw('SUM(CASE WHEN UPPER(TRIM(COALESCE(m.status,"")))<>"C" THEN m.billamt ELSE 0 END) as total_amt')
            )
            ->whereBetween('m.tdate', [$from, $to])
            ->whereRaw("UPPER(TRIM(COALESCE(m.status,''))) <> 'X'")
            ->groupBy('m.tdate', DB::raw('TRIM(COALESCE(m.counter,""))'))
            ->orderBy('m.tdate')
            ->get();

        return response()->json([
            'ok'    => true,
            'from'  => $from, 'to' => $to,
            'rows'  => $rows,
            'grand' => $grand,
            'daily' => $daily,
        ]);
    }

    /* ══════════════════════════════════════════════════════════════
       4. INCHARGE REPORT
       ══════════════════════════════════════════════════════════════ */
    public function inchargeReport(Request $request): JsonResponse
    {
        if (!$this->guard($request)) return response()->json(['ok' => false], 401);
        $this->relaxGroupBy();

        [$from, $to] = $this->dateRange($request);

        $incharges = $this->hasTable('incharge')
            ? DB::table('incharge')->select('code', 'name')->orderBy('name')->get()->keyBy('code')
            : collect();

        $rows = DB::table('salesm as m')
            ->select(
                DB::raw('TRIM(COALESCE(m.ic,"")) as ic_code'),
                DB::raw('COUNT(*) as total_bills'),
                DB::raw('SUM(CASE WHEN UPPER(TRIM(COALESCE(m.status,"")))<>"C" THEN 1 ELSE 0 END) as active_bills'),
                DB::raw('SUM(CASE WHEN UPPER(TRIM(COALESCE(m.status,"")))<>"C" THEN m.billamt ELSE 0 END) as total_amt'),
                DB::raw('SUM(CASE WHEN UPPER(TRIM(COALESCE(m.status,"")))<>"C" THEN m.netamt ELSE 0 END) as net_amt'),
                DB::raw('SUM(CASE WHEN UPPER(TRIM(COALESCE(m.status,"")))<>"C" THEN m.discount ELSE 0 END) as total_disc'),
                DB::raw('AVG(CASE WHEN UPPER(TRIM(COALESCE(m.status,"")))<>"C" THEN m.billamt ELSE NULL END) as avg_bill'),
                DB::raw('MAX(CASE WHEN UPPER(TRIM(COALESCE(m.status,"")))<>"C" THEN m.billamt ELSE NULL END) as max_bill')
            )
            ->whereBetween('m.tdate', [$from, $to])
            ->whereRaw("UPPER(TRIM(COALESCE(m.status,''))) <> 'X'")
            ->groupBy(DB::raw('TRIM(COALESCE(m.ic,""))'))
            ->orderByDesc('total_amt')
            ->get()
            ->map(function($r) use ($incharges) {
                $r->ic_name = isset($incharges[$r->ic_code])
                    ? $incharges[$r->ic_code]->name
                    : ($r->ic_code ?: 'No Incharge');
                return $r;
            });

        $grand = [
            'total_bills'  => $rows->sum('total_bills'),
            'active_bills' => $rows->sum('active_bills'),
            'total_amt'    => $rows->sum('total_amt'),
            'net_amt'      => $rows->sum('net_amt'),
            'total_disc'   => $rows->sum('total_disc'),
        ];

        return response()->json([
            'ok'    => true,
            'from'  => $from, 'to' => $to,
            'rows'  => $rows,
            'grand' => $grand,
            'incharges' => $incharges->values(),
        ]);
    }

    /* ══════════════════════════════════════════════════════════════
       5. COMMISSION REPORT
       ══════════════════════════════════════════════════════════════ */
    public function commissionReport(Request $request): JsonResponse
    {
        if (!$this->guard($request)) return response()->json(['ok' => false], 401);
        $this->relaxGroupBy();

        [$from, $to] = $this->dateRange($request);
        $smcode = trim($request->query('smcode', ''));

        $q = DB::table('salesm as m')
            ->join('sman as s', DB::raw('TRIM(s.code)'), '=', DB::raw('TRIM(m.smcode)'))
            ->select(
                DB::raw('TRIM(m.smcode) as smcode'),
                DB::raw('TRIM(s.name) as smname'),
                DB::raw('COALESCE(s.comn, 0) as comn_pct'),
                'm.billno', 'm.tdate',
                DB::raw('TRIM(m.custname) as custname'),
                'm.billamt',
                DB::raw('m.billamt * COALESCE(s.comn,0) / 100 as commission'),
                DB::raw("CASE WHEN UPPER(TRIM(m.status))='C' THEN 'Cancelled' ELSE 'Active' END as status")
            )
            ->whereBetween('m.tdate', [$from, $to])
            ->whereRaw("UPPER(TRIM(COALESCE(m.status,''))) <> 'X'")
            ->whereRaw("UPPER(TRIM(COALESCE(m.status,''))) <> 'C'");

        if ($smcode !== '') {
            $q->whereRaw('TRIM(m.smcode) = ?', [$smcode]);
        }

        $rows = $q->orderBy('m.tdate')->orderBy('smcode')->get();

        // Group by salesman
        $grouped = [];
        foreach ($rows as $r) {
            $key = $r->smcode;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'smcode'     => $r->smcode,
                    'smname'     => $r->smname,
                    'comn_pct'   => $r->comn_pct,
                    'bills'      => [],
                    'total_amt'  => 0,
                    'total_comm' => 0,
                    'bill_count' => 0,
                ];
            }
            $grouped[$key]['bills'][]      = $r;
            $grouped[$key]['total_amt']   += (float)$r->billamt;
            $grouped[$key]['total_comm']  += (float)$r->commission;
            $grouped[$key]['bill_count']++;
        }

        $grandAmt  = array_sum(array_column($grouped, 'total_amt'));
        $grandComm = array_sum(array_column($grouped, 'total_comm'));

        return response()->json([
            'ok'        => true,
            'from'      => $from, 'to' => $to,
            'grouped'   => array_values($grouped),
            'grand'     => compact('grandAmt','grandComm'),
            'sman_list' => $this->smanList(),
        ]);
    }

    /* ══════════════════════════════════════════════════════════════
       6. STAFF PERFORMANCE DASHBOARD  — comparison
       ══════════════════════════════════════════════════════════════ */
    public function performance(Request $request): JsonResponse
    {
        if (!$this->guard($request)) return response()->json(['ok' => false], 401);
        $this->relaxGroupBy();

        [$from, $to] = $this->dateRange($request);

        // Monthly trend per salesman (last 6 months)
        $trend = DB::table('salesm as m')
            ->leftJoin('sman as s', DB::raw('TRIM(s.code)'), '=', DB::raw('TRIM(m.smcode)'))
            ->select(
                DB::raw("DATE_FORMAT(m.tdate,'%Y-%m') as month"),
                DB::raw('TRIM(m.smcode) as smcode'),
                DB::raw('COALESCE(MAX(TRIM(s.name)), TRIM(m.smcode), "—") as smname'),
                DB::raw('SUM(CASE WHEN UPPER(TRIM(COALESCE(m.status,"")))<>"C" THEN m.billamt ELSE 0 END) as total_amt'),
                DB::raw('COUNT(CASE WHEN UPPER(TRIM(COALESCE(m.status,"")))<>"C" THEN 1 END) as bill_count')
            )
            ->where('m.tdate', '>=', date('Y-m-d', strtotime('-6 months')))
            ->whereRaw("UPPER(TRIM(COALESCE(m.status,''))) <> 'X'")
            ->groupBy(DB::raw("DATE_FORMAT(m.tdate,'%Y-%m')"), DB::raw('TRIM(m.smcode)'))
            ->orderBy('month')
            ->get();

        // Period summary
        $summary = DB::table('salesm as m')
            ->leftJoin('sman as s', DB::raw('TRIM(s.code)'), '=', DB::raw('TRIM(m.smcode)'))
            ->select(
                DB::raw('TRIM(m.smcode) as smcode'),
                DB::raw('COALESCE(MAX(TRIM(s.name)), TRIM(m.smcode), "No Salesman") as smname'),
                DB::raw('MAX(COALESCE(s.comn, 0)) as comn_pct'),
                DB::raw('SUM(CASE WHEN UPPER(TRIM(COALESCE(m.status,"")))<>"C" THEN 1 ELSE 0 END) as active_bills'),
                DB::raw('SUM(CASE WHEN UPPER(TRIM(COALESCE(m.status,"")))<>"C" THEN m.billamt ELSE 0 END) as total_amt'),
                DB::raw('SUM(CASE WHEN UPPER(TRIM(COALESCE(m.status,"")))<>"C" THEN m.discount ELSE 0 END) as total_disc'),
                DB::raw('AVG(CASE WHEN UPPER(TRIM(COALESCE(m.status,"")))<>"C" THEN m.billamt ELSE NULL END) as avg_bill'),
                DB::raw('MAX(CASE WHEN UPPER(TRIM(COALESCE(m.status,"")))<>"C" THEN m.billamt ELSE NULL END) as max_bill'),
                DB::raw('COUNT(DISTINCT DATE(m.tdate)) as active_days'),
                DB::raw('SUM(CASE WHEN UPPER(TRIM(COALESCE(m.status,"")))<>"C" THEN m.billamt ELSE 0 END)*MAX(COALESCE(s.comn,0))/100 as commission')
            )
            ->whereBetween('m.tdate', [$from, $to])
            ->whereRaw("UPPER(TRIM(COALESCE(m.status,''))) <> 'X'")
            ->groupBy(DB::raw('TRIM(m.smcode)'))
            ->orderByDesc('total_amt')
            ->get();

        // Top performer
        $top = $summary->sortByDesc('total_amt')->first();

        return response()->json([
            'ok'      => true,
            'from'    => $from, 'to' => $to,
            'summary' => $summary,
            'trend'   => $trend,
            'top'     => $top,
        ]);
    }

    /* ══════════════════════════════════════════════════════════════
       7. TERM SUMMARY — month-wise salesman breakdown
       ══════════════════════════════════════════════════════════════ */
    public function termSummary(Request $request): JsonResponse
    {
        if (!$this->guard($request)) return response()->json(['ok' => false], 401);
        $this->relaxGroupBy();

        [$from, $to] = $this->dateRange($request);

        $rows = DB::table('salesm as m')
            ->leftJoin('sman as s', DB::raw('TRIM(s.code)'), '=', DB::raw('TRIM(m.smcode)'))
            ->select(
                DB::raw("DATE_FORMAT(m.tdate,'%Y-%m') as month"),
                DB::raw("DATE_FORMAT(m.tdate,'%b %Y') as month_label"),
                DB::raw('TRIM(m.smcode) as smcode'),
                DB::raw('COALESCE(MAX(TRIM(s.name)), TRIM(m.smcode), "No Salesman") as smname'),
                DB::raw('COUNT(CASE WHEN UPPER(TRIM(COALESCE(m.status,"")))<>"C" THEN 1 END) as active_bills'),
                DB::raw('SUM(CASE WHEN UPPER(TRIM(COALESCE(m.status,"")))<>"C" THEN m.billamt ELSE 0 END) as total_amt'),
                DB::raw('SUM(CASE WHEN UPPER(TRIM(COALESCE(m.status,"")))<>"C" THEN m.discount ELSE 0 END) as total_disc'),
                DB::raw('SUM(CASE WHEN UPPER(TRIM(COALESCE(m.status,"")))<>"C" THEN m.netamt ELSE 0 END) as net_amt'),
                DB::raw('AVG(CASE WHEN UPPER(TRIM(COALESCE(m.status,"")))<>"C" THEN m.billamt ELSE NULL END) as avg_bill'),
                DB::raw('SUM(CASE WHEN UPPER(TRIM(COALESCE(m.status,"")))<>"C" THEN m.billamt ELSE 0 END)*MAX(COALESCE(s.comn,0))/100 as commission')
            )
            ->whereBetween('m.tdate', [$from, $to])
            ->whereRaw("UPPER(TRIM(COALESCE(m.status,''))) <> 'X'")
            ->groupBy(DB::raw("DATE_FORMAT(m.tdate,'%Y-%m')"), DB::raw('TRIM(m.smcode)'))
            ->orderBy('month')
            ->orderByDesc('total_amt')
            ->get();

        $months = $rows->pluck('month_label', 'month')->unique()->values();
        $grand  = [
            'total_amt'   => $rows->sum('total_amt'),
            'total_disc'  => $rows->sum('total_disc'),
            'net_amt'     => $rows->sum('net_amt'),
            'active_bills'=> $rows->sum('active_bills'),
            'commission'  => $rows->sum('commission'),
        ];

        return response()->json([
            'ok'     => true,
            'from'   => $from, 'to' => $to,
            'rows'   => $rows,
            'months' => $months,
            'grand'  => $grand,
        ]);
    }

    /* ══════════════════════════════════════════════════════════════
       8. ATTENDANCE REPORT — staffcheckin table
       ══════════════════════════════════════════════════════════════ */
    public function attendanceReport(Request $request): JsonResponse
    {
        if (!$this->guard($request)) return response()->json(['ok' => false], 401);
        $this->relaxGroupBy();

        [$from, $to] = $this->dateRange($request);
        $staff = trim($request->query('staff', ''));

        $sman = DB::table('sman')->select('code', 'name')->orderBy('name')->get()->keyBy('code');

        $q = DB::table('staffcheckin')
            ->select(
                DB::raw('TRIM(code) as code'),
                'tdate',
                DB::raw('MIN(ttime) as in_time'),
                DB::raw('MAX(ttime) as out_time'),
                DB::raw('COUNT(*) as punches'),
                DB::raw('TRIM(MAX(stat)) as stat')
            )
            ->whereBetween('tdate', [$from, $to]);

        if ($staff !== '') $q->whereRaw('TRIM(code) = ?', [$staff]);

        $rows = $q->groupBy(DB::raw('TRIM(code)'), 'tdate')
            ->orderBy('tdate')
            ->orderBy('code')
            ->get()
            ->map(function($r) use ($sman) {
                $r->name = $sman[$r->code]->name ?? $r->code;
                return $r;
            });

        // Summary per staff
        $summary = $rows->groupBy('code')->map(function($grp, $code) use ($sman) {
            return [
                'code'      => $code,
                'name'      => $sman[$code]->name ?? $code,
                'total_days'=> $grp->count(),
                'present'   => $grp->where('stat', 'P')->count() + $grp->where('stat', '')->count(),
                'absent'    => $grp->where('stat', 'A')->count(),
                'late'      => $grp->where('stat', 'L')->count(),
            ];
        })->values();

        return response()->json([
            'ok'      => true,
            'from'    => $from, 'to' => $to,
            'rows'    => $rows,
            'summary' => $summary,
            'sman'    => $sman->values(),
        ]);
    }

    /* ══════════════════════════════════════════════════════════════
       9. LEAVE REPORT — staffleave table
       ══════════════════════════════════════════════════════════════ */
    public function leaveReport(Request $request): JsonResponse
    {
        if (!$this->guard($request)) return response()->json(['ok' => false], 401);
        $this->relaxGroupBy();

        [$from, $to] = $this->dateRange($request);
        $staff = trim($request->query('staff', ''));

        $sman = DB::table('sman')->select('code', 'name')->orderBy('name')->get()->keyBy('code');

        $q = DB::table('staffleave')
            ->select('tdate', DB::raw('TRIM(staff) as staff'), 'leave', 'ldays', 'reason', 'note', 'plusdays')
            ->whereBetween('tdate', [$from, $to]);

        if ($staff !== '') $q->whereRaw('TRIM(staff) = ?', [$staff]);

        $rows = $q->orderBy('tdate')->orderBy('staff')->get()
            ->map(function($r) use ($sman) {
                $r->name = $sman[$r->staff]->name ?? $r->staff;
                return $r;
            });

        // Summary per staff
        $summary = $rows->groupBy('staff')->map(function($grp, $code) use ($sman) {
            return [
                'code'      => $code,
                'name'      => $sman[$code]->name ?? $code,
                'total_leaves'=> $grp->sum('ldays'),
                'plus_days'   => $grp->sum('plusdays'),
                'net_leaves'  => $grp->sum('ldays') - $grp->sum('plusdays'),
                'entries'     => $grp->count(),
            ];
        })->values();

        $grand = [
            'total_leaves' => $rows->sum('ldays'),
            'plus_days'    => $rows->sum('plusdays'),
        ];

        return response()->json([
            'ok'      => true,
            'from'    => $from, 'to' => $to,
            'rows'    => $rows,
            'summary' => $summary,
            'grand'   => $grand,
            'sman'    => $sman->values(),
        ]);
    }

    /* ══════════════════════════════════════════════════════════════
       10. STAFF LOG REPORT — staff_log table
       ══════════════════════════════════════════════════════════════ */
    public function staffLog(Request $request): JsonResponse
    {
        if (!$this->guard($request)) return response()->json(['ok' => false], 401);
        $this->relaxGroupBy();

        [$from, $to] = $this->dateRange($request);
        $staff = trim($request->query('staff', ''));

        $sman = DB::table('sman')->select('code', 'name')->orderBy('name')->get()->keyBy('code');

        $q = DB::table('staff_log')
            ->select(DB::raw('TRIM(scode) as scode'), 'tdate', 'ttime', 'status', 'idno')
            ->whereBetween('tdate', [$from, $to]);

        if ($staff !== '') $q->whereRaw('TRIM(scode) = ?', [$staff]);

        $rows = $q->orderBy('tdate')->orderBy('ttime')->get()
            ->map(function($r) use ($sman) {
                $r->name = $sman[$r->scode]->name ?? $r->scode;
                return $r;
            });

        $summary = $rows->groupBy('scode')->map(function($grp, $code) use ($sman) {
            return [
                'code'    => $code,
                'name'    => $sman[$code]->name ?? $code,
                'entries' => $grp->count(),
                'login'   => $grp->where('status', 'L')->count(),
                'logout'  => $grp->where('status', 'O')->count(),
            ];
        })->values();

        return response()->json([
            'ok'      => true,
            'from'    => $from, 'to' => $to,
            'rows'    => $rows,
            'summary' => $summary,
            'sman'    => $sman->values(),
        ]);
    }

    /* ══════════════════════════════════════════════════════════════
       11. STAFF LEDGER — attendance day-wise ledger
       ══════════════════════════════════════════════════════════════ */
    public function ledger(Request $request): JsonResponse
    {
        if (!$this->guard($request)) return response()->json(['ok' => false], 401);
        $this->relaxGroupBy();

        [$from, $to] = $this->dateRange($request);
        $staff = trim($request->query('staff', ''));

        $sman = DB::table('sman')->select('code', 'name', 'cat')->orderBy('name')->get();

        // Attendance from staffcheckin
        $attQ = DB::table('staffcheckin')
            ->select(DB::raw('TRIM(code) as code'), 'tdate',
                DB::raw('MIN(ttime) as in_time'), DB::raw('MAX(ttime) as out_time'), DB::raw('TRIM(MAX(stat)) as stat'))
            ->whereBetween('tdate', [$from, $to])
            ->groupBy(DB::raw('TRIM(code)'), 'tdate');

        if ($staff !== '') $attQ->whereRaw('TRIM(code) = ?', [$staff]);
        $att = $attQ->get();

        // Leaves from staffleave
        $leaveQ = DB::table('staffleave')
            ->select(DB::raw('TRIM(staff) as code'), 'tdate', 'leave', 'ldays', 'reason')
            ->whereBetween('tdate', [$from, $to]);
        if ($staff !== '') $leaveQ->whereRaw('TRIM(staff) = ?', [$staff]);
        $leaves = $leaveQ->get();

        $smanMap = $sman->keyBy('code');

        // Merge into ledger
        $ledger = [];
        foreach ($att as $a) {
            $key = $a->code . '_' . $a->tdate;
            $ledger[$key] = [
                'code'    => $a->code,
                'name'    => $smanMap[$a->code]->name ?? $a->code,
                'tdate'   => $a->tdate,
                'in_time' => $a->in_time,
                'out_time'=> $a->out_time,
                'status'  => $a->stat ?: 'P',
                'leave'   => '',
                'ldays'   => 0,
            ];
        }
        foreach ($leaves as $l) {
            $key = $l->code . '_' . $l->tdate;
            if (!isset($ledger[$key])) {
                $ledger[$key] = [
                    'code'    => $l->code,
                    'name'    => $smanMap[$l->code]->name ?? $l->code,
                    'tdate'   => $l->tdate,
                    'in_time' => '',
                    'out_time'=> '',
                    'status'  => 'L',
                    'leave'   => $l->leave,
                    'ldays'   => $l->ldays,
                ];
            } else {
                $ledger[$key]['leave'] = $l->leave;
                $ledger[$key]['ldays'] = $l->ldays;
            }
        }
        usort($ledger, fn($a,$b) => strcmp($a['tdate'].$a['code'], $b['tdate'].$b['code']));

        return response()->json([
            'ok'    => true,
            'from'  => $from, 'to' => $to,
            'rows'  => array_values($ledger),
            'sman'  => $sman->values(),
        ]);
    }

    /* ══════════════════════════════════════════════════════════════
       12. WEIGHT TRANSACTION REPORT — staffwgtm / staffwgtd
       ══════════════════════════════════════════════════════════════ */
    public function wgtTrans(Request $request): JsonResponse
    {
        if (!$this->guard($request)) return response()->json(['ok' => false], 401);
        $this->relaxGroupBy();

        [$from, $to] = $this->dateRange($request);
        $staff = trim($request->query('staff', ''));

        $sman = DB::table('sman')->select('code', 'name')->orderBy('name')->get()->keyBy('code');

        $q = DB::table('staffwgtm as m')
            ->join('staffwgtd as d', 'd.slno', '=', 'm.slno')
            ->leftJoin('items as i', DB::raw('TRIM(i.code)'), '=', DB::raw('TRIM(d.code)'))
            ->select(
                'm.tdate', 'm.docno',
                DB::raw('TRIM(m.staff) as staff'),
                'm.ttype', 'm.route',
                DB::raw('TRIM(d.code) as item_code'),
                DB::raw('COALESCE(TRIM(i.name), TRIM(d.code)) as item_name'),
                'd.qty', 'd.weight', 'd.stwgt',
                DB::raw('d.weight - COALESCE(d.stwgt,0) as net_wgt'),
                'd.rate', 'd.rtype'
            )
            ->whereBetween('m.tdate', [$from, $to]);

        if ($staff !== '') $q->whereRaw('TRIM(m.staff) = ?', [$staff]);

        $rows = $q->orderBy('m.tdate')->orderBy('m.slno')->get()
            ->map(function($r) use ($sman) {
                $r->staff_name = $sman[$r->staff]->name ?? $r->staff;
                return $r;
            });

        $summary = $rows->groupBy('staff')->map(function($grp, $code) use ($sman) {
            return [
                'code'       => $code,
                'name'       => $sman[$code]->name ?? $code,
                'total_wgt'  => $grp->sum('weight'),
                'total_net'  => $grp->sum('net_wgt'),
                'total_qty'  => $grp->sum('qty'),
                'entries'    => $grp->count(),
            ];
        })->values();

        $grand = [
            'total_wgt' => $rows->sum('weight'),
            'total_net' => $rows->sum('net_wgt'),
            'total_qty' => $rows->sum('qty'),
        ];

        return response()->json([
            'ok'      => true,
            'from'    => $from, 'to' => $to,
            'rows'    => $rows,
            'summary' => $summary,
            'grand'   => $grand,
            'sman'    => $sman->values(),
        ]);
    }
}
