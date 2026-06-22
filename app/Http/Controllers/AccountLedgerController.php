<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
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
        $show = $request->boolean('show');
        $code = $show ? $this->resolveAccountCode($accountQuery) : '';
        $type = (string) $request->query('type', 'ordinary');
        $search = trim((string) $request->query('search', ''));
        $amountSearch = trim((string) $request->query('amount', ''));
        $suspOnly = $request->boolean('susp_only');

        $accountOptions = [];
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

    public function searchAccounts(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'accounts' => []], 401);
        }
        if (!$this->hasTable('accountm')) {
            return response()->json(['ok' => true, 'accounts' => [], 'total' => 0]);
        }

        $q = trim((string) $request->query('q', ''));
        $type = trim((string) $request->query('type', ''));
        $limit = min(120, max(20, (int) $request->query('limit', 80)));

        $query = DB::table('accountm')
            ->selectRaw('TRIM(accode) as accode, TRIM(name) as name, TRIM(actype2) as actype2')
            ->orderBy('accode');

        if (Schema::hasColumn('accountm', 'removed')) {
            $query->where(function ($inner) {
                $inner->where('removed', '!=', 1)->orWhereNull('removed');
            });
        }

        $this->applyAccountTypeFilter($query, $type);

        $phoneMatchedAccounts = $this->accountCodesByPhoneSearch($q);
        if ($q !== '') {
            $like = '%' . strtoupper($q) . '%';
            $query->where(function ($inner) use ($like, $phoneMatchedAccounts) {
                $inner->whereRaw('UPPER(TRIM(accode)) like ?', [$like])
                    ->orWhereRaw('UPPER(TRIM(name)) like ?', [$like]);
                if ($phoneMatchedAccounts !== []) {
                    $inner->orWhereIn(DB::raw('TRIM(accode)'), $phoneMatchedAccounts);
                }
            });
        }

        $rows = $query->limit($limit)->get();
        $phoneMap = $this->accountPhoneMap(
            $rows->pluck('accode')->map(fn ($code) => trim((string) $code))->filter()->values()->all()
        );

        $accounts = $rows->map(fn ($row) => [
            'accode' => (string) $row->accode,
            'name' => (string) $row->name,
            'actype2' => (string) $row->actype2,
            'mobile' => $phoneMap[trim((string) $row->accode)] ?? '',
        ])->all();

        return response()->json([
            'ok' => true,
            'accounts' => $accounts,
            'total' => count($accounts),
            'limited' => count($accounts) >= $limit,
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

    public function clearLedgerIssue(Request $request): RedirectResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $slno = (int) $request->input('slno', 0);
        $module = trim((string) $request->input('module', ''));
        $deleted = $this->clearOrphanSourceLedger($slno, $module);

        return redirect(url('/accounts/ac-ledger') . '?' . http_build_query([
            'show' => 1,
            'accode' => (string) $request->input('accode', ''),
            'date1' => (string) $request->input('date1', ''),
            'date2' => (string) $request->input('date2', ''),
            'type' => (string) $request->input('type', 'ordinary'),
            'search' => (string) $request->input('search', ''),
            'amount' => (string) $request->input('amount', ''),
            'susp_only' => $request->boolean('susp_only') ? 1 : 0,
        ]))->with('status', $deleted > 0
            ? "Cleared orphan ledger posting #{$slno}."
            : "No ledger posting cleared for #{$slno}; source bill may exist or row is not safe to clear.");
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

        $rows = $q->get();
        $phoneMap = $this->accountPhoneMap(
            $rows->pluck('accode')->map(fn ($code) => trim((string) $code))->filter()->values()->all()
        );

        return $rows->map(fn ($row) => [
            'accode' => (string) $row->accode,
            'name' => (string) $row->name,
            'actype2' => (string) $row->actype2,
            'mobile' => $phoneMap[trim((string) $row->accode)] ?? '',
        ])->all();
    }

    private function applyAccountTypeFilter($query, string $type): void
    {
        $type = strtolower(trim($type));
        if ($type === '' || $type === 'all') {
            return;
        }

        $query->where(function ($inner) use ($type) {
            if ($type === 'customer') {
                $inner->whereRaw('UPPER(TRIM(COALESCE(actype2, ""))) like ?', ['%CUST%'])
                    ->orWhereRaw('UPPER(TRIM(COALESCE(actype2, ""))) = ?', ['C']);
            } elseif ($type === 'supplier') {
                $inner->whereRaw('UPPER(TRIM(COALESCE(actype2, ""))) like ?', ['%SUP%'])
                    ->orWhereRaw('UPPER(TRIM(COALESCE(actype2, ""))) = ?', ['S']);
            } elseif ($type === 'staff') {
                $inner->whereRaw('UPPER(TRIM(COALESCE(actype2, ""))) like ?', ['%STAF%'])
                    ->orWhereRaw('UPPER(TRIM(COALESCE(actype2, ""))) like ?', ['%EMP%']);
            } elseif ($type === 'goldsmith') {
                $inner->whereRaw('UPPER(TRIM(COALESCE(actype2, ""))) like ?', ['%GOLD%'])
                    ->orWhereRaw('UPPER(TRIM(COALESCE(actype2, ""))) like ?', ['%SMITH%']);
            } elseif ($type === 'agent') {
                $inner->whereRaw('UPPER(TRIM(COALESCE(actype2, ""))) like ?', ['%AGENT%']);
            } elseif ($type === 'bank') {
                $inner->whereRaw('UPPER(TRIM(COALESCE(actype2, ""))) like ?', ['%BANK%']);
            } elseif ($type === 'cash') {
                $inner->whereRaw('UPPER(TRIM(COALESCE(actype2, ""))) like ?', ['%CASH%']);
            } elseif ($type === 'other') {
                $inner->whereRaw('TRIM(COALESCE(actype2, "")) = ?', ['']);
            }
        });
    }

    private function accountCodesByPhoneSearch(string $phone): array
    {
        $phone = trim($phone);
        $phoneColumns = $this->clientPhoneColumns();
        if ($phone === '' || $phoneColumns === []) {
            return [];
        }

        $like = '%' . $phone . '%';
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '' && strlen($phone) < 3) {
            return [];
        }

        $clientCodes = DB::table('clients')
            ->where(function ($q) use ($phoneColumns, $like, $digits) {
                foreach ($phoneColumns as $idx => $column) {
                    $method = $idx === 0 ? 'where' : 'orWhere';
                    $q->{$method}($column, 'like', $like);
                    if ($digits !== '') {
                        $q->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE({$column}, ''), ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') LIKE ?", ['%' . $digits . '%']);
                    }
                }
            })
            ->limit(120)
            ->pluck('code')
            ->map(fn ($code) => trim((string) $code))
            ->filter()
            ->values()
            ->all();

        if ($clientCodes === []) {
            return [];
        }

        $accountCodes = $clientCodes;
        if ($this->hasTable('clients_kuridet')) {
            $linked = DB::table('clients_kuridet')
                ->whereIn('code', $clientCodes)
                ->pluck('custlinkac')
                ->map(fn ($code) => trim((string) $code))
                ->filter()
                ->values()
                ->all();
            $accountCodes = array_merge($accountCodes, $linked);
        }

        return array_values(array_unique(array_filter($accountCodes)));
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

        $digits = preg_replace('/\D+/', '', $query);
        if ($digits !== '' && strlen($digits) >= 3) {
            $byPhone = $this->resolveAccountCodeByPhone($query);
            if ($byPhone !== '') {
                return $byPhone;
            }
        }

        $byNameLike = DB::table('accountm')
            ->selectRaw('TRIM(accode) as accode')
            ->whereRaw('UPPER(TRIM(name)) like ?', ['%' . $upper . '%'])
            ->orderBy('name')
            ->orderBy('accode')
            ->first();

        if ($byNameLike && !empty($byNameLike->accode)) {
            return (string) $byNameLike->accode;
        }

        if ($digits === '' || strlen($digits) < 3) {
            $byPhone = $this->resolveAccountCodeByPhone($query);
            if ($byPhone !== '') {
                return $byPhone;
            }
        }

        return strtoupper($query);
    }

    private function accountPhoneMap(array $accountCodes): array
    {
        $accountCodes = array_values(array_unique(array_filter(array_map(fn ($code) => trim((string) $code), $accountCodes))));
        if ($accountCodes === [] || !$this->hasTable('clients')) {
            return [];
        }

        $phoneColumns = $this->clientPhoneColumns();
        if ($phoneColumns === []) {
            return [];
        }

        $clientToAccount = [];
        foreach ($accountCodes as $code) {
            $clientToAccount[$code] = $code;
        }

        if ($this->hasTable('clients_kuridet')) {
            DB::table('clients_kuridet')
                ->whereIn('custlinkac', $accountCodes)
                ->get(['code', 'custlinkac'])
                ->each(function ($row) use (&$clientToAccount) {
                    $clientCode = trim((string) ($row->code ?? ''));
                    $accountCode = trim((string) ($row->custlinkac ?? ''));
                    if ($clientCode !== '' && $accountCode !== '') {
                        $clientToAccount[$clientCode] = $accountCode;
                    }
                });
        }

        $phones = [];
        DB::table('clients')
            ->whereIn('code', array_keys($clientToAccount))
            ->get(array_merge(['code'], $phoneColumns))
            ->each(function ($row) use (&$phones, $clientToAccount, $phoneColumns) {
                $clientCode = trim((string) ($row->code ?? ''));
                $accountCode = $clientToAccount[$clientCode] ?? '';
                if ($accountCode === '' || ($phones[$accountCode] ?? '') !== '') {
                    return;
                }
                foreach ($phoneColumns as $column) {
                    $phone = trim((string) ($row->{$column} ?? ''));
                    if ($phone !== '') {
                        $phones[$accountCode] = $phone;
                        break;
                    }
                }
            });

        return $phones;
    }

    private function clientPhoneColumns(): array
    {
        if (!$this->hasTable('clients')) {
            return [];
        }

        return array_values(array_filter(
            ['mobile', 'telephone', 'homemobile'],
            fn ($column) => Schema::hasColumn('clients', $column)
        ));
    }

    private function resolveAccountCodeByPhone(string $phone): string
    {
        $phone = trim($phone);
        $phoneColumns = $this->clientPhoneColumns();
        if ($phone === '' || $phoneColumns === []) {
            return '';
        }

        $like = '%' . $phone . '%';
        $digits = preg_replace('/\D+/', '', $phone);
        $client = DB::table('clients')
            ->where(function ($q) use ($phoneColumns, $like, $digits) {
                foreach ($phoneColumns as $idx => $column) {
                    $method = $idx === 0 ? 'where' : 'orWhere';
                    $q->{$method}($column, 'like', $like);
                    if ($digits !== '') {
                        $q->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE({$column}, ''), ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') LIKE ?", ['%' . $digits . '%']);
                    }
                }
            })
            ->orderBy('code')
            ->first(['code']);

        $clientCode = trim((string) ($client->code ?? ''));
        if ($clientCode === '') {
            return '';
        }

        if ($this->hasTable('clients_kuridet')) {
            $linked = trim((string) (DB::table('clients_kuridet')->where('code', $clientCode)->value('custlinkac') ?? ''));
            if ($linked !== '') {
                return strtoupper($linked);
            }
        }

        return strtoupper($clientCode);
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
            $query = DB::table('daybook');
            if ($this->hasTable('daybookpart')) {
                $query->leftJoin('daybookpart', 'daybook.slno', '=', 'daybookpart.slno');
                $this->applyTransferShadowDocFilter($query, 'daybookpart.vchno');
            }
            $sum = (float) $query
                ->whereRaw('TRIM(daybook.accode)=?', [$code])
                ->where('daybook.tdate', '<', $dateFrom)
                ->where('daybook.control', '<=', $gilevel)
                ->sum('daybook.amount');
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
        $this->applyTransferShadowDocFilter($query, 'daybookpart.vchno');

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
        $oppositeSplits = $this->oppositeAccountSplitsForRows($rows, $code);

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
            $orphanModule = $this->orphanSourceModuleForRow($row);

            $splits = $oppositeSplits[$this->rowSplitKey($row)] ?? [];
            if ($splits === []) {
                $splits = [[
                    'name' => (string) $row['othacname'],
                    'amount' => abs($amount),
                ]];
            }

            foreach ($splits as $index => $split) {
                $lineAmount = $amount < 0 ? -abs((float) $split['amount']) : abs((float) $split['amount']);
                $running += $lineAmount;

                $result[] = [
                    'slno' => (int) $row['slno'],
                    'date' => (string) $row['tdate'],
                    'vchno' => (string) $row['vchno'],
                    'othacname' => (string) $split['name'],
                    'part' => $part,
                    'debit' => $lineAmount < 0 ? abs($lineAmount) : 0.0,
                    'credit' => $lineAmount > 0 ? $lineAmount : 0.0,
                    'running_balance' => $running,
                    'running_side' => $running < 0 ? 'Dr' : 'Cr',
                    'slno2' => (int) ($row['slno2'] ?? 0),
                    'rate' => $index === 0 && $rateRow ? (float) ($rateRow->rate ?? 0) : 0.0,
                    'wgt' => $index === 0 && $rateRow ? (float) ($rateRow->wgt ?? 0) : 0.0,
                    'mcp' => $index === 0 && $rateRow ? (float) ($rateRow->mcp ?? 0) : 0.0,
                    'navigate_url' => $navigateUrl,
                    'can_clear' => $index === 0 && $orphanModule !== '',
                    'clear_module' => $orphanModule,
                ];
            }
        }

        return $result;
    }

    private function oppositeAccountSplitsForRows(array $rows, string $currentCode): array
    {
        if ($rows === [] || !$this->hasTable('daybook')) {
            return [];
        }

        $slnos = collect($rows)->pluck('slno')->unique()->values()->all();
        if ($slnos === []) {
            return [];
        }

        $otherRows = DB::table('daybook as d')
            ->leftJoin('accountm as a', DB::raw('TRIM(d.accode)'), '=', DB::raw('TRIM(a.accode)'))
            ->selectRaw('
                d.slno,
                TRIM(COALESCE(d.accode,"")) as accode,
                COALESCE(d.amount,0) as amount,
                TRIM(COALESCE(a.name, d.accode, "")) as name
            ')
            ->whereIn('d.slno', $slnos)
            ->whereRaw('TRIM(COALESCE(d.accode,"")) <> ?', [$currentCode])
            ->whereRaw('ABS(COALESCE(d.amount,0)) > 0.005')
            ->get()
            ->groupBy('slno');

        $map = [];
        foreach ($rows as $row) {
            $amount = round((float) ($row['amount'] ?? 0), 2);
            if ($amount == 0.0) {
                continue;
            }

            $opposites = collect($otherRows[$row['slno']] ?? [])
                ->filter(fn ($other) => $amount > 0 ? (float) $other->amount < 0 : (float) $other->amount > 0)
                ->values();
            $oppositeTotal = round((float) $opposites->sum(fn ($other) => abs((float) $other->amount)), 2);
            if ($opposites->isEmpty() || abs($oppositeTotal - abs($amount)) > 0.01) {
                continue;
            }

            $parts = $opposites
                ->map(function ($other) {
                    $name = trim((string) ($other->name ?: $other->accode));
                    return [
                        'name' => $name,
                        'amount' => abs((float) $other->amount),
                    ];
                })
                ->all();

            $map[$this->rowSplitKey($row)] = $parts;
        }

        return $map;
    }

    private function rowSplitKey(array $row): string
    {
        return (string) ($row['slno'] ?? '') . '|' . number_format((float) ($row['amount'] ?? 0), 2, '.', '');
    }

    private function orphanSourceModuleForRow(array $row): string
    {
        $slno = (int) ($row['slno'] ?? 0);
        if ($slno <= 0) {
            return '';
        }

        $particular = strtoupper(trim((string) ($row['particular'] ?? '')));
        $opaccode = strtoupper(trim((string) ($row['opaccode'] ?? '')));
        $amount = (float) ($row['amount'] ?? 0);

        foreach ($this->sourceLedgerConfigs() as $module => $config) {
            if (!$this->hasTable($config['table'])) {
                continue;
            }
            if ($opaccode !== $config['opaccode']) {
                continue;
            }
            if (!str_starts_with($particular, $config['particular_prefix'])) {
                continue;
            }
            if ($config['amount_sign'] === 'negative' && $amount >= 0) {
                continue;
            }
            if ($config['amount_sign'] === 'positive' && $amount <= 0) {
                continue;
            }
            if (!DB::table($config['table'])->where('slno', $slno)->exists()) {
                return $module;
            }
        }

        return '';
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
                'opaccode' => 'RS',
                'amount_sign' => 'negative',
                'particular_prefix' => 'BY SALES (',
            ],
            'purchase' => [
                'table' => 'purchasem',
                'opaccode' => 'EP',
                'amount_sign' => 'positive',
                'particular_prefix' => 'BY PURCHASE -',
            ],
            'sales_return' => [
                'table' => 'salesrm',
                'opaccode' => 'ESR',
                'amount_sign' => 'positive',
                'particular_prefix' => 'BY SALES RETURN',
            ],
            'purchase_return' => [
                'table' => 'purchaserm',
                'opaccode' => 'EP',
                'amount_sign' => 'negative',
                'particular_prefix' => 'BY PURCHASE RETURN',
            ],
        ];
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
                return url('/purchase-bill/edit?' . http_build_query(['slno' => $slno, 'doc_no' => $docNo]));
            }
        }

        if ($this->hasTable('purchaserm')) {
            $purchaseReturn = DB::table('purchaserm')
                ->where('slno', $slno)
                ->first(['docno']);
            $docNo = trim((string) ($purchaseReturn->docno ?? ''));
            if ($docNo !== '') {
                return url('/purchase-return/edit?' . http_build_query(['slno' => $slno, 'doc_no' => $docNo]));
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
