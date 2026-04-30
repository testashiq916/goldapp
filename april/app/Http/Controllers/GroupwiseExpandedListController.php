<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GroupwiseExpandedListController extends Controller
{
    public function index(Request $request): View|RedirectResponse|StreamedResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $gilevel = max(1, (int) $request->session()->get('gilevel', 1));
        $today = date('Y-m-d');
        $date1 = $this->normalizeDate((string) $request->query('date1', '')) ?: $today;
        $date2 = $this->normalizeDate((string) $request->query('date2', '')) ?: $today;
        $term = $request->boolean('term');
        $withBalanceOnly = $request->boolean('with_balance_only', true);
        $groupOnly = $request->boolean('grp_only', true);
        $export = strtolower(trim((string) $request->query('export', '')));
        $groupCode = strtoupper(trim((string) $request->query('grcode', '')));
        $show = $request->boolean('show') || $export !== '';

        $groupOptions = $this->groupOptions();
        $rows = [];
        $summary = [
            'row_count' => 0,
            'account_count' => 0,
            'group_count' => 0,
            'db_total' => 0.0,
            'cr_total' => 0.0,
        ];
        $heading = $term
            ? 'Groupwise Expanded Account List From ' . date('d/m/Y', strtotime($date1)) . ' To ' . date('d/m/Y', strtotime($date2))
            : 'Groupwise Expanded Account List Upto ' . date('d/m/Y', strtotime($date1));

        if ($show) {
            $rows = $this->loadRows(
                $gilevel,
                $date1,
                $date2,
                $term,
                $withBalanceOnly,
                $groupOnly,
                $groupCode
            );

            foreach ($rows as $row) {
                if ($row['ctype'] === 'A') {
                    $summary['account_count']++;
                    if ($row['dbcr'] === 'Db') {
                        $summary['db_total'] += $row['signed_amount'];
                    } else {
                        $summary['cr_total'] += $row['signed_amount'];
                    }
                } else {
                    $summary['group_count']++;
                }
            }

            $summary['row_count'] = count($rows);
        }

        if ($show && $export === 'csv') {
            return $this->exportCsv($rows);
        }

        if ($show && $export === 'ledgerprint') {
            $ledgerSections = $this->buildLedgerSections(
                collect($rows)->where('ctype', 'A')->values(),
                $gilevel,
                $date1,
                $date2,
                $term,
                $this->normalizeDate((string) $request->session()->get('gdtstartdate', '')) ?: $date1
            );

            return view('reports.groupwise-expanded-ledgers-print', [
                'title' => 'Print All Ledger',
                'heading' => $heading,
                'term' => $term,
                'date1' => $date1,
                'date2' => $date2,
                'ledgerSections' => $ledgerSections,
            ]);
        }

        return view('reports.groupwise-expanded-list', [
            'title' => 'Groupwise Expanded List',
            'gilevel' => $gilevel,
            'date1' => $date1,
            'date2' => $date2,
            'term' => $term,
            'withBalanceOnly' => $withBalanceOnly,
            'groupOnly' => $groupOnly,
            'groupCode' => $groupCode,
            'groupOptions' => $groupOptions,
            'show' => $show,
            'rows' => $rows,
            'summary' => $summary,
            'heading' => $heading,
        ]);
    }

    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }

        return null;
    }

    private function groupOptions(): array
    {
        if (!$this->hasTable('accountg')) {
            return [];
        }

        return DB::table('accountg')
            ->selectRaw('TRIM(grcode) as grcode, TRIM(COALESCE(name, grcode)) as name')
            ->orderBy('name')
            ->get()
            ->map(fn ($row) => [
                'grcode' => (string) $row->grcode,
                'name' => (string) $row->name,
            ])
            ->all();
    }

    private function loadRows(
        int $gilevel,
        string $date1,
        string $date2,
        bool $term,
        bool $withBalanceOnly,
        bool $groupOnly,
        string $groupCode
    ): array {
        if (!$this->hasTable('accountg') || !$this->hasTable('accountm')) {
            return [];
        }

        $groups = DB::table('accountg')
            ->selectRaw('TRIM(grcode) as grcode, TRIM(COALESCE(name, grcode)) as name, TRIM(COALESCE(mgrp, "")) as mgrp')
            ->orderBy('name')
            ->get()
            ->map(fn ($row) => [
                'grcode' => (string) $row->grcode,
                'name' => (string) $row->name,
                'mgrp' => (string) $row->mgrp,
            ])
            ->all();

        $groupMap = [];
        $childrenByParent = [];
        foreach ($groups as $group) {
            $groupMap[$group['grcode']] = $group;
            $childrenByParent[$group['mgrp']][] = $group['grcode'];
        }

        $opColumn = $gilevel === 1 ? 'opbal' : 'opbalb';
        $accounts = DB::table('accountm')
            ->selectRaw("
                TRIM(accode) as accode,
                TRIM(COALESCE(name, accode)) as name,
                TRIM(COALESCE(grcode, '')) as grcode,
                COALESCE($opColumn, 0) as opening_base
            ")
            ->where('control', '<=', $gilevel)
            ->orderBy('name')
            ->get()
            ->map(fn ($row) => [
                'accode' => (string) $row->accode,
                'name' => (string) $row->name,
                'grcode' => (string) $row->grcode,
                'opening_base' => (float) $row->opening_base,
            ])
            ->all();

        $accountsByGroup = [];
        $codes = [];
        foreach ($accounts as $account) {
            $accountsByGroup[$account['grcode']][] = $account;
            $codes[] = $account['accode'];
        }

        $balanceByCode = array_fill_keys($codes, 0.0);
        if (!empty($codes) && $this->hasTable('daybook')) {
            if ($term) {
                $amounts = DB::table('daybook')
                    ->selectRaw('TRIM(accode) as accode, COALESCE(SUM(amount), 0) as amount')
                    ->whereIn('accode', $codes)
                    ->where('control', '<=', $gilevel)
                    ->whereBetween('tdate', [$date1, $date2])
                    ->groupBy('accode')
                    ->get();

                foreach ($amounts as $row) {
                    $balanceByCode[trim((string) $row->accode)] = round((float) $row->amount, 2);
                }
            } else {
                $amounts = DB::table('daybook')
                    ->selectRaw('TRIM(accode) as accode, COALESCE(SUM(amount), 0) as amount')
                    ->whereIn('accode', $codes)
                    ->where('control', '<=', $gilevel)
                    ->where('tdate', '<=', $date1)
                    ->groupBy('accode')
                    ->get();

                foreach ($accounts as $account) {
                    $balanceByCode[$account['accode']] = round($account['opening_base'], 2);
                }

                foreach ($amounts as $row) {
                    $code = trim((string) $row->accode);
                    $balanceByCode[$code] = round(($balanceByCode[$code] ?? 0) + (float) $row->amount, 2);
                }
            }
        } elseif (!$term) {
            foreach ($accounts as $account) {
                $balanceByCode[$account['accode']] = round($account['opening_base'], 2);
            }
        }

        $rootGroups = [];
        if ($groupOnly && $groupCode !== '') {
            if (isset($groupMap[$groupCode])) {
                $rootGroups[] = $groupCode;
            }
        } else {
            $rootGroups = $childrenByParent['MGRP'] ?? [];
        }

        $rows = [];
        foreach ($rootGroups as $rootGroupCode) {
            $this->appendGroupRows(
                $rootGroupCode,
                1,
                $rows,
                $groupMap,
                $childrenByParent,
                $accountsByGroup,
                $balanceByCode,
                $withBalanceOnly
            );
        }

        return $rows;
    }

    private function appendGroupRows(
        string $groupCode,
        int $depth,
        array &$rows,
        array $groupMap,
        array $childrenByParent,
        array $accountsByGroup,
        array $balanceByCode,
        bool $withBalanceOnly
    ): array {
        if (!isset($groupMap[$groupCode])) {
            return ['total' => 0.0, 'account_count' => 0];
        }

        $childRows = [];
        $groupTotal = 0.0;
        $accountCount = 0;

        $directAccounts = $accountsByGroup[$groupCode] ?? [];
        foreach ($directAccounts as $account) {
            $signed = round((float) ($balanceByCode[$account['accode']] ?? 0), 2);
            if ($withBalanceOnly && abs($signed) < 0.0001) {
                continue;
            }

            $childRows[] = [
                'accode' => $account['accode'],
                'acname' => str_repeat('_', $depth * 3) . $account['name'],
                'display_name' => str_repeat(' ', $depth * 3) . $account['name'],
                'amount' => abs($signed),
                'dbcr' => $signed <= 0 ? 'Db' : 'Cr',
                'signed_amount' => abs($signed),
                'stg' => $depth + 1,
                'ctype' => 'A',
                'raw_name' => $account['name'],
            ];
            $groupTotal += $signed;
            $accountCount++;
        }

        foreach ($childrenByParent[$groupCode] ?? [] as $childGroupCode) {
            $childSummary = $this->appendGroupRows(
                $childGroupCode,
                $depth + 1,
                $childRows,
                $groupMap,
                $childrenByParent,
                $accountsByGroup,
                $balanceByCode,
                $withBalanceOnly
            );
            $groupTotal += $childSummary['total'];
            $accountCount += $childSummary['account_count'];
        }

        if ($withBalanceOnly && abs($groupTotal) < 0.0001 && $accountCount === 0) {
            return ['total' => 0.0, 'account_count' => 0];
        }

        $rows[] = [
            'accode' => $groupCode,
            'acname' => str_repeat('_', max(0, ($depth - 1) * 3)) . $groupMap[$groupCode]['name'],
            'display_name' => str_repeat(' ', max(0, ($depth - 1) * 3)) . $groupMap[$groupCode]['name'],
            'amount' => abs($groupTotal),
            'dbcr' => $groupTotal <= 0 ? 'Db' : 'Cr',
            'signed_amount' => abs($groupTotal),
            'stg' => $depth,
            'ctype' => 'G',
            'raw_name' => $groupMap[$groupCode]['name'],
        ];

        foreach ($childRows as $childRow) {
            $rows[] = $childRow;
        }

        return ['total' => round($groupTotal, 2), 'account_count' => $accountCount];
    }

    private function exportCsv(array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Code', 'Name', 'Amount', 'Db/Cr', 'Type', 'Stage']);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['accode'],
                    $row['display_name'] ?? $row['raw_name'],
                    $row['amount'],
                    $row['dbcr'],
                    $row['ctype'],
                    $row['stg'],
                ]);
            }
            fclose($handle);
        }, 'groupwise_expanded_list_' . date('Ymd_His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function buildLedgerSections(
        Collection $accountRows,
        int $gilevel,
        string $date1,
        string $date2,
        bool $term,
        string $defaultFromDate
    ): array {
        if ($accountRows->isEmpty() || !$this->hasTable('accountm') || !$this->hasTable('daybook') || !$this->hasTable('daybookpart')) {
            return [];
        }

        $dateFrom = $term ? $date1 : $defaultFromDate;
        $dateTo = $term ? $date2 : $date1;
        $codes = $accountRows->pluck('accode')->map(fn ($code) => trim((string) $code))->filter()->values()->all();
        if (empty($codes)) {
            return [];
        }

        $opColumn = $gilevel === 1 ? 'opbal' : 'opbalb';
        $accounts = DB::table('accountm as a')
            ->leftJoin('clients as c', 'a.accode', '=', 'c.code')
            ->selectRaw("
                TRIM(a.accode) as accode,
                TRIM(COALESCE(a.name, a.accode)) as name,
                COALESCE(a.$opColumn, 0) as opening_base,
                TRIM(COALESCE(c.addr1, '')) as addr1,
                TRIM(COALESCE(c.addr2, '')) as addr2,
                TRIM(COALESCE(c.addr3, '')) as addr3,
                TRIM(COALESCE(c.city, '')) as city,
                TRIM(COALESCE(c.telephone, '')) as telephone
            ")
            ->whereIn('a.accode', $codes)
            ->get()
            ->keyBy(fn ($row) => trim((string) $row->accode));

        $openingMap = DB::table('daybook')
            ->selectRaw('TRIM(accode) as accode, COALESCE(SUM(amount), 0) as amount')
            ->whereIn('accode', $codes)
            ->where('control', '<=', $gilevel)
            ->where('tdate', '<', $dateFrom)
            ->groupBy('accode')
            ->get()
            ->keyBy(fn ($row) => trim((string) $row->accode));

        $ledgerRows = DB::table('daybook')
            ->join('daybookpart', 'daybook.slno', '=', 'daybookpart.slno')
            ->leftJoin('accountm as oth', DB::raw('TRIM(daybook.opaccode)'), '=', DB::raw('TRIM(oth.accode)'))
            ->selectRaw('
                TRIM(daybook.accode) as accode,
                daybook.tdate,
                daybook.amount,
                daybook.slno,
                TRIM(COALESCE(daybookpart.vchno, "")) as vchno,
                TRIM(COALESCE(daybookpart.particular, "")) as particular,
                TRIM(COALESCE(oth.name, "")) as othacname
            ')
            ->whereIn('daybook.accode', $codes)
            ->where('daybook.control', '<=', $gilevel)
            ->whereBetween('daybook.tdate', [$dateFrom, $dateTo])
            ->orderBy('daybook.accode')
            ->orderBy('daybook.tdate')
            ->orderBy('daybook.slno')
            ->get()
            ->groupBy(fn ($row) => trim((string) $row->accode));

        $sections = [];
        foreach ($accountRows as $accountRow) {
            $code = trim((string) $accountRow['accode']);
            $meta = $accounts->get($code);
            if (!$meta) {
                continue;
            }

            $openingBalance = round((float) $meta->opening_base + (float) ($openingMap->get($code)->amount ?? 0), 2);
            $running = $openingBalance;
            $totalDebit = $openingBalance < 0 ? abs($openingBalance) : 0.0;
            $totalCredit = $openingBalance > 0 ? abs($openingBalance) : 0.0;
            $lines = [];

            foreach ($ledgerRows->get($code, collect()) as $row) {
                $amount = round((float) $row->amount, 2);
                $running = round($running + $amount, 2);
                if ($amount < 0) {
                    $totalDebit += abs($amount);
                } elseif ($amount > 0) {
                    $totalCredit += abs($amount);
                }

                $lines[] = [
                    'date' => (string) $row->tdate,
                    'vchno' => trim((string) $row->vchno),
                    'particular' => trim((string) $row->particular),
                    'other_name' => trim((string) $row->othacname),
                    'debit' => $amount < 0 ? abs($amount) : 0.0,
                    'credit' => $amount > 0 ? $amount : 0.0,
                    'running_balance' => $running,
                ];
            }

            $address = implode(', ', array_filter([
                trim((string) $meta->addr1),
                trim((string) $meta->addr2),
                trim((string) $meta->addr3),
                trim((string) $meta->city),
                trim((string) $meta->telephone) !== '' ? 'Ph: ' . trim((string) $meta->telephone) : '',
            ]));

            $sections[] = [
                'accode' => $code,
                'name' => trim((string) $meta->name),
                'address' => $address,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'opening_balance' => $openingBalance,
                'closing_balance' => $running,
                'total_debit' => round($totalDebit, 2),
                'total_credit' => round($totalCredit, 2),
                'lines' => $lines,
            ];
        }

        return $sections;
    }
}
