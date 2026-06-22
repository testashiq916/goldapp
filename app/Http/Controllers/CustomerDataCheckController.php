<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class CustomerDataCheckController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('customers.data-check.index', [
            'title' => 'Customer Data Check',
            'date1' => $this->normalizeDate((string) $request->query('date1', $this->startDate($request))),
            'date2' => $this->normalizeDate((string) $request->query('date2', date('Y-m-d'))),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'message' => 'Login required'], 401);
        }

        $check = trim((string) $request->query('check', 'all'));
        $date1 = $this->normalizeDate((string) $request->query('date1', $this->startDate($request)));
        $date2 = $this->normalizeDate((string) $request->query('date2', date('Y-m-d')));
        $search = trim((string) $request->query('search', ''));
        $sort = trim((string) $request->query('sort', 'date'));
        $gilevel = max(1, (int) $request->session()->get('gilevel', 1));

        $rows = match ($check) {
            'credit' => $this->creditRows($date1, $date2, $gilevel, $search),
            'balance_mismatch' => $this->balanceMismatchRows($date1, $date2, $gilevel, $search),
            'bill_no_issue' => $this->billNoIssueRows($date1, $date2, $gilevel, $search),
            'missing_link' => $this->missingLinkRows($date1, $date2, $gilevel, $search),
            'opening_mismatch' => $this->openingMismatchRows($gilevel, $search),
            'ledger_mismatch' => $this->ledgerMismatchRows($date2, $gilevel, $search),
            'ledger_posting_issue' => $this->ledgerPostingIssueRows($date1, $date2, $gilevel, $search),
            default => array_merge(
                $this->creditRows($date1, $date2, $gilevel, $search),
                $this->balanceMismatchRows($date1, $date2, $gilevel, $search),
                $this->billNoIssueRows($date1, $date2, $gilevel, $search),
                $this->missingLinkRows($date1, $date2, $gilevel, $search),
                $this->openingMismatchRows($gilevel, $search),
                $this->ledgerMismatchRows($date2, $gilevel, $search),
                $this->ledgerPostingIssueRows($date1, $date2, $gilevel, $search),
            ),
        };

        $this->sortRows($rows, $sort);

        $totals = [
            'count' => count($rows),
            'billamt' => round(array_sum(array_map(fn ($row) => (float) ($row['billamt'] ?? 0), $rows)), 2),
            'received' => round(array_sum(array_map(fn ($row) => (float) ($row['received'] ?? 0), $rows)), 2),
            'balance' => round(array_sum(array_map(fn ($row) => (float) ($row['balance'] ?? 0), $rows)), 2),
            'difference' => round(array_sum(array_map(fn ($row) => (float) ($row['difference'] ?? 0), $rows)), 2),
        ];

        return response()->json(['ok' => true, 'rows' => $rows, 'totals' => $totals]);
    }

    public function updateIssue(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'message' => 'Login required'], 401);
        }

        $action = trim((string) $request->input('action', ''));
        $slno = trim((string) $request->input('slno', ''));
        $sno = (int) $request->input('sno', 0);
        $module = trim((string) $request->input('module', ''));
        $code = strtoupper(trim((string) $request->input('code', '')));
        $uptoDate = $this->normalizeDate((string) $request->input('date', date('Y-m-d')));
        $gilevel = max(1, (int) $request->session()->get('gilevel', 1));

        if ($action === 'clear_orphan_sales_ledger' || $action === 'clear_orphan_source_ledger') {
            $deleted = $this->clearOrphanSourceLedger((int) $slno, $module);

            return response()->json([
                'ok' => true,
                'message' => 'Cleared orphan source ledger posting.',
                'old_balance' => $deleted,
                'new_balance' => 0,
            ]);
        }

        if ($action === 'clear_daybook_row') {
            $deleted = $this->clearDaybookRow((int) $slno, $sno);

            return response()->json([
                'ok' => true,
                'message' => 'Cleared duplicate ledger row.',
                'old_balance' => $deleted,
                'new_balance' => 0,
            ]);
        }

        if ($action === 'sync_bill_balance_to_ledger') {
            if ($code === '') {
                return response()->json(['ok' => false, 'message' => 'Customer code required.'], 422);
            }

            $result = $this->syncSalesBalanceToLedger($code, $uptoDate, $gilevel);

            return response()->json([
                'ok' => true,
                'message' => 'Updated bill balances to match account receivable.',
                'old_balance' => $result['old_balance'],
                'new_balance' => $result['new_balance'],
                'target_balance' => $result['target_balance'],
                'updated' => $result['updated'],
            ]);
        }

        if ($action !== 'update_sales_balance') {
            return response()->json(['ok' => false, 'message' => 'This issue needs manual checking.'], 422);
        }
        if ($slno === '' || !$this->hasTable('salesm') || !Schema::hasColumn('salesm', 'bal')) {
            return response()->json(['ok' => false, 'message' => 'Sales balance cannot be updated.'], 422);
        }

        $row = DB::table('salesm')->where('slno', $slno)->first();
        if (!$row) {
            return response()->json(['ok' => false, 'message' => 'Sales bill not found.'], 404);
        }

        $amount = Schema::hasColumn('salesm', 'netamt')
            ? (float) ($row->netamt ?? $row->billamt ?? 0)
            : (float) ($row->billamt ?? 0);
        $received = (float) ($row->ramt ?? 0);
        $oldBalance = $row->bal === null ? null : round((float) $row->bal, 2);
        $newBalance = round($amount - $received, 2);

        DB::table('salesm')->where('slno', $slno)->update(['bal' => $newBalance]);

        return response()->json([
            'ok' => true,
            'message' => 'Updated sales bill balance.',
            'old_balance' => $oldBalance,
            'new_balance' => $newBalance,
        ]);
    }

    private function creditRows(string $date1, string $date2, int $gilevel, string $search): array
    {
        if (!$this->hasTable('salesm')) {
            return [];
        }

        $rows = $this->salesBaseQuery($date1, $date2, $gilevel, $search)
            ->whereRaw('COALESCE(s.bal,0) > 0.005')
            ->orderBy('s.tdate')
            ->orderBy('s.billno')
            ->get();

        return $rows->map(fn ($row) => $this->salesRow($row, 'Credit Bill', 'Outstanding credit balance'))->all();
    }

    private function balanceMismatchRows(string $date1, string $date2, int $gilevel, string $search): array
    {
        if (!$this->hasTable('salesm')) {
            return [];
        }

        $amountExpr = $this->salesAmountExpression();
        $rows = $this->salesBaseQuery($date1, $date2, $gilevel, $search)
            ->whereRaw('ABS(COALESCE(s.bal,0) - (' . $amountExpr . ' - COALESCE(s.ramt,0))) > 0.01')
            ->orderBy('s.tdate')
            ->orderBy('s.billno')
            ->get();

        return $rows->map(fn ($row) => $this->salesRow($row, 'Balance Mismatch', 'Bill balance is not equal to bill amount minus received amount'))->all();
    }

    private function billNoIssueRows(string $date1, string $date2, int $gilevel, string $search): array
    {
        if (!$this->hasTable('salesm')) {
            return [];
        }

        $blankRows = $this->salesBaseQuery($date1, $date2, $gilevel, $search)
            ->whereRaw('TRIM(COALESCE(s.billno,"")) = ""')
            ->get()
            ->map(fn ($row) => $this->salesRow($row, 'Bill No Issue', 'Blank bill number'))
            ->all();

        $duplicateBillNos = DB::table('salesm')
            ->selectRaw('TRIM(billno) as billno')
            ->whereBetween('tdate', [$date1, $date2])
            ->where('control', '<=', $gilevel)
            ->whereRaw('TRIM(COALESCE(billno,"")) <> ""')
            ->groupByRaw('TRIM(billno)')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('billno')
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values()
            ->all();

        if ($duplicateBillNos === []) {
            return $blankRows;
        }

        $duplicateRows = $this->salesBaseQuery($date1, $date2, $gilevel, $search)
            ->whereIn(DB::raw('TRIM(s.billno)'), $duplicateBillNos)
            ->orderBy('s.billno')
            ->orderBy('s.tdate')
            ->get()
            ->map(fn ($row) => $this->salesRow($row, 'Bill No Issue', 'Duplicate bill number'))
            ->all();

        return array_merge($blankRows, $duplicateRows);
    }

    private function missingLinkRows(string $date1, string $date2, int $gilevel, string $search): array
    {
        if (!$this->hasTable('salesm')) {
            return [];
        }

        $rows = $this->salesBaseQuery($date1, $date2, $gilevel, $search)
            ->where(function ($query) {
                $query->whereRaw('TRIM(COALESCE(s.custcode,"")) = ""')
                    ->orWhereNull('c.code')
                    ->orWhereNull('a.accode');
            })
            ->orderBy('s.tdate')
            ->orderBy('s.billno')
            ->get();

        return $rows->map(function ($row) {
            $details = [];
            if (trim((string) ($row->code ?? '')) === '') {
                $details[] = 'Missing customer code in bill';
            }
            if ((int) ($row->client_found ?? 0) === 0) {
                $details[] = 'Customer not found in clients';
            }
            if ((int) ($row->account_found ?? 0) === 0) {
                $details[] = 'Customer account not found in accountm';
            }

            return $this->salesRow($row, 'Missing Link', implode(', ', $details));
        })->all();
    }

    private function openingMismatchRows(int $gilevel, string $search): array
    {
        if (!$this->hasTable('clients')) {
            return [];
        }

        $clientOpCol = $gilevel === 1 ? 'opbalance' : 'opbalanceb';
        $accountOpCol = $gilevel === 1 ? 'opbal' : 'opbalb';
        $clientOpExpr = Schema::hasColumn('clients', $clientOpCol) ? 'COALESCE(c.' . $clientOpCol . ',0)' : '0';
        $accountOpExpr = $this->hasTable('accountm') && Schema::hasColumn('accountm', $accountOpCol)
            ? 'COALESCE(a.' . $accountOpCol . ',0)'
            : '0';

        $query = DB::table('clients as c')
            ->leftJoin('accountm as a', 'c.code', '=', 'a.accode')
            ->selectRaw('
                TRIM(c.code) as code,
                TRIM(COALESCE(c.name,"")) as name,
                TRIM(COALESCE(c.mobile,"")) as mobile,
                ' . $clientOpExpr . ' as client_opening,
                ' . $accountOpExpr . ' as account_opening,
                CASE WHEN a.accode IS NULL THEN 0 ELSE 1 END as account_found
            ')
            ->whereRaw('UPPER(TRIM(COALESCE(c.ctype,"C"))) = "C"')
            ->where(function ($query) use ($clientOpExpr, $accountOpExpr) {
                $query->whereNull('a.accode')
                    ->orWhereRaw('ABS((' . $clientOpExpr . ') - (' . $accountOpExpr . ')) > 0.01');
            });

        $this->applyCustomerSearch($query, $search, 'c.code', 'c.name', 'c.mobile');

        return $query->orderBy('c.name')->limit(1000)->get()->map(function ($row) {
            $clientOpening = round((float) ($row->client_opening ?? 0), 2);
            $accountOpening = round((float) ($row->account_opening ?? 0), 2);

            return [
                'ref' => '',
                'issue' => 'Opening Mismatch',
                'date' => '',
                'billno' => '',
                'code' => (string) $row->code,
                'name' => (string) $row->name,
                'mobile' => (string) $row->mobile,
                'billamt' => 0,
                'received' => 0,
                'balance' => 0,
                'expected' => $accountOpening,
                'difference' => round($clientOpening - $accountOpening, 2),
                'details' => ((int) ($row->account_found ?? 0) === 0)
                    ? 'Customer account missing in accountm'
                    : 'clients opening does not match accountm opening',
                'can_update' => false,
                'update_action' => '',
            ];
        })->all();
    }

    private function ledgerMismatchRows(string $uptoDate, int $gilevel, string $search): array
    {
        if (!$this->hasTable('clients') || !$this->hasTable('accountm')) {
            return [];
        }

        $opColumn = $gilevel === 1 ? 'opbal' : 'opbalb';
        $opExpr = Schema::hasColumn('accountm', $opColumn) ? 'COALESCE(a.' . $opColumn . ',0)' : '0';
        $daybookRows = [];
        if ($this->hasTable('daybook')) {
            $daybookRows = DB::table('daybook')
                ->selectRaw('TRIM(accode) as code, COALESCE(SUM(amount),0) as amount')
                ->where('tdate', '<=', $uptoDate)
                ->where('control', '<=', $gilevel)
                ->groupByRaw('TRIM(accode)')
                ->get()
                ->keyBy(fn ($row) => trim((string) $row->code))
                ->all();
        }

        $billRows = [];
        if ($this->hasTable('salesm')) {
            $amountExpr = $this->salesAmountExpression();
            $billRows = DB::table('salesm as s')
                ->selectRaw('TRIM(s.custcode) as code, COALESCE(SUM(CASE WHEN (' . $amountExpr . ' - COALESCE(s.ramt,0)) > 0 THEN (' . $amountExpr . ' - COALESCE(s.ramt,0)) ELSE 0 END),0) as bill_balance')
                ->where('s.tdate', '<=', $uptoDate)
                ->where('s.control', '<=', $gilevel)
                ->whereRaw('TRIM(COALESCE(s.custcode,"")) <> ""')
                ->groupByRaw('TRIM(s.custcode)')
                ->get()
                ->keyBy(fn ($row) => trim((string) $row->code))
                ->all();
        }

        $query = DB::table('clients as c')
            ->join('accountm as a', 'c.code', '=', 'a.accode')
            ->selectRaw('
                TRIM(c.code) as code,
                TRIM(COALESCE(c.name,"")) as name,
                TRIM(COALESCE(c.mobile,"")) as mobile,
                ' . $opExpr . ' as opbal
            ')
            ->where('a.control', '<=', $gilevel)
            ->whereRaw('UPPER(TRIM(COALESCE(a.actype2,""))) = "C"');

        $this->applyCustomerSearch($query, $search, 'c.code', 'c.name', 'c.mobile');

        return $query->orderBy('c.name')->limit(2000)->get()->map(function ($row) use ($daybookRows, $billRows, $uptoDate) {
            $code = trim((string) $row->code);
            $ledgerNet = round((float) ($row->opbal ?? 0) + (float) ($daybookRows[$code]->amount ?? 0), 2);
            $ledgerReceivable = $ledgerNet < 0 ? abs($ledgerNet) : 0.0;
            $ledgerPayable = $ledgerNet > 0 ? $ledgerNet : 0.0;
            $billBalance = round((float) ($billRows[$code]->bill_balance ?? 0), 2);
            $difference = round($billBalance - $ledgerReceivable, 2);

            if (abs($difference) <= 0.01 && $ledgerPayable <= 0.01) {
                return null;
            }

            $detail = $ledgerPayable > 0.01
                ? 'Receivable summary shows customer payable (TG) ' . number_format($ledgerPayable, 2) . ' up to ' . $uptoDate
                : 'Bill-wise credit total does not match account ledger receivable up to ' . $uptoDate;

            return [
                'ref' => '',
                'issue' => 'Ledger/Summary Mismatch',
                'date' => $uptoDate,
                'billno' => '',
                'code' => $code,
                'name' => (string) $row->name,
                'mobile' => (string) $row->mobile,
                'billamt' => $billBalance,
                'received' => 0,
                'balance' => round($ledgerReceivable + $ledgerPayable, 2),
                'expected' => $billBalance,
                'difference' => $ledgerPayable > 0.01 ? round($billBalance + $ledgerPayable, 2) : $difference,
                'details' => $detail,
                'can_update' => true,
                'update_action' => 'sync_bill_balance_to_ledger',
            ];
        })->filter()->values()->all();
    }

    private function ledgerPostingIssueRows(string $date1, string $date2, int $gilevel, string $search): array
    {
        if (!$this->hasTable('daybook') || !$this->hasTable('daybookpart')) {
            return [];
        }

        return array_merge(
            $this->orphanSourceLedgerRows($date1, $date2, $gilevel, $search),
            $this->oppositeDaybookRows($date1, $date2, $gilevel, $search),
        );
    }

    private function orphanSourceLedgerRows(string $date1, string $date2, int $gilevel, string $search): array
    {
        $rows = [];
        foreach ($this->sourceLedgerConfigs() as $module => $config) {
            if (!$this->hasTable($config['table'])) {
                continue;
            }

            $query = DB::table('daybook as d')
                ->join('daybookpart as p', 'p.slno', '=', 'd.slno')
                ->leftJoin($config['table'] . ' as src', 'src.slno', '=', 'd.slno')
                ->leftJoin('accountm as a', 'a.accode', '=', 'd.accode')
                ->selectRaw('
                    d.slno,
                    d.sno,
                    d.tdate,
                    TRIM(COALESCE(p.vchno,"")) as billno,
                    TRIM(COALESCE(d.accode,"")) as code,
                    TRIM(COALESCE(a.name,"")) as name,
                    COALESCE(d.amount,0) as amount,
                    TRIM(COALESCE(p.particular,"")) as details
                ')
                ->whereNull('src.slno')
                ->whereBetween('d.tdate', [$date1, $date2])
                ->where('d.control', '<=', $gilevel)
                ->whereRaw('UPPER(TRIM(COALESCE(d.opaccode,""))) = ?', [$config['opaccode']])
                ->whereRaw('UPPER(TRIM(COALESCE(p.particular,""))) LIKE ?', [$config['particular_like']]);

            if ($config['amount_sign'] === 'negative') {
                $query->whereRaw('COALESCE(d.amount,0) < 0');
            } elseif ($config['amount_sign'] === 'positive') {
                $query->whereRaw('COALESCE(d.amount,0) > 0');
            }

            $this->applyCustomerSearch($query, $search, 'd.accode', 'a.name', 'p.vchno', 'p.particular');

            $rows = array_merge($rows, $query->orderBy('d.tdate')->orderBy('d.slno')->limit(1000)->get()->map(fn ($row) => [
                'ref' => (string) $row->slno,
                'sno' => (int) $row->sno,
                'module' => $module,
                'issue' => 'Ledger Posting Issue',
                'date' => (string) $row->tdate,
                'billno' => (string) $row->billno,
                'code' => (string) $row->code,
                'name' => (string) $row->name,
                'mobile' => '',
                'billamt' => abs(round((float) $row->amount, 2)),
                'received' => 0,
                'balance' => abs(round((float) $row->amount, 2)),
                'expected' => 0,
                'difference' => abs(round((float) $row->amount, 2)),
                'details' => $config['label'] . ' ledger exists but source bill is missing. ' . (string) $row->details,
                'can_update' => true,
                'update_action' => 'clear_orphan_source_ledger',
            ])->all());
        }

        return $rows;
    }

    private function oppositeDaybookRows(string $date1, string $date2, int $gilevel, string $search): array
    {
        $groups = DB::table('daybook as d')
            ->selectRaw('d.slno, TRIM(COALESCE(d.accode,"")) as code, ABS(COALESCE(d.amount,0)) as amount_abs')
            ->whereBetween('d.tdate', [$date1, $date2])
            ->where('d.control', '<=', $gilevel)
            ->whereRaw('ABS(COALESCE(d.amount,0)) > 0.01')
            ->groupByRaw('d.slno, TRIM(COALESCE(d.accode,"")), ABS(COALESCE(d.amount,0))')
            ->havingRaw('SUM(CASE WHEN COALESCE(d.amount,0) > 0 THEN 1 ELSE 0 END) > 0')
            ->havingRaw('SUM(CASE WHEN COALESCE(d.amount,0) < 0 THEN 1 ELSE 0 END) > 0')
            ->limit(1000)
            ->get();

        if ($groups->isEmpty()) {
            return [];
        }

        $rows = [];
        foreach ($groups as $group) {
            $slno = (int) $group->slno;
            $code = trim((string) $group->code);
            $amountAbs = round((float) $group->amount_abs, 2);
            $source = $this->ledgerSourceForAccount($slno, $code);
            if ($source === null) {
                continue;
            }

            $clearNegative = in_array($source['type'], ['purchase', 'sales_return'], true);
            $entry = DB::table('daybook as d')
                ->join('daybookpart as p', 'p.slno', '=', 'd.slno')
                ->leftJoin('accountm as a', 'a.accode', '=', 'd.accode')
                ->selectRaw('
                    d.slno,
                    d.sno,
                    d.tdate,
                    TRIM(COALESCE(p.vchno,"")) as billno,
                    TRIM(COALESCE(d.accode,"")) as code,
                    TRIM(COALESCE(a.name,"")) as name,
                    COALESCE(d.amount,0) as amount,
                    TRIM(COALESCE(p.particular,"")) as details
                ')
                ->where('d.slno', $slno)
                ->whereRaw('TRIM(COALESCE(d.accode,"")) = ?', [$code])
                ->whereRaw('ABS(COALESCE(d.amount,0)) = ?', [$amountAbs])
                ->whereRaw($clearNegative ? 'COALESCE(d.amount,0) < 0' : 'COALESCE(d.amount,0) > 0')
                ->orderBy('d.sno')
                ->first();

            if (!$entry) {
                continue;
            }
            if ($search !== '' && !str_contains(strtoupper($entry->code . ' ' . $entry->name . ' ' . $entry->billno . ' ' . $entry->details), strtoupper($search))) {
                continue;
            }

            $rows[] = [
                'ref' => (string) $entry->slno,
                'sno' => (int) $entry->sno,
                'module' => $source['type'],
                'issue' => 'Ledger Posting Issue',
                'date' => (string) $entry->tdate,
                'billno' => (string) $entry->billno,
                'code' => (string) $entry->code,
                'name' => (string) $entry->name,
                'mobile' => '',
                'billamt' => $amountAbs,
                'received' => 0,
                'balance' => abs(round((float) $entry->amount, 2)),
                'expected' => 0,
                'difference' => abs(round((float) $entry->amount, 2)),
                'details' => 'Same voucher has debit and credit for this account. Keeping ' . $source['type'] . ' side; clear daybook row sno ' . (int) $entry->sno . '. ' . (string) $entry->details,
                'can_update' => true,
                'update_action' => 'clear_daybook_row',
            ];
        }

        return $rows;
    }

    private function ledgerSourceForAccount(int $slno, string $code): ?array
    {
        foreach ($this->sourceLedgerConfigs() as $module => $config) {
            if (!$this->hasTable($config['table'])) {
                continue;
            }
            $row = DB::table($config['table'])
                ->where('slno', $slno)
                ->whereRaw('TRIM(COALESCE(' . $config['code_column'] . ',"")) = ?', [$code])
                ->first(['slno']);
            if ($row) {
                return ['type' => $module];
            }
        }

        return null;
    }

    private function clearOrphanSourceLedger(int $slno, string $module): int
    {
        $configs = $this->sourceLedgerConfigs();
        $config = $configs[$module] ?? null;
        if ($slno <= 0 || !$config || !$this->hasTable('daybook') || !$this->hasTable('daybookpart') || !$this->hasTable($config['table'])) {
            return 0;
        }
        if (DB::table($config['table'])->where('slno', $slno)->exists()) {
            return 0;
        }

        return DB::transaction(function () use ($slno) {
            $deleted = DB::table('daybook')->where('slno', $slno)->delete();
            DB::table('daybookpart')->where('slno', $slno)->delete();

            return $deleted;
        });
    }

    private function sourceLedgerConfigs(): array
    {
        return [
            'sales' => [
                'table' => 'salesm',
                'code_column' => 'custcode',
                'opaccode' => 'RS',
                'amount_sign' => 'negative',
                'particular_like' => 'BY SALES (%',
                'label' => 'Sales',
            ],
            'purchase' => [
                'table' => 'purchasem',
                'code_column' => 'suppcode',
                'opaccode' => 'EP',
                'amount_sign' => 'positive',
                'particular_like' => 'BY PURCHASE -%',
                'label' => 'Purchase',
            ],
            'sales_return' => [
                'table' => 'salesrm',
                'code_column' => 'custcode',
                'opaccode' => 'ESR',
                'amount_sign' => 'positive',
                'particular_like' => 'BY SALES RETURN%',
                'label' => 'Sales return',
            ],
            'purchase_return' => [
                'table' => 'purchaserm',
                'code_column' => 'suppcode',
                'opaccode' => 'EP',
                'amount_sign' => 'negative',
                'particular_like' => 'BY PURCHASE RETURN%',
                'label' => 'Purchase return',
            ],
        ];
    }

    private function clearDaybookRow(int $slno, int $sno): int
    {
        if ($slno <= 0 || $sno <= 0 || !$this->hasTable('daybook')) {
            return 0;
        }

        return DB::table('daybook')
            ->where('slno', $slno)
            ->where('sno', $sno)
            ->delete();
    }

    private function syncSalesBalanceToLedger(string $code, string $uptoDate, int $gilevel): array
    {
        if (!$this->hasTable('salesm') || !Schema::hasColumn('salesm', 'bal')) {
            return ['old_balance' => 0, 'new_balance' => 0, 'target_balance' => 0, 'updated' => 0];
        }

        $ledgerNet = $this->ledgerNetBalance($code, $uptoDate, $gilevel);
        $targetBalance = $ledgerNet < 0 ? abs($ledgerNet) : 0.0;
        $amountExpr = $this->salesAmountExpression();
        $rows = DB::table('salesm as s')
            ->selectRaw('s.slno, s.tdate, COALESCE(s.bal,0) as old_balance, ' . $amountExpr . ' as bill_amount, COALESCE(s.ramt,0) as received, (' . $amountExpr . ' - COALESCE(s.ramt,0)) as expected')
            ->whereRaw('UPPER(TRIM(COALESCE(s.custcode,""))) = ?', [$code])
            ->where('s.tdate', '<=', $uptoDate)
            ->where('s.control', '<=', $gilevel)
            ->whereRaw('((' . $amountExpr . ' - COALESCE(s.ramt,0)) > 0.01 OR COALESCE(s.bal,0) > 0.01)')
            ->orderByDesc('s.tdate')
            ->orderByDesc('s.slno')
            ->get();

        $oldBalance = round((float) $rows->sum(fn ($row) => max(0, (float) ($row->expected ?? 0))), 2);
        $remaining = $targetBalance;
        $updated = 0;

        DB::transaction(function () use ($rows, &$remaining, &$updated) {
            foreach ($rows as $row) {
                $billAmount = max(0, round((float) ($row->bill_amount ?? 0), 2));
                $newBalance = min($billAmount, $remaining);
                $remaining = round($remaining - $newBalance, 2);
                $newReceived = round($billAmount - $newBalance, 2);
                $updates = ['bal' => $newBalance];
                if (Schema::hasColumn('salesm', 'ramt')) {
                    $updates['ramt'] = $newReceived;
                }

                if (
                    abs(round((float) ($row->old_balance ?? 0), 2) - $newBalance) <= 0.01
                    && abs(round((float) ($row->received ?? 0), 2) - $newReceived) <= 0.01
                ) {
                    continue;
                }

                DB::table('salesm')->where('slno', $row->slno)->update($updates);
                $updated++;
            }
        });

        return [
            'old_balance' => $oldBalance,
            'new_balance' => round($targetBalance - max(0, $remaining), 2),
            'target_balance' => round($targetBalance, 2),
            'updated' => $updated,
        ];
    }

    private function ledgerNetBalance(string $code, string $uptoDate, int $gilevel): float
    {
        $opColumn = $gilevel === 1 ? 'opbal' : 'opbalb';
        $opbal = 0.0;
        if ($this->hasTable('accountm') && Schema::hasColumn('accountm', $opColumn)) {
            $opbal = (float) DB::table('accountm')
                ->whereRaw('UPPER(TRIM(accode)) = ?', [$code])
                ->value($opColumn);
        }

        $tran = 0.0;
        if ($this->hasTable('daybook')) {
            $tran = (float) DB::table('daybook')
                ->whereRaw('UPPER(TRIM(accode)) = ?', [$code])
                ->where('tdate', '<=', $uptoDate)
                ->where('control', '<=', $gilevel)
                ->sum('amount');
        }

        return round($opbal + $tran, 2);
    }

    private function salesBaseQuery(string $date1, string $date2, int $gilevel, string $search)
    {
        $amountExpr = $this->salesAmountExpression();
        $query = DB::table('salesm as s')
            ->leftJoin('clients as c', 's.custcode', '=', 'c.code')
            ->leftJoin('accountm as a', 's.custcode', '=', 'a.accode')
            ->selectRaw('
                s.slno,
                TRIM(COALESCE(s.billno,"")) as billno,
                s.tdate,
                TRIM(COALESCE(s.custcode,"")) as code,
                TRIM(COALESCE(c.name, s.custname, "")) as name,
                TRIM(COALESCE(c.mobile, "")) as mobile,
                ' . $amountExpr . ' as billamt,
                COALESCE(s.ramt,0) as received,
                COALESCE(s.bal,0) as balance,
                (' . $amountExpr . ' - COALESCE(s.ramt,0)) as expected,
                (COALESCE(s.bal,0) - (' . $amountExpr . ' - COALESCE(s.ramt,0))) as difference,
                CASE WHEN c.code IS NULL THEN 0 ELSE 1 END as client_found,
                CASE WHEN a.accode IS NULL THEN 0 ELSE 1 END as account_found
            ')
            ->whereBetween('s.tdate', [$date1, $date2])
            ->where('s.control', '<=', $gilevel);

        $this->applyCustomerSearch($query, $search, 's.custcode', 'c.name', 'c.mobile', 's.billno', 's.custname');

        return $query;
    }

    private function salesAmountExpression(): string
    {
        if (Schema::hasColumn('salesm', 'netamt')) {
            return 'COALESCE(s.netamt, s.billamt, 0)';
        }

        return 'COALESCE(s.billamt, 0)';
    }

    private function salesRow(object $row, string $issue, string $details): array
    {
        $slno = (string) ($row->slno ?? '');

        return [
            'ref' => $slno,
            'issue' => $issue,
            'date' => (string) ($row->tdate ?? ''),
            'billno' => (string) ($row->billno ?? ''),
            'code' => (string) ($row->code ?? ''),
            'name' => (string) ($row->name ?? ''),
            'mobile' => (string) ($row->mobile ?? ''),
            'billamt' => round((float) ($row->billamt ?? 0), 2),
            'received' => round((float) ($row->received ?? 0), 2),
            'balance' => round((float) ($row->balance ?? 0), 2),
            'expected' => round((float) ($row->expected ?? 0), 2),
            'difference' => round((float) ($row->difference ?? 0), 2),
            'details' => $details,
            'can_update' => $slno !== '' && $issue === 'Balance Mismatch',
            'update_action' => 'update_sales_balance',
        ];
    }

    private function sortRows(array &$rows, string $sort): void
    {
        $fallback = fn ($a, $b) => strcmp((string) ($a['date'] ?? ''), (string) ($b['date'] ?? ''))
            ?: strcmp((string) ($a['code'] ?? ''), (string) ($b['code'] ?? ''))
            ?: strcmp((string) ($a['billno'] ?? ''), (string) ($b['billno'] ?? ''));

        usort($rows, function ($a, $b) use ($sort, $fallback) {
            return match ($sort) {
                'issue' => strcmp((string) ($a['issue'] ?? ''), (string) ($b['issue'] ?? '')) ?: $fallback($a, $b),
                'billno' => strcmp((string) ($a['billno'] ?? ''), (string) ($b['billno'] ?? '')) ?: $fallback($a, $b),
                'code' => strcmp((string) ($a['code'] ?? ''), (string) ($b['code'] ?? '')) ?: $fallback($a, $b),
                'customer' => strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')) ?: $fallback($a, $b),
                'balance' => ((float) ($b['balance'] ?? 0) <=> (float) ($a['balance'] ?? 0)) ?: $fallback($a, $b),
                'difference' => (abs((float) ($b['difference'] ?? 0)) <=> abs((float) ($a['difference'] ?? 0))) ?: $fallback($a, $b),
                default => $fallback($a, $b),
            };
        });
    }

    private function applyCustomerSearch($query, string $search, string ...$columns): void
    {
        if ($search === '') {
            return;
        }

        $needle = '%' . strtoupper($search) . '%';
        $query->where(function ($sub) use ($columns, $needle) {
            foreach ($columns as $column) {
                $sub->orWhereRaw('UPPER(TRIM(COALESCE(' . $column . ', ""))) LIKE ?', [$needle]);
            }
        });
    }

    private function startDate(Request $request): string
    {
        $date = (string) $request->session()->get('gdtstartdate', '');
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : date('Y') . '-04-01';
    }

    private function normalizeDate(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }

        return date('Y-m-d');
    }
}
