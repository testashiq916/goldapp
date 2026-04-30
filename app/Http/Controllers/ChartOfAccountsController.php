<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChartOfAccountsController extends Controller
{
    public function index(Request $request): View|RedirectResponse|StreamedResponse
    {
        $startedAt = microtime(true);

        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $gilevel = max(1, (int) $request->session()->get('gilevel', 1));
        $type = trim((string) $request->query('type', 'All'));
        $validTypes = ['All', 'Assets Only', 'Liabilities Only', 'Expenses Only', 'Incomes Only'];
        if (!in_array($type, $validTypes, true)) {
            $type = 'All';
        }

        $groupCode = strtoupper(trim((string) $request->query('grcode', '')));
        $bshead = strtoupper(trim((string) $request->query('bshead', '')));
        $withBalance = $request->boolean('with_balance', true);
        $removedOnly = $request->boolean('removed_only');
        $show = $request->boolean('show', true);
        $export = strtolower(trim((string) $request->query('export', '')));
        $today = date('Y-m-d');

        $rows = [];
        $totals = [
            'count' => 0,
            'abs_balance' => 0.0,
        ];
        $loadRowsMs = 0.0;
        $lookupMs = 0.0;

        if ($show) {
            $loadRowsStart = microtime(true);
            $rows = $this->loadRows($gilevel, $today, $type, $groupCode, $bshead, $withBalance, $removedOnly);
            $loadRowsMs = round((microtime(true) - $loadRowsStart) * 1000, 2);
            $totals['count'] = count($rows);
            $totals['abs_balance'] = array_reduce($rows, fn ($carry, $row) => $carry + abs((float) ($row['balance'] ?? 0)), 0.0);
        }

        if ($show && $export === 'csv') {
            $this->logTiming('export', $startedAt, $loadRowsMs, $lookupMs, $rows, $request);
            return $this->exportCsv($rows);
        }

        $lookupStart = microtime(true);
        $groups = $this->groupOptions();
        $bsheads = $this->bsHeadOptions();
        $lookupMs = round((microtime(true) - $lookupStart) * 1000, 2);

        $this->logTiming('view', $startedAt, $loadRowsMs, $lookupMs, $rows, $request);

        return view('reports.chart-of-accounts', [
            'title' => 'Chart of Accounts',
            'type' => $type,
            'types' => $validTypes,
            'groupCode' => $groupCode,
            'bshead' => $bshead,
            'withBalance' => $withBalance,
            'removedOnly' => $removedOnly,
            'show' => $show,
            'today' => $today,
            'rows' => $rows,
            'totals' => $totals,
            'groups' => $groups,
            'bsheads' => $bsheads,
        ]);
    }

    private function loadRows(
        int $gilevel,
        string $asOfDate,
        string $type,
        string $groupCode,
        string $bshead,
        bool $withBalance,
        bool $removedOnly
    ): array {
        if (!$this->hasTable('accountm')) {
            return [];
        }

        $opCol = $gilevel === 1 ? 'opbal' : 'opbalb';
        $groupNameExpr = $this->hasTable('accountg') ? 'trim(coalesce(accountg.name, ""))' : '""';
        $groupSortExpr = $this->hasTable('accountg') && $this->hasCol('accountg', 'grp') ? 'trim(coalesce(accountg.grp, ""))' : 'trim(coalesce(accountm.grcode, ""))';

        $q = DB::table('accountm');
        if ($this->hasTable('accountg')) {
            $q->leftJoin('accountg', 'accountg.grcode', '=', 'accountm.grcode');
        }

        if ($this->hasTable('daybook')) {
            $movementSubquery = DB::table('daybook as d')
                ->selectRaw('trim(d.accode) as accode_key, coalesce(sum(d.amount), 0) as movement')
                ->where('d.control', '<=', $gilevel)
                ->where('d.tdate', '<=', $asOfDate)
                ->groupByRaw('trim(d.accode)');

            $q->leftJoinSub($movementSubquery, 'mv', function ($join) {
                $join->on(DB::raw('trim(accountm.accode)'), '=', 'mv.accode_key');
            });
            $movementExpr = 'coalesce(mv.movement, 0)';
        } elseif ($this->hasCol('accountm', 'amount')) {
            $movementExpr = 'coalesce(accountm.amount,0)';
        } else {
            $movementExpr = '0';
        }

        $q->selectRaw('trim(accountm.accode) as accode')
            ->selectRaw('trim(coalesce(accountm.name, "")) as acname')
            ->selectRaw('trim(coalesce(accountm.grcode, "")) as grcode')
            ->selectRaw("{$groupNameExpr} as group_name")
            ->selectRaw("{$groupSortExpr} as group_sort")
            ->selectRaw('trim(coalesce(accountm.bshead, "")) as bshead')
            ->selectRaw('trim(coalesce(accountm.actype1, "")) as actype1')
            ->selectRaw('trim(coalesce(accountm.actype2, "")) as actype2')
            ->selectRaw('coalesce(accountm.removed, 0) as removed_flag')
            ->selectRaw("coalesce(accountm.{$opCol}, 0) as opening_balance")
            ->selectRaw("{$movementExpr} as movement")
            ->selectRaw("(coalesce(accountm.{$opCol}, 0) + {$movementExpr}) as balance");

        if ($this->hasCol('accountm', 'hlp')) {
            $q->where('accountm.hlp', '<>', 0);
        }

        if ($type !== 'All') {
            $map = [
                'Assets Only' => 'A',
                'Liabilities Only' => 'L',
                'Expenses Only' => 'E',
                'Incomes Only' => 'R',
            ];
            $q->whereRaw('trim(coalesce(accountm.actype1, "")) = ?', [$map[$type]]);
        }

        if ($groupCode !== '') {
            $q->whereRaw('trim(coalesce(accountm.grcode, "")) = ?', [$groupCode]);
        }

        if ($bshead !== '') {
            $q->whereRaw('trim(coalesce(accountm.bshead, "")) = ?', [$bshead]);
        }

        if ($removedOnly) {
            $q->where('accountm.removed', 1);
        }

        if ($withBalance) {
            $q->whereRaw("(coalesce(accountm.{$opCol}, 0) + {$movementExpr}) <> 0");
        }

        $rows = $q->orderByRaw("{$groupSortExpr} asc")
            ->orderByRaw("{$groupNameExpr} asc")
            ->orderBy('accountm.name')
            ->get()
            ->map(function ($row) {
                $balance = (float) ($row->balance ?? 0);
                return [
                    'accode' => (string) $row->accode,
                    'acname' => (string) $row->acname,
                    'grcode' => (string) $row->grcode,
                    'group_name' => (string) $row->group_name,
                    'group_sort' => (string) $row->group_sort,
                    'bshead' => (string) $row->bshead,
                    'actype1' => (string) $row->actype1,
                    'actype2' => (string) $row->actype2,
                    'removed_flag' => (int) ($row->removed_flag ?? 0),
                    'opening_balance' => (float) ($row->opening_balance ?? 0),
                    'movement' => (float) ($row->movement ?? 0),
                    'balance' => $balance,
                    'balance_abs' => abs($balance),
                    'balance_type' => $balance < 0 ? 'Db' : 'Cr',
                ];
            })
            ->values()
            ->all();

        return $rows;
    }

    private function exportCsv(array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Group', 'Code', 'Account Name', 'BS Head', 'Type', 'Opening', 'Movement', 'Balance', 'Db/Cr', 'Removed']);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['group_name'],
                    $row['accode'],
                    $row['acname'],
                    $row['bshead'],
                    $row['actype1'],
                    $row['opening_balance'],
                    $row['movement'],
                    $row['balance_abs'],
                    $row['balance_type'],
                    $row['removed_flag'] ? 'Yes' : 'No',
                ]);
            }
            fclose($handle);
        }, 'chart_of_accounts_' . date('Ymd_His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function groupOptions(): array
    {
        if (!$this->hasTable('accountg')) {
            return [];
        }

        return DB::table('accountg')
            ->selectRaw('trim(grcode) as code, trim(coalesce(name, "")) as name')
            ->orderBy('name')
            ->get()
            ->map(fn ($row) => ['code' => (string) $row->code, 'name' => (string) $row->name])
            ->all();
    }

    private function bsHeadOptions(): array
    {
        if ($this->hasTable('accountgbs')) {
            return DB::table('accountgbs')
                ->selectRaw('trim(hcode) as code, trim(coalesce(hname, "")) as name')
                ->orderBy('hname')
                ->get()
                ->map(fn ($row) => ['code' => (string) $row->code, 'name' => (string) $row->name])
                ->all();
        }

        if (!$this->hasTable('accountm') || !$this->hasCol('accountm', 'bshead')) {
            return [];
        }

        return DB::table('accountm')
            ->selectRaw('distinct trim(bshead) as code')
            ->whereRaw('trim(coalesce(bshead, "")) <> \'\'')
            ->orderBy('code')
            ->get()
            ->map(fn ($row) => ['code' => (string) $row->code, 'name' => (string) $row->code])
            ->all();
    }

    private function hasCol(string $table, string $column): bool
    {
        return $this->hasTable($table) && Schema::hasColumn($table, $column);
    }

    private function logTiming(
        string $mode,
        float $startedAt,
        float $loadRowsMs,
        float $lookupMs,
        array $rows,
        Request $request
    ): void {
        Log::info('chart_of_accounts_timing', [
            'mode' => $mode,
            'path' => $request->path(),
            'query' => $request->query(),
            'rows' => count($rows),
            'load_rows_ms' => $loadRowsMs,
            'lookups_ms' => $lookupMs,
            'total_ms' => round((microtime(true) - $startedAt) * 1000, 2),
        ]);
    }
}
