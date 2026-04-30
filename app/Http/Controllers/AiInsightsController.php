<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AiInsightsController extends Controller
{
    private function guard(Request $request): bool
    {
        return $request->session()->has('user_code');
    }

    public function index(Request $request)
    {
        if (!$this->guard($request)) {
            return redirect('/login');
        }

        return view('ai-insights.index', [
            'title' => 'AI Workspace - Smart Operations',
        ]);
    }

    public function fraudAlerts(Request $request): JsonResponse
    {
        if (!$this->guard($request)) {
            return response()->json(['ok' => false], 401);
        }

        $alerts = [];
        $thresholdDisc = (float) $request->query('disc_pct', 20);
        $thresholdAmt = (float) $request->query('big_amt', 50000);
        $days = (int) $request->query('days', 30);
        $since = now()->subDays($days)->toDateString();

        if ($this->hasColumns('salesm', ['billno', 'tdate', 'discount', 'billamt'])) {
            $rows = DB::table('salesm')
                ->select(
                    'billno',
                    'tdate',
                    DB::raw('TRIM(custcode) as custcode'),
                    DB::raw('TRIM(custname) as custname'),
                    'discount',
                    'billamt',
                    DB::raw('ROUND(discount*100/NULLIF(billamt,0),2) as disc_pct')
                )
                ->where('tdate', '>=', $since)
                ->whereRaw('billamt > 0')
                ->whereRaw('discount*100/NULLIF(billamt,0) >= ?', [$thresholdDisc])
                ->orderByDesc('disc_pct')
                ->limit(50)
                ->get();

            foreach ($rows as $row) {
                $alerts[] = [
                    'type' => 'discount',
                    'severity' => $row->disc_pct >= 40 ? 'high' : 'medium',
                    'icon' => 'fa-tag',
                    'title' => "High discount {$row->disc_pct}% on bill {$row->billno}",
                    'detail' => "Customer {$row->custname} ({$row->custcode}) | Discount {$this->money($row->discount)} on bill {$this->money($row->billamt)}",
                    'date' => $row->tdate,
                ];
            }
        }

        if ($this->hasColumns('salesm', ['billno', 'tdate', 'billamt'])) {
            $rows = DB::table('salesm')
                ->select('billno', 'tdate', DB::raw('TRIM(custname) as custname'), 'billamt')
                ->where('tdate', '>=', $since)
                ->where('billamt', '>=', $thresholdAmt)
                ->orderByDesc('billamt')
                ->limit(20)
                ->get();

            foreach ($rows as $row) {
                $alerts[] = [
                    'type' => 'large_bill',
                    'severity' => $row->billamt >= $thresholdAmt * 3 ? 'high' : 'low',
                    'icon' => 'fa-money-bill-wave',
                    'title' => 'Large transaction ' . $this->money($row->billamt),
                    'detail' => "Bill {$row->billno} | Customer {$row->custname}",
                    'date' => $row->tdate,
                ];
            }
        }

        usort($alerts, function (array $a, array $b) {
            $sev = ['high' => 0, 'medium' => 1, 'low' => 2];
            if (($sev[$a['severity']] ?? 9) !== ($sev[$b['severity']] ?? 9)) {
                return ($sev[$a['severity']] ?? 9) <=> ($sev[$b['severity']] ?? 9);
            }

            return strcmp((string) ($b['date'] ?? ''), (string) ($a['date'] ?? ''));
        });

        return response()->json([
            'ok' => true,
            'alerts' => $alerts,
            'count' => count($alerts),
            'high' => count(array_filter($alerts, fn (array $row) => $row['severity'] === 'high')),
            'medium' => count(array_filter($alerts, fn (array $row) => $row['severity'] === 'medium')),
            'low' => count(array_filter($alerts, fn (array $row) => $row['severity'] === 'low')),
        ]);
    }

    public function customerAnalytics(Request $request): JsonResponse
    {
        if (!$this->guard($request)) {
            return response()->json(['ok' => false], 401);
        }

        $days = (int) $request->query('days', 30);
        $since = now()->subDays($days)->toDateString();
        $data = [];
        $insights = [];

        if ($this->hasColumns('salesd', ['slno', 'code', 'name', 'qty', 'amount']) && $this->hasColumns('salesm', ['slno', 'tdate'])) {
            $data['top_items'] = DB::table('salesd as d')
                ->join('salesm as m', 'm.slno', '=', 'd.slno')
                ->select(
                    DB::raw('TRIM(d.code) as code'),
                    DB::raw('MAX(TRIM(d.name)) as name'),
                    DB::raw('SUM(d.qty) as total_qty'),
                    DB::raw('SUM(d.amount) as total_amt'),
                    DB::raw('COUNT(DISTINCT m.slno) as bill_count')
                )
                ->where('m.tdate', '>=', $since)
                ->groupBy(DB::raw('TRIM(d.code)'))
                ->orderByDesc('total_qty')
                ->limit(10)
                ->get();
        }

        if ($this->hasColumns('salesm', ['tdate', 'billamt', 'custcode', 'custname'])) {
            $data['top_customers'] = DB::table('salesm')
                ->select(
                    DB::raw('TRIM(custcode) as custcode'),
                    DB::raw('MAX(TRIM(custname)) as custname'),
                    DB::raw('COUNT(*) as visits'),
                    DB::raw('SUM(billamt) as total_spent'),
                    DB::raw('AVG(billamt) as avg_bill'),
                    DB::raw('MAX(tdate) as last_visit')
                )
                ->where('tdate', '>=', $since)
                ->groupBy(DB::raw('TRIM(custcode)'))
                ->orderByDesc('total_spent')
                ->limit(10)
                ->get();

            $data['monthly'] = DB::table('salesm')
                ->select(
                    DB::raw("DATE_FORMAT(tdate,'%Y-%m') as month"),
                    DB::raw('COUNT(*) as bills'),
                    DB::raw('SUM(billamt) as total_amt'),
                    DB::raw('AVG(billamt) as avg_amt')
                )
                ->where('tdate', '>=', now()->subMonths(6)->toDateString())
                ->groupBy(DB::raw("DATE_FORMAT(tdate,'%Y-%m')"))
                ->orderBy('month')
                ->get();

            $data['weekday_sales'] = DB::table('salesm')
                ->select(
                    DB::raw('DAYNAME(tdate) as day_name'),
                    DB::raw('DAYOFWEEK(tdate) as day_num'),
                    DB::raw('COUNT(*) as bills'),
                    DB::raw('SUM(billamt) as total_amt')
                )
                ->where('tdate', '>=', $since)
                ->groupBy(DB::raw('DAYNAME(tdate)'), DB::raw('DAYOFWEEK(tdate)'))
                ->orderBy('day_num')
                ->get();
        }

        if (!empty($data['top_items']) && count($data['top_items']) > 0) {
            $top = $data['top_items'][0];
            $insights[] = "Best seller this period: {$top->name} ({$this->num($top->total_qty)} units)";
        }

        if (!empty($data['top_customers']) && count($data['top_customers']) > 0) {
            $topCustomer = $data['top_customers'][0];
            $insights[] = "Highest customer value: {$topCustomer->custname} with {$this->money($topCustomer->total_spent)} in {$topCustomer->visits} visits";
        }

        return response()->json(['ok' => true, 'data' => $data, 'insights' => $insights]);
    }

    public function inventoryPrediction(Request $request): JsonResponse
    {
        if (!$this->guard($request)) {
            return response()->json(['ok' => false], 401);
        }

        $warnDays = (int) $request->query('warn_days', 7);
        $items = [];

        if (!$this->hasColumns('salesd', ['slno', 'code', 'name', 'qty']) || !$this->hasColumns('salesm', ['slno', 'tdate']) || !$this->hasTable('items')) {
            return response()->json(['ok' => true, 'items' => [], 'summary' => ['critical' => 0, 'warning' => 0]]);
        }

        $dailySales = DB::table('salesd as d')
            ->join('salesm as m', 'm.slno', '=', 'd.slno')
            ->select(
                DB::raw('TRIM(d.code) as code'),
                DB::raw('MAX(TRIM(d.name)) as name'),
                DB::raw('SUM(d.qty) as sold_30'),
                DB::raw('SUM(d.qty)/30 as daily_rate')
            )
            ->where('m.tdate', '>=', now()->subDays(30)->toDateString())
            ->where('d.qty', '>', 0)
            ->groupBy(DB::raw('TRIM(d.code)'))
            ->having('daily_rate', '>', 0)
            ->get()
            ->keyBy('code');

        $stockCol = $this->firstExistingColumn('items', ['curstock', 'stock', 'qty']);
        if (!$stockCol) {
            return response()->json(['ok' => true, 'items' => [], 'summary' => ['critical' => 0, 'warning' => 0]]);
        }

        $stocks = DB::table('items')
            ->select(DB::raw('TRIM(code) as code'), DB::raw('TRIM(name) as name'), DB::raw($stockCol . ' as stock'))
            ->where($stockCol, '>', 0)
            ->get()
            ->keyBy('code');

        foreach ($stocks as $code => $item) {
            if (!isset($dailySales[$code])) {
                continue;
            }

            $rate = (float) $dailySales[$code]->daily_rate;
            if ($rate <= 0) {
                continue;
            }

            $stock = (float) $item->stock;
            $daysLeft = round($stock / $rate, 1);
            $status = $daysLeft <= 3 ? 'critical' : ($daysLeft <= $warnDays ? 'warning' : 'ok');

            if ($status !== 'ok') {
                $items[] = [
                    'code' => $code,
                    'name' => $item->name,
                    'stock' => $stock,
                    'daily_rate' => round($rate, 3),
                    'sold_30' => (float) $dailySales[$code]->sold_30,
                    'days_left' => $daysLeft,
                    'status' => $status,
                    'message' => $daysLeft <= 1 ? 'Out of stock today' : "Will run out in about {$daysLeft} days",
                ];
            }
        }

        usort($items, fn (array $a, array $b) => $a['days_left'] <=> $b['days_left']);

        return response()->json([
            'ok' => true,
            'items' => $items,
            'summary' => [
                'critical' => count(array_filter($items, fn (array $row) => $row['status'] === 'critical')),
                'warning' => count(array_filter($items, fn (array $row) => $row['status'] === 'warning')),
            ],
        ]);
    }

    public function businessInsights(Request $request): JsonResponse
    {
        if (!$this->guard($request)) {
            return response()->json(['ok' => false], 401);
        }

        $days = max(7, (int) $request->query('days', 30));
        $dateCol = $this->firstExistingColumn('daybook', ['billdate', 'tdate']);
        $summary = [
            'sales' => 0,
            'purchases' => 0,
            'collections' => 0,
            'outstanding' => 0,
            'gross_spread' => 0,
            'top_customer_share' => 0,
        ];
        $narratives = [];
        $topCustomers = [];

        if ($dateCol && $this->hasColumns('daybook', ['billtype', 'amount'])) {
            $start = now()->subDays($days)->toDateString();
            $prevStart = now()->subDays($days * 2)->toDateString();
            $prevEnd = now()->subDays($days + 1)->toDateString();

            $summary['sales'] = (float) DB::table('daybook')->where('billtype', 'S')->whereDate($dateCol, '>=', $start)->sum('amount');
            $summary['purchases'] = (float) DB::table('daybook')->where('billtype', 'P')->whereDate($dateCol, '>=', $start)->sum('amount');
            $summary['collections'] = (float) DB::table('daybook')->where('billtype', 'R')->whereDate($dateCol, '>=', $start)->sum('amount');
            $summary['gross_spread'] = $summary['sales'] - $summary['purchases'];

            $prevSales = (float) DB::table('daybook')->where('billtype', 'S')->whereBetween(DB::raw("DATE($dateCol)"), [$prevStart, $prevEnd])->sum('amount');
            $prevPurchases = (float) DB::table('daybook')->where('billtype', 'P')->whereBetween(DB::raw("DATE($dateCol)"), [$prevStart, $prevEnd])->sum('amount');
            $spreadMove = $summary['gross_spread'] - ($prevSales - $prevPurchases);

            $narratives[] = 'Sales for the selected period are ' . $this->money($summary['sales']) . ', while purchases are ' . $this->money($summary['purchases']) . '.';
            $narratives[] = 'Estimated gross spread is ' . $this->money($summary['gross_spread']) . ', which is ' . ($spreadMove >= 0 ? 'up' : 'down') . ' by ' . $this->money(abs($spreadMove)) . ' versus the previous period.';

            if ($summary['collections'] > 0) {
                $narratives[] = 'Collections recorded in the same period are ' . $this->money($summary['collections']) . '.';
            } else {
                $narratives[] = 'No receipt total was detected from the standard daybook receipt code in this period.';
            }
        }

        if ($this->hasTable('clients') && Schema::hasColumn('clients', 'balance')) {
            $summary['outstanding'] = (float) DB::table('clients')->where('ctype', 'C')->sum(DB::raw('ABS(balance)'));
            $narratives[] = 'Customer outstanding currently stands at ' . $this->money($summary['outstanding']) . '.';
        }

        if ($this->hasColumns('salesm', ['custcode', 'custname', 'billamt', 'tdate'])) {
            $salesStart = now()->subDays($days)->toDateString();
            $topCustomers = DB::table('salesm')
                ->select(
                    DB::raw('TRIM(custcode) as custcode'),
                    DB::raw('MAX(TRIM(custname)) as custname'),
                    DB::raw('SUM(billamt) as total_spent'),
                    DB::raw('COUNT(*) as bills')
                )
                ->where('tdate', '>=', $salesStart)
                ->whereNotNull('custcode')
                ->groupBy(DB::raw('TRIM(custcode)'))
                ->orderByDesc('total_spent')
                ->limit(8)
                ->get()
                ->map(fn ($row) => [
                    'custcode' => $row->custcode,
                    'custname' => $row->custname,
                    'total_spent' => (float) $row->total_spent,
                    'bills' => (int) $row->bills,
                ])
                ->all();

            $top3 = array_slice($topCustomers, 0, 3);
            $top3Total = array_sum(array_map(fn (array $row) => $row['total_spent'], $top3));
            $summary['top_customer_share'] = $summary['sales'] > 0 ? round(($top3Total / $summary['sales']) * 100, 2) : 0;

            if ($summary['top_customer_share'] > 0) {
                $narratives[] = 'Top customer concentration is ' . $summary['top_customer_share'] . '% of total sales from the top 3 customers.';
            }
        }

        return response()->json([
            'ok' => true,
            'summary' => $summary,
            'narratives' => $narratives,
            'top_customers' => $topCustomers,
        ]);
    }

    public function salesCashFlowForecast(Request $request): JsonResponse
    {
        if (!$this->guard($request)) {
            return response()->json(['ok' => false], 401);
        }

        $days = max(30, (int) $request->query('days', 90));
        $dateCol = $this->firstExistingColumn('daybook', ['billdate', 'tdate']);
        $forecast = [
            'next_sales' => 0,
            'next_collections' => 0,
            'overdue_pressure' => 0,
            'purchase_need' => 0,
        ];
        $narratives = [];
        $drivers = [];

        if ($dateCol && $this->hasColumns('daybook', ['billtype', 'amount'])) {
            $salesSeries = [];
            $purchaseSeries = [];
            $collectionSeries = [];

            for ($i = 2; $i >= 0; $i--) {
                $mStart = now()->startOfMonth()->subMonths($i)->toDateString();
                $mEnd = now()->startOfMonth()->subMonths($i)->endOfMonth()->toDateString();
                $label = date('M Y', strtotime($mStart));

                $sales = (float) DB::table('daybook')->where('billtype', 'S')->whereBetween(DB::raw("DATE($dateCol)"), [$mStart, $mEnd])->sum('amount');
                $purchase = (float) DB::table('daybook')->where('billtype', 'P')->whereBetween(DB::raw("DATE($dateCol)"), [$mStart, $mEnd])->sum('amount');
                $collection = (float) DB::table('daybook')->where('billtype', 'R')->whereBetween(DB::raw("DATE($dateCol)"), [$mStart, $mEnd])->sum('amount');

                $salesSeries[] = $sales;
                $purchaseSeries[] = $purchase;
                $collectionSeries[] = $collection;
                $drivers[] = [
                    'month' => $label,
                    'sales' => $sales,
                    'purchase' => $purchase,
                    'collections' => $collection,
                ];
            }

            $forecast['next_sales'] = round(array_sum($salesSeries) / max(1, count($salesSeries)), 2);
            $forecast['next_collections'] = round(array_sum($collectionSeries) / max(1, count($collectionSeries)), 2);
            $forecast['purchase_need'] = round(array_sum($purchaseSeries) / max(1, count($purchaseSeries)), 2);

            $narratives[] = 'Projected next-period sales are about ' . $this->money($forecast['next_sales']) . ' based on the recent monthly average.';
            $narratives[] = 'Projected collections are about ' . $this->money($forecast['next_collections']) . '.';
            $narratives[] = 'Expected purchase requirement is about ' . $this->money($forecast['purchase_need']) . ' if the current run-rate continues.';
        }

        if ($this->hasTable('clients') && Schema::hasColumn('clients', 'balance')) {
            $positiveOutstanding = (float) DB::table('clients')->where('ctype', 'C')->where('balance', '>', 0)->sum('balance');
            $forecast['overdue_pressure'] = $positiveOutstanding;
            $narratives[] = 'Overdue pressure is estimated from customer outstanding at ' . $this->money($forecast['overdue_pressure']) . '.';
        }

        return response()->json([
            'ok' => true,
            'forecast' => $forecast,
            'narratives' => $narratives,
            'drivers' => $drivers,
        ]);
    }

    public function salesAssistant(Request $request): JsonResponse
    {
        if (!$this->guard($request)) {
            return response()->json(['ok' => false], 401);
        }

        $days = max(7, (int) $request->query('days', 30));
        $customerCode = trim((string) $request->query('customer_code', ''));
        $since = now()->subDays($days)->toDateString();

        $response = [
            'ok' => true,
            'insights' => [],
            'top_items' => [],
            'cross_sell' => [],
            'discount_guardrails' => [],
            'customer_focus' => [],
        ];

        if ($this->hasColumns('salesd', ['slno', 'code', 'name', 'qty', 'amount']) && $this->hasColumns('salesm', ['slno', 'tdate'])) {
            $response['top_items'] = DB::table('salesd as d')
                ->join('salesm as m', 'm.slno', '=', 'd.slno')
                ->select(
                    DB::raw('TRIM(d.code) as code'),
                    DB::raw('MAX(TRIM(d.name)) as name'),
                    DB::raw('SUM(d.qty) as qty'),
                    DB::raw('SUM(d.amount) as amount')
                )
                ->where('m.tdate', '>=', $since)
                ->groupBy(DB::raw('TRIM(d.code)'))
                ->orderByDesc('qty')
                ->limit(8)
                ->get()
                ->map(fn ($row) => [
                    'code' => $row->code,
                    'name' => $row->name,
                    'qty' => (float) $row->qty,
                    'amount' => (float) $row->amount,
                ])
                ->all();

            $pairRows = DB::table('salesd as a')
                ->join('salesd as b', function ($join) {
                    $join->on('a.slno', '=', 'b.slno')
                        ->whereRaw('TRIM(a.code) < TRIM(b.code)');
                })
                ->join('salesm as m', 'm.slno', '=', 'a.slno')
                ->select(
                    DB::raw('TRIM(a.code) as primary_code'),
                    DB::raw('MAX(TRIM(a.name)) as primary_name'),
                    DB::raw('TRIM(b.code) as paired_code'),
                    DB::raw('MAX(TRIM(b.name)) as paired_name'),
                    DB::raw('COUNT(*) as together_count')
                )
                ->where('m.tdate', '>=', $since)
                ->groupBy(DB::raw('TRIM(a.code)'), DB::raw('TRIM(b.code)'))
                ->orderByDesc('together_count')
                ->limit(6)
                ->get();

            $response['cross_sell'] = $pairRows->map(fn ($row) => [
                'primary' => trim($row->primary_name ?: $row->primary_code),
                'paired' => trim($row->paired_name ?: $row->paired_code),
                'together_count' => (int) $row->together_count,
            ])->all();
        }

        if ($this->hasColumns('salesm', ['tdate', 'billamt', 'discount'])) {
            $avgDiscPct = (float) (DB::table('salesm')
                ->where('tdate', '>=', $since)
                ->whereRaw('billamt > 0')
                ->selectRaw('AVG(discount*100/NULLIF(billamt,0)) as pct')
                ->value('pct') ?? 0);

            $guardRows = DB::table('salesm')
                ->select(
                    'billno',
                    'tdate',
                    DB::raw('TRIM(custname) as custname'),
                    'billamt',
                    'discount',
                    DB::raw('ROUND(discount*100/NULLIF(billamt,0),2) as discount_pct')
                )
                ->where('tdate', '>=', $since)
                ->whereRaw('billamt > 0')
                ->whereRaw('discount*100/NULLIF(billamt,0) > ?', [max(5, $avgDiscPct + 5)])
                ->orderByDesc('discount_pct')
                ->limit(6)
                ->get();

            $response['discount_guardrails'] = $guardRows->map(fn ($row) => [
                'billno' => trim((string) $row->billno),
                'date' => (string) $row->tdate,
                'customer' => trim((string) $row->custname),
                'billamt' => (float) $row->billamt,
                'discount' => (float) $row->discount,
                'discount_pct' => (float) $row->discount_pct,
            ])->all();

            $response['insights'][] = 'Average discount baseline for this period is ' . round($avgDiscPct, 2) . '%.';
        }

        if ($customerCode !== '' && $this->hasColumns('salesm', ['slno', 'tdate', 'custcode']) && $this->hasColumns('salesd', ['slno', 'code', 'name', 'qty'])) {
            $customerFocus = DB::table('salesd as d')
                ->join('salesm as m', 'm.slno', '=', 'd.slno')
                ->select(
                    DB::raw('TRIM(d.code) as code'),
                    DB::raw('MAX(TRIM(d.name)) as name'),
                    DB::raw('SUM(d.qty) as qty'),
                    DB::raw('COUNT(DISTINCT m.slno) as bills'),
                    DB::raw('MAX(m.tdate) as last_date')
                )
                ->whereRaw('TRIM(m.custcode) = ?', [$customerCode])
                ->groupBy(DB::raw('TRIM(d.code)'))
                ->orderByDesc('qty')
                ->limit(6)
                ->get();

            $response['customer_focus'] = $customerFocus->map(fn ($row) => [
                'code' => $row->code,
                'name' => $row->name,
                'qty' => (float) $row->qty,
                'bills' => (int) $row->bills,
                'last_date' => (string) $row->last_date,
            ])->all();

            if (!empty($response['customer_focus'])) {
                $response['insights'][] = "Customer {$customerCode} usually buys " . $response['customer_focus'][0]['name'] . '.';
            }
        }

        if (!empty($response['top_items'])) {
            $response['insights'][] = $response['top_items'][0]['name'] . ' is the strongest current sales suggestion.';
        }

        if (!empty($response['cross_sell'])) {
            $firstPair = $response['cross_sell'][0];
            $response['insights'][] = "{$firstPair['primary']} and {$firstPair['paired']} are frequently billed together.";
        }

        return response()->json($response);
    }

    public function orderPredictions(Request $request): JsonResponse
    {
        if (!$this->guard($request)) {
            return response()->json(['ok' => false], 401);
        }

        if (!$this->hasTable('orderm')) {
            return response()->json(['ok' => true, 'summary' => [], 'orders' => [], 'insights' => []]);
        }

        $days = max(30, (int) $request->query('days', 90));
        $since = now()->subDays($days)->toDateString();
        $dueCol = $this->firstExistingColumn('orderm', ['duedate', 'duedate_org']);

        $rows = DB::table('orderm')
            ->select(
                'slno',
                'ordno',
                'tdate',
                DB::raw($dueCol ? "{$dueCol} as due_date" : 'NULL as due_date'),
                DB::raw('TRIM(custcode) as custcode'),
                DB::raw('TRIM(custname) as custname'),
                DB::raw('COALESCE(billamt,0) as billamt'),
                DB::raw('COALESCE(advance,0) as advance'),
                DB::raw('COALESCE(status,0) as status')
            )
            ->where('tdate', '>=', $since)
            ->orderByDesc('tdate')
            ->limit(200)
            ->get();

        $avgLeadDays = 7.0;
        if ($dueCol) {
            $lead = DB::table('orderm')
                ->whereNotNull('tdate')
                ->whereNotNull($dueCol)
                ->selectRaw("AVG(GREATEST(DATEDIFF({$dueCol}, tdate), 0)) as avg_lead")
                ->value('avg_lead');
            if ($lead !== null) {
                $avgLeadDays = max(1, round((float) $lead, 1));
            }
        }

        $itemCounts = [];
        if ($this->hasColumns('orderd', ['slno'])) {
            $itemCounts = DB::table('orderd')
                ->select('slno', DB::raw('COUNT(*) as item_count'))
                ->groupBy('slno')
                ->pluck('item_count', 'slno')
                ->all();
        }

        $orders = [];
        foreach ($rows as $row) {
            if ((int) $row->status === 2) {
                continue;
            }

            $ageDays = $this->daysBetween($row->tdate, now()->toDateString());
            $predicted = $row->due_date ?: date('Y-m-d', strtotime((string) $row->tdate . ' +' . (int) ceil($avgLeadDays) . ' days'));
            $dueInDays = $this->daysBetween(now()->toDateString(), $predicted);
            $risk = $dueInDays < 0 ? 'high' : ($dueInDays <= 2 ? 'medium' : 'low');

            $orders[] = [
                'ordno' => trim((string) $row->ordno),
                'customer' => trim((string) $row->custname),
                'customer_code' => trim((string) $row->custcode),
                'order_date' => (string) $row->tdate,
                'due_date' => $predicted,
                'billamt' => (float) $row->billamt,
                'advance' => (float) $row->advance,
                'age_days' => $ageDays,
                'due_in_days' => $dueInDays,
                'risk' => $risk,
                'item_count' => (int) ($itemCounts[$row->slno] ?? 0),
                'status_label' => $dueInDays < 0 ? 'Likely delayed' : ($dueInDays <= 2 ? 'Watch closely' : 'On track'),
            ];
        }

        usort($orders, function (array $a, array $b) {
            $sev = ['high' => 0, 'medium' => 1, 'low' => 2];
            if ($sev[$a['risk']] !== $sev[$b['risk']]) {
                return $sev[$a['risk']] <=> $sev[$b['risk']];
            }

            return $a['due_in_days'] <=> $b['due_in_days'];
        });

        $orders = array_slice($orders, 0, 20);

        return response()->json([
            'ok' => true,
            'summary' => [
                'avg_lead_days' => $avgLeadDays,
                'delayed' => count(array_filter($orders, fn (array $row) => $row['risk'] === 'high')),
                'watchlist' => count(array_filter($orders, fn (array $row) => $row['risk'] === 'medium')),
                'open_orders' => count($orders),
            ],
            'orders' => $orders,
            'insights' => [
                "Average order lead time is about {$avgLeadDays} days.",
                count(array_filter($orders, fn (array $row) => $row['risk'] === 'high')) . ' orders look delayed right now.',
            ],
        ]);
    }

    public function customerFollowup(Request $request): JsonResponse
    {
        if (!$this->guard($request)) {
            return response()->json(['ok' => false], 401);
        }

        $inactiveDays = max(30, (int) $request->query('inactive_days', 90));
        $cutoff = now()->subDays($inactiveDays)->toDateString();

        $segments = [];
        $birthdays = [];
        $insights = [];

        if ($this->hasColumns('salesm', ['custcode', 'custname', 'tdate', 'billamt'])) {
            $customerSales = DB::table('salesm')
                ->select(
                    DB::raw('TRIM(custcode) as custcode'),
                    DB::raw('MAX(TRIM(custname)) as custname'),
                    DB::raw('COUNT(*) as visits'),
                    DB::raw('SUM(billamt) as total_spent'),
                    DB::raw('AVG(billamt) as avg_bill'),
                    DB::raw('MAX(tdate) as last_visit')
                )
                ->whereNotNull('custcode')
                ->where('custcode', '<>', '')
                ->groupBy(DB::raw('TRIM(custcode)'))
                ->get();

            $segments = $customerSales->map(function ($row) use ($cutoff) {
                $segment = 'steady';
                if ($row->total_spent >= 100000 && $row->last_visit < $cutoff) {
                    $segment = 'high_value_inactive';
                } elseif ($row->visits >= 5) {
                    $segment = 'loyal';
                } elseif ($row->last_visit >= now()->subDays(30)->toDateString()) {
                    $segment = 'recent';
                }

                return [
                    'custcode' => $row->custcode,
                    'custname' => $row->custname,
                    'visits' => (int) $row->visits,
                    'total_spent' => (float) $row->total_spent,
                    'avg_bill' => (float) $row->avg_bill,
                    'last_visit' => (string) $row->last_visit,
                    'segment' => $segment,
                    'action' => match ($segment) {
                        'high_value_inactive' => 'Send comeback or personal follow-up',
                        'loyal' => 'Share premium previews and loyalty benefits',
                        'recent' => 'Offer matching item or exchange follow-up',
                        default => 'General campaign list',
                    },
                ];
            })
                ->sortByDesc('total_spent')
                ->take(15)
                ->values()
                ->all();

            $inactiveCount = count(array_filter($segments, fn (array $row) => $row['segment'] === 'high_value_inactive'));
            $insights[] = "{$inactiveCount} high-value customers are due for follow-up.";
        }

        if ($this->hasTable('clients')) {
            $dobCol = $this->firstExistingColumn('clients', ['dob', 'birthdate', 'birthday']);
            if ($dobCol) {
                $rows = DB::table('clients')
                    ->select(DB::raw('TRIM(code) as code'), DB::raw('TRIM(name) as name'), DB::raw($dobCol . ' as dob'))
                    ->whereNotNull($dobCol)
                    ->limit(200)
                    ->get();

                $birthdays = $rows->map(function ($row) {
                    if (!$row->dob) {
                        return null;
                    }
                    $monthDay = date('m-d', strtotime((string) $row->dob));
                    $thisYear = date('Y') . '-' . $monthDay;
                    $days = $this->daysBetween(now()->toDateString(), $thisYear);
                    if ($days < 0) {
                        $days = $this->daysBetween(now()->toDateString(), (date('Y') + 1) . '-' . $monthDay);
                    }

                    return [
                        'code' => $row->code,
                        'name' => $row->name,
                        'dob' => (string) $row->dob,
                        'days_left' => $days,
                    ];
                })
                    ->filter(fn ($row) => $row && $row['days_left'] <= 30)
                    ->sortBy('days_left')
                    ->take(10)
                    ->values()
                    ->all();

                if (!empty($birthdays)) {
                    $insights[] = count($birthdays) . ' customer birthdays are coming up in the next 30 days.';
                }
            }
        }

        return response()->json([
            'ok' => true,
            'segments' => $segments,
            'birthdays' => $birthdays,
            'insights' => $insights,
        ]);
    }

    public function stockRecommendations(Request $request): JsonResponse
    {
        if (!$this->guard($request)) {
            return response()->json(['ok' => false], 401);
        }

        $targetDays = max(15, (int) $request->query('target_days', 30));
        $stockCol = $this->firstExistingColumn('items', ['curstock', 'stock', 'qty']);
        if (!$stockCol || !$this->hasColumns('salesd', ['slno', 'code', 'name', 'qty']) || !$this->hasColumns('salesm', ['slno', 'tdate'])) {
            return response()->json(['ok' => true, 'reorder' => [], 'dead_stock' => [], 'fast_movers' => [], 'insights' => []]);
        }

        $sales30 = DB::table('salesd as d')
            ->join('salesm as m', 'm.slno', '=', 'd.slno')
            ->select(
                DB::raw('TRIM(d.code) as code'),
                DB::raw('MAX(TRIM(d.name)) as name'),
                DB::raw('SUM(d.qty) as sold_30'),
                DB::raw('SUM(d.qty)/30 as daily_rate')
            )
            ->where('m.tdate', '>=', now()->subDays(30)->toDateString())
            ->groupBy(DB::raw('TRIM(d.code)'))
            ->get()
            ->keyBy('code');

        $last90SalesCodes = DB::table('salesd as d')
            ->join('salesm as m', 'm.slno', '=', 'd.slno')
            ->where('m.tdate', '>=', now()->subDays(90)->toDateString())
            ->distinct()
            ->pluck(DB::raw('TRIM(d.code)'))
            ->filter()
            ->all();
        $last90SalesCodes = array_map('strval', $last90SalesCodes);

        $items = DB::table('items')
            ->select(DB::raw('TRIM(code) as code'), DB::raw('TRIM(name) as name'), DB::raw($stockCol . ' as stock'))
            ->where($stockCol, '>', 0)
            ->limit(500)
            ->get();

        $reorder = [];
        $deadStock = [];
        foreach ($items as $item) {
            $code = trim((string) $item->code);
            $stock = (float) $item->stock;
            $sale = $sales30[$code] ?? null;
            $dailyRate = (float) ($sale->daily_rate ?? 0);

            if ($dailyRate > 0) {
                $recommendedQty = max(0, round(($dailyRate * $targetDays) - $stock, 3));
                if ($recommendedQty > 0) {
                    $reorder[] = [
                        'code' => $code,
                        'name' => trim((string) ($sale->name ?? $item->name)),
                        'stock' => $stock,
                        'daily_rate' => round($dailyRate, 3),
                        'recommended_qty' => $recommendedQty,
                        'target_days' => $targetDays,
                    ];
                }
            }

            if (!in_array($code, $last90SalesCodes, true) && $stock > 0) {
                $deadStock[] = [
                    'code' => $code,
                    'name' => trim((string) $item->name),
                    'stock' => $stock,
                ];
            }
        }

        $fastMovers = collect($sales30)
            ->sortByDesc('sold_30')
            ->take(8)
            ->map(fn ($row) => [
                'code' => $row->code,
                'name' => $row->name,
                'sold_30' => (float) $row->sold_30,
                'daily_rate' => round((float) $row->daily_rate, 3),
            ])
            ->values()
            ->all();

        usort($reorder, fn (array $a, array $b) => $b['recommended_qty'] <=> $a['recommended_qty']);
        usort($deadStock, fn (array $a, array $b) => $b['stock'] <=> $a['stock']);

        return response()->json([
            'ok' => true,
            'reorder' => array_slice($reorder, 0, 15),
            'dead_stock' => array_slice($deadStock, 0, 15),
            'fast_movers' => $fastMovers,
            'insights' => [
                count($reorder) . ' items need reorder planning.',
                count($deadStock) . ' stocked items had no sale in the last 90 days.',
            ],
        ]);
    }

    public function reportAssistant(Request $request): JsonResponse
    {
        if (!$this->guard($request)) {
            return response()->json(['ok' => false], 401);
        }

        $message = strtolower(trim((string) $request->input('message', '')));
        $days = $this->extractDays($message) ?? 30;
        $since = now()->subDays($days)->toDateString();
        $intent = 'overview';
        $summary = [];
        $suggestions = [];
        $title = 'Business summary';

        if (str_contains($message, 'sales')) {
            $intent = 'sales';
            $title = "Sales report for last {$days} days";
            if ($this->hasColumns('daybook', ['billdate', 'billtype', 'amount'])) {
                $summary['total_sales'] = (float) (DB::table('daybook')
                    ->where('billtype', 'S')
                    ->whereDate('billdate', '>=', $since)
                    ->sum('amount') ?? 0);
                $summary['bill_count'] = (int) (DB::table('daybook')
                    ->where('billtype', 'S')
                    ->whereDate('billdate', '>=', $since)
                    ->distinct('billno')
                    ->count('billno') ?? 0);
            }
            $suggestions[] = ['label' => 'Monthly Sales Report', 'url' => url('/monthly-sales-report'), 'reason' => 'Compare trend month by month'];
            $suggestions[] = ['label' => 'Sales Register', 'url' => url('/sales-register'), 'reason' => 'Review detailed sales lines'];
        } elseif (str_contains($message, 'purchase')) {
            $intent = 'purchase';
            $title = "Purchase report for last {$days} days";
            if ($this->hasColumns('daybook', ['billdate', 'billtype', 'amount'])) {
                $summary['total_purchase'] = (float) (DB::table('daybook')
                    ->where('billtype', 'P')
                    ->whereDate('billdate', '>=', $since)
                    ->sum('amount') ?? 0);
            }
            $suggestions[] = ['label' => 'Purchase Register', 'url' => url('/purchase-register'), 'reason' => 'Review supplier-side entries'];
        } elseif (str_contains($message, 'stock')) {
            $intent = 'stock';
            $title = 'Stock review suggestion';
            $summary['focus'] = 'Use stock register and AI stock recommendation together for fast action.';
            $suggestions[] = ['label' => 'Stock Register', 'url' => url('/stock-register'), 'reason' => 'Check movement and balances'];
            $suggestions[] = ['label' => 'Reorder Levels', 'url' => url('/reorder'), 'reason' => 'Maintain reorder controls'];
        } elseif (str_contains($message, 'customer')) {
            $intent = 'customer';
            $title = 'Customer follow-up suggestion';
            $summary['focus'] = 'Use customer campaign with AI follow-up segments.';
            $suggestions[] = ['label' => 'Customer Campaign', 'url' => url('/customer-campaign'), 'reason' => 'Target filtered customers'];
            $suggestions[] = ['label' => 'Customer Master', 'url' => url('/customer'), 'reason' => 'Review customer details'];
        } elseif (str_contains($message, 'order')) {
            $intent = 'order';
            $title = 'Order monitoring suggestion';
            $summary['focus'] = 'Review pending and delayed orders first.';
            $suggestions[] = ['label' => 'Order Pending Register', 'url' => url('/order-pending-register'), 'reason' => 'See due orders quickly'];
            $suggestions[] = ['label' => 'Order Process Report', 'url' => url('/order-process-report'), 'reason' => 'Track order stage details'];
        } else {
            $summary['focus'] = 'Ask in plain English, for example: sales last 30 days, stock report, pending orders, inactive customers.';
            $suggestions[] = ['label' => 'Dashboard', 'url' => url('/dashboard'), 'reason' => 'Open overall business numbers'];
        }

        return response()->json([
            'ok' => true,
            'intent' => $intent,
            'title' => $title,
            'interpreted_period_days' => $days,
            'summary' => $summary,
            'suggestions' => $suggestions,
        ]);
    }

    public function chatbot(Request $request): JsonResponse
    {
        if (!$this->guard($request)) {
            return response()->json(['ok' => false], 401);
        }

        $module = trim((string) $request->input('module', ''));
        $message = strtolower(trim((string) $request->input('message', '')));
        $kb = self::knowledgeBase();

        if ($module !== '' && isset($kb[$module]) && $message === '') {
            return response()->json([
                'ok' => true,
                'type' => 'faq_list',
                'faqs' => $kb[$module]['faqs'],
                'tips' => $kb[$module]['tips'] ?? [],
                'context' => $this->moduleContext($module),
            ]);
        }

        $results = [];
        $searchIn = ($module !== '' && isset($kb[$module])) ? [$module => $kb[$module]] : $kb;
        foreach ($searchIn as $mod => $info) {
            foreach ($info['faqs'] as $faq) {
                foreach ($faq['keywords'] as $keyword) {
                    if ($message !== '' && str_contains($message, $keyword)) {
                        $results[] = [
                            'module' => $info['label'],
                            'question' => $faq['q'],
                            'answer' => $faq['a'],
                            'steps' => $faq['steps'] ?? [],
                            'actions' => $info['actions'] ?? [],
                            'context' => $this->moduleContext($mod),
                        ];
                        break;
                    }
                }
            }
        }

        if (!empty($results)) {
            return response()->json(['ok' => true, 'type' => 'answers', 'results' => $results]);
        }

        return response()->json([
            'ok' => true,
            'type' => 'no_match',
            'message' => 'No direct match found. Try a clearer issue description.',
            'suggest' => ['bill not saving', 'stock wrong', 'report empty', 'late order', 'customer inactive', 'barcode not scanning'],
            'context' => $module ? $this->moduleContext($module) : [],
        ]);
    }

    private function moduleContext(string $module): array
    {
        return match ($module) {
            'sales-bill' => $this->todaySalesContext(),
            'stock' => $this->stockContext(),
            'orders' => $this->orderContext(),
            'customers' => $this->customerContext(),
            'tools' => $this->toolsContext(),
            'menu-access' => $this->menuAccessContext(),
            default => [],
        };
    }

    private function todaySalesContext(): array
    {
        if (!$this->hasColumns('daybook', ['billdate', 'billtype', 'amount'])) {
            return [];
        }

        return [[
            'label' => 'Today sales',
            'value' => $this->money((float) DB::table('daybook')->where('billtype', 'S')->whereDate('billdate', now()->toDateString())->sum('amount')),
        ]];
    }

    private function stockContext(): array
    {
        $stockCol = $this->firstExistingColumn('items', ['curstock', 'stock', 'qty']);
        if (!$stockCol) {
            return [];
        }

        return [[
            'label' => 'Items with stock',
            'value' => (string) DB::table('items')->where($stockCol, '>', 0)->count(),
        ]];
    }

    private function orderContext(): array
    {
        if (!$this->hasTable('orderm')) {
            return [];
        }

        return [[
            'label' => 'Open orders',
            'value' => (string) DB::table('orderm')->where('status', '<>', 2)->count(),
        ]];
    }

    private function customerContext(): array
    {
        if (!$this->hasTable('clients')) {
            return [];
        }

        return [[
            'label' => 'Customers',
            'value' => (string) DB::table('clients')->count(),
        ]];
    }

    private function toolsContext(): array
    {
        return [
            ['label' => 'Tools', 'value' => 'Administration, Calendar, Backup, Year End Account Close, Phone Book, Show WM Weight, Reminder, AI Insights'],
            ['label' => 'AI Workspace', 'value' => '/ai-insights'],
            ['label' => 'Reorder', 'value' => '/reorder'],
            ['label' => 'Stock Register', 'value' => '/stock-register'],
        ];
    }

    private function menuAccessContext(): array
    {
        return [
            ['label' => 'Registration', 'value' => 'Software Settings, Items, Customer, Supplier, Goldsmith Add, Refiner Add, Jewellery Add, Depositors (Wgt), Accounts Master, Staff Adding, Sales Man, Scheme Type Master, Bill Prefix, States Adding, Repair Complaints, Gift Table, Point Card, Denomination Master, Op.Bill Creation, Users'],
            ['label' => 'Transaction', 'value' => 'Sales Bill, Sales Return Bill, Purchase Bill, Purchase Return Bill, Diamonds/Pres.Stone Bill, Goldsmith Bill, Refinery Bill, Jewellery Bill, Party Wgt Deposit Bill, Order Bill, Remake, Item Stock Adjustment, Staff, Other Items Trans, BarcodeAdd, Stock Suspense Entry, Purity Testing, Kuri Details, Scheme Details, Accounts'],
            ['label' => 'Reports', 'value' => 'Sales, Purchase, Tax Reports, Goldsmith, Refinery, Jewellery, Party Wgt Deposit, Order, Remake, Other Item Reports, Customers, Supplier, Staff, Kuri Reports, Scheme Reports, Partners Deposit Reports, Accounts, Item Reports, Barcode Reports, Stock Ledger, Stock List, Other Reports'],
            ['label' => 'Tools', 'value' => 'Administration, Calendar, Backup, Year End Account Close, Phone Book, Show WM Weight, Reminder, AI Insights'],
            ['label' => 'Integrates App', 'value' => 'AI Insights is available from this top menu group'],
            ['label' => 'User Access', 'value' => 'Registration > Users > Provide Access | /user-access | MDI_USER_ACCESS / ADMINBUTTON'],
        ];
    }

    private static function knowledgeBase(): array
    {
        return [
            'sales-bill' => [
                'label' => 'Sales Bill',
                'tips' => ['Search customer before item entry', 'Watch discount exceptions before save', 'Check stock if an item does not load'],
                'actions' => [['label' => 'Open Sales Bill', 'url' => url('/sales-bill')]],
                'faqs' => [
                    ['q' => 'Bill is not saving', 'keywords' => ['not saving', 'save fail', 'cannot save'], 'a' => 'Check the bill header, at least one item line, and stock availability.', 'steps' => ['Select a customer', 'Enter at least one valid item', 'Verify weight and rate', 'Try save again']],
                    ['q' => 'Amount is wrong', 'keywords' => ['amount wrong', 'total wrong', 'calculation'], 'a' => 'Review rate, weight, making charge, and discount fields.', 'steps' => ['Verify item rate', 'Check wastage and making values', 'Confirm discount is intended']],
                    ['q' => 'Customer search issue', 'keywords' => ['customer not found', 'customer search'], 'a' => 'Use customer search by code, name, or phone.', 'steps' => ['Try code', 'Try name', 'Try mobile number']],
                ],
            ],
            'stock' => [
                'label' => 'Stock',
                'tips' => ['Check stock register before manual correction', 'Use reorder planning for fast movers'],
                'actions' => [['label' => 'Open Stock Register', 'url' => url('/stock-register')], ['label' => 'Open Reorder', 'url' => url('/reorder')]],
                'faqs' => [
                    ['q' => 'Stock balance incorrect', 'keywords' => ['stock wrong', 'balance wrong', 'incorrect stock'], 'a' => 'Trace the item in stock ledger and verify recent transactions.', 'steps' => ['Open Stock Register', 'Check recent sale or purchase', 'Review cancelled bills']],
                    ['q' => 'Item missing in sale', 'keywords' => ['item not found', 'item missing'], 'a' => 'Item may be inactive, empty stock, or code mismatch.', 'steps' => ['Verify item code', 'Check available stock', 'Review item master']],
                ],
            ],
            'orders' => [
                'label' => 'Orders',
                'tips' => ['Watch overdue due dates', 'Use order process report for stage tracking'],
                'actions' => [['label' => 'Open Pending Orders', 'url' => url('/order-pending-register')], ['label' => 'Open Order Process', 'url' => url('/order-process-report')]],
                'faqs' => [
                    ['q' => 'Order is delayed', 'keywords' => ['late order', 'delayed order', 'pending order'], 'a' => 'Review due date, stage, and assigned processing details.', 'steps' => ['Open pending register', 'Check due date', 'Review process stage', 'Contact customer if needed']],
                    ['q' => 'Order not loading', 'keywords' => ['order not found', 'cannot open order'], 'a' => 'Search by order number or customer name.', 'steps' => ['Open order search', 'Check exact order number', 'Verify order date']],
                ],
            ],
            'customers' => [
                'label' => 'Customers',
                'tips' => ['Focus on inactive high-value customers', 'Use birthdays for campaign timing'],
                'actions' => [['label' => 'Open Customer Campaign', 'url' => url('/customer-campaign')], ['label' => 'Open Customer Master', 'url' => url('/customer')]],
                'faqs' => [
                    ['q' => 'Inactive customers follow-up', 'keywords' => ['inactive customer', 'follow up', 'comeback'], 'a' => 'Prioritize high-value inactive customers first.', 'steps' => ['Sort by total spend', 'Check last visit date', 'Launch campaign list']],
                    ['q' => 'Customer balance issue', 'keywords' => ['customer balance', 'outstanding'], 'a' => 'Review customer ledger and billwise receipt entries.', 'steps' => ['Open customer ledger', 'Check receipt allocation', 'Review journal adjustments']],
                ],
            ],
            'reports' => [
                'label' => 'Reports',
                'tips' => ['Ask the report assistant in plain language', 'Use date words like last 30 days or this month'],
                'actions' => [['label' => 'Open Dashboard', 'url' => url('/dashboard')]],
                'faqs' => [
                    ['q' => 'Report showing no data', 'keywords' => ['no data', 'empty report', 'blank report'], 'a' => 'Usually the date range or filters are too narrow.', 'steps' => ['Check date range', 'Clear extra filters', 'Compare with dashboard totals']],
                    ['q' => 'Need correct report', 'keywords' => ['which report', 'correct report'], 'a' => 'Use the report assistant to map your question to the right report.', 'steps' => ['Type the business question', 'Review suggested screen', 'Open the recommended report']],
                ],
            ],
            'tools' => [
                'label' => 'Tools',
                'tips' => ['Use tools screens for setup, controls, and utility actions', 'AI Workspace is under Integrates App in the main menu'],
                'actions' => [['label' => 'Open AI Workspace', 'url' => url('/ai-insights')], ['label' => 'Open Reorder', 'url' => url('/reorder')], ['label' => 'Open Stock Register', 'url' => url('/stock-register')]],
                'faqs' => [
                    ['q' => 'Where is AI Insights', 'keywords' => ['ai insights', 'ai workspace', 'ai menu'], 'a' => 'AI Insights is available from the dashboard menu and opens the AI workspace.', 'steps' => ['Open dashboard', 'Go to Integrates App', 'Click AI Insights']],
                    ['q' => 'Where is reorder tool', 'keywords' => ['reorder', 'reorder tool', 'stock reorder'], 'a' => 'Use the reorder screen to maintain reorder level controls.', 'steps' => ['Open Registration', 'Go to Items', 'Open ReOrder Level Table or open /reorder directly']],
                    ['q' => 'Where is stock register', 'keywords' => ['stock register', 'stock tool'], 'a' => 'Stock Register is the main movement and balance review tool.', 'steps' => ['Open Reports or stock menu', 'Launch Stock Register', 'Filter by date or item']],
                    ['q' => 'What is in tools menu', 'keywords' => ['tools menu', 'administration', 'backup', 'calendar', 'reminder'], 'a' => 'The Tools menu contains system utilities and maintenance screens.', 'steps' => ['Administration', 'Calendar', 'Backup', 'Year End Account Close', 'Phone Book', 'Show WM Weight', 'Reminder', 'AI Insights']],
                ],
            ],
            'menu-access' => [
                'label' => 'Menu / Access',
                'tips' => ['User access is managed from Registration > Users > Provide Access', 'Support help covers the wider menu structure too'],
                'actions' => [['label' => 'Open User Access', 'url' => url('/user-access')]],
                'faqs' => [
                    ['q' => 'Where is user access', 'keywords' => ['user access', 'provide access', 'permission', 'menu access'], 'a' => 'Open Registration > Users > Provide Access to assign or restrict module permissions.', 'steps' => ['Open Registration', 'Open Users', 'Click Provide Access', 'Select user and save permissions']],
                    ['q' => 'What is mdi user access', 'keywords' => ['mdi user access', 'mdi access', 'adminbutton'], 'a' => 'The access screen is guarded by admin-level menu flags.', 'steps' => ['Allowed users include ADMIN and MGR', 'Other users need ADMINBUTTON or MDI_USER_ACCESS permission', 'Open User Access screen to review permissions']],
                    ['q' => 'Where is users history', 'keywords' => ['users history', 'login history', 'activity history'], 'a' => 'Users History is under Registration > Users > Users History.', 'steps' => ['Open Registration', 'Open Users', 'Click Users History']],
                    ['q' => 'Full menu details', 'keywords' => ['full menu', 'menu details', 'mdi details', 'menu path', 'all menu'], 'a' => 'The dashboard is organized into the main menu groups below.', 'steps' => ['Registration: Software Settings, Items, Customer, Supplier, Goldsmith Add, Refiner Add, Jewellery Add, Depositors, Accounts Master, Staff Adding, Sales Man, Scheme Type Master, Bill Prefix, States Adding, Repair Complaints, Gift Table, Point Card, Denomination Master, Op.Bill Creation, Users', 'Transaction: Sales Bill, Sales Return Bill, Purchase Bill, Purchase Return Bill, Diamonds/Pres.Stone Bill, Goldsmith Bill, Refinery Bill, Jewellery Bill, Party Wgt Deposit Bill, Order Bill, Remake, Item Stock Adjustment, Staff, Other Items Trans, BarcodeAdd, Stock Suspense Entry, Purity Testing, Kuri Details, Scheme Details, Accounts', 'Reports: Sales, Purchase, Tax Reports, Goldsmith, Refinery, Jewellery, Party Wgt Deposit, Order, Remake, Other Item Reports, Customers, Supplier, Staff, Kuri Reports, Scheme Reports, Partners Deposit Reports, Accounts, Item Reports, Barcode Reports, Stock Ledger, Stock List, Other Reports', 'Tools: Administration, Calendar, Backup, Year End Account Close, Phone Book, Show WM Weight, Reminder, AI Insights', 'Integrates App: AI Insights entry group']],
                    ['q' => 'What is in registration menu', 'keywords' => ['registration menu', 'registration details', 'registration modules'], 'a' => 'Registration holds setup and master screens.', 'steps' => ['Software Settings: Update > Rate, Application, Country Currency Religion', 'Items: Item Add/Edit, Item Temp, Other Items, Groups, Sub Groups, Purity Type, Counters, Wastage Table, MC Table, Party MC Table, Stock Type, Models, Opening Stock Entry, ReOrder Level Table', 'Masters: Customer, Supplier, Goldsmith Add, Refiner Add, Jewellery Add, Depositors (Wgt)', 'Accounts Master: Account, Position Settings, Book Stock, Op.Stock Value Set, C/o Party Limit', 'Other setup: Staff Adding, Sales Man, Scheme Type Master, Bill Prefix, States Adding, Repair Complaints, Gift Table, Point Card, Denomination Master, Op.Bill Creation > Customers', 'Users: Provide Access and Users History']],
                    ['q' => 'What is in transaction menu', 'keywords' => ['transaction menu', 'transactions details', 'billing menu'], 'a' => 'Transaction contains day-to-day billing and operational modules.', 'steps' => ['Billing: Sales Bill, Sales Return Bill, Purchase Bill, Purchase Return Bill', 'Special bills: Diamonds/Pres.Stone Bill, Goldsmith Bill, Refinery Bill, Jewellery Bill, Party Wgt Deposit Bill', 'Order flow: New Order, Edit Order Entry, Advance After, Order Sale, Edit Order Sale, Sale W/o Order, Update from Jewelleries, Block Unblock, Order Rate Fix, Cancel, Reprint', 'Operations: Remake, Item Stock Adjustment, Staff, Other Items Trans, BarcodeAdd, Stock Suspense Entry, Purity Testing', 'Collections and accounts: Kuri Details, Scheme Details, Accounts']],
                    ['q' => 'What is in reports menu', 'keywords' => ['reports menu', 'reports details', 'all reports'], 'a' => 'Reports contains operational, accounting, stock, and analytical reports.', 'steps' => ['Core reports: Sales, Purchase, Tax Reports, Goldsmith, Refinery, Jewellery, Party Wgt Deposit, Order, Remake', 'Party reports: Customers, Supplier, Staff, Kuri Reports, Scheme Reports, Partners Deposit Reports', 'Accounts and stock: Accounts, Item Reports, Barcode Reports, Stock Ledger, Stock List', 'Utility reports: Other Item Reports, Other Reports, Cash Balance']],
                ],
            ],
            'barcode' => [
                'label' => 'Barcode',
                'tips' => ['Try manual code entry if scanner fails'],
                'actions' => [],
                'faqs' => [
                    ['q' => 'Barcode not scanning', 'keywords' => ['barcode not scanning', 'scanner', 'scan issue'], 'a' => 'Verify scanner connection and barcode availability.', 'steps' => ['Reconnect scanner', 'Try manual barcode entry', 'Check barcode settings']],
                ],
            ],
        ];
    }

    private function hasColumns(string $table, array $columns): bool
    {
        if (!$this->hasTable($table)) {
            return false;
        }

        $existing = array_map('strtolower', $this->columnList($table));
        foreach ($columns as $column) {
            if (!in_array(strtolower($column), $existing, true)) {
                return false;
            }
        }

        return true;
    }

    private function firstExistingColumn(string $table, array $candidates): ?string
    {
        if (!$this->hasTable($table)) {
            return null;
        }

        $existing = array_map('strtolower', $this->columnList($table));
        foreach ($candidates as $candidate) {
            if (in_array(strtolower($candidate), $existing, true)) {
                return $candidate;
            }
        }

        return null;
    }

    private function daysBetween(?string $from, ?string $to): int
    {
        if (!$from || !$to) {
            return 0;
        }

        return (int) round((strtotime($to) - strtotime($from)) / 86400);
    }

    private function extractDays(string $message): ?int
    {
        if (preg_match('/(\d+)\s*day/', $message, $match)) {
            return max(1, (int) $match[1]);
        }

        if (str_contains($message, 'this month')) {
            return now()->day;
        }

        if (str_contains($message, 'last month')) {
            return 30;
        }

        return null;
    }

    private function num(float|int|string|null $value): string
    {
        return number_format((float) $value, 0, '.', ',');
    }

    private function money(float|int|string|null $value): string
    {
        return 'Rs ' . number_format((float) $value, 2, '.', ',');
    }
}
