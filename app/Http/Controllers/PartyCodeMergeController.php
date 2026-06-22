<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PartyCodeMergeController extends Controller
{
    private const REFERENCE_MAP = [
        ['table' => 'daybook',       'column' => 'accode',   'label' => 'Ledger entries'],
        ['table' => 'daybook',       'column' => 'opaccode', 'label' => 'Ledger opposite account'],
        ['table' => 'daybook',       'column' => 'cbcode',   'label' => 'Ledger bank/cash account'],
        ['table' => 'daybook',       'column' => 'cocode',   'label' => 'Ledger company account'],
        ['table' => 'salesm',        'column' => 'custcode', 'label' => 'Sales bills'],
        ['table' => 'salesm',        'column' => 'cbcode',   'label' => 'Sales cash/bank account'],
        ['table' => 'salesm',        'column' => 'cocode',   'label' => 'Sales company account'],
        ['table' => 'salesrm',       'column' => 'custcode', 'label' => 'Sales return bills'],
        ['table' => 'orderm',        'column' => 'custcode', 'label' => 'Order bills'],
        ['table' => 'orderm',        'column' => 'cbcode',   'label' => 'Order cash/bank account'],
        ['table' => 'orderm',        'column' => 'cocode',   'label' => 'Order company account'],
        ['table' => 'repairm',       'column' => 'custcode', 'label' => 'Repair bills'],
        ['table' => 'purchasem',     'column' => 'suppcode', 'label' => 'Purchase bills'],
        ['table' => 'purchaserm',    'column' => 'suppcode', 'label' => 'Purchase return bills'],
        ['table' => 'clients',       'column' => 'cocode',   'label' => 'Client company links'],
        ['table' => 'collection',    'column' => 'code',     'label' => 'Collection rows'],
        ['table' => 'collection',    'column' => 'cbcode',   'label' => 'Collection bank/cash links'],
        ['table' => 'loan',          'column' => 'ccode',    'label' => 'Loan customer rows'],
        ['table' => 'loan',          'column' => 'cbcode',   'label' => 'Loan bank/cash links'],
        ['table' => 'loancolln',     'column' => 'ccode',    'label' => 'Loan collection rows'],
        ['table' => 'loancolln',     'column' => 'cbcode',   'label' => 'Loan collection bank/cash links'],
        ['table' => 'pdclist',       'column' => 'code',     'label' => 'PDC rows'],
        ['table' => 'kuricolln',     'column' => 'code',     'label' => 'Kuri collection rows'],
        ['table' => 'kuriint',       'column' => 'code',     'label' => 'Kuri interest rows'],
        ['table' => 'kurifinishdet', 'column' => 'code',     'label' => 'Kuri finish rows'],
        ['table' => 'suspentry',     'column' => 'accode',   'label' => 'Suspense entries'],
    ];

    private const ACCOUNT_SUM_COLUMNS = ['opbal', 'opbalb', 'opwgt', 'opwgtb'];
    private const CLIENT_SUM_COLUMNS = ['opbalance', 'opbalanceb', 'opweight', 'opdepwgtbal', 'clwgt', 'clamt'];
    private const CLIENT_GS_SUM_COLUMNS = ['opweight', 'opweightb', 'opwgtamt', 'opwgtamtb', 'intamt', 'intwgt'];

    public function index(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('account.party-code-merge', [
            'codeHints' => $this->loadCodeHints(),
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        [$error, $payload] = $this->validateRequestPayload($request);
        if ($error !== null) {
            return response()->json(['ok' => false, 'message' => $error], 422);
        }

        return response()->json([
            'ok' => true,
            'preview' => $this->buildPreview($payload['sources'], $payload['target']),
        ]);
    }

    public function merge(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        [$error, $payload] = $this->validateRequestPayload($request);
        if ($error !== null) {
            return response()->json(['ok' => false, 'message' => $error], 422);
        }

        $deleteSources = strtoupper(trim((string) $request->input('delete_sources', 'Y'))) !== 'N';
        $preview = $this->buildPreview($payload['sources'], $payload['target']);

        DB::beginTransaction();

        try {
            $balanceSummary = $this->carryOpeningBalances($payload['sources'], $payload['target']);
            $updatedRefs = $this->updateReferenceTables($payload['sources'], $payload['target']);

            if ($deleteSources) {
                $this->cleanupSourceMasters($payload['sources']);
            } else {
                $this->resetSourceMasterBalances($payload['sources']);
            }

            DB::commit();

            return response()->json([
                'ok' => true,
                'message' => 'Bill/account codes merged successfully.',
                'summary' => [
                    'target' => $payload['target'],
                    'sources' => $payload['sources'],
                    'reference_rows_updated' => $updatedRefs,
                    'source_codes_cleared' => $deleteSources ? $this->countSourceMasterRows($preview['source_rows']) : 0,
                    'opening_balances_moved' => $balanceSummary,
                ],
                'preview' => $preview,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function saleBillsPreview(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        [$error, $payload] = $this->validateSaleBillPayload($request);
        if ($error !== null) {
            return response()->json(['ok' => false, 'message' => $error], 422);
        }

        return response()->json([
            'ok' => true,
            'preview' => $this->buildSaleBillPreview($payload['billnos'], $payload['target']),
        ]);
    }

    public function saleBillsLink(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        [$error, $payload] = $this->validateSaleBillPayload($request);
        if ($error !== null) {
            return response()->json(['ok' => false, 'message' => $error], 422);
        }

        $preview = $this->buildSaleBillPreview($payload['billnos'], $payload['target']);
        $slnos = collect($preview['bill_rows'] ?? [])
            ->pluck('slno')
            ->map(fn ($slno) => (int) $slno)
            ->filter(fn ($slno) => $slno > 0)
            ->values()
            ->all();

        if ($slnos === []) {
            return response()->json(['ok' => false, 'message' => 'No matching sale bills found.'], 422);
        }

        $target = $payload['target'];
        $targetName = (string) $preview['target']['name'];

        // Capture the old custcode per slno BEFORE updating salesm so we can
        // remap the matching daybook rows too (otherwise the A/c Ledger of the
        // target code won't reflect the link).
        $originalCodes = [];
        if ($this->hasTable('salesm') && in_array('custcode', $this->columnList('salesm'), true)) {
            $originalCodes = DB::table('salesm')
                ->whereIn('slno', $slnos)
                ->pluck('custcode', 'slno')
                ->toArray();
        }

        $update = ['custcode' => $target];
        if ($this->hasTable('salesm') && in_array('custname', $this->columnList('salesm'), true)) {
            $update['custname'] = $targetName;
        }

        DB::table('salesm')->whereIn('slno', $slnos)->update($update);

        $ledgerUpdated = $this->remapSaleBillLedger($slnos, $originalCodes, $target, $targetName);

        return response()->json([
            'ok' => true,
            'message' => count($slnos) . ' sale bill(s) linked to customer ' . $target . '.'
                . ($ledgerUpdated > 0 ? ' A/c Ledger rows updated: ' . $ledgerUpdated . '.' : ''),
            'summary' => [
                'updated' => count($slnos),
                'target' => $target,
                'ledger_rows_updated' => $ledgerUpdated,
            ],
            'preview' => $preview,
        ]);
    }

    private function remapSaleBillLedger(array $slnos, array $originalCodes, string $target, string $targetName): int
    {
        if (!$this->hasTable('daybook')) {
            return 0;
        }

        $touched = 0;
        $target = strtoupper(trim($target));
        $oldNameCache = [];

        foreach ($slnos as $slno) {
            $slno = (int) $slno;
            $oldCode = strtoupper(trim((string) ($originalCodes[$slno] ?? '')));
            if ($oldCode === '' || $oldCode === $target) {
                continue;
            }

            $touched += DB::table('daybook')
                ->where('slno', $slno)
                ->whereRaw('UPPER(TRIM(accode)) = ?', [$oldCode])
                ->update(['accode' => $target]);

            if (in_array('opaccode', $this->columnList('daybook'), true)) {
                $touched += DB::table('daybook')
                    ->where('slno', $slno)
                    ->whereRaw('UPPER(TRIM(opaccode)) = ?', [$oldCode])
                    ->update(['opaccode' => $target]);
            }

            if ($targetName !== '' && $this->hasTable('daybookpart') && in_array('particular', $this->columnList('daybookpart'), true)) {
                if (!array_key_exists($oldCode, $oldNameCache)) {
                    $oldNameCache[$oldCode] = $this->lookupPartyName($oldCode);
                }
                $oldName = $oldNameCache[$oldCode];
                if ($oldName !== '' && $oldName !== $targetName) {
                    DB::table('daybookpart')
                        ->where('slno', $slno)
                        ->update([
                            'particular' => DB::raw('REPLACE(particular, ' . $this->quoteSqlString($oldName) . ', ' . $this->quoteSqlString($targetName) . ')'),
                        ]);
                }
            }
        }

        return $touched;
    }

    private function lookupPartyName(string $code): string
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return '';
        }
        foreach (['clients', 'accountm'] as $table) {
            if (!$this->hasTable($table) || !in_array('name', $this->columnList($table), true)) {
                continue;
            }
            $codeCol = $table === 'accountm' ? 'accode' : 'code';
            $name = DB::table($table)
                ->whereRaw('UPPER(TRIM(' . $codeCol . ')) = ?', [$code])
                ->value('name');
            $name = trim((string) $name);
            if ($name !== '') {
                return $name;
            }
        }
        return '';
    }

    private function quoteSqlString(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    private function loadCodeHints(): array
    {
        $rows = collect();

        if ($this->hasTable('accountm')) {
            $rows = $rows->merge(
                DB::table('accountm')
                    ->selectRaw('TRIM(accode) as code, TRIM(COALESCE(name, "")) as name')
                    ->orderBy('accode')
                    ->limit(2500)
                    ->get()
            );
        }

        if ($this->hasTable('clients')) {
            $rows = $rows->merge(
                DB::table('clients')
                    ->selectRaw('TRIM(code) as code, TRIM(COALESCE(name, "")) as name')
                    ->orderBy('code')
                    ->limit(2500)
                    ->get()
            );
        }

        return $rows
            ->filter(fn ($row) => trim((string) ($row->code ?? '')) !== '')
            ->groupBy(fn ($row) => strtoupper(trim((string) $row->code)))
            ->map(function ($group, $code) {
                $first = $group->first();
                return [
                    'code' => $code,
                    'name' => trim((string) ($first->name ?? '')),
                ];
            })
            ->sortBy('code')
            ->values()
            ->all();
    }

    private function validateRequestPayload(Request $request): array
    {
        $sources = $this->parseCodes((string) $request->input('source_codes', ''));
        $target = strtoupper(trim((string) $request->input('target_code', '')));

        if ($target === '') {
            return ['Target code is required.', null];
        }

        $sources = array_values(array_filter($sources, fn ($code) => $code !== $target));
        if ($sources === []) {
            return ['Enter at least one source code different from the target code.', null];
        }

        $sourceMeta = [];
        foreach ($sources as $code) {
            $meta = $this->loadCodeMeta($code);
            if (!$meta['exists']) {
                return ['Source code not found: ' . $code, null];
            }
            $sourceMeta[$code] = $meta;
        }

        $targetMeta = $this->loadCodeMeta($target);
        if (!$targetMeta['exists']) {
            return ['Target code not found: ' . $target, null];
        }

        $needsAccountTarget = collect($sourceMeta)->contains(fn ($meta) => $meta['has_account']);
        $needsClientTarget = collect($sourceMeta)->contains(fn ($meta) => $meta['has_client']);

        if ($needsAccountTarget && !$targetMeta['has_account']) {
            return ['Target code must exist in Accounts Master because one or more source codes already exist there.', null];
        }
        if ($needsClientTarget && !$targetMeta['has_client']) {
            return ['Target code must exist in Customer/Supplier master because one or more source codes already exist there.', null];
        }

        foreach ($sourceMeta as $code => $meta) {
            if ($meta['account_type'] !== '' && $targetMeta['account_type'] !== '' && $meta['account_type'] !== $targetMeta['account_type']) {
                return ['Account type mismatch between ' . $code . ' and target ' . $target . '.', null];
            }
            if ($meta['client_type'] !== '' && $targetMeta['client_type'] !== '' && $meta['client_type'] !== $targetMeta['client_type']) {
                return ['Client type mismatch between ' . $code . ' and target ' . $target . '.', null];
            }
        }

        return [null, ['sources' => $sources, 'target' => $target]];
    }

    private function parseCodes(string $raw): array
    {
        $parts = preg_split('/[\s,;]+/', strtoupper(trim($raw))) ?: [];
        $codes = [];
        foreach ($parts as $part) {
            $code = trim($part);
            if ($code === '') {
                continue;
            }
            $codes[$code] = $code;
        }
        return array_values($codes);
    }

    private function validateSaleBillPayload(Request $request): array
    {
        if (!$this->hasTable('salesm') || !in_array('billno', $this->columnList('salesm'), true) || !in_array('custcode', $this->columnList('salesm'), true)) {
            return ['Sales bill table or required columns are not available.', null];
        }

        $billnos = $this->parseBillNos((string) $request->input('billnos', ''));
        $target = strtoupper(trim((string) $request->input('target_code', '')));

        if ($billnos === []) {
            return ['Enter at least one sale bill number.', null];
        }
        if ($target === '') {
            return ['Customer code is required.', null];
        }

        $targetMeta = $this->loadCodeMeta($target);
        if (!$targetMeta['has_client']) {
            return ['Customer code not found: ' . $target, null];
        }

        return [null, ['billnos' => $billnos, 'target' => $target]];
    }

    private function parseBillNos(string $raw): array
    {
        $parts = preg_split('/[\r\n,;]+/', strtoupper(trim($raw))) ?: [];
        $billnos = [];
        foreach ($parts as $part) {
            $billno = trim($part);
            if ($billno === '') {
                continue;
            }
            $billnos[$billno] = $billno;
        }
        return array_values($billnos);
    }

    private function buildSaleBillPreview(array $billnos, string $target): array
    {
        $placeholders = implode(',', array_fill(0, count($billnos), '?'));
        $select = ['slno', 'billno'];
        foreach (['tdate', 'custcode', 'custname', 'netamt', 'billamt'] as $column) {
            if (in_array($column, $this->columnList('salesm'), true)) {
                $select[] = $column;
            }
        }

        $query = DB::table('salesm')
            ->select($select)
            ->whereRaw('TRIM(billno) IN (' . $placeholders . ')', $billnos);

        if (in_array('tdate', $this->columnList('salesm'), true)) {
            $query->orderBy('tdate');
        }

        $rows = $query->orderBy('billno')
            ->get()
            ->map(fn ($row) => [
                'slno' => (int) ($row->slno ?? 0),
                'billno' => trim((string) ($row->billno ?? '')),
                'tdate' => (string) ($row->tdate ?? ''),
                'custcode' => trim((string) ($row->custcode ?? '')),
                'custname' => trim((string) ($row->custname ?? '')),
                'billamt' => (float) ($row->netamt ?? $row->billamt ?? 0),
            ])
            ->values()
            ->all();

        $found = collect($rows)->pluck('billno')->map(fn ($billno) => strtoupper(trim((string) $billno)))->all();
        $missing = array_values(array_diff($billnos, $found));

        return [
            'target' => array_merge(['code' => $target], $this->loadCodeMeta($target)),
            'bill_rows' => $rows,
            'missing_billnos' => $missing,
            'total_bills' => count($rows),
        ];
    }

    private function buildPreview(array $sources, string $target): array
    {
        $sourceRows = [];
        foreach ($sources as $code) {
            $meta = $this->loadCodeMeta($code);
            $sourceRows[] = [
                'code' => $code,
                'name' => $meta['name'],
                'account_type' => $meta['account_type'],
                'client_type' => $meta['client_type'],
                'opening_amount' => $meta['opening_amount'],
                'opening_amount_b' => $meta['opening_amount_b'],
                'opening_weight' => $meta['opening_weight'],
                'opening_weight_b' => $meta['opening_weight_b'],
            ];
        }

        $referenceRows = [];
        $totalReferences = 0;

        foreach (self::REFERENCE_MAP as $ref) {
            if (!$this->hasTable($ref['table']) || !in_array(strtolower($ref['column']), $this->columnList($ref['table']), true)) {
                continue;
            }

            $count = $this->countRowsByCodes($ref['table'], $ref['column'], $sources);
            if ($count <= 0) {
                continue;
            }

            $referenceRows[] = [
                'label' => $ref['label'],
                'table' => $ref['table'],
                'column' => $ref['column'],
                'rows' => $count,
            ];
            $totalReferences += $count;
        }

        return [
            'target' => array_merge(['code' => $target], $this->loadCodeMeta($target)),
            'source_rows' => $sourceRows,
            'reference_rows' => $referenceRows,
            'total_references' => $totalReferences,
        ];
    }

    private function loadCodeMeta(string $code): array
    {
        $meta = [
            'exists' => false,
            'has_account' => false,
            'has_client' => false,
            'name' => '',
            'account_type' => '',
            'client_type' => '',
            'opening_amount' => 0.0,
            'opening_amount_b' => 0.0,
            'opening_weight' => 0.0,
            'opening_weight_b' => 0.0,
        ];

        if ($this->hasTable('accountm')) {
            $row = DB::table('accountm')
                ->selectRaw('TRIM(COALESCE(name, "")) as name, TRIM(COALESCE(actype2, "")) as actype2, COALESCE(opbal, 0) as opbal, COALESCE(opbalb, 0) as opbalb, COALESCE(opwgt, 0) as opwgt, COALESCE(opwgtb, 0) as opwgtb')
                ->whereRaw('TRIM(accode) = ?', [$code])
                ->first();

            if ($row) {
                $meta['exists'] = true;
                $meta['has_account'] = true;
                $meta['name'] = trim((string) $row->name);
                $meta['account_type'] = trim((string) $row->actype2);
                $meta['opening_amount'] = (float) $row->opbal;
                $meta['opening_amount_b'] = (float) $row->opbalb;
                $meta['opening_weight'] = (float) $row->opwgt;
                $meta['opening_weight_b'] = (float) $row->opwgtb;
            }
        }

        if ($this->hasTable('clients')) {
            $row = DB::table('clients')
                ->selectRaw('TRIM(COALESCE(name, "")) as name, TRIM(COALESCE(ctype, "")) as ctype, COALESCE(opbalance, 0) as opbalance, COALESCE(opbalanceb, 0) as opbalanceb, COALESCE(opweight, 0) as opweight')
                ->whereRaw('TRIM(code) = ?', [$code])
                ->first();

            if ($row) {
                $meta['exists'] = true;
                $meta['has_client'] = true;
                if ($meta['name'] === '') {
                    $meta['name'] = trim((string) $row->name);
                }
                $meta['client_type'] = trim((string) $row->ctype);
                if (!$meta['has_account']) {
                    $meta['opening_amount'] = (float) $row->opbalance;
                    $meta['opening_amount_b'] = (float) $row->opbalanceb;
                    $meta['opening_weight'] = (float) $row->opweight;
                }
            }
        }

        return $meta;
    }

    private function countRowsByCodes(string $table, string $column, array $codes): int
    {
        if ($codes === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($codes), '?'));
        return (int) DB::table($table)
            ->whereRaw('TRIM(' . $column . ') IN (' . $placeholders . ')', $codes)
            ->count();
    }

    private function carryOpeningBalances(array $sources, string $target): array
    {
        $summary = [
            'accountm' => 0,
            'clients' => 0,
            'clientsgs' => 0,
        ];

        if ($this->hasTable('accountm')) {
            $accountColumns = array_values(array_intersect(self::ACCOUNT_SUM_COLUMNS, $this->columnList('accountm')));
            if ($accountColumns !== [] && DB::table('accountm')->whereRaw('TRIM(accode)=?', [$target])->exists()) {
                $sourceRows = DB::table('accountm')
                    ->whereRaw('TRIM(accode) <> ?', [$target])
                    ->whereRaw('TRIM(accode) IN (' . implode(',', array_fill(0, count($sources), '?')) . ')', $sources)
                    ->get();
                if ($sourceRows->isNotEmpty()) {
                    $targetRow = DB::table('accountm')->whereRaw('TRIM(accode)=?', [$target])->first();
                    $update = [];
                    foreach ($accountColumns as $column) {
                        $sum = (float) $sourceRows->sum(fn ($row) => (float) ($row->{$column} ?? 0));
                        $update[$column] = (float) ($targetRow->{$column} ?? 0) + $sum;
                    }
                    DB::table('accountm')->whereRaw('TRIM(accode)=?', [$target])->update($update);
                    $summary['accountm'] = (int) $sourceRows->count();
                }
            }
        }

        if ($this->hasTable('clients')) {
            $clientColumns = array_values(array_intersect(self::CLIENT_SUM_COLUMNS, $this->columnList('clients')));
            if ($clientColumns !== [] && DB::table('clients')->whereRaw('TRIM(code)=?', [$target])->exists()) {
                $sourceRows = DB::table('clients')
                    ->whereRaw('TRIM(code) <> ?', [$target])
                    ->whereRaw('TRIM(code) IN (' . implode(',', array_fill(0, count($sources), '?')) . ')', $sources)
                    ->get();
                if ($sourceRows->isNotEmpty()) {
                    $targetRow = DB::table('clients')->whereRaw('TRIM(code)=?', [$target])->first();
                    $update = [];
                    foreach ($clientColumns as $column) {
                        $sum = (float) $sourceRows->sum(fn ($row) => (float) ($row->{$column} ?? 0));
                        $update[$column] = (float) ($targetRow->{$column} ?? 0) + $sum;
                    }
                    DB::table('clients')->whereRaw('TRIM(code)=?', [$target])->update($update);
                    $summary['clients'] = (int) $sourceRows->count();
                }
            }
        }

        if ($this->hasTable('clientsgs')) {
            $columns = array_values(array_intersect(self::CLIENT_GS_SUM_COLUMNS, $this->columnList('clientsgs')));
            $sourceRows = DB::table('clientsgs')
                ->whereRaw('TRIM(code) IN (' . implode(',', array_fill(0, count($sources), '?')) . ')', $sources)
                ->get();
            if ($columns !== [] && $sourceRows->isNotEmpty()) {
                $targetRow = DB::table('clientsgs')->whereRaw('TRIM(code)=?', [$target])->first();
                if (!$targetRow) {
                    $seed = ['code' => $target];
                    if (in_array('ctype', $this->columnList('clientsgs'), true)) {
                        $seed['ctype'] = (string) ($sourceRows->first()->ctype ?? '');
                    }
                    DB::table('clientsgs')->insert($seed);
                    $targetRow = DB::table('clientsgs')->whereRaw('TRIM(code)=?', [$target])->first();
                }
                $update = [];
                foreach ($columns as $column) {
                    $sum = (float) $sourceRows->sum(fn ($row) => (float) ($row->{$column} ?? 0));
                    $update[$column] = (float) ($targetRow->{$column} ?? 0) + $sum;
                }
                DB::table('clientsgs')->whereRaw('TRIM(code)=?', [$target])->update($update);
                $summary['clientsgs'] = (int) $sourceRows->count();
            }
        }

        return $summary;
    }

    private function updateReferenceTables(array $sources, string $target): int
    {
        $updated = 0;
        $placeholders = implode(',', array_fill(0, count($sources), '?'));

        foreach (self::REFERENCE_MAP as $ref) {
            if (!$this->hasTable($ref['table']) || !in_array(strtolower($ref['column']), $this->columnList($ref['table']), true)) {
                continue;
            }

            $count = $this->countRowsByCodes($ref['table'], $ref['column'], $sources);
            if ($count <= 0) {
                continue;
            }

            DB::table($ref['table'])
                ->whereRaw('TRIM(' . $ref['column'] . ') IN (' . $placeholders . ')', $sources)
                ->update([$ref['column'] => $target]);

            $updated += $count;
        }

        return $updated;
    }

    private function cleanupSourceMasters(array $sources): void
    {
        $sourcePlaceholders = implode(',', array_fill(0, count($sources), '?'));

        foreach ([
            ['table' => 'clientspict', 'column' => 'code'],
            ['table' => 'clients_advanced', 'column' => 'code'],
            ['table' => 'clientsgs', 'column' => 'code'],
            ['table' => 'clients', 'column' => 'code'],
            ['table' => 'accountm', 'column' => 'accode'],
        ] as $ref) {
            if (!$this->hasTable($ref['table']) || !in_array(strtolower($ref['column']), $this->columnList($ref['table']), true)) {
                continue;
            }

            DB::table($ref['table'])
                ->whereRaw('TRIM(' . $ref['column'] . ') IN (' . $sourcePlaceholders . ')', $sources)
                ->delete();
        }
    }

    private function resetSourceMasterBalances(array $sources): void
    {
        $sourcePlaceholders = implode(',', array_fill(0, count($sources), '?'));

        if ($this->hasTable('accountm')) {
            $columns = array_values(array_intersect(self::ACCOUNT_SUM_COLUMNS, $this->columnList('accountm')));
            if ($columns !== []) {
                $update = array_fill_keys($columns, 0);
                DB::table('accountm')
                    ->whereRaw('TRIM(accode) IN (' . $sourcePlaceholders . ')', $sources)
                    ->update($update);
            }
        }

        if ($this->hasTable('clients')) {
            $columns = array_values(array_intersect(self::CLIENT_SUM_COLUMNS, $this->columnList('clients')));
            if ($columns !== []) {
                $update = array_fill_keys($columns, 0);
                DB::table('clients')
                    ->whereRaw('TRIM(code) IN (' . $sourcePlaceholders . ')', $sources)
                    ->update($update);
            }
        }

        if ($this->hasTable('clientsgs')) {
            $columns = array_values(array_intersect(self::CLIENT_GS_SUM_COLUMNS, $this->columnList('clientsgs')));
            if ($columns !== []) {
                $update = array_fill_keys($columns, 0);
                DB::table('clientsgs')
                    ->whereRaw('TRIM(code) IN (' . $sourcePlaceholders . ')', $sources)
                    ->update($update);
            }
        }
    }

    private function countSourceMasterRows(array $rows): int
    {
        return count($rows);
    }
}
