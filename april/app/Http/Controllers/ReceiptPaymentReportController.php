<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ReceiptPaymentReportController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $gilevel = max(1, (int) $request->session()->get('gilevel', 1));
        $dateFrom = $this->normalizeDate((string) $request->query('date1', '')) ?: date('Y-m-d');
        $dateTo = $this->normalizeDate((string) $request->query('date2', '')) ?: date('Y-m-d');
        $type = trim((string) $request->query('type', 'All'));
        $actype = trim((string) $request->query('actype', 'All'));
        $groupCode = strtoupper(trim((string) $request->query('grcode', '')));
        $useGroup = $request->boolean('use_group', $groupCode !== '');
        $summary = $request->boolean('summary');
        $sortByName = $request->boolean('sort_name');
        $show = $request->boolean('show');

        $groupOptions = $this->groupOptions();
        $rows = [];
        $summaryRows = [];

        $openingCash = $this->cashBalanceBefore($dateFrom, $gilevel);
        $closingCash = $this->cashBalanceUpto($dateTo, $gilevel);
        $salesAmt = $this->safeSum('salesm', 'ramt', $dateFrom, $dateTo, $gilevel);
        $salesAmt -= $this->safeConditionalSum('salesrm', 'pamt', $dateFrom, $dateTo, $gilevel, 'sr', 'R');
        $purchaseAmt = $this->safeConditionalSum('purchasem', 'pamt', $dateFrom, $dateTo, $gilevel, 'pr', 'P');
        $purchaseAmt -= $this->safeConditionalSum('purchaserm', 'ramt', $dateFrom, $dateTo, $gilevel, 'pr', 'R');
        $orderAmt = $this->safeSum('orderm', 'advance', $dateFrom, $dateTo, $gilevel);
        $repairAmt = $this->safeSum('repairm', 'rcvd', $dateFrom, $dateTo, $gilevel);
        $smithAmt = $this->safeSum('smithm', 'pamt', $dateFrom, $dateTo, $gilevel);
        $refineAmt = $this->safeSum('refinerym', 'paidamt', $dateFrom, $dateTo, $gilevel);

        if ($show) {
            if ($summary) {
                $summaryRows = $this->loadSummaryRows($dateFrom, $dateTo, $gilevel, $type, $actype, $useGroup ? $groupCode : '');
            } else {
                $rows = $this->loadDetailRows($dateFrom, $dateTo, $gilevel, $type, $actype, $useGroup ? $groupCode : '', $sortByName);
            }
        }

        return view('reports.receipt-payment-report', [
            'gilevel' => $gilevel,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'type' => $type,
            'actype' => $actype,
            'groupCode' => $groupCode,
            'useGroup' => $useGroup,
            'summary' => $summary,
            'sortByName' => $sortByName,
            'show' => $show,
            'groupOptions' => $groupOptions,
            'rows' => $rows,
            'summaryRows' => $summaryRows,
            'openingCash' => -$openingCash,
            'closingCash' => -$closingCash,
            'salesAmt' => $salesAmt,
            'purchaseAmt' => $purchaseAmt,
            'orderAmt' => $orderAmt,
            'repairAmt' => $repairAmt,
            'smithAmt' => $smithAmt,
            'refineAmt' => $refineAmt,
        ]);
    }

    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') return null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return $value;
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        return null;
    }

    private function groupOptions(): array
    {
        if (!$this->hasTable('accountg')) return [];
        return DB::table('accountg')
            ->selectRaw('TRIM(grcode) as grcode, TRIM(name) as name')
            ->orderBy('name')
            ->get()
            ->map(fn ($r) => ['grcode' => (string) $r->grcode, 'name' => (string) $r->name])
            ->all();
    }

    private function cashBalanceBefore(string $dateFrom, int $gilevel): float
    {
        $cashBankCodes = $this->cashBankCodes();
        $base = $this->openingCashBankBalance($gilevel, $cashBankCodes);
        if (!$this->hasTable('daybook')) return $base;
        return $base + (float) DB::table('daybook')
            ->whereIn('accode', $cashBankCodes)
            ->where('control', '<=', $gilevel)
            ->where('tdate', '<', $dateFrom)
            ->sum('amount');
    }

    private function cashBalanceUpto(string $dateTo, int $gilevel): float
    {
        $cashBankCodes = $this->cashBankCodes();
        $base = $this->openingCashBankBalance($gilevel, $cashBankCodes);
        if (!$this->hasTable('daybook')) return $base;
        return $base + (float) DB::table('daybook')
            ->whereIn('accode', $cashBankCodes)
            ->where('control', '<=', $gilevel)
            ->where('tdate', '<=', $dateTo)
            ->sum('amount');
    }

    private function cashBankCodes(): array
    {
        if (!$this->hasTable('accountm') || !Schema::hasColumn('accountm', 'accode')) {
            return ['CASH'];
        }

        if (Schema::hasColumn('accountm', 'actype2')) {
            $codes = DB::table('accountm')
                ->whereRaw("TRIM(COALESCE(actype2, '')) IN ('H','B')")
                ->pluck('accode')
                ->map(fn ($code) => trim((string) $code))
                ->filter()
                ->values()
                ->all();

            if ($codes !== []) {
                return $codes;
            }
        }

        return ['CASH'];
    }

    private function openingCashBankBalance(int $gilevel, array $cashBankCodes): float
    {
        if (!$this->hasTable('accountm')) {
            return 0.0;
        }

        $balanceColumn = $gilevel === 1 ? 'opbal' : 'opbalb';
        if (!Schema::hasColumn('accountm', $balanceColumn)) {
            return 0.0;
        }

        return (float) DB::table('accountm')
            ->whereIn('accode', $cashBankCodes)
            ->sum($balanceColumn);
    }

    private function safeSum(string $table, string $amountColumn, string $dateFrom, string $dateTo, int $gilevel): float
    {
        if (!$this->hasTable($table)) return 0.0;
        return (float) DB::table($table)
            ->whereBetween('tdate', [$dateFrom, $dateTo])
            ->where('control', '<=', $gilevel)
            ->sum($amountColumn);
    }

    private function safeConditionalSum(string $table, string $amountColumn, string $dateFrom, string $dateTo, int $gilevel, string $filterCol, string $filterVal): float
    {
        if (!$this->hasTable($table)) return 0.0;
        return (float) DB::table($table)
            ->whereBetween('tdate', [$dateFrom, $dateTo])
            ->where('control', '<=', $gilevel)
            ->where($filterCol, $filterVal)
            ->sum($amountColumn);
    }

    private function baseDetailQuery(string $dateFrom, string $dateTo, int $gilevel)
    {
        $cashBankCodes = $this->cashBankCodes();

        return DB::table('daybook')
            ->join('daybookpart', 'daybook.slno', '=', 'daybookpart.slno')
            ->selectRaw('
                daybook.tdate,
                daybook.amount,
                TRIM(daybookpart.vchno) as vchno,
                TRIM(COALESCE(daybookpart.particular, "")) as particular,
                daybook.slno,
                TRIM(COALESCE((select max(dbk.accode) from daybook dbk where dbk.accode <> daybook.accode and dbk.slno = daybook.slno), "")) as othercode,
                TRIM(COALESCE((select accountm.name from accountm where accountm.accode = (select max(dbk.accode) from daybook dbk where dbk.accode <> daybook.accode and dbk.slno = daybook.slno)), "")) as othername,
                TRIM(COALESCE(daybookpart.staff, "")) as staff,
                TRIM(COALESCE((select accountm.actype2 from accountm where accountm.accode = (select max(dbk.accode) from daybook dbk where dbk.accode <> daybook.accode and dbk.slno = daybook.slno)), "")) as otheractype,
                TRIM(COALESCE((select accountm.grcode from accountm where accountm.accode = (select max(dbk.accode) from daybook dbk where dbk.accode <> daybook.accode and dbk.slno = daybook.slno)), "")) as otheracgrp,
                TRIM(COALESCE((select accountm.actype1 from accountm where accountm.accode = (select max(dbk.accode) from daybook dbk where dbk.accode <> daybook.accode and dbk.slno = daybook.slno)), "")) as otheractype1,
                (select count(clients_kuridet.code) from clients_kuridet where clients_kuridet.code = (select max(dbk.accode) from daybook dbk where dbk.accode <> daybook.accode and dbk.slno = daybook.slno)) as ksparty
            ')
            ->where('daybook.control', '<=', $gilevel)
            ->whereBetween('daybook.tdate', [$dateFrom, $dateTo])
            ->whereIn('daybook.accode', $cashBankCodes)
            ->whereRaw('trim(daybookpart.vchno) <> \'\'');
    }

    private function applyFilters($query, string $type, string $actype, string $groupCode)
    {
        $typeMap = [
            'Customers' => ['otheractype' => 'C'],
            'Suppliers' => ['otheractype' => 'S'],
            'Jewelleries' => ['otheractype' => 'J'],
            'Goldsmiths' => ['otheractype' => 'G'],
            'Refiners' => ['otheractype' => 'R'],
            'Staff' => ['otheractype' => 'F'],
            'General' => ['otheractype' => ''],
            'Kuri/Scheme Customers' => ['otheractype' => 'C', 'ksparty' => '>0'],
        ];

        if (isset($typeMap[$type])) {
            $cfg = $typeMap[$type];
            if (($cfg['otheractype'] ?? null) === '') {
                $query->whereRaw('TRIM(COALESCE((select accountm.actype2 from accountm where accountm.accode = (select max(dbk.accode) from daybook dbk where dbk.accode <> daybook.accode and dbk.slno = daybook.slno)), "")) = \'\'');
            } else {
                $query->whereRaw('TRIM(COALESCE((select accountm.actype2 from accountm where accountm.accode = (select max(dbk.accode) from daybook dbk where dbk.accode <> daybook.accode and dbk.slno = daybook.slno)), "")) = ?', [$cfg['otheractype']]);
            }
            if (($cfg['ksparty'] ?? '') === '>0') {
                $query->whereRaw('(select count(clients_kuridet.code) from clients_kuridet where clients_kuridet.code = (select max(dbk.accode) from daybook dbk where dbk.accode <> daybook.accode and dbk.slno = daybook.slno)) > 0');
            }
        }

        $actypeMap = [
            'Expense Only' => 'E',
            'Revenue Only' => 'I',
            'Asset Only' => 'A',
            'Liability Only' => 'L',
        ];
        if (isset($actypeMap[$actype])) {
            $query->whereRaw('TRIM(COALESCE((select accountm.actype1 from accountm where accountm.accode = (select max(dbk.accode) from daybook dbk where dbk.accode <> daybook.accode and dbk.slno = daybook.slno)), "")) = ?', [$actypeMap[$actype]]);
        }

        if ($groupCode !== '') {
            $query->whereRaw('TRIM(COALESCE((select accountm.grcode from accountm where accountm.accode = (select max(dbk.accode) from daybook dbk where dbk.accode <> daybook.accode and dbk.slno = daybook.slno)), "")) = ?', [$groupCode]);
        }

        return $query;
    }

    private function loadDetailRows(string $dateFrom, string $dateTo, int $gilevel, string $type, string $actype, string $groupCode, bool $sortByName): array
    {
        $query = $this->applyFilters($this->baseDetailQuery($dateFrom, $dateTo, $gilevel), $type, $actype, $groupCode);
        if ($sortByName) {
            $query->orderBy('othername');
        } else {
            $query->orderBy('daybook.tdate')->orderBy('daybook.slno');
        }

        return $query->get()->map(fn ($r) => [
            'date' => (string) $r->tdate,
            'amount' => (float) $r->amount,
            'vchno' => (string) $r->vchno,
            'particular' => (string) $r->particular,
            'slno' => (int) $r->slno,
            'othercode' => (string) $r->othercode,
            'othername' => (string) $r->othername,
            'staff' => (string) $r->staff,
            'otheractype' => (string) $r->otheractype,
            'otheracgrp' => (string) $r->otheracgrp,
            'otheractype1' => (string) $r->otheractype1,
            'ksparty' => (int) ($r->ksparty ?? 0),
        ])->all();
    }

    private function loadSummaryRows(string $dateFrom, string $dateTo, int $gilevel, string $type, string $actype, string $groupCode): array
    {
        if (!$this->hasTable('accountm')) {
            return [];
        }

        $accounts = DB::table('accountm')
            ->selectRaw('TRIM(accode) as accode, TRIM(name) as name, TRIM(COALESCE(actype1, "")) as actype1, TRIM(COALESCE(actype2, "")) as actype2, TRIM(COALESCE(grcode, "")) as grcode')
            ->where('accode', '<>', 'CASH')
            ->orderBy('name')
            ->get();

        $rows = [];
        foreach ($accounts as $account) {
            $otherType = (string) $account->actype2;
            $otherType1 = (string) $account->actype1;
            $isKuri = $this->hasTable('clients_kuridet')
                ? DB::table('clients_kuridet')->where('code', $account->accode)->exists()
                : false;

            if (!$this->matchesSummaryFilters($type, $actype, $groupCode, $otherType, $otherType1, (string) $account->grcode, $isKuri)) {
                continue;
            }

            $tdbamt = 0.0;
            $tcramt = 0.0;
            if ($this->hasTable('daybook') && $this->hasTable('daybookpart')) {
                $tdbamt = (float) DB::table('daybook')
                    ->join('daybookpart', 'daybook.slno', '=', 'daybookpart.slno')
                    ->where('daybook.accode', $account->accode)
                    ->where('daybook.amount', '<', 0)
                    ->whereRaw('trim(daybookpart.vchno) <> \'\'')
                    ->whereBetween('daybook.tdate', [$dateFrom, $dateTo])
                    ->where('daybook.control', '<=', $gilevel)
                    ->sum('daybook.amount');

                $tcramt = (float) DB::table('daybook')
                    ->join('daybookpart', 'daybook.slno', '=', 'daybookpart.slno')
                    ->where('daybook.accode', $account->accode)
                    ->where('daybook.amount', '>', 0)
                    ->whereRaw('trim(daybookpart.vchno) <> \'\'')
                    ->whereBetween('daybook.tdate', [$dateFrom, $dateTo])
                    ->where('daybook.control', '<=', $gilevel)
                    ->sum('daybook.amount');
            }

            if (abs($tdbamt) + abs($tcramt) <= 0) {
                continue;
            }

            $rows[] = [
                'accode' => (string) $account->accode,
                'name' => (string) $account->name,
                'receipt' => abs($tdbamt),
                'payment' => $tcramt,
                'otheractype1' => $otherType1,
            ];
        }

        return $rows;
    }

    private function matchesSummaryFilters(string $type, string $actype, string $groupCode, string $otherType, string $otherType1, string $otherGroup, bool $isKuri): bool
    {
        if ($type !== 'All') {
            $ok = match ($type) {
                'Customers' => $otherType === 'C',
                'Suppliers' => $otherType === 'S',
                'Jewelleries' => $otherType === 'J',
                'Goldsmiths' => $otherType === 'G',
                'Refiners' => $otherType === 'R',
                'Staff' => $otherType === 'F',
                'General' => $otherType === '',
                'Kuri/Scheme Customers' => $otherType === 'C' && $isKuri,
                default => true,
            };
            if (!$ok) return false;
        }

        if ($groupCode !== '' && $otherGroup !== $groupCode) return false;

        if ($actype !== 'All') {
            $map = ['Expense Only' => 'E', 'Revenue Only' => 'I', 'Asset Only' => 'A', 'Liability Only' => 'L'];
            if (($map[$actype] ?? null) !== $otherType1) return false;
        }

        return true;
    }
}
