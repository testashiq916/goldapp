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

    private function getDashboardData(): array
    {
        $data = [
            'todaySales' => 0,
            'todayPurchase' => 0,
            'monthSales' => 0,
            'monthPurchase' => 0,
            'todaySalesBills' => 0,
            'todayPurchaseBills' => 0,
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
                $data['monthlySalesSeries'][]   = (float) ($sRow->total ?? 0);
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
                $data['salesSeries'][]    = (float) ($sRow->total ?? 0);
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
