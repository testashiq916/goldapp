<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class FinancialStatementController extends Controller
{
    private function requireLogin(Request $request): void
    {
        if (!$request->session()->has('user_code')) {
            abort(redirect('/login'));
        }
    }

    private function json(array $payload, int $status = 200): JsonResponse
    {
        return response()->json($payload, $status);
    }

    private function hasCol(string $table, string $col): bool
    {
        static $cache = [];
        $key = $table . ':' . strtolower($col);
        if (!isset($cache[$key])) {
            $cache[$key] = Schema::hasColumn($table, $col);
        }
        return $cache[$key];
    }

    public function index(Request $request): View|RedirectResponse
    {
        $this->requireLogin($request);
        return view('reports.financial-statements');
    }

    public function api(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return $this->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $action = strtolower(trim((string) $request->input('action', '')));
        $control = (int) ($request->session()->get('gilevel', 1));
        if ($control <= 0) $control = 1;

        switch ($action) {
            case 'init':
                return $this->actionInit($request, $control);
            case 'data':
                return $this->actionData($request, $control);
            default:
                return $this->json(['success' => false, 'error' => 'Invalid action'], 400);
        }
    }

    // ── Init: return available years ──
    private function actionInit(Request $request, int $control): JsonResponse
    {
        $years = [];
        if ($this->hasTable('daybook') && $this->hasCol('daybook', 'tdate')) {
            $minMax = DB::table('daybook')
                ->selectRaw('MIN(YEAR(tdate)) AS min_yr, MAX(YEAR(tdate)) AS max_yr')
                ->first();
            if ($minMax && $minMax->max_yr) {
                $curYear = (int) date('Y');
                $maxYr = max((int) $minMax->max_yr, $curYear);
                $minYr = max((int) ($minMax->min_yr ?: $maxYr - 5), $maxYr - 6);
                for ($y = $maxYr; $y >= $minYr; $y--) {
                    $years[] = $y;
                }
            }
        }
        if (empty($years)) {
            $curYear = (int) date('Y');
            for ($y = $curYear; $y >= $curYear - 3; $y--) {
                $years[] = $y;
            }
        }

        // Financial year start date from session
        $fyStart = trim((string) $request->session()->get('gdtstartdate', ''));

        return $this->json([
            'success' => true,
            'years' => $years,
            'fyStart' => $fyStart,
            'control' => $control,
        ]);
    }

    // ── Data: return financial data for a tab and period ──
    private function actionData(Request $request, int $control): JsonResponse
    {
        $tab = strtolower(trim((string) $request->input('tab', 'income')));
        $period = strtolower(trim((string) $request->input('period', 'annual')));
        $years = $request->input('years', []);

        if (!is_array($years) || empty($years)) {
            $years = [(int) date('Y')];
        }
        $years = array_map('intval', $years);

        $opbalCol = ($control === 1) ? 'opbal' : 'opbalb';

        switch ($tab) {
            case 'income':
                return $this->getIncomeStatement($years, $control, $opbalCol);
            case 'balance':
                return $this->getBalanceSheet($years, $control, $opbalCol);
            case 'cashflow':
                return $this->getCashFlow($years, $control, $opbalCol);
            case 'trial':
                return $this->getTrialBalance($years, $control, $opbalCol);
            default:
                return $this->json(['success' => false, 'error' => 'Invalid tab']);
        }
    }

    // ── Compute account balances per year ──
    private function getAccountBalances(array $accodes, array $years, int $control, string $opbalCol): array
    {
        if (empty($accodes)) return [];

        $balances = [];
        foreach ($accodes as $ac) {
            $balances[$ac] = [];
        }

        foreach ($years as $year) {
            $startDate = $year . '-01-01';
            $endDate = $year . '-12-31';

            // Get daybook sums for each account for this year
            if ($this->hasTable('daybook')) {
                $sums = DB::table('daybook')
                    ->selectRaw('TRIM(accode) AS accode, SUM(amount) AS total')
                    ->where('control', $control)
                    ->whereBetween('tdate', [$startDate, $endDate])
                    ->whereIn(DB::raw('TRIM(accode)'), $accodes)
                    ->groupBy(DB::raw('TRIM(accode)'))
                    ->get();

                foreach ($sums as $row) {
                    $ac = trim($row->accode);
                    if (isset($balances[$ac])) {
                        $balances[$ac][$year] = round((float) $row->total, 2);
                    }
                }
            }

            // Fill missing with 0
            foreach ($accodes as $ac) {
                if (!isset($balances[$ac][$year])) {
                    $balances[$ac][$year] = 0;
                }
            }
        }

        return $balances;
    }

    // ── Get cumulative balances (opening + transactions up to year end) ──
    private function getCumulativeBalances(array $accodes, array $years, int $control, string $opbalCol): array
    {
        if (empty($accodes)) return [];

        // Get opening balances
        $opBals = [];
        if ($this->hasTable('accountm') && $this->hasCol('accountm', $opbalCol)) {
            $rows = DB::table('accountm')
                ->selectRaw("TRIM(accode) AS accode, COALESCE($opbalCol, 0) AS opbal")
                ->whereIn(DB::raw('TRIM(accode)'), $accodes)
                ->get();
            foreach ($rows as $r) {
                $opBals[trim($r->accode)] = (float) $r->opbal;
            }
        }

        $balances = [];
        foreach ($accodes as $ac) {
            $balances[$ac] = [];
        }

        foreach ($years as $year) {
            $endDate = $year . '-12-31';

            // Get cumulative daybook sums up to year end
            $sums = [];
            if ($this->hasTable('daybook')) {
                $rows = DB::table('daybook')
                    ->selectRaw('TRIM(accode) AS accode, SUM(amount) AS total')
                    ->where('control', $control)
                    ->where('tdate', '<=', $endDate)
                    ->whereIn(DB::raw('TRIM(accode)'), $accodes)
                    ->groupBy(DB::raw('TRIM(accode)'))
                    ->get();
                foreach ($rows as $r) {
                    $sums[trim($r->accode)] = (float) $r->total;
                }
            }

            foreach ($accodes as $ac) {
                $ob = $opBals[$ac] ?? 0;
                $txn = $sums[$ac] ?? 0;
                $balances[$ac][$year] = round($ob + $txn, 2);
            }
        }

        return $balances;
    }

    // ── Income Statement (P&L) ──
    private function getIncomeStatement(array $years, int $control, string $opbalCol): JsonResponse
    {
        $groups = $this->loadGroups(['R', 'E']);
        $accounts = $this->loadAccountsByType(['R', 'E']);

        $allCodes = array_column($accounts, 'accode');
        $yearBalances = $this->getAccountBalances($allCodes, $years, $control, $opbalCol);

        // Build sections
        $revenueSection = $this->buildSection('R', 'Revenue', $groups, $accounts, $yearBalances, $years);
        $expenseSection = $this->buildSection('E', 'Expenses', $groups, $accounts, $yearBalances, $years);

        // Compute totals
        $revTotals = [];
        $expTotals = [];
        $netIncome = [];
        foreach ($years as $y) {
            $revTotals[$y] = abs($revenueSection['totals'][$y] ?? 0);
            $expTotals[$y] = abs($expenseSection['totals'][$y] ?? 0);
            $netIncome[$y] = $revTotals[$y] - $expTotals[$y];
        }

        return $this->json([
            'success' => true,
            'sections' => [
                $revenueSection,
                $expenseSection,
            ],
            'summary' => [
                ['label' => 'Total Revenue', 'values' => $revTotals, 'bold' => true],
                ['label' => 'Total Expenses', 'values' => $expTotals, 'bold' => true],
                ['label' => 'Net Income', 'values' => $netIncome, 'bold' => true, 'highlight' => true],
            ],
            'years' => $years,
        ]);
    }

    // ── Balance Sheet ──
    private function getBalanceSheet(array $years, int $control, string $opbalCol): JsonResponse
    {
        $groups = $this->loadGroups(['A', 'L']);
        $accounts = $this->loadAccountsByType(['A', 'L']);

        $allCodes = array_column($accounts, 'accode');
        $yearBalances = $this->getCumulativeBalances($allCodes, $years, $control, $opbalCol);

        $assetSection = $this->buildSection('A', 'Assets', $groups, $accounts, $yearBalances, $years);
        $liabSection = $this->buildSection('L', 'Liabilities', $groups, $accounts, $yearBalances, $years);

        $assetTotals = [];
        $liabTotals = [];
        $equity = [];
        foreach ($years as $y) {
            $assetTotals[$y] = abs($assetSection['totals'][$y] ?? 0);
            $liabTotals[$y] = abs($liabSection['totals'][$y] ?? 0);
            $equity[$y] = $assetTotals[$y] - $liabTotals[$y];
        }

        return $this->json([
            'success' => true,
            'sections' => [
                $assetSection,
                $liabSection,
            ],
            'summary' => [
                ['label' => 'Total Assets', 'values' => $assetTotals, 'bold' => true],
                ['label' => 'Total Liabilities', 'values' => $liabTotals, 'bold' => true],
                ['label' => 'Equity (Assets - Liabilities)', 'values' => $equity, 'bold' => true, 'highlight' => true],
            ],
            'years' => $years,
        ]);
    }

    // ── Cash Flow ──
    private function getCashFlow(array $years, int $control, string $opbalCol): JsonResponse
    {
        // Cash flow: group by actype1 and show net movement per year
        $types = [
            'R' => 'Operating Activities (Revenue)',
            'E' => 'Operating Activities (Expenses)',
            'A' => 'Investing Activities (Assets)',
            'L' => 'Financing Activities (Liabilities)',
        ];

        $accounts = $this->loadAccountsByType(['R', 'E', 'A', 'L']);
        $allCodes = array_column($accounts, 'accode');
        $yearBalances = $this->getAccountBalances($allCodes, $years, $control, $opbalCol);

        // Group by actype1
        $typeTotals = [];
        foreach ($types as $t => $label) {
            $typeTotals[$t] = [];
            foreach ($years as $y) {
                $typeTotals[$t][$y] = 0;
            }
        }

        foreach ($accounts as $ac) {
            $t = strtoupper($ac['actype1'] ?? '');
            if (!isset($typeTotals[$t])) continue;
            foreach ($years as $y) {
                $typeTotals[$t][$y] += $yearBalances[$ac['accode']][$y] ?? 0;
            }
        }

        // Cash/Bank ending balance
        $cashAccounts = [];
        if ($this->hasTable('accountm')) {
            $rows = DB::table('accountm')
                ->selectRaw("TRIM(accode) AS accode, TRIM(name) AS name")
                ->whereRaw("TRIM(actype2) IN ('H','B')")
                ->when($this->hasCol('accountm', 'removed'), fn($q) => $q->whereRaw('(removed <> 1 OR removed IS NULL)'))
                ->get()->all();
            $cashAccounts = array_map(fn($r) => ['accode' => $r->accode, 'name' => $r->name], $rows);
        }
        $cashCodes = array_column($cashAccounts, 'accode');
        $cashBals = $this->getCumulativeBalances($cashCodes, $years, $control, $opbalCol);

        $cashEndTotals = [];
        foreach ($years as $y) {
            $cashEndTotals[$y] = 0;
            foreach ($cashCodes as $cc) {
                $cashEndTotals[$y] += $cashBals[$cc][$y] ?? 0;
            }
            $cashEndTotals[$y] = round($cashEndTotals[$y], 2);
        }

        // Net operating = Revenue + Expense movements
        $opCash = [];
        $investCash = [];
        $finCash = [];
        foreach ($years as $y) {
            $opCash[$y] = round(($typeTotals['R'][$y] ?? 0) + ($typeTotals['E'][$y] ?? 0), 2);
            $investCash[$y] = round($typeTotals['A'][$y] ?? 0, 2);
            $finCash[$y] = round($typeTotals['L'][$y] ?? 0, 2);
        }

        return $this->json([
            'success' => true,
            'sections' => [],
            'summary' => [
                ['label' => 'Cash from Operating Activities', 'values' => $opCash, 'bold' => true],
                ['label' => 'Cash from Investing Activities', 'values' => $investCash, 'bold' => true],
                ['label' => 'Cash from Financing Activities', 'values' => $finCash, 'bold' => true],
                ['label' => 'Cash & Equivalents, End of Period', 'values' => $cashEndTotals, 'bold' => true, 'highlight' => true],
            ],
            'years' => $years,
        ]);
    }

    // ── Trial Balance ──
    private function getTrialBalance(array $years, int $control, string $opbalCol): JsonResponse
    {
        $groups = $this->loadGroups(['A', 'L', 'R', 'E']);
        $accounts = $this->loadAccountsByType(['A', 'L', 'R', 'E']);

        $allCodes = array_column($accounts, 'accode');
        $yearBalances = $this->getCumulativeBalances($allCodes, $years, $control, $opbalCol);

        $sections = [];
        foreach (['A' => 'Assets', 'L' => 'Liabilities', 'R' => 'Revenue', 'E' => 'Expenses'] as $type => $label) {
            $sections[] = $this->buildSection($type, $label, $groups, $accounts, $yearBalances, $years);
        }

        // Totals: Debit = Assets + Expenses, Credit = Liabilities + Revenue
        $debitTotals = [];
        $creditTotals = [];
        foreach ($years as $y) {
            $debitTotals[$y] = abs($sections[0]['totals'][$y] ?? 0) + abs($sections[3]['totals'][$y] ?? 0);
            $creditTotals[$y] = abs($sections[1]['totals'][$y] ?? 0) + abs($sections[2]['totals'][$y] ?? 0);
        }

        return $this->json([
            'success' => true,
            'sections' => $sections,
            'summary' => [
                ['label' => 'Total Debit (Assets + Expenses)', 'values' => $debitTotals, 'bold' => true],
                ['label' => 'Total Credit (Liabilities + Revenue)', 'values' => $creditTotals, 'bold' => true],
            ],
            'years' => $years,
        ]);
    }

    // ── Helpers ──

    private function loadGroups(array $types): array
    {
        if (!$this->hasTable('accountg')) return [];

        $typeCol = $this->hasCol('accountg', 'actype1') ? 'actype1' : ($this->hasCol('accountg', 'actype') ? 'actype' : null);
        if (!$typeCol) return [];

        return DB::table('accountg')
            ->selectRaw("TRIM(grcode) AS grcode, TRIM(name) AS name, TRIM($typeCol) AS actype, pos")
            ->whereIn(DB::raw("UPPER(TRIM($typeCol))"), $types)
            ->orderBy('pos')
            ->orderBy('grcode')
            ->get()
            ->map(fn($r) => (array) $r)
            ->all();
    }

    private function loadAccountsByType(array $types): array
    {
        if (!$this->hasTable('accountm')) return [];

        $typeCol = $this->hasCol('accountm', 'actype1') ? 'actype1' : null;
        if (!$typeCol) return [];

        $query = DB::table('accountm')
            ->selectRaw("TRIM(accode) AS accode, TRIM(name) AS name, TRIM(grcode) AS grcode, TRIM($typeCol) AS actype1")
            ->whereIn(DB::raw("UPPER(TRIM($typeCol))"), $types);

        if ($this->hasCol('accountm', 'removed')) {
            $query->whereRaw('(removed <> 1 OR removed IS NULL)');
        }

        return $query->orderBy('accode')
            ->get()
            ->map(fn($r) => (array) $r)
            ->all();
    }

    private function buildSection(string $type, string $label, array $groups, array $accounts, array $yearBalances, array $years): array
    {
        $typeGroups = array_filter($groups, fn($g) => strtoupper($g['actype']) === $type);
        $typeAccounts = array_filter($accounts, fn($a) => strtoupper($a['actype1']) === $type);

        $rows = [];
        $sectionTotals = [];
        foreach ($years as $y) $sectionTotals[$y] = 0;

        // Group accounts by grcode
        $byGroup = [];
        foreach ($typeAccounts as $ac) {
            $gc = $ac['grcode'] ?? '';
            $byGroup[$gc][] = $ac;
        }

        foreach ($typeGroups as $grp) {
            $gc = $grp['grcode'] ?? '';
            $grpAccounts = $byGroup[$gc] ?? [];
            if (empty($grpAccounts)) continue;

            $grpTotals = [];
            foreach ($years as $y) $grpTotals[$y] = 0;

            $children = [];
            foreach ($grpAccounts as $ac) {
                $acVals = [];
                foreach ($years as $y) {
                    $val = $yearBalances[$ac['accode']][$y] ?? 0;
                    $acVals[$y] = round($val, 2);
                    $grpTotals[$y] += $val;
                }
                // Only include if any year has non-zero
                if (array_sum(array_map('abs', $acVals)) > 0) {
                    $children[] = [
                        'code' => $ac['accode'],
                        'name' => $ac['name'] ?? $ac['accode'],
                        'values' => $acVals,
                    ];
                }
            }

            if (!empty($children)) {
                foreach ($years as $y) {
                    $grpTotals[$y] = round($grpTotals[$y], 2);
                    $sectionTotals[$y] += $grpTotals[$y];
                }
                $rows[] = [
                    'group' => $grp['name'] ?? $gc,
                    'groupCode' => $gc,
                    'totals' => $grpTotals,
                    'children' => $children,
                ];
            }
        }

        // Handle ungrouped accounts
        $ungrouped = [];
        $usedGroups = array_column($typeGroups, 'grcode');
        foreach ($byGroup as $gc => $accs) {
            if (!in_array($gc, $usedGroups)) {
                foreach ($accs as $ac) $ungrouped[] = $ac;
            }
        }
        if (!empty($ungrouped)) {
            $grpTotals = [];
            foreach ($years as $y) $grpTotals[$y] = 0;
            $children = [];
            foreach ($ungrouped as $ac) {
                $acVals = [];
                foreach ($years as $y) {
                    $val = $yearBalances[$ac['accode']][$y] ?? 0;
                    $acVals[$y] = round($val, 2);
                    $grpTotals[$y] += $val;
                }
                if (array_sum(array_map('abs', $acVals)) > 0) {
                    $children[] = [
                        'code' => $ac['accode'],
                        'name' => $ac['name'] ?? $ac['accode'],
                        'values' => $acVals,
                    ];
                }
            }
            if (!empty($children)) {
                foreach ($years as $y) {
                    $grpTotals[$y] = round($grpTotals[$y], 2);
                    $sectionTotals[$y] += $grpTotals[$y];
                }
                $rows[] = [
                    'group' => 'Other',
                    'groupCode' => '',
                    'totals' => $grpTotals,
                    'children' => $children,
                ];
            }
        }

        foreach ($years as $y) $sectionTotals[$y] = round($sectionTotals[$y], 2);

        return [
            'label' => $label,
            'type' => $type,
            'rows' => $rows,
            'totals' => $sectionTotals,
        ];
    }
}
