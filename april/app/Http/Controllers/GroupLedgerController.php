<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class GroupLedgerController extends Controller
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
        $groupCode = strtoupper(trim((string) $request->query('grcode', '')));
        $summary = $request->boolean('summary');
        $show = $request->boolean('show') || $groupCode !== '';

        $groupOptions = $this->groupOptions();
        $group = null;
        $rows = [];
        $totals = ['debit' => 0.0, 'credit' => 0.0, 'opening' => 0.0, 'closing' => 0.0];
        $openingBalance = 0.0;
        $closingBalance = 0.0;

        if ($show && $groupCode !== '') {
            $group = $this->loadGroup($groupCode);
            if ($group) {
                if ($summary) {
                    $rows = $this->summaryRows($groupCode, $dateFrom, $dateTo, $gilevel);
                    foreach ($rows as $row) {
                        $totals['opening'] += $row['opening_balance'];
                        $totals['debit'] += $row['debit'];
                        $totals['credit'] += $row['credit'];
                        $totals['closing'] += $row['closing_balance_signed'];
                    }
                } else {
                    $openingBalance = $this->groupOpeningBalance($groupCode, $dateFrom, $gilevel);
                    $rows = $this->detailRows($groupCode, $dateFrom, $dateTo, $gilevel, $openingBalance);
                    foreach ($rows as $row) {
                        $totals['debit'] += $row['debit'];
                        $totals['credit'] += $row['credit'];
                    }
                    $closingBalance = empty($rows) ? $openingBalance : (float) end($rows)['running_balance'];
                }
            }
        }

        return view('reports.group-ledger', [
            'gilevel' => $gilevel,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'groupCode' => $groupCode,
            'summary' => $summary,
            'show' => $show,
            'groupOptions' => $groupOptions,
            'group' => $group,
            'rows' => $rows,
            'totals' => $totals,
            'openingBalance' => $openingBalance,
            'closingBalance' => $closingBalance,
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
            ->selectRaw('TRIM(grcode) as grcode, TRIM(name) as name')
            ->orderBy('name')
            ->get()
            ->map(fn ($row) => ['grcode' => (string) $row->grcode, 'name' => (string) $row->name])
            ->all();
    }

    private function loadGroup(string $groupCode): ?array
    {
        if (!$this->hasTable('accountg')) {
            return null;
        }

        $row = DB::table('accountg')
            ->selectRaw('TRIM(grcode) as grcode, TRIM(name) as name')
            ->whereRaw('TRIM(grcode)=?', [$groupCode])
            ->first();

        if (!$row) {
            return null;
        }

        return ['grcode' => (string) $row->grcode, 'name' => (string) $row->name];
    }

    private function groupOpeningBalance(string $groupCode, string $dateFrom, int $gilevel): float
    {
        if (!$this->hasTable('accountm')) {
            return 0.0;
        }

        $opColumn = $gilevel === 1 ? 'opbal' : 'opbalb';
        $opening = (float) DB::table('accountm')
            ->whereRaw('TRIM(grcode)=?', [$groupCode])
            ->sum(DB::raw("COALESCE($opColumn,0)"));

        if ($this->hasTable('daybook')) {
            $opening += (float) DB::table('daybook')
                ->join('accountm', 'daybook.accode', '=', 'accountm.accode')
                ->whereRaw('TRIM(accountm.grcode)=?', [$groupCode])
                ->where('daybook.tdate', '<', $dateFrom)
                ->where('daybook.control', '<=', $gilevel)
                ->sum('daybook.amount');
        }

        return $opening;
    }

    private function detailRows(string $groupCode, string $dateFrom, string $dateTo, int $gilevel, float $openingBalance): array
    {
        if (!$this->hasTable('daybook') || !$this->hasTable('daybookpart') || !$this->hasTable('accountm')) {
            return [];
        }

        $rows = DB::table('daybook')
            ->join('daybookpart', 'daybook.slno', '=', 'daybookpart.slno')
            ->join('accountm', 'daybook.accode', '=', 'accountm.accode')
            ->selectRaw('
                daybook.slno,
                daybook.tdate,
                daybook.amount,
                TRIM(daybookpart.vchno) as vchno,
                TRIM(COALESCE(daybookpart.staff, "")) as staff,
                TRIM(COALESCE(daybookpart.particular, "")) as particular,
                TRIM(accountm.name) as account_name
            ')
            ->whereRaw('TRIM(accountm.grcode)=?', [$groupCode])
            ->whereBetween('daybook.tdate', [$dateFrom, $dateTo])
            ->where('daybook.control', '<=', $gilevel)
            ->orderBy('daybook.tdate')
            ->orderBy('daybook.slno')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        $running = $openingBalance;
        $result = [];
        foreach ($rows as $row) {
            $amount = (float) $row['amount'];
            $running += $amount;
            $part = trim((string) $row['particular']);
            $staff = trim((string) $row['staff']);
            if ($staff !== '') {
                $part .= ($part !== '' ? ' ->Staff-> ' : 'Staff-> ') . $staff;
            }

            $result[] = [
                'slno' => (int) $row['slno'],
                'date' => (string) $row['tdate'],
                'account_name' => (string) $row['account_name'],
                'vchno' => (string) $row['vchno'],
                'part' => $part,
                'debit' => $amount < 0 ? abs($amount) : 0.0,
                'credit' => $amount > 0 ? $amount : 0.0,
                'running_balance' => $running,
                'running_side' => $running < 0 ? 'Dr' : 'Cr',
            ];
        }

        return $result;
    }

    private function summaryRows(string $groupCode, string $dateFrom, string $dateTo, int $gilevel): array
    {
        if (!$this->hasTable('accountm')) {
            return [];
        }

        $accounts = DB::table('accountm')
            ->selectRaw('TRIM(accode) as accode, TRIM(name) as name, COALESCE(opbal,0) as opbal, COALESCE(opbalb,0) as opbalb')
            ->whereRaw('TRIM(grcode)=?', [$groupCode])
            ->orderBy('name')
            ->get();

        $rows = [];
        foreach ($accounts as $account) {
            $opBase = (float) ($gilevel === 1 ? $account->opbal : $account->opbalb);
            $opTran = 0.0;
            $periodTran = 0.0;
            $debit = 0.0;
            $credit = 0.0;

            if ($this->hasTable('daybook')) {
                $opTran = (float) DB::table('daybook')
                    ->whereRaw('TRIM(accode)=?', [$account->accode])
                    ->where('tdate', '<', $dateFrom)
                    ->where('control', '<=', $gilevel)
                    ->sum('amount');

                $periodTran = (float) DB::table('daybook')
                    ->whereRaw('TRIM(accode)=?', [$account->accode])
                    ->whereBetween('tdate', [$dateFrom, $dateTo])
                    ->where('control', '<=', $gilevel)
                    ->sum('amount');

                $debit = (float) DB::table('daybook')
                    ->whereRaw('TRIM(accode)=?', [$account->accode])
                    ->whereBetween('tdate', [$dateFrom, $dateTo])
                    ->where('control', '<=', $gilevel)
                    ->where('amount', '<', 0)
                    ->sum(DB::raw('ABS(amount)'));

                $credit = (float) DB::table('daybook')
                    ->whereRaw('TRIM(accode)=?', [$account->accode])
                    ->whereBetween('tdate', [$dateFrom, $dateTo])
                    ->where('control', '<=', $gilevel)
                    ->where('amount', '>', 0)
                    ->sum('amount');
            }

            $openingSigned = $opBase + $opTran;
            $closingSigned = $openingSigned + $periodTran;

            $rows[] = [
                'accode' => (string) $account->accode,
                'name' => (string) $account->name,
                'opening_balance' => abs($openingSigned),
                'opening_side' => $openingSigned <= 0 ? 'Dr' : 'Cr',
                'debit' => $debit,
                'credit' => $credit,
                'closing_balance' => abs($closingSigned),
                'closing_side' => $closingSigned <= 0 ? 'Dr' : 'Cr',
                'closing_balance_signed' => $closingSigned,
            ];
        }

        return $rows;
    }
}
