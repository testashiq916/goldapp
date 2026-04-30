<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class SuspenseLedgerController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $gilevel = max(1, (int) $request->session()->get('gilevel', 1));
        $dateFrom = $this->normalizeDate((string) $request->query('date1', ''))
            ?: $this->normalizeDate((string) $request->session()->get('gdtstartdate', ''))
            ?: date('Y-m-01');
        $dateTo = $this->normalizeDate((string) $request->query('date2', '')) ?: date('Y-m-d');
        $accode = strtoupper(trim((string) $request->query('accode', '')));
        $scode = strtoupper(trim((string) $request->query('scode', '')));
        $useScode = $request->boolean('use_scode', $scode !== '');
        $type = trim((string) $request->query('type', 'entry'));
        $pendingOnly = $request->boolean('pending_only', true);
        $show = $request->boolean('show') || $accode !== '';

        $accountOptions = $this->accountOptions();
        $suspenseOptions = $this->suspenseOptions();
        $account = null;
        $openingBalance = 0.0;
        $closingBalance = 0.0;
        $detailRows = [];
        $entryRows = [];
        $codeSummaryRows = [];
        $totals = ['debit' => 0.0, 'credit' => 0.0];

        if ($show && $accode !== '') {
            $account = $this->loadAccount($accode, $gilevel);
            if ($account) {
                $openingBalance = $this->openingBalance($accode, $dateFrom, $gilevel, $account);
                $filterScode = $useScode ? $scode : '';

                if ($type === 'detail') {
                    $detailRows = $this->detailRows($accode, $dateFrom, $dateTo, $gilevel, $openingBalance, $filterScode, $pendingOnly);
                    foreach ($detailRows as $row) {
                        $totals['debit'] += $row['debit'];
                        $totals['credit'] += $row['credit'];
                    }
                    $closingBalance = empty($detailRows) ? $openingBalance : (float) end($detailRows)['running_balance'];
                } elseif ($type === 'suspbal') {
                    $codeSummaryRows = $this->suspenseBalanceRows($accode, $dateFrom, $dateTo, $gilevel, $filterScode, $pendingOnly);
                    foreach ($codeSummaryRows as $row) {
                        $totals['debit'] += $row['debit'];
                        $totals['credit'] += $row['credit'];
                    }
                } else {
                    $entryRows = $this->entryBalanceRows($accode, $dateFrom, $dateTo, $gilevel, $filterScode, $pendingOnly);
                    foreach ($entryRows as $row) {
                        $totals['debit'] += $row['debit'];
                        $totals['credit'] += $row['credit'];
                    }
                }
            }
        }

        return view('reports.suspense-ledger', [
            'gilevel' => $gilevel,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'accode' => $accode,
            'scode' => $scode,
            'useScode' => $useScode,
            'type' => $type,
            'pendingOnly' => $pendingOnly,
            'show' => $show,
            'accountOptions' => $accountOptions,
            'suspenseOptions' => $suspenseOptions,
            'account' => $account,
            'openingBalance' => $openingBalance,
            'closingBalance' => $closingBalance,
            'detailRows' => $detailRows,
            'entryRows' => $entryRows,
            'codeSummaryRows' => $codeSummaryRows,
            'totals' => $totals,
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

    private function accountOptions(): array
    {
        if (!$this->hasTable('accountm')) {
            return [];
        }

        return DB::table('accountm')
            ->selectRaw('TRIM(accode) as accode, TRIM(name) as name')
            ->orderBy('accode')
            ->get()
            ->map(fn ($row) => ['accode' => (string) $row->accode, 'name' => (string) $row->name])
            ->all();
    }

    private function suspenseOptions(): array
    {
        if (!$this->hasTable('suspac')) {
            return [];
        }

        return DB::table('suspac')
            ->selectRaw('TRIM(code) as code, TRIM(name) as name')
            ->orderBy('name')
            ->get()
            ->map(fn ($row) => ['code' => (string) $row->code, 'name' => (string) $row->name])
            ->all();
    }

    private function loadAccount(string $accode, int $gilevel): ?array
    {
        if (!$this->hasTable('accountm')) {
            return null;
        }

        $row = DB::table('accountm')
            ->selectRaw('TRIM(accode) as accode, TRIM(name) as name, opbal, opbalb')
            ->whereRaw('TRIM(accode)=?', [$accode])
            ->first();

        if (!$row) {
            return null;
        }

        return [
            'accode' => (string) $row->accode,
            'name' => (string) $row->name,
            'opbal' => (float) ($gilevel === 1 ? ($row->opbal ?? 0) : ($row->opbalb ?? 0)),
        ];
    }

    private function openingBalance(string $accode, string $dateFrom, int $gilevel, array $account): float
    {
        if (!$this->hasTable('daybook')) {
            return (float) $account['opbal'];
        }

        return (float) $account['opbal'] + (float) DB::table('daybook')
            ->whereRaw('TRIM(accode)=?', [$accode])
            ->where('tdate', '<', $dateFrom)
            ->where('control', '<=', $gilevel)
            ->sum('amount');
    }

    private function detailRows(
        string $accode,
        string $dateFrom,
        string $dateTo,
        int $gilevel,
        float $openingBalance,
        string $scode,
        bool $pendingOnly
    ): array {
        if (!$this->hasTable('daybook') || !$this->hasTable('daybookpart')) {
            return [];
        }

        $query = DB::table('daybook')
            ->join('daybookpart', 'daybook.slno', '=', 'daybookpart.slno')
            ->leftJoin('suspentry', 'suspentry.slno', '=', 'daybook.slno')
            ->leftJoin('suspac', 'suspac.code', '=', 'suspentry.scode')
            ->selectRaw('
                daybook.slno,
                daybook.tdate,
                daybook.amount,
                TRIM(COALESCE(daybookpart.vchno, "")) as vchno,
                TRIM(COALESCE(daybookpart.staff, "")) as staff,
                TRIM(COALESCE(daybookpart.particular, "")) as particular,
                COALESCE(daybookpart.slno2, 0) as slno2,
                TRIM(COALESCE(suspentry.scode, "")) as scode,
                TRIM(COALESCE(suspentry.pend, "")) as pend,
                TRIM(COALESCE(suspac.name, "")) as suspacname
            ')
            ->whereRaw('TRIM(daybook.accode)=?', [$accode])
            ->whereBetween('daybook.tdate', [$dateFrom, $dateTo])
            ->where('daybook.control', '<=', $gilevel)
            ->orderBy('daybook.tdate')
            ->orderBy('daybook.slno');

        if ($pendingOnly) {
            $query->whereRaw('TRIM(COALESCE(suspentry.pend, "")) = ?', ['Y']);
        }
        if ($scode !== '') {
            $query->whereRaw('TRIM(COALESCE(suspentry.scode, "")) = ?', [$scode]);
        }

        $running = $openingBalance;
        $rows = [];
        foreach ($query->get() as $row) {
            $amount = (float) ($row->amount ?? 0);
            $running += $amount;
            $part = trim((string) $row->particular);
            $staff = trim((string) $row->staff);
            if ($staff !== '') {
                $part .= ($part !== '' ? ' ->Staff-> ' : 'Staff-> ') . $staff;
            }

            $rows[] = [
                'slno' => (int) $row->slno,
                'date' => (string) $row->tdate,
                'suspacname' => (string) $row->suspacname,
                'particular' => $part,
                'vchno' => (string) $row->vchno,
                'debit' => $amount < 0 ? abs($amount) : 0.0,
                'credit' => $amount > 0 ? $amount : 0.0,
                'running_balance' => $running,
                'running_side' => $running < 0 ? 'Dr' : 'Cr',
                'pend' => (string) $row->pend,
                'scode' => (string) $row->scode,
            ];
        }

        return $rows;
    }

    private function entryBalanceRows(
        string $accode,
        string $dateFrom,
        string $dateTo,
        int $gilevel,
        string $scode,
        bool $pendingOnly
    ): array {
        if (!$this->hasTable('suspentry')) {
            return [];
        }

        $query = DB::table('suspentry')
            ->leftJoin('suspac', 'suspac.code', '=', 'suspentry.scode')
            ->selectRaw('
                suspentry.slno,
                suspentry.tdate,
                TRIM(COALESCE(suspentry.vchno, "")) as vchno,
                TRIM(COALESCE(suspentry.note, "")) as note,
                TRIM(COALESCE(suspentry.scode, "")) as scode,
                TRIM(COALESCE(suspentry.pend, "")) as pend,
                COALESCE(suspentry.amount, 0) as amount,
                TRIM(COALESCE(suspac.name, "")) as suspacname,
                COALESCE((select sum(se.amount) from suspentry se where se.pslno = suspentry.slno), 0) as totrcpt
            ')
            ->whereBetween('suspentry.tdate', [$dateFrom, $dateTo])
            ->where('suspentry.control', '<=', $gilevel)
            ->whereRaw('TRIM(suspentry.accode)=?', [$accode])
            ->where('suspentry.pslno', 0)
            ->orderBy('suspentry.tdate')
            ->orderBy('suspentry.slno');

        if ($pendingOnly) {
            $query->whereRaw('TRIM(COALESCE(suspentry.pend, "")) = ?', ['Y']);
        }
        if ($scode !== '') {
            $query->whereRaw('TRIM(COALESCE(suspentry.scode, "")) = ?', [$scode]);
        }

        $rows = [];
        foreach ($query->get() as $row) {
            $amount = (float) ($row->amount ?? 0) + (float) ($row->totrcpt ?? 0);
            $rows[] = [
                'slno' => (int) $row->slno,
                'date' => (string) $row->tdate,
                'suspacname' => (string) $row->suspacname,
                'note' => (string) $row->note,
                'vchno' => (string) $row->vchno,
                'debit' => $amount > 0 ? abs($amount) : 0.0,
                'credit' => $amount < 0 ? abs($amount) : 0.0,
                'pend' => (string) $row->pend,
                'scode' => (string) $row->scode,
            ];
        }

        return $rows;
    }

    private function suspenseBalanceRows(
        string $accode,
        string $dateFrom,
        string $dateTo,
        int $gilevel,
        string $scode,
        bool $pendingOnly
    ): array {
        if (!$this->hasTable('suspentry')) {
            return [];
        }

        $query = DB::table('suspentry')
            ->leftJoin('suspac', 'suspac.code', '=', 'suspentry.scode')
            ->selectRaw('
                TRIM(COALESCE(suspentry.scode, "")) as scode,
                TRIM(COALESCE(suspac.name, "")) as suspacname,
                SUM(COALESCE(suspentry.amount, 0)) as amount,
                COUNT(*) as entry_count
            ')
            ->whereBetween('suspentry.tdate', [$dateFrom, $dateTo])
            ->where('suspentry.control', '<=', $gilevel)
            ->whereRaw('TRIM(suspentry.accode)=?', [$accode])
            ->groupBy('suspentry.scode', 'suspac.name')
            ->orderBy('suspac.name');

        if ($pendingOnly) {
            $query->whereRaw('TRIM(COALESCE(suspentry.pend, "")) = ?', ['Y']);
        }
        if ($scode !== '') {
            $query->whereRaw('TRIM(COALESCE(suspentry.scode, "")) = ?', [$scode]);
        }

        $rows = [];
        foreach ($query->get() as $row) {
            $amount = (float) ($row->amount ?? 0);
            $rows[] = [
                'scode' => (string) $row->scode,
                'suspacname' => (string) $row->suspacname,
                'debit' => $amount > 0 ? abs($amount) : 0.0,
                'credit' => $amount < 0 ? abs($amount) : 0.0,
                'entry_count' => (int) ($row->entry_count ?? 0),
            ];
        }

        return $rows;
    }
}
