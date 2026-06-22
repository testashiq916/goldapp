<?php

namespace App\Http\Controllers;

use App\Http\Controllers\CountryCurrencyController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class NativeDashboardController extends Controller
{
    private array $columnCache = [];

    public function index(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $userName = (string) ($request->session()->get('user_name') ?? 'User');
        $userCode = (string) ($request->session()->get('user_code') ?? '');
        $shopName = $this->getShopName();
        $currentDatabase = (string) Config::get('database.connections.mysql.database', '');
        $primaryDatabase = trim((string) env('DB_DATABASE', $currentDatabase));
        [$userPermissions, $hasRestrictedMenuAccess] = $this->getUserPermissions($request, $userCode);

        // Fetch current rates
        $rates = $this->getCurrentRates();

        // Fetch dashboard statistics
        $dashboardData = $this->getDashboardData();
        $dashboardData['goldRate22K'] = $rates['GRATE'] ?? 0;
        $dashboardData['goldRate18K'] = $rates['G18RATE'] ?? 0;
        $dashboardData['silverRate'] = $rates['SRATE'] ?? 0;
        $dashboardData['thRate'] = $rates['THRATE'] ?? 0;
        $dashboardData['platinumRate'] = $rates['PRATE'] ?? 0;
        $dashboardData['todayDuePendingAccounts'] = $this->getTodayDuePendingAccounts($request);
        $dashboardData['creditBillSummary'] = $this->getCreditBillSummary($request);

        // Currency / country / religion config — used by all iframe modules via window.GOLDAPP_CFG
        $currencyConfig = $request->session()->get('currency_config')
            ?? CountryCurrencyController::getConfig();

        return view('native.dashboard', [
            'userName'       => $userName,
            'userCode'       => $userCode,
            'siteName'       => $shopName,
            'dashboardData'  => $dashboardData,
            'currencyConfig' => $currencyConfig,
            'currentDatabase' => $currentDatabase,
            'savedDashboardTheme' => $this->getSavedDashboardTheme(),
            'companyThemeActive' => $currentDatabase !== '' && strcasecmp($currentDatabase, $primaryDatabase) !== 0,
            'userPermissions' => $userPermissions,
            'hasRestrictedMenuAccess' => $hasRestrictedMenuAccess,
            'dashboardAccessDenied' => $hasRestrictedMenuAccess && !in_array('MDI_DASHBOARD', $userPermissions, true),
            'showStartupPopups' => (bool) $request->session()->pull('show_startup_popups', false),
        ]);
    }

    private function getSavedDashboardTheme(): string
    {
        $validThemes = ['default', 'alt', 'ocean', 'forest', 'rose', 'dark', 'violet'];
        $path = storage_path('app/software-settings.ini');
        if (!is_file($path)) {
            return 'default';
        }

        try {
            $settings = parse_ini_file($path, true, INI_SCANNER_RAW);
            $theme = strtolower(trim((string) ($settings['Software']['DashboardTheme'] ?? 'default')));
            return in_array($theme, $validThemes, true) ? $theme : 'default';
        } catch (\Throwable) {
            return 'default';
        }
    }

    private function getUserPermissions(Request $request, string $userCode): array
    {
        $userCode = strtoupper(trim($userCode));
        if ($userCode === '' || !$this->hasTable('userd')) {
            return [[], false];
        }

        if (in_array($userCode, ['ADMIN', 'MGR', 'DEVELOPER'], true)) {
            return [[], false];
        }

        $userPermissions = DB::table('userd')
            ->whereRaw('UPPER(TRIM(code)) = ?', [$userCode])
            ->pluck('menuitem')
            ->map(fn ($item) => strtoupper(trim((string) $item)))
            ->values()
            ->all();

        $hasRestrictedMenuAccess = collect($userPermissions)->contains(
            fn (string $permission) => str_starts_with($permission, 'MDI_')
        );

        return [$userPermissions, $hasRestrictedMenuAccess];
    }

    private function getShopName(): string
    {
        try {
            $row = DB::table('generals')->select('cvalue')->where('code', 'SHOPNM')->first();
            return (string) ($row->cvalue ?? 'Proaims Custom Dashboard');
        } catch (\Throwable) {
            return 'Proaims Custom Dashboard';
        }
    }

    private function getCurrentRates(): array
    {
        $rates = [];
        $codes = ['GRATE', 'G18RATE', 'SRATE', 'PRATE', 'THRATE', 'BULRATE', 'BULTOUCH', 'JRATE', 'OGRATE', 'OSRATE'];

        try {
            if (!$this->hasTable('generald')) {
                return $rates;
            }
            $rows = DB::table('generald')->whereIn('code', $codes)->get();
            foreach ($rows as $row) {
                $rates[(string) $row->code] = (float) ($row->cvalue ?? 0);
            }
        } catch (\Throwable) {
            // ignore
        }

        return $rates;
    }

    private function getTodayDuePendingAccounts(Request $request): array
    {
        if (!$this->hasTable('accountm') || !$this->hasTable('clients')) {
            return ['rows' => [], 'totals' => ['count' => 0, 'to_get' => 0.0, 'to_give' => 0.0, 'net' => 0.0]];
        }

        try {
            $today = date('Y-m-d');
            $rl = max(1, (int) $request->session()->get('gilevel', 1));
            $opColumn = $rl === 1 && $this->hasCol('accountm', 'opbal') ? 'opbal' : ($this->hasCol('accountm', 'opbalb') ? 'opbalb' : 'opbal');
            $hasDaybook = $this->hasTable('daybook') && $this->hasCol('daybook', 'accode') && $this->hasCol('daybook', 'amount') && $this->hasCol('daybook', 'tdate');

            $baseRows = DB::table('accountm as a')
                ->join('clients as c', 'a.accode', '=', 'c.code')
                ->selectRaw("
                    TRIM(a.accode) as accode,
                    TRIM(COALESCE(c.name, a.name, '')) as cname,
                    TRIM(COALESCE(c.mobile, '')) as mobile,
                    TRIM(COALESCE(c.telephone, '')) as telephone,
                    TRIM(COALESCE(a.actype2, '')) as actype2,
                    COALESCE(a.{$opColumn}, 0) as opbal,
                    COALESCE(c.duedate, null) as client_duedate
                ")
                ->where('a.control', '<=', $rl)
                ->get();

            $codes = $baseRows->pluck('accode')->map(fn ($v) => trim((string) $v))->filter()->values()->all();

            $daybookStats = [];
            if ($hasDaybook && $codes !== []) {
                $daybookStats = DB::table('daybook')
                    ->selectRaw('TRIM(accode) as code, COALESCE(SUM(amount), 0) as tran_amount, MAX(CASE WHEN amount > 0 THEN tdate ELSE NULL END) as lcdate')
                    ->whereIn('accode', $codes)
                    ->where('tdate', '<=', $today)
                    ->where('control', '<=', $rl)
                    ->groupBy('accode')
                    ->get()
                    ->keyBy(fn ($row) => trim((string) $row->code))
                    ->all();
            }

            $salesDue = [];
            if ($this->hasTable('salesm') && $this->hasCol('salesm', 'custcode') && $this->hasCol('salesm', 'duedate') && $codes !== []) {
                $salesDue = DB::table('salesm')
                    ->selectRaw('TRIM(custcode) as code, MAX(duedate) as due')
                    ->whereIn('custcode', $codes)
                    ->groupBy('custcode')
                    ->get()
                    ->keyBy(fn ($row) => trim((string) $row->code))
                    ->all();
            }

            $purchaseDue = [];
            if ($this->hasTable('purchasem') && $this->hasCol('purchasem', 'suppcode') && $this->hasCol('purchasem', 'duedate') && $codes !== []) {
                $purchaseDue = DB::table('purchasem')
                    ->selectRaw('TRIM(suppcode) as code, MAX(duedate) as due')
                    ->whereIn('suppcode', $codes)
                    ->groupBy('suppcode')
                    ->get()
                    ->keyBy(fn ($row) => trim((string) $row->code))
                    ->all();
            }

            $mapped = [];
            foreach ($baseRows as $row) {
                $code = trim((string) ($row->accode ?? ''));
                $clientDue = (string) ($row->client_duedate ?? '');
                $clientDue = $clientDue !== '0000-00-00' ? $clientDue : '';
                $salesDueDate = (string) ($salesDue[$code]->due ?? '');
                $purchaseDueDate = (string) ($purchaseDue[$code]->due ?? '');
                $dueDate = collect([$clientDue, $salesDueDate, $purchaseDueDate])
                    ->filter(fn ($value) => $value !== '' && $value !== '0000-00-00')
                    ->sort()
                    ->last();

                if ($dueDate !== $today) {
                    continue;
                }

                $netBal = round((float) ($row->opbal ?? 0) + (float) ($daybookStats[$code]->tran_amount ?? 0), 2);
                if (abs($netBal) < 0.01) {
                    continue;
                }

                $mapped[] = [
                    'code' => $code,
                    'name' => trim((string) ($row->cname ?? '')),
                    'mobile' => trim((string) ($row->mobile ?? '')),
                    'telephone' => trim((string) ($row->telephone ?? '')),
                    'actype2' => trim((string) ($row->actype2 ?? '')),
                    'balance' => $netBal,
                    'balance_abs' => abs($netBal),
                    'status' => $netBal > 0 ? 'TG' : 'TR',
                    'duedate' => $dueDate,
                    'lcdate' => (string) ($daybookStats[$code]->lcdate ?? ''),
                ];
            }

            usort($mapped, fn ($a, $b) => strcmp($a['name'], $b['name']));

            $totals = ['count' => count($mapped), 'to_get' => 0.0, 'to_give' => 0.0, 'net' => 0.0];
            foreach ($mapped as $row) {
                if ($row['status'] === 'TR') {
                    $totals['to_get'] += $row['balance_abs'];
                } else {
                    $totals['to_give'] += $row['balance_abs'];
                }
                $totals['net'] += $row['balance'];
            }

            return ['rows' => $mapped, 'totals' => $totals];
        } catch (\Throwable) {
            return ['rows' => [], 'totals' => ['count' => 0, 'to_get' => 0.0, 'to_give' => 0.0, 'net' => 0.0]];
        }
    }

    private function getDashboardData(): array
    {
        $data = [
            'todaySales' => 0,
            'todayPurchase' => 0,
            'monthSales' => 0,
            'monthPurchase' => 0,
            'todaySalesBills' => 0,
            'todayPurchaseBills' => 0,
            'todaySalesWeight' => 0,
            'todayPurchaseWeight' => 0,
            'todaySalesReturn' => 0,
            'todaySalesReturnWeight' => 0,
            'monthSalesWeight' => 0,
            'monthPurchaseWeight' => 0,
            'monthPurchaseBills' => 0,
            'monthSalesReturn' => 0,
            'monthSalesReturnWeight' => 0,
            'totalCustomers' => 0,
            'totalInvoices' => 0,
            'outstandingBalance' => 0,
            'pendingBills' => 0,
            'prevMonthSales' => 0,
            'topItems' => [],
            'labels' => [],
            'salesSeries' => [],
            'purchaseSeries' => [],
            'monthLabels' => [],
            'monthlySalesSeries' => [],
            'monthlyPurchaseSeries' => [],
            'recentSales' => [],
            'pendingAccounts' => [],
            'yearlyFinancials' => [],
        ];

        try {
            if (!$this->hasTable('daybook')) {
                return $data;
            }

            $today = date('Y-m-d');
            $monthStart = date('Y-m-01');

            // Detect daybook column names
            $dbDateCol = Schema::hasColumn('daybook', 'tdate') ? 'tdate' : 'billdate';
            $hasBilltype = Schema::hasColumn('daybook', 'billtype');

            // Today's sales (RS = Retail Sales in opaccode, or billtype='S')
            if ($hasBilltype) {
                $salesWhere = function ($q) { $q->where('billtype', 'S'); };
                $purchWhere = function ($q) { $q->where('billtype', 'P'); };
            } else {
                $salesWhere = function ($q) { $q->where('opaccode', 'RS'); };
                $purchWhere = function ($q) { $q->where('opaccode', 'EP'); };
            }

            $ts = DB::table('daybook')
                ->selectRaw('COALESCE(SUM(ABS(amount)), 0) as total, COUNT(DISTINCT slno) as bills')
                ->where($dbDateCol, $today)
                ->where($salesWhere)
                ->first();
            $data['todaySales'] = (float) ($ts->total ?? 0);
            $data['todaySalesBills'] = (int) ($ts->bills ?? 0);

            // Today's purchases
            $tp = DB::table('daybook')
                ->selectRaw('COALESCE(SUM(ABS(amount)), 0) as total, COUNT(DISTINCT slno) as bills')
                ->where($dbDateCol, $today)
                ->where($purchWhere)
                ->first();
            $data['todayPurchase'] = (float) ($tp->total ?? 0);
            $data['todayPurchaseBills'] = (int) ($tp->bills ?? 0);

            // Month sales
            $ms = DB::table('daybook')
                ->selectRaw('COALESCE(SUM(ABS(amount)), 0) as total')
                ->where($dbDateCol, '>=', $monthStart)
                ->where($salesWhere)
                ->first();
            $data['monthSales'] = (float) ($ms->total ?? 0);

            // Month purchases
            $mp = DB::table('daybook')
                ->selectRaw('COALESCE(SUM(ABS(amount)), 0) as total')
                ->where($dbDateCol, '>=', $monthStart)
                ->where($purchWhere)
                ->first();
            $data['monthPurchase'] = (float) ($mp->total ?? 0);

            if ($this->hasTable('salesm')) {
                $todayGross = $this->salesSummary($today, $today);
                $todayReturn = $this->salesReturnSummary($today, $today);
                $monthGross = $this->salesSummary($monthStart, $today);
                $monthReturn = $this->salesReturnSummary($monthStart, $today);

                $data['todaySales'] = round($todayGross['amount'] - $todayReturn['amount'], 2);
                $data['todaySalesBills'] = $todayGross['bills'];
                $data['todaySalesWeight'] = round($todayGross['weight'] - $todayReturn['weight'], 3);
                $data['todaySalesReturn'] = round($todayReturn['amount'], 2);
                $data['todaySalesReturnWeight'] = round($todayReturn['weight'], 3);

                $data['monthSales'] = round($monthGross['amount'] - $monthReturn['amount'], 2);
                $data['monthSalesWeight'] = round($monthGross['weight'] - $monthReturn['weight'], 3);
                $data['monthSalesReturn'] = round($monthReturn['amount'], 2);
                $data['monthSalesReturnWeight'] = round($monthReturn['weight'], 3);
            }

            if ($this->hasTable('purchasem')) {
                $todayPurchase = $this->purchaseSummary($today, $today);
                $monthPurchase = $this->purchaseSummary($monthStart, $today);

                $data['todayPurchase'] = round($todayPurchase['amount'], 2);
                $data['todayPurchaseBills'] = $todayPurchase['bills'];
                $data['todayPurchaseWeight'] = round($todayPurchase['weight'], 3);

                $data['monthPurchase'] = round($monthPurchase['amount'], 2);
                $data['monthPurchaseBills'] = $monthPurchase['bills'];
                $data['monthPurchaseWeight'] = round($monthPurchase['weight'], 3);
            }

            // Top 5 items by sales amount
            if ($this->hasTable('salesd') && $this->hasTable('salesm')) {
                $amtExpr = $this->hasCol('salesd', 'amount') ? 'COALESCE(SUM(sd.amount), 0)' : '0';
                $qtyExpr = $this->hasCol('salesd', 'qty') ? 'COALESCE(SUM(sd.qty), 0)' : '0';
                $topItems = DB::table('salesd as sd')
                    ->join('salesm as sm', 'sm.slno', '=', 'sd.slno')
                    ->selectRaw("sd.code as code, {$qtyExpr} as total_qty, {$amtExpr} as total_amount")
                    ->whereDate('sm.tdate', '>=', $monthStart)
                    ->groupBy('sd.code')
                    ->orderByDesc('total_amount')
                    ->limit(5)
                    ->get();

                $itemCodes = $topItems->pluck('code')->filter()->unique()->values();
                $itemNames = ($itemCodes->isNotEmpty() && $this->hasTable('items'))
                    ? DB::table('items')->whereIn('code', $itemCodes)->pluck('name', 'code')
                    : collect();
                $data['topItems'] = $topItems->map(function ($row) use ($itemNames) {
                    return [
                        'code'         => trim((string) $row->code),
                        'item_name'    => trim((string) ($itemNames->get($row->code) ?? $row->code)),
                        'total_qty'    => (float) ($row->total_qty ?? 0),
                        'total_amount' => (float) ($row->total_amount ?? 0),
                    ];
                })->all();
            } elseif (Schema::hasColumn('daybook', 'itemcode')) {
                $topItems = DB::table('daybook')
                    ->selectRaw('itemcode as code, COALESCE(SUM(qty), 0) as total_qty, COALESCE(SUM(amount), 0) as total_amount')
                    ->where('billtype', 'S')
                    ->whereRaw('DATE(billdate) >= ?', [$monthStart])
                    ->groupBy('itemcode')
                    ->orderByDesc('total_amount')
                    ->limit(5)
                    ->get();

                $itemCodes2 = $topItems->pluck('code')->filter()->unique()->values();
                $itemNames2 = ($itemCodes2->isNotEmpty() && $this->hasTable('items'))
                    ? DB::table('items')->whereIn('code', $itemCodes2)->pluck('name', 'code')
                    : collect();
                $data['topItems'] = $topItems->map(function ($row) use ($itemNames2) {
                    return [
                        'code'         => trim((string) $row->code),
                        'item_name'    => trim((string) ($itemNames2->get($row->code) ?? $row->code)),
                        'total_qty'    => (float) ($row->total_qty ?? 0),
                        'total_amount' => (float) ($row->total_amount ?? 0),
                    ];
                })->all();
            }

            // Total customers
            if ($this->hasTable('clients')) {
                $data['totalCustomers'] = (int) DB::table('clients')
                    ->where('ctype', 'C')->count();
                if ($this->hasCol('clients', 'balance')) {
                    $ob = DB::table('clients')
                        ->where('ctype', 'C')
                        ->selectRaw('COALESCE(SUM(ABS(balance)), 0) as total, COUNT(CASE WHEN ABS(balance) > 0 THEN 1 END) as cnt')
                        ->first();
                    $data['outstandingBalance'] = (float) ($ob->total ?? 0);
                    $data['pendingBills'] = (int) ($ob->cnt ?? 0);
                } elseif ($this->hasTable('accountm') && $this->hasCol('accountm', 'clbal')) {
                    $ob = DB::table('clients as c')
                        ->join('accountm as a', 'a.accode', '=', 'c.code')
                        ->where('c.ctype', 'C')
                        ->selectRaw('COALESCE(SUM(ABS(a.clbal)), 0) as total, COUNT(CASE WHEN ABS(a.clbal) > 0 THEN 1 END) as cnt')
                        ->first();
                    $data['outstandingBalance'] = (float) ($ob->total ?? 0);
                    $data['pendingBills'] = (int) ($ob->cnt ?? 0);
                }
            }

            // Total invoices (all sales bills)
            $ti = DB::table('daybook')
                ->where($salesWhere)
                ->selectRaw('COUNT(DISTINCT slno) as cnt')
                ->first();
            $data['totalInvoices'] = (int) ($ti->cnt ?? 0);

            // Previous month sales (for trend %)
            $prevStart = date('Y-m-01', strtotime('-1 month'));
            $prevEnd = date('Y-m-t', strtotime('-1 month'));
            $ps = DB::table('daybook')
                ->selectRaw('COALESCE(SUM(ABS(amount)), 0) as total')
                ->whereBetween($dbDateCol, [$prevStart, $prevEnd])
                ->where($salesWhere)
                ->first();
            $data['prevMonthSales'] = (float) ($ps->total ?? 0);
            if ($this->hasTable('salesm')) {
                $prevGross = $this->salesSummary($prevStart, $prevEnd);
                $prevReturn = $this->salesReturnSummary($prevStart, $prevEnd);
                $data['prevMonthSales'] = round($prevGross['amount'] - $prevReturn['amount'], 2);
            }

            // 6-month trend (single query instead of 12)
            $sixMonthsAgo = date('Y-m-01', strtotime('-5 months'));
            if ($hasBilltype) {
                $monthTrendRows = DB::table('daybook')
                    ->selectRaw("DATE_FORMAT($dbDateCol, '%Y-%m') as ym, billtype, COALESCE(SUM(ABS(amount)), 0) as total")
                    ->where($dbDateCol, '>=', $sixMonthsAgo)
                    ->whereIn('billtype', ['S', 'P'])
                    ->groupByRaw("DATE_FORMAT($dbDateCol, '%Y-%m'), billtype")
                    ->get()->groupBy('ym');
            } else {
                $monthTrendRows = DB::table('daybook')
                    ->selectRaw("DATE_FORMAT($dbDateCol, '%Y-%m') as ym, opaccode, COALESCE(SUM(ABS(amount)), 0) as total")
                    ->where($dbDateCol, '>=', $sixMonthsAgo)
                    ->whereIn('opaccode', ['RS', 'EP'])
                    ->groupByRaw("DATE_FORMAT($dbDateCol, '%Y-%m'), opaccode")
                    ->get()->groupBy('ym');
            }
            for ($i = 5; $i >= 0; $i--) {
                $mStart = date('Y-m-01', strtotime("-{$i} months"));
                $ym = date('Y-m', strtotime($mStart));
                $data['monthLabels'][] = date('M Y', strtotime($mStart));
                $ymData = $monthTrendRows->get($ym, collect());
                $sRow = $hasBilltype ? $ymData->firstWhere('billtype', 'S') : $ymData->firstWhere('opaccode', 'RS');
                $pRow = $hasBilltype ? $ymData->firstWhere('billtype', 'P') : $ymData->firstWhere('opaccode', 'EP');
                if ($this->hasTable('salesm')) {
                    $mEnd = date('Y-m-t', strtotime($mStart));
                    $gross = $this->salesSummary($mStart, $mEnd);
                    $returns = $this->salesReturnSummary($mStart, $mEnd);
                    $data['monthlySalesSeries'][] = round($gross['amount'] - $returns['amount'], 2);
                } else {
                    $data['monthlySalesSeries'][] = (float) ($sRow->total ?? 0);
                }
                $data['monthlyPurchaseSeries'][] = (float) ($pRow->total ?? 0);
            }

            // 7-day trend (single query instead of 14)
            $sevenDaysAgo = date('Y-m-d', strtotime('-6 days'));
            if ($hasBilltype) {
                $dailyTrendRows = DB::table('daybook')
                    ->selectRaw("DATE($dbDateCol) as day, billtype, COALESCE(SUM(ABS(amount)), 0) as total")
                    ->where($dbDateCol, '>=', $sevenDaysAgo)
                    ->whereIn('billtype', ['S', 'P'])
                    ->groupByRaw("DATE($dbDateCol), billtype")
                    ->get()->groupBy('day');
            } else {
                $dailyTrendRows = DB::table('daybook')
                    ->selectRaw("DATE($dbDateCol) as day, opaccode, COALESCE(SUM(ABS(amount)), 0) as total")
                    ->where($dbDateCol, '>=', $sevenDaysAgo)
                    ->whereIn('opaccode', ['RS', 'EP'])
                    ->groupByRaw("DATE($dbDateCol), opaccode")
                    ->get()->groupBy('day');
            }
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                $data['labels'][] = date('d M', strtotime($date));
                $dayData = $dailyTrendRows->get($date, collect());
                $sRow = $hasBilltype ? $dayData->firstWhere('billtype', 'S') : $dayData->firstWhere('opaccode', 'RS');
                $pRow = $hasBilltype ? $dayData->firstWhere('billtype', 'P') : $dayData->firstWhere('opaccode', 'EP');
                if ($this->hasTable('salesm')) {
                    $gross = $this->salesSummary($date, $date);
                    $returns = $this->salesReturnSummary($date, $date);
                    $data['salesSeries'][] = round($gross['amount'] - $returns['amount'], 2);
                } else {
                    $data['salesSeries'][] = (float) ($sRow->total ?? 0);
                }
                $data['purchaseSeries'][] = (float) ($pRow->total ?? 0);
            }

            // Recent sales bills (last 10)
            if ($this->hasTable('salesm')) {
                $docCol = $this->hasCol('salesm', 'billno') ? 'billno' : ($this->hasCol('salesm', 'docno') ? 'docno' : 'slno');
                $nameCol = $this->hasCol('salesm', 'name') ? 'name' : null;
                $codeCol = $this->hasCol('salesm', 'custcode') ? 'custcode' : ($this->hasCol('salesm', 'accode') ? 'accode' : null);
                $amtCol = $this->hasCol('salesm', 'netamt') ? 'netamt' : ($this->hasCol('salesm', 'billamt') ? 'billamt' : null);

                $recentQuery = DB::table('salesm')->orderByDesc('slno')->limit(8);
                if ($this->hasCol('salesm', 'tdate')) {
                    $recentQuery->whereNotNull('tdate');
                }

                $selects = ['slno', 'tdate'];
                $selects[] = DB::raw("{$docCol} as docref");
                $selects[] = $codeCol ? DB::raw("{$codeCol} as partycode") : DB::raw("'' as partycode");
                $selects[] = $nameCol ? DB::raw("{$nameCol} as partyname") : DB::raw("'' as partyname");
                $selects[] = $amtCol ? DB::raw("{$amtCol} as amount") : DB::raw("0 as amount");

                $recent = $recentQuery->get($selects);
                $missingCodes = $recent
                    ->filter(fn($r) => trim((string) ($r->partyname ?? '')) === '' && trim((string) ($r->partycode ?? '')) !== '')
                    ->pluck('partycode')->unique()->values();
                $clientNames = ($missingCodes->isNotEmpty() && $this->hasTable('clients'))
                    ? DB::table('clients')->whereIn('code', $missingCodes)->pluck('name', 'code')
                    : collect();
                $data['recentSales'] = $recent->map(function ($r) use ($clientNames) {
                    $name = trim((string) ($r->partyname ?? ''));
                    $code = trim((string) ($r->partycode ?? ''));
                    if ($name === '' && $code !== '') {
                        $name = trim((string) ($clientNames->get($code) ?? ''));
                    }
                    return [
                        'docno'  => trim((string) ($r->docref ?? '')),
                        'date'   => $r->tdate ?? '',
                        'code'   => $code,
                        'name'   => $name,
                        'amount' => (float) ($r->amount ?? 0),
                    ];
                })->all();
            }

            // Pending accounts (customers with balance)
            if ($this->hasTable('clients')) {
                if ($this->hasCol('clients', 'balance')) {
                    $pending = DB::table('clients')
                        ->select('code', 'name', 'balance')
                        ->where('ctype', 'C')
                        ->whereRaw('ABS(balance) > 0')
                        ->orderByRaw('ABS(balance) DESC')
                        ->limit(8)
                        ->get();
                    $data['pendingAccounts'] = $pending->map(fn ($r) => [
                        'code' => trim((string) ($r->code ?? '')),
                        'name' => trim((string) ($r->name ?? '')),
                        'balance' => (float) ($r->balance ?? 0),
                    ])->all();
                } elseif ($this->hasTable('accountm') && $this->hasCol('accountm', 'clbal')) {
                    $pending = DB::table('clients as c')
                        ->join('accountm as a', 'a.accode', '=', 'c.code')
                        ->select('c.code', 'c.name', 'a.clbal as balance')
                        ->where('c.ctype', 'C')
                        ->whereRaw('ABS(a.clbal) > 0')
                        ->orderByRaw('ABS(a.clbal) DESC')
                        ->limit(8)
                        ->get();
                    $data['pendingAccounts'] = $pending->map(fn ($r) => [
                        'code' => trim((string) ($r->code ?? '')),
                        'name' => trim((string) ($r->name ?? '')),
                        'balance' => (float) ($r->balance ?? 0),
                    ])->all();
                }
            }

            // Yearly financials (single query instead of 20)
            $curYear = (int) date('Y');
            $yearlyRows = DB::table('daybook')
                ->selectRaw("YEAR($dbDateCol) as yr, billtype, COALESCE(SUM(amount), 0) as total")
                ->whereRaw("YEAR($dbDateCol) >= ?", [$curYear - 4])
                ->whereIn('billtype', ['S', 'P', 'R', 'A'])
                ->groupByRaw("YEAR($dbDateCol), billtype")
                ->get()->groupBy('yr');
            for ($y = $curYear; $y >= $curYear - 4; $y--) {
                $yData     = $yearlyRows->get((string) $y, collect());
                $sales     = (float) ($yData->firstWhere('billtype', 'S')->total ?? 0);
                $purchases = (float) ($yData->firstWhere('billtype', 'P')->total ?? 0);
                $receipts  = (float) ($yData->firstWhere('billtype', 'R')->total ?? 0);
                $payments  = (float) ($yData->firstWhere('billtype', 'A')->total ?? 0);
                $data['yearlyFinancials'][] = [
                    'year'      => $y,
                    'sales'     => $sales,
                    'purchases' => $purchases,
                    'gross'     => $sales - $purchases,
                    'receipts'  => $receipts,
                    'payments'  => $payments,
                    'cashFlow'  => $receipts - $payments,
                ];
            }
        } catch (\Throwable) {
            // Return defaults on any DB error
        }

        return $data;
    }

    private function getCreditBillSummary(Request $request): array
    {
        $empty = [
            'rows' => [],
            'totals' => ['count' => 0, 'billamt' => 0.0, 'ramt' => 0.0, 'bal' => 0.0],
        ];

        if (!$this->hasTable('salesm') || !$this->hasCol('salesm', 'bal')) {
            return $empty;
        }

        try {
            $rl = max(1, (int) $request->session()->get('gilevel', 1));
            $billCol = $this->hasCol('salesm', 'billno') ? 'billno' : ($this->hasCol('salesm', 'docno') ? 'docno' : 'slno');
            $dateCol = $this->hasCol('salesm', 'tdate') ? 'tdate' : null;
            $custCodeCol = $this->hasCol('salesm', 'custcode') ? 'custcode' : null;
            $custNameCol = $this->hasCol('salesm', 'custname') ? 'custname' : ($this->hasCol('salesm', 'name') ? 'name' : null);
            $billAmtCol = $this->hasCol('salesm', 'netamt') ? 'netamt' : ($this->hasCol('salesm', 'billamt') ? 'billamt' : null);
            $rcvdCol = $this->hasCol('salesm', 'ramt') ? 'ramt' : null;
            $dueCol = $this->hasCol('salesm', 'duedate') ? 'duedate' : null;
            $statusCol = $this->hasCol('salesm', 'status') ? 'status' : null;

            $clientJoin = $custCodeCol && $this->hasTable('clients') && $this->hasCol('clients', 'code');
            $clientNameExpr = $clientJoin && $this->hasCol('clients', 'name') ? 'c.name' : 'NULL';
            $clientMobileExpr = $clientJoin && $this->hasCol('clients', 'mobile') ? 'c.mobile' : "''";
            $salesNameExpr = $custNameCol ? "s.{$custNameCol}" : 'NULL';
            $custCodeExpr = $custCodeCol ? "s.{$custCodeCol}" : "''";

            $query = DB::table('salesm as s')
                ->when($clientJoin, fn ($q) => $q->leftJoin('clients as c', "s.{$custCodeCol}", '=', 'c.code'))
                ->whereRaw('COALESCE(s.bal, 0) > 0.005')
                ->when($this->hasCol('salesm', 'control'), fn ($q) => $q->where('s.control', '<=', $rl));

            if ($statusCol) {
                $query->where(function ($q) use ($statusCol) {
                    $q->whereNull("s.{$statusCol}")
                        ->orWhere("s.{$statusCol}", '')
                        ->orWhere("s.{$statusCol}", '<>', 0);
                });
            }

            $totals = (clone $query)
                ->selectRaw(
                    'COUNT(*) as count, '
                    . ($billAmtCol ? "COALESCE(SUM(s.{$billAmtCol}), 0)" : '0') . ' as billamt, '
                    . ($rcvdCol ? "COALESCE(SUM(s.{$rcvdCol}), 0)" : '0') . ' as ramt, '
                    . 'COALESCE(SUM(s.bal), 0) as bal'
                )
                ->first();

            $rows = $query
                ->selectRaw("
                    TRIM(CAST(s.{$billCol} AS CHAR)) as billno,
                    " . ($dateCol ? "s.{$dateCol}" : 'NULL') . " as tdate,
                    TRIM(COALESCE({$custCodeExpr}, '')) as custcode,
                    TRIM(COALESCE({$clientNameExpr}, {$salesNameExpr}, '')) as cname,
                    TRIM(COALESCE({$clientMobileExpr}, '')) as mobile,
                    " . ($billAmtCol ? "COALESCE(s.{$billAmtCol}, 0)" : '0') . " as billamt,
                    " . ($rcvdCol ? "COALESCE(s.{$rcvdCol}, 0)" : '0') . " as ramt,
                    COALESCE(s.bal, 0) as bal,
                    " . ($dueCol ? "s.{$dueCol}" : 'NULL') . " as duedate
                ")
                ->orderByDesc('s.bal')
                ->limit(10)
                ->get()
                ->map(fn ($row) => [
                    'billno' => trim((string) ($row->billno ?? '')),
                    'tdate' => (string) ($row->tdate ?? ''),
                    'custcode' => trim((string) ($row->custcode ?? '')),
                    'cname' => trim((string) ($row->cname ?? '')),
                    'mobile' => trim((string) ($row->mobile ?? '')),
                    'billamt' => (float) ($row->billamt ?? 0),
                    'ramt' => (float) ($row->ramt ?? 0),
                    'bal' => (float) ($row->bal ?? 0),
                    'duedate' => (string) ($row->duedate ?? ''),
                ])
                ->all();

            return [
                'rows' => $rows,
                'totals' => [
                    'count' => (int) ($totals->count ?? 0),
                    'billamt' => (float) ($totals->billamt ?? 0),
                    'ramt' => (float) ($totals->ramt ?? 0),
                    'bal' => (float) ($totals->bal ?? 0),
                ],
            ];
        } catch (\Throwable) {
            return $empty;
        }
    }

    private function salesSummary(string $date1, string $date2): array
    {
        if (!$this->hasTable('salesm')) {
            return ['amount' => 0.0, 'weight' => 0.0, 'bills' => 0];
        }

        $amountCol = $this->hasCol('salesm', 'netamt') ? 'netamt' : ($this->hasCol('salesm', 'billamt') ? 'billamt' : null);
        $master = DB::table('salesm')
            ->whereBetween('tdate', [$date1, $date2])
            ->when($this->hasCol('salesm', 'status'), fn ($q) => $q->where('status', '<>', 0))
            ->when($this->hasCol('salesm', 'control'), fn ($q) => $q->where('control', '<=', 1))
            ->selectRaw(($amountCol ? "COALESCE(SUM($amountCol), 0)" : '0') . ' as amount, COUNT(DISTINCT slno) as bills')
            ->first();

        $weight = 0.0;
        if ($this->hasTable('salesd') && $this->hasCol('salesd', 'weight')) {
            $weight = (float) DB::table('salesd as d')
                ->join('salesm as m', 'm.slno', '=', 'd.slno')
                ->whereBetween('m.tdate', [$date1, $date2])
                ->when($this->hasCol('salesm', 'status'), fn ($q) => $q->where('m.status', '<>', 0))
                ->when($this->hasCol('salesm', 'control'), fn ($q) => $q->where('m.control', '<=', 1))
                ->sum('d.weight');
        }

        return [
            'amount' => (float) ($master->amount ?? 0),
            'weight' => $weight,
            'bills' => (int) ($master->bills ?? 0),
        ];
    }

    private function salesReturnSummary(string $date1, string $date2): array
    {
        if (!$this->hasTable('salesrm')) {
            return ['amount' => 0.0, 'weight' => 0.0, 'bills' => 0];
        }

        $amountCol = $this->hasCol('salesrm', 'netamt') ? 'netamt' : ($this->hasCol('salesrm', 'billamt') ? 'billamt' : null);
        $master = DB::table('salesrm')
            ->whereBetween('tdate', [$date1, $date2])
            ->when($this->hasCol('salesrm', 'status'), fn ($q) => $q->where('status', '<>', 0))
            ->when($this->hasCol('salesrm', 'control'), fn ($q) => $q->where('control', '<=', 1))
            ->selectRaw(($amountCol ? "COALESCE(SUM($amountCol), 0)" : '0') . ' as amount, COUNT(DISTINCT slno) as bills')
            ->first();

        $weight = 0.0;
        if ($this->hasTable('salesrd') && $this->hasCol('salesrd', 'weight')) {
            $weight = (float) DB::table('salesrd as d')
                ->join('salesrm as m', 'm.slno', '=', 'd.slno')
                ->whereBetween('m.tdate', [$date1, $date2])
                ->when($this->hasCol('salesrm', 'status'), fn ($q) => $q->where('m.status', '<>', 0))
                ->when($this->hasCol('salesrm', 'control'), fn ($q) => $q->where('m.control', '<=', 1))
                ->sum('d.weight');
        }

        return [
            'amount' => (float) ($master->amount ?? 0),
            'weight' => $weight,
            'bills' => (int) ($master->bills ?? 0),
        ];
    }

    private function purchaseSummary(string $date1, string $date2): array
    {
        if (!$this->hasTable('purchasem')) {
            return ['amount' => 0.0, 'weight' => 0.0, 'bills' => 0];
        }

        $amountCol = $this->hasCol('purchasem', 'netamt')
            ? 'netamt'
            : ($this->hasCol('purchasem', 'billamt') ? 'billamt' : null);

        $master = DB::table('purchasem')
            ->whereBetween('tdate', [$date1, $date2])
            ->when($this->hasCol('purchasem', 'status'), fn ($q) => $q->where('status', '<>', 0))
            ->when($this->hasCol('purchasem', 'control'), fn ($q) => $q->where('control', '<=', 1))
            ->selectRaw(($amountCol ? "COALESCE(SUM($amountCol), 0)" : '0') . ' as amount, COUNT(DISTINCT slno) as bills')
            ->first();

        $weight = 0.0;
        if ($this->hasTable('purchased') && $this->hasCol('purchased', 'weight')) {
            $weight = (float) DB::table('purchased as d')
                ->join('purchasem as m', 'm.slno', '=', 'd.slno')
                ->whereBetween('m.tdate', [$date1, $date2])
                ->when($this->hasCol('purchasem', 'status'), fn ($q) => $q->where('m.status', '<>', 0))
                ->when($this->hasCol('purchasem', 'control'), fn ($q) => $q->where('m.control', '<=', 1))
                ->sum('d.weight');
        }

        return [
            'amount' => (float) ($master->amount ?? 0),
            'weight' => $weight,
            'bills' => (int) ($master->bills ?? 0),
        ];
    }

    private function hasCol(string $table, string $column): bool
    {
        $table = strtolower($table);
        if (!array_key_exists($table, $this->columnCache)) {
            $this->columnCache[$table] = $this->hasTable($table)
                ? array_map('strtolower', $this->columnList($table))
                : [];
        }

        return in_array(strtolower($column), $this->columnCache[$table], true);
    }
}
