<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AccountLedgerController extends Controller
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
        $accountQuery = trim((string) $request->query('accode', ''));
        $code = $this->resolveAccountCode($accountQuery);
        $type = (string) $request->query('type', 'ordinary');
        $search = trim((string) $request->query('search', ''));
        $amountSearch = trim((string) $request->query('amount', ''));
        $suspOnly = $request->boolean('susp_only');
        $show = $request->boolean('show') || $accountQuery !== '';

        $accountOptions = $this->accountOptions();
        $account = null;
        $rows = [];
        $totals = ['debit' => 0.0, 'credit' => 0.0];
        $openingBalance = 0.0;
        $closingBalance = 0.0;
        $linkedCode = '';
        $address = '';
        $weightNote = '';

        if ($show && $code !== '') {
            $account = $this->loadAccount($code, $gilevel);
            if ($account) {
                $this->repairMissingSalesLedgerPostings($code, $dateFrom, $dateTo, $gilevel);
                $openingBalance = $this->openingBalance($code, $dateFrom, $gilevel, $account);
                $rows = $this->ledgerRows($code, $dateFrom, $dateTo, $gilevel, $type, $suspOnly, $search, $amountSearch, $openingBalance);
                foreach ($rows as $row) {
                    $totals['debit'] += $row['debit'];
                    $totals['credit'] += $row['credit'];
                }
                $closingBalance = empty($rows)
                    ? $openingBalance
                    : (float) end($rows)['running_balance'];
                $linkedCode = $this->linkedAccountCode($code);
                $address = $this->clientAddress($code);
                $weightNote = $this->weightNote($code, $dateTo, (string) ($account['actype2'] ?? ''));
            }
        }

        return view('reports.account-ledger', [
            'gilevel' => $gilevel,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'code' => $code,
            'accountQuery' => $accountQuery,
            'type' => $type,
            'search' => $search,
            'amountSearch' => $amountSearch,
            'suspOnly' => $suspOnly,
            'show' => $show,
            'accountOptions' => $accountOptions,
            'account' => $account,
            'rows' => $rows,
            'totals' => $totals,
            'openingBalance' => $openingBalance,
            'closingBalance' => $closingBalance,
            'linkedCode' => $linkedCode,
            'address' => $address,
            'weightNote' => $weightNote,
        ]);
    }

    public function customerLedgerApi(Request $request): \Illuminate\Http\JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $gilevel = max(1, (int) $request->session()->get('gilevel', 1));
        $code    = trim((string) $request->query('code', ''));
        $dateFrom = $this->normalizeDate((string) $request->query('date1', ''))
            ?: $this->normalizeDate((string) $request->session()->get('gdtstartdate', ''))
            ?: date('Y-m-01');
        $dateTo   = $this->normalizeDate((string) $request->query('date2', '')) ?: date('Y-m-d');

        if ($code === '') {
            return response()->json(['ok' => false, 'message' => 'Code required']);
        }

        $account = $this->loadAccount($code, $gilevel);
        if (!$account) {
            return response()->json(['ok' => false, 'message' => 'Account not found']);
        }

        $this->repairMissingSalesLedgerPostings($code, $dateFrom, $dateTo, $gilevel);
        $openingBalance = $this->openingBalance($code, $dateFrom, $gilevel, $account);
        $rows           = $this->ledgerRows($code, $dateFrom, $dateTo, $gilevel, 'ordinary', false, '', '', $openingBalance);

        $totals = ['debit' => 0.0, 'credit' => 0.0];
        foreach ($rows as $row) {
            $totals['debit']  += $row['debit'];
            $totals['credit'] += $row['credit'];
        }
        $closingBalance = empty($rows) ? $openingBalance : (float) end($rows)['running_balance'];

        return response()->json([
            'ok'              => true,
            'accode'          => $account['accode'],
            'name'            => $account['name'],
            'date_from'       => $dateFrom,
            'date_to'         => $dateTo,
            'opening_balance' => $openingBalance,
            'closing_balance' => $closingBalance,
            'totals'          => $totals,
            'rows'            => array_map(fn($r) => [
                'date'            => $r['date'],
                'vchno'           => $r['vchno'],
                'othacname'       => $r['othacname'],
                'part'            => $r['part'],
                'debit'           => $r['debit'],
                'credit'          => $r['credit'],
                'running_balance' => $r['running_balance'],
                'running_side'    => $r['running_side'],
                'navigate_url'    => $r['navigate_url'],
            ], $rows),
        ]);
    }

    public function repairSalesLedger(Request $request): RedirectResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $gilevel = max(1, (int) $request->session()->get('gilevel', 1));
        $dateFrom = $this->normalizeDate((string) $request->input('date1', ''))
            ?: $this->normalizeDate((string) $request->session()->get('gdtstartdate', ''))
            ?: date('Y-m-01');
        $dateTo = $this->normalizeDate((string) $request->input('date2', '')) ?: date('Y-m-d');
        $accountQuery = trim((string) $request->input('accode', ''));
        $code = $this->resolveAccountCode($accountQuery);

        $count = $this->repairMissingSalesLedgerPostings($code, $dateFrom, $dateTo, $gilevel);
        $scope = $code !== '' ? ('account ' . $code) : 'selected period';

        return redirect(url('/accounts/ac-ledger') . '?' . http_build_query([
            'show' => 1,
            'accode' => $accountQuery !== '' ? $accountQuery : $code,
            'date1' => $dateFrom,
            'date2' => $dateTo,
            'type' => (string) $request->input('type', 'ordinary'),
            'search' => (string) $request->input('search', ''),
            'amount' => (string) $request->input('amount', ''),
            'susp_only' => $request->boolean('susp_only') ? 1 : 0,
        ]))->with('status', "Repaired {$count} missing sales ledger posting(s) for {$scope}.");
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

        $q = DB::table('accountm')
            ->selectRaw('TRIM(accode) as accode, TRIM(name) as name, TRIM(actype2) as actype2')
            ->orderBy('accode');

        if (Schema::hasColumn('accountm', 'removed')) {
            $q->where(function ($inner) {
                $inner->where('removed', '!=', 1)->orWhereNull('removed');
            });
        }

        return $q->get()->map(fn ($row) => [
            'accode' => (string) $row->accode,
            'name' => (string) $row->name,
            'actype2' => (string) $row->actype2,
        ])->all();
    }

    private function resolveAccountCode(string $query): string
    {
        $query = trim($query);
        if ($query === '' || !$this->hasTable('accountm')) {
            return '';
        }

        $upper = strtoupper($query);

        $byCode = DB::table('accountm')
            ->selectRaw('TRIM(accode) as accode')
            ->whereRaw('UPPER(TRIM(accode)) = ?', [$upper])
            ->first();

        if ($byCode && !empty($byCode->accode)) {
            return (string) $byCode->accode;
        }

        $byNameExact = DB::table('accountm')
            ->selectRaw('TRIM(accode) as accode')
            ->whereRaw('UPPER(TRIM(name)) = ?', [$upper])
            ->orderBy('accode')
            ->first();

        if ($byNameExact && !empty($byNameExact->accode)) {
            return (string) $byNameExact->accode;
        }

        $byNameLike = DB::table('accountm')
            ->selectRaw('TRIM(accode) as accode')
            ->whereRaw('UPPER(TRIM(name)) like ?', ['%' . $upper . '%'])
            ->orderBy('name')
            ->orderBy('accode')
            ->first();

        return $byNameLike && !empty($byNameLike->accode)
            ? (string) $byNameLike->accode
            : strtoupper($query);
    }

    private function loadAccount(string $code, int $gilevel): ?array
    {
        if (!$this->hasTable('accountm')) {
            return null;
        }

        $row = DB::table('accountm')
            ->selectRaw('TRIM(accode) as accode, TRIM(name) as name, TRIM(actype2) as actype2, opbal, opbalb')
            ->whereRaw('TRIM(accode)=?', [$code])
            ->first();

        if (!$row) {
            return null;
        }

        return [
            'accode' => (string) $row->accode,
            'name' => (string) $row->name,
            'actype2' => (string) $row->actype2,
            'opbal' => (float) ($gilevel === 1 ? ($row->opbal ?? 0) : ($row->opbalb ?? 0)),
        ];
    }

    private function openingBalance(string $code, string $dateFrom, int $gilevel, array $account): float
    {
        $sum = 0.0;
        if ($this->hasTable('daybook')) {
            $sum = (float) DB::table('daybook')
                ->whereRaw('TRIM(accode)=?', [$code])
                ->where('tdate', '<', $dateFrom)
                ->where('control', '<=', $gilevel)
                ->sum('amount');
        }

        return (float) $account['opbal'] + $sum;
    }

    private function repairMissingSalesLedgerPostings(string $code, string $dateFrom, string $dateTo, int $gilevel): int
    {
        if (
            !$this->hasTable('salesm')
            || !$this->hasTable('daybook')
            || !$this->hasTable('daybookpart')
        ) {
            return 0;
        }

        $query = DB::table('salesm as s')
            ->leftJoin('daybook as d', 'd.slno', '=', 's.slno')
            ->select([
                's.slno',
                's.billno',
                's.tdate',
                's.ttime',
                's.custcode',
                's.custname',
                's.netamt',
                's.control',
            ])
            ->whereBetween('s.tdate', [$dateFrom, $dateTo])
            ->where('s.control', '<=', $gilevel)
            ->whereNull('d.slno')
            ->orderBy('s.tdate')
            ->orderBy('s.slno');

        if ($code !== '') {
            $query->whereRaw('TRIM(COALESCE(s.custcode, "")) = ?', [$code]);
        }

        $missingSales = $query->get();
        $repaired = 0;

        foreach ($missingSales as $sale) {
            $slno = (int) ($sale->slno ?? 0);
            $netAmt = round((float) ($sale->netamt ?? 0), 2);
            $billNo = trim((string) ($sale->billno ?? ''));
            $custCode = trim((string) ($sale->custcode ?? ''));
            $custName = trim((string) ($sale->custname ?? ''));
            $tdate = (string) ($sale->tdate ?? '');
            $ttime = trim((string) ($sale->ttime ?? ''));
            $control = max(1, (int) ($sale->control ?? 1));

            if ($slno <= 0 || $custCode === '' || $netAmt == 0.0 || $tdate === '') {
                continue;
            }

            DB::transaction(function () use ($slno, $billNo, $custCode, $custName, $tdate, $ttime, $control, $netAmt) {
                if (!DB::table('daybookpart')->where('slno', $slno)->exists()) {
                    DB::table('daybookpart')->insert([
                        'slno' => $slno,
                        'particular' => mb_substr('By Sales (' . $billNo . ') To ' . $custName, 0, 100),
                        'vchno' => mb_substr($billNo, 0, 10),
                        'ic' => '',
                        'uid' => '',
                        'ttime' => $ttime !== '' ? $ttime : null,
                        'rate' => 0,
                    ]);
                }

                if (!DB::table('daybook')->where('slno', $slno)->exists()) {
                    DB::table('daybook')->insert([
                        'slno' => $slno,
                        'sno' => 1,
                        'tdate' => $tdate,
                        'accode' => mb_substr($custCode, 0, 8),
                        'amount' => -$netAmt,
                        'control' => $control,
                        'opaccode' => 'RS',
                    ]);

                    DB::table('daybook')->insert([
                        'slno' => $slno,
                        'sno' => 2,
                        'tdate' => $tdate,
                        'accode' => 'RS',
                        'amount' => $netAmt,
                        'control' => $control,
                        'opaccode' => mb_substr($custCode, 0, 8),
                    ]);
                }
            });
            $repaired++;
        }

        return $repaired;
    }

    private function ledgerRows(
        string $code,
        string $dateFrom,
        string $dateTo,
        int $gilevel,
        string $type,
        bool $suspOnly,
        string $search,
        string $amountSearch,
        float $openingBalance
    ): array {
        if (!$this->hasTable('daybook') || !$this->hasTable('daybookpart')) {
            return [];
        }

        $query = DB::table('daybook')
            ->join('daybookpart', 'daybook.slno', '=', 'daybookpart.slno')
            ->leftJoin('accountm as oth', DB::raw('TRIM(daybook.opaccode)'), '=', DB::raw('TRIM(oth.accode)'))
            ->selectRaw('
                daybook.slno,
                daybook.tdate,
                TRIM(daybook.accode) as accode,
                daybook.amount,
                TRIM(COALESCE(daybookpart.vchno, "")) as vchno,
                TRIM(COALESCE(daybookpart.staff, "")) as staff,
                TRIM(COALESCE(daybookpart.particular, "")) as particular,
                COALESCE(daybookpart.slno2, 0) as slno2,
                TRIM(COALESCE(daybook.opaccode, "")) as opaccode,
                TRIM(COALESCE(oth.name, "")) as othacname
            ')
            ->whereRaw('TRIM(daybook.accode)=?', [$code])
            ->whereBetween('daybook.tdate', [$dateFrom, $dateTo])
            ->where('daybook.control', '<=', $gilevel)
            ->orderBy('daybook.tdate')
            ->orderBy('daybook.slno');

        if ($suspOnly) {
            $query->where(function ($inner) {
                $inner->where('daybookpart.slno2', 0)->orWhereNull('daybookpart.slno2');
            });
        }

        if ($search !== '') {
            $like = '%' . strtoupper($search) . '%';
            $query->where(function ($inner) use ($like) {
                $inner->whereRaw('UPPER(TRIM(COALESCE(daybookpart.particular, ""))) like ?', [$like])
                    ->orWhereRaw('UPPER(TRIM(COALESCE(oth.name, ""))) like ?', [$like])
                    ->orWhereRaw('UPPER(TRIM(COALESCE(daybookpart.vchno, ""))) like ?', [$like]);
            });
        }

        $amountSearch = trim($amountSearch);
        if ($amountSearch !== '' && is_numeric($amountSearch)) {
            $query->whereRaw('ABS(COALESCE(daybook.amount,0)) = ?', [(float) $amountSearch]);
        }

        $rows = $query->get()->map(fn ($row) => (array) $row)->all();

        $rateMap = [];
        if (($type === 'withrate' || $type === 'withwgt') && $this->hasTable('daybookratewgt')) {
            $slnoList = collect($rows)->pluck('slno')->unique()->values()->all();
            if (!empty($slnoList)) {
                $rateMap = DB::table('daybookratewgt')
                    ->selectRaw('slno, COALESCE(rate,0) as rate, COALESCE(wgt,0) as wgt, COALESCE(mcp,0) as mcp')
                    ->whereIn('slno', $slnoList)
                    ->get()
                    ->keyBy('slno')
                    ->all();
            }
        }

        $running = $openingBalance;
        $result = [];
        foreach ($rows as $row) {
            $amount = (float) ($row['amount'] ?? 0);
            $running += $amount;
            $rateRow = $rateMap[$row['slno']] ?? null;
            $part = trim((string) $row['particular']);
            $staff = trim((string) $row['staff']);
            if ($staff !== '') {
                $part .= ($part !== '' ? ' ->Staff-> ' : 'Staff-> ') . $staff;
            }
            $navigateUrl = $this->resolveDocumentUrl(
                (int) $row['slno'],
                (string) ($row['tdate'] ?? ''),
                (string) ($row['vchno'] ?? ''),
                $part
            );

            $result[] = [
                'slno' => (int) $row['slno'],
                'date' => (string) $row['tdate'],
                'vchno' => (string) $row['vchno'],
                'othacname' => (string) $row['othacname'],
                'part' => $part,
                'debit' => $amount < 0 ? abs($amount) : 0.0,
                'credit' => $amount > 0 ? $amount : 0.0,
                'running_balance' => $running,
                'running_side' => $running < 0 ? 'Dr' : 'Cr',
                'slno2' => (int) ($row['slno2'] ?? 0),
                'rate' => $rateRow ? (float) ($rateRow->rate ?? 0) : 0.0,
                'wgt' => $rateRow ? (float) ($rateRow->wgt ?? 0) : 0.0,
                'mcp' => $rateRow ? (float) ($rateRow->mcp ?? 0) : 0.0,
                'navigate_url' => $navigateUrl,
            ];
        }

        return $result;
    }

    private function resolveDocumentUrl(int $slno, string $tdate, string $vchno, string $particular): string
    {
        if ($slno <= 0) {
            return '';
        }

        if ($this->hasTable('salesm')) {
            $sales = DB::table('salesm')
                ->where('slno', $slno)
                ->first(['billno']);
            $billNo = trim((string) ($sales->billno ?? ''));
            if ($billNo !== '') {
                return url('/sales-bill/edit?bill_no=' . urlencode($billNo));
            }
        }

        if ($this->hasTable('salesrm')) {
            $salesReturn = DB::table('salesrm')
                ->where('slno', $slno)
                ->first(['slno']);
            if ($salesReturn) {
                return url('/sales-return/edit?' . http_build_query(['slno' => $slno]));
            }
        }

        if ($this->hasTable('purchasem')) {
            $purchase = DB::table('purchasem')
                ->where('slno', $slno)
                ->first(['docno', 'pr']);
            $docNo = trim((string) ($purchase->docno ?? ''));
            if ($docNo !== '' && in_array(strtoupper(trim((string) ($purchase->pr ?? 'P'))), ['P', 'E'], true)) {
                return url('/purchase-bill/edit?' . http_build_query(['doc_no' => $docNo]));
            }
        }

        if ($this->hasTable('purchaserm')) {
            $purchaseReturn = DB::table('purchaserm')
                ->where('slno', $slno)
                ->first(['docno']);
            $docNo = trim((string) ($purchaseReturn->docno ?? ''));
            if ($docNo !== '') {
                return url('/purchase-return/edit?' . http_build_query(['doc_no' => $docNo]));
            }
        }

        if ($this->hasTable('purchasem')) {
            $expenseVoucher = DB::table('purchasem')
                ->where('slno', $slno)
                ->where('note', 'Expense Voucher')
                ->first(['slno']);
            if ($expenseVoucher) {
                return url('/accounts/expense-voucher-entry?' . http_build_query(['slno' => $slno]));
            }
        }

        $voucherPrefix = strtoupper(substr(trim($vchno), 0, 2));
        if ($voucherPrefix === 'VR') {
            return url('/accounts/receipt?' . http_build_query([
                'mode' => 'E',
                'slno' => $slno,
                'vchno' => trim($vchno),
            ]));
        }

        if ($voucherPrefix === 'VP') {
            return url('/accounts/payment?' . http_build_query(['mode' => 'E', 'slno' => $slno]));
        }

        if ($voucherPrefix === 'JL') {
            return url('/accounts/journal?' . http_build_query([
                'slno' => $slno,
                'vchno' => trim($vchno),
            ]));
        }

        return '';
    }

    private function linkedAccountCode(string $code): string
    {
        if (!$this->hasTable('clients_kuridet')) {
            return '';
        }

        $link = trim((string) (DB::table('clients_kuridet')->where('custlinkac', $code)->value('code') ?? ''));
        if ($link !== '') {
            return strtoupper($link);
        }

        return strtoupper(trim((string) (DB::table('clients_kuridet')->where('code', $code)->value('custlinkac') ?? '')));
    }

    private function clientAddress(string $code): string
    {
        if (!$this->hasTable('clients')) {
            return '';
        }

        $row = DB::table('clients')
            ->selectRaw('TRIM(COALESCE(addr1, "")) as addr1, TRIM(COALESCE(addr2, "")) as addr2, TRIM(COALESCE(addr3, "")) as addr3, TRIM(COALESCE(city, "")) as city')
            ->where('code', $code)
            ->first();

        if (!$row) {
            return '';
        }

        return implode(', ', array_values(array_filter([
            (string) $row->addr1,
            (string) $row->addr2,
            (string) $row->addr3,
            (string) $row->city,
        ], fn ($value) => $value !== '')));
    }

    private function weightNote(string $code, string $dateTo, string $actype2): string
    {
        if (!in_array($actype2, ['G', 'J'], true) || !function_exists('app')) {
            return '';
        }

        if (!$this->hasTable('smithm') && !$this->hasTable('smithd')) {
            return '';
        }

        return '';
    }
}
