<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\LogsDelpartAudit;
use App\Support\SecondaryDatabaseSync;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AdministrationController extends Controller
{
    use LogsDelpartAudit;

    private const GENERAL_CODE_ALIASES = [
        'REFINEBPREF' => 'RFBPREF',
        'REFINEBLEN' => 'RFBLEN',
        'REPAIRBPREF' => 'RMBPREF',
        'REPAIRBLEN' => 'RMBLEN',
        'VCHNORBPREF' => 'VRBPREF',
        'VCHNORBLEN' => 'VRBLEN',
        'VCHNOPBPREF' => 'VPBPREF',
        'VCHNOPBLEN' => 'VPBLEN',
        'VCHNOJBPREF' => 'VJBPREF',
        'VCHNOJBLEN' => 'VJBLEN',
        'OTHERTSBPREF' => 'OISPREF',
        'OTHERTSBLEN' => 'OISLEN',
        'OTHERTPBPREF' => 'OIPPREF',
        'OTHERTPBLEN' => 'OIPLEN',
        'VCHNOKRBPREF' => 'KRBPREF',
        'VCHNOKRBLEN' => 'KRBLEN',
        'VCHNOKPBPREF' => 'KPBPREF',
        'VCHNOKPBLEN' => 'KPBLEN',
    ];

    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('admin-tools.administration', [
            'userCode' => (string) $request->session()->get('user_code', ''),
        ]);
    }

    public function initialise(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('admin-tools.initialise');
    }

    public function stockUpdate(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('admin-tools.stock-update', [
            'today' => now()->format('Y-m-d'),
        ]);
    }

    public function sqlUpdate(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('admin-tools.sql-update', [
            'actions' => $this->sqlUpdateActions(),
            'aiRecommendations' => $this->sqlUpdateAiRecommendations(),
        ]);
    }

    public function dataTransfer(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $companies = $this->loadCompanySelectRegistry();
        $currentDatabase = trim((string) Config::get('database.connections.mysql.database', ''));
        $companies = collect($companies)
            ->filter(fn (array $company) => strtoupper((string) ($company['database'] ?? '')) !== strtoupper($currentDatabase))
            ->values()
            ->all();
        $defaultTarget = $this->readCompanyTransferDefault();
        if (($defaultTarget === '' || strtoupper($defaultTarget) === strtoupper($currentDatabase)) && $companies !== []) {
            $defaultTarget = (string) ($companies[0]['database'] ?? '');
        }

        $availableModules = collect($this->dataTransferQuickOptions())
            ->flatMap(fn (array $group) => collect($group['options'] ?? [])->pluck('key'))
            ->map(fn ($value) => (string) $value)
            ->all();

        $presetModules = collect(explode(',', (string) $request->query('modules', '')))
            ->map(fn ($value) => strtolower(trim((string) $value)))
            ->filter(fn ($value) => $value !== '' && in_array($value, $availableModules, true))
            ->values()
            ->all();

        return view('admin-tools.data-transfer', [
            'today' => now()->format('Y-m-d'),
            'companies' => $companies,
            'currentDatabase' => $currentDatabase,
            'defaultTarget' => $defaultTarget,
            'quickOptions' => $this->dataTransferQuickOptions(),
            'presetModules' => $presetModules,
        ]);
    }

    public function dataTransferPreview(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        [$error, $payload] = $this->validateDataTransferRequest($request);
        if ($error !== null) {
            return response()->json(['ok' => false, 'message' => $error], 422);
        }

        $expanded = $this->expandDataTransferModules($payload['modules']);
        $rows = collect($expanded)
            ->map(fn ($module) => $this->buildDataTransferPreviewRow($payload['source_database'], $module, $payload['date_from'], $payload['date_to']))
            ->values()
            ->all();

        return response()->json([
            'ok' => true,
            'items' => $rows,
            'summary' => [
                'modules' => count($rows),
                'rows' => collect($rows)->sum(fn ($row) => (int) ($row['rows'] ?? 0)),
            ],
        ]);
    }

    public function dataTransferRun(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        [$error, $payload] = $this->validateDataTransferRequest($request);
        if ($error !== null) {
            return response()->json(['ok' => false, 'message' => $error], 422);
        }

        $expanded = $this->expandDataTransferModules($payload['modules']);
        $results = [];
        $copied = 0;
        $debugRunId = 'dt-' . now()->format('Ymd-His') . '-' . substr(md5((string) microtime(true)), 0, 6);

        Log::info('data-transfer.run.start', [
            'run_id' => $debugRunId,
            'source_database' => $payload['source_database'],
            'target_database' => $payload['target_database'],
            'date_from' => $payload['date_from'],
            'date_to' => $payload['date_to'],
            'modules' => $expanded,
        ]);

        foreach ($expanded as $module) {
            try {
                $row = $this->runDataTransferModule($payload['source_database'], $payload['target_database'], $module, $payload['date_from'], $payload['date_to']);
                $results[] = $row;
                $copied += (int) ($row['copied'] ?? 0);
                Log::info('data-transfer.run.module', [
                    'run_id' => $debugRunId,
                    'module' => $module,
                    'row' => $row,
                ]);
            } catch (\Throwable $e) {
                Log::error('data-transfer.run.module.failed', [
                    'run_id' => $debugRunId,
                    'module' => $module,
                    'message' => $e->getMessage(),
                ]);
                $results[] = [
                    'module' => $module,
                    'label' => $this->dataTransferModules()[$module]['label'] ?? ucfirst(str_replace('_', ' ', $module)),
                    'status' => 'error',
                    'message' => $e->getMessage(),
                    'copied' => 0,
                ];
            }
        }

        $this->logDelpart($request, 'Data Transfer Run', ['utype' => 'E', 'ttype' => 'M']);

        $response = [
            'ok' => true,
            'items' => $results,
            'summary' => [
                'modules' => count($results),
                'copied' => $copied,
            ],
            'message' => 'Data transfer completed. Debug: ' . $debugRunId,
        ];

        Log::info('data-transfer.run.done', [
            'run_id' => $debugRunId,
            'summary' => $response['summary'],
        ]);

        return response()->json($response);
    }

    public function initialiseDocNo(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('admin-tools.initialise-docno');
    }

    public function addSlno(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('admin-tools.add-slno', $this->buildAddSlnoPayload());
    }

    public function updateLog(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('admin-tools.update-log', [
            'today' => now()->format('Y-m-d'),
        ]);
    }

    public function rearrangeDocNos(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('admin-tools.rearrange-docnos', [
            'today' => now()->format('Y-m-d'),
        ]);
    }

    public function changeDocNo(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $sections = $this->changeDocNoSections();

        foreach ($sections as &$section) {
            foreach ($section['fields'] as &$field) {
                $field['value'] = $this->loadChangeDocNoField($field);
            }
            unset($field);
        }
        unset($section);

        return view('admin-tools.change-docno', [
            'sections' => $sections,
        ]);
    }

    public function updateLogData(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $dateFrom = $request->query('date_from', now()->format('Y-m-d'));
        $dateTo = $request->query('date_to', now()->format('Y-m-d'));
        $sort = strtolower(trim((string) $request->query('sort', 'date')));

        $userHistory = [];
        if ($this->hasTable('userhist')) {
            $query = DB::table('userhist')
                ->leftJoin('userm', 'userhist.code', '=', 'userm.code')
                ->select(
                    'userhist.tdate',
                    'userhist.time1',
                    'userhist.time2',
                    DB::raw('TRIM(COALESCE(userm.name, userhist.code)) as user_name'),
                    DB::raw('TRIM(userhist.code) as user_code')
                )
                ->whereBetween('userhist.tdate', [$dateFrom, $dateTo]);

            if ($sort === 'user') {
                $query->orderBy('userm.name')->orderBy('userhist.tdate')->orderBy('userhist.time1');
            } else {
                $query->orderBy('userhist.tdate')->orderBy('userhist.time1');
            }

            $userHistory = $query->limit(500)->get()->map(fn ($row) => [
                'tdate' => (string) ($row->tdate ?? ''),
                'time1' => (string) ($row->time1 ?? ''),
                'time2' => (string) ($row->time2 ?? ''),
                'user_name' => trim((string) ($row->user_name ?? '')),
                'user_code' => trim((string) ($row->user_code ?? '')),
            ])->all();
        }

        $updateLog = [];
        if ($this->hasTable('delpart')) {
            $query = DB::table('delpart')
                ->select(['updtdate', 'updttime', 'tdate', 'part', 'uid', 'ic', 'utype', 'ttype', 'slno'])
                ->whereBetween('updtdate', [$dateFrom, $dateTo]);

            if (Schema::hasColumn('delpart', 'control')) {
                $query->where('control', '<=', 9999);
            }

            $query->orderBy('updtdate')->orderBy('updttime')->orderBy('tdate');

            $updateLog = $query->limit(500)->get()->map(fn ($row) => [
                'updtdate' => (string) ($row->updtdate ?? ''),
                'updttime' => (string) ($row->updttime ?? ''),
                'tdate' => (string) ($row->tdate ?? ''),
                'part' => trim((string) ($row->part ?? '')),
                'uid' => trim((string) ($row->uid ?? '')),
                'ic' => trim((string) ($row->ic ?? '')),
                'utype' => trim((string) ($row->utype ?? '')),
                'ttype' => trim((string) ($row->ttype ?? '')),
                'slno' => (string) ($row->slno ?? ''),
            ])->all();
        }

        return response()->json([
            'ok' => true,
            'user_history' => $userHistory,
            'update_log' => $updateLog,
        ]);
    }

    public function clearUpdateLog(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        DB::beginTransaction();
        try {
            if ($this->hasTable('delpart')) {
                DB::table('delpart')->delete();
            }
            if ($this->hasTable('userhist')) {
                DB::table('userhist')->delete();
            }
            DB::commit();

            return response()->json(['ok' => true, 'message' => 'Update logs cleared.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'message' => 'Clear failed: ' . $e->getMessage()], 500);
        }
    }

    public function runInitialise(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $flags = [
            'delentries', 'initbillno', 'initstock', 'initopbal', 'slno',
            'delcust', 'delsupp', 'delsmith', 'delrefin', 'deljewl', 'delstaff',
            'delbarcode', 'delitems', 'initstaffattendance', 'initopbalie',
            'initopwgtbalcustomers', 'removeaddr', 'delmctable', 'delwstgtable',
            'delpict', 'delmodels',
        ];

        $data = [];
        foreach ($flags as $flag) {
            $data[$flag] = (bool) $request->boolean($flag);
        }

        DB::beginTransaction();
        try {
            if ($data['delentries']) {
                $this->deleteTables([
                    'salesm', 'salesd', 'salesrm', 'salesrd', 'purchasem', 'purchased',
                    'purchased_dmddet', 'oglist', 'purchaserm', 'purchaserd', 'repairm',
                    'repaird', 'orderm', 'orderd', 'orderdga', 'refinerym', 'refineryd',
                    'smithm', 'smithd', 'delpart', 'itemadj', 'daybook', 'daybookpart',
                    'daybookratewgt', 'suspentry', 'collection', 'advafter', 'wsalem',
                    'wsaled', 'wsrefinery', 'smithnewwrk', 'smithsusp', 'appoint',
                    'ruffwrk', 'userhist', 'ratehistory', 'staffleave', 'oitemtranm',
                    'oitemtrand', 'spdmddet', 'kuricolln', 'kurifinishdet', 'kuriint',
                    'wgtrcptpmnt', 'stkandprofit', 'staffwgtm', 'staffwgtd', 'loan',
                    'loan_dates', 'loan_items', 'loancolln',
                ]);
            }

            if ($data['initbillno']) {
                if ($this->hasTable('generali')) {
                    if ($data['slno']) {
                        DB::table('generali')
                            ->where('code', '<>', 'CLASTNO')
                            ->where('code', '<>', 'SLASTNO')
                            ->update(['cvalue' => 0]);

                        $slnoStart = (int) $this->generalValue('generals', 'SLNOSTART', '0');
                        $this->upsertGeneralInt('SERIALNO', $slnoStart);
                    } else {
                        DB::table('generali')
                            ->where('code', '<>', 'SERIALNO')
                            ->where('code', '<>', 'CLASTNO')
                            ->where('code', '<>', 'SLASTNO')
                            ->update(['cvalue' => 0]);
                    }

                    $this->upsertGeneralInt('ORDERB', (int) $this->generalValue('generals', 'ORDBSTART', '0'));
                    $this->upsertGeneralInt('ORDERE', (int) $this->generalValue('generals', 'ORDESTART', '0'));
                }
            }

            if ($data['initstock']) {
                if ($this->hasTable('items')) {
                    DB::table('items')->update([
                        'opqty' => 0, 'opweight' => 0, 'opqtyb' => 0, 'opweightb' => 0,
                        'opstonewgt' => 0, 'opstonewgtb' => 0, 'qty' => 0, 'weight' => 0,
                        'qtyb' => 0, 'weightb' => 0, 'stonewgt' => 0, 'stonewgtb' => 0,
                    ]);
                }
                $this->deleteTables(['itemsstk']);
                if ($this->hasTable('itemsothers')) {
                    DB::table('itemsothers')->update(['opstock' => 0, 'stock' => 0]);
                }
            }

            if ($data['initopbal']) {
                if ($this->hasTable('clients')) {
                    DB::table('clients')->update(['opbalance' => 0, 'opbalanceb' => 0, 'opweight' => 0]);
                }
                if ($this->hasTable('clientsgs')) {
                    DB::table('clientsgs')->update(['opweight' => 0, 'opweightb' => 0]);
                }
                if ($this->hasTable('accountm')) {
                    DB::table('accountm')->update(['opbal' => 0, 'opbalb' => 0]);
                }
            }

            $this->deleteClientsByType($data['delcust'], 'C', true, false, false);
            $this->deleteClientsByType($data['delsupp'], 'S', false, false, false);
            $this->deleteClientsByType($data['delsmith'], 'G', false, true, true);
            $this->deleteClientsByType($data['delrefin'], 'R', false, true, false);
            $this->deleteClientsByType($data['deljewl'], 'J', false, true, false);
            $this->deleteClientsByType($data['delstaff'], 'F', false, true, false);

            if ($data['delitems']) {
                if ($this->hasTable('items')) {
                    DB::table('items')
                        ->where(function ($q) {
                            $q->where('reserve', '<>', 'Y')->orWhereNull('reserve');
                        })
                        ->delete();
                }
                $this->deleteTables(['itemsothers']);
            }

            if ($data['delbarcode']) {
                $this->deleteTables(['barcode', 'barcodedmd', 'barcodedoc', 'barcode_dmddet']);
            }

            if ($data['delmctable']) {
                $this->deleteTables(['mctable', 'pmctable']);
            }

            if ($data['delmodels']) {
                $this->deleteTables(['models']);
            }

            if ($data['delwstgtable']) {
                $this->deleteTables(['wstgtable']);
            }

            if ($data['delpict']) {
                $this->deleteTables(['clientspict']);
            }

            if ($data['initstaffattendance']) {
                $this->deleteTables(['staffcheckin']);
            }

            if ($data['initopbalie'] && $this->hasTable('accountm')) {
                DB::table('accountm')
                    ->where(function ($q) {
                        $q->where('actype1', 'R')->orWhere('actype1', 'E');
                    })
                    ->update(['opbal' => 0, 'opbalb' => 0]);
            }

            if ($data['initopwgtbalcustomers'] && $this->hasTable('clients')) {
                DB::table('clients')->update(['opweight' => 0]);
            }

            if ($data['removeaddr'] && $this->hasTable('clients')) {
                DB::table('clients')->update([
                    'addr1' => '', 'addr2' => '', 'addr3' => '', 'city' => '',
                    'telephone' => '', 'mobile' => '',
                ]);
            }

            DB::commit();
            return response()->json(['ok' => true, 'message' => 'Initialise completed successfully.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'message' => 'Initialise failed: ' . $e->getMessage()], 500);
        }
    }

    public function runInitialiseDocNo(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $map = [
            'initsbillno' => 'SALESE',
            'initpbillno' => 'PURCHASEE',
            'initgsbillno' => 'SMITHE',
            'initrepbillno' => 'REPAIRE',
            'initrefbillno' => 'REFINEE',
            'initordbillno' => 'ORDERE',
        ];

        DB::beginTransaction();
        try {
            foreach ($map as $input => $code) {
                if ($request->boolean($input)) {
                    $this->upsertGeneralInt($code, 0);
                }
            }

            DB::commit();
            return response()->json(['ok' => true, 'message' => 'Bill numbers initialised successfully.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'message' => 'Initialise Doc. No. failed: ' . $e->getMessage()], 500);
        }
    }

    public function runRearrangeDocNos(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $fromDate = $this->normalizeDateInput((string) $request->input('from_date', now()->format('Y-m-d')));
        if ($fromDate === null) {
            return response()->json(['ok' => false, 'message' => 'Invalid from date.'], 422);
        }

        $control = $request->boolean('estimates_only') ? 2 : 1;

        DB::beginTransaction();
        try {
            $summary = [];

            $summary['sales'] = $request->boolean('sales_enabled')
                ? $this->rearrangeSales((int) $request->input('sales_start', 1), $fromDate, $control)
                : 0;
            $summary['sales_return'] = $request->boolean('sales_return_enabled')
                ? $this->rearrangeSalesReturn((int) $request->input('sales_return_start', 1), $fromDate, $control)
                : 0;
            $summary['purchase'] = $request->boolean('purchase_enabled')
                ? $this->rearrangePurchase((int) $request->input('purchase_start', 1), $fromDate, $control, false)
                : 0;
            $summary['diamond_purchase'] = $request->boolean('diamond_purchase_enabled')
                ? $this->rearrangePurchase((int) $request->input('diamond_purchase_start', 1), $fromDate, $control, true)
                : 0;
            $summary['purchase_return'] = $request->boolean('purchase_return_enabled')
                ? $this->rearrangePurchaseReturn((int) $request->input('purchase_return_start', 1), $fromDate, $control)
                : 0;
            $summary['order'] = $request->boolean('order_enabled')
                ? $this->rearrangeOrder((int) $request->input('order_start', 1), $fromDate, $control)
                : 0;
            $summary['refinery'] = $request->boolean('refinery_enabled')
                ? $this->rearrangeRefinery((int) $request->input('refinery_start', 1), $fromDate, $control)
                : 0;
            $summary['smith_issue'] = $request->boolean('smith_issue_enabled')
                ? $this->rearrangeSmith(
                    (int) $request->input('smith_issue_start', 1),
                    (int) $request->input('smith_receipt_start', 1),
                    $fromDate,
                    $control,
                    'G',
                    $request->boolean('smith_trn_based')
                )
                : 0;
            $summary['smith_receipt'] = !$request->boolean('smith_trn_based') && $request->boolean('smith_receipt_enabled')
                ? $this->rearrangeSmithReceiptOnly((int) $request->input('smith_receipt_start', 1), $fromDate, $control, 'G')
                : 0;
            $summary['jewl_issue'] = $request->boolean('jewl_issue_enabled')
                ? $this->rearrangeSmith(
                    (int) $request->input('jewl_issue_start', 1),
                    (int) $request->input('jewl_receipt_start', 1),
                    $fromDate,
                    $control,
                    'J',
                    $request->boolean('jewl_trn_based')
                )
                : 0;
            $summary['jewl_receipt'] = !$request->boolean('jewl_trn_based') && $request->boolean('jewl_receipt_enabled')
                ? $this->rearrangeSmithReceiptOnly((int) $request->input('jewl_receipt_start', 1), $fromDate, $control, 'J')
                : 0;
            $summary['repair_receipt'] = $request->boolean('repair_receipt_enabled')
                ? $this->rearrangeRepair((int) $request->input('repair_receipt_start', 1), $fromDate, $control, 'R')
                : 0;
            $summary['repair_return'] = $request->boolean('repair_return_enabled')
                ? $this->rearrangeRepair((int) $request->input('repair_return_start', 1), $fromDate, $control, 'G')
                : 0;
            $summary['receipt'] = $request->boolean('receipt_enabled')
                ? $this->rearrangeVoucherNumbers((int) $request->input('receipt_start', 1), $fromDate, $control, 'VR', 'VCHNORB', 'VCHNORE', 'VRB/', 'VRE/', 'kuricolln', 'docno')
                : 0;
            $summary['payment'] = $request->boolean('payment_enabled')
                ? $this->rearrangeVoucherNumbers((int) $request->input('payment_start', 1), $fromDate, $control, 'VP', 'VCHNOPB', 'VCHNOPE', 'VPB/', 'VPE/', 'kuricolln', 'docno')
                : 0;
            $summary['journal'] = $request->boolean('journal_enabled')
                ? $this->rearrangeVoucherNumbers((int) $request->input('journal_start', 1), $fromDate, $control, 'JL', 'VCHNOJB', 'VCHNOJE', 'JLB/', 'JLE/', 'collection', 'voucher')
                : 0;

            DB::commit();

            $done = array_sum($summary);
            $this->logDelpart($request, 'Rearange Docnos Done', ['utype' => 'E', 'ttype' => 'A', 'tdate' => $fromDate, 'control' => $control]);

            return response()->json([
                'ok' => true,
                'message' => 'Rearrange completed. Updated ' . $done . ' record(s).',
                'summary' => $summary,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'message' => 'Rearange failed: ' . $e->getMessage()], 500);
        }
    }

    public function runChangeDocNo(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $fields = collect($this->changeDocNoSections())
            ->flatMap(fn (array $section) => $section['fields'])
            ->values()
            ->all();

        DB::beginTransaction();
        try {
            $updated = 0;

            foreach ($fields as $field) {
                $input = $field['input'];
                $value = trim((string) $request->input($input, ''));

                if (($field['type'] ?? 'int') === 'int') {
                    $number = is_numeric($value) ? (int) $value : 0;
                    $this->upsertGeneralIntCode($field['table'], $field['code'], $number);
                } else {
                    $text = $value === '' ? (string) ($field['default'] ?? '') : $value;
                    $this->upsertGeneralTextCode($field['table'], $field['code'], $text);
                }

                $updated++;
            }

            DB::commit();

            $this->logDelpart($request, 'Change Doc. No. Done', ['utype' => 'E', 'ttype' => 'M']);

            return response()->json([
                'ok' => true,
                'message' => 'Document number settings updated successfully.',
                'updated' => $updated,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'message' => 'Change Doc. No. failed: ' . $e->getMessage()], 500);
        }
    }

    public function runAddSlno(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $fromDate = $this->normalizeDateInput((string) $request->input('from_date', now()->format('Y-m-d')));
        if ($fromDate === null) {
            return response()->json(['ok' => false, 'message' => 'Invalid from date.'], 422);
        }

        $addSlno = (int) $request->input('add_slno', 0);
        if ($addSlno <= 0) {
            return response()->json(['ok' => false, 'message' => 'Add Slno must be greater than zero.'], 422);
        }

        DB::beginTransaction();
        try {
            $summary = [];

            $summary['salesd'] = $this->incrementChildSlnoByParentDate('salesd', 'salesm', $fromDate, $addSlno);
            $summary['salesm'] = $this->incrementSlnoByDate('salesm', $fromDate, $addSlno);
            $summary['salesrd'] = $this->incrementChildSlnoByParentDate('salesrd', 'salesrm', $fromDate, $addSlno);
            $summary['salesrm'] = $this->incrementSlnoByDate('salesrm', $fromDate, $addSlno);
            $summary['purchased'] = $this->incrementChildSlnoByParentDate('purchased', 'purchasem', $fromDate, $addSlno);
            $summary['purchased_dmddet'] = $this->incrementChildSlnoByParentDate('purchased_dmddet', 'purchasem', $fromDate, $addSlno);
            $summary['purchasem'] = $this->incrementSlnoByDate('purchasem', $fromDate, $addSlno);
            $summary['purchaserd'] = $this->incrementChildSlnoByParentDate('purchaserd', 'purchaserm', $fromDate, $addSlno);
            $summary['purchaserm'] = $this->incrementSlnoByDate('purchaserm', $fromDate, $addSlno);
            $summary['orderd'] = $this->incrementChildSlnoByParentDate('orderd', 'orderm', $fromDate, $addSlno);
            $summary['orderdmodel'] = $this->incrementChildSlnoByParentDate('orderdmodel', 'orderm', $fromDate, $addSlno);
            $summary['orderdga'] = $this->incrementSlnoByDate('orderdga', $fromDate, $addSlno);
            $summary['orderm'] = $this->incrementSlnoByDate('orderm', $fromDate, $addSlno);
            $summary['advafter'] = $this->incrementSlnoByDate('advafter', $fromDate, $addSlno);
            $summary['smithd'] = $this->incrementChildSlnoByParentDate('smithd', 'smithm', $fromDate, $addSlno);
            $summary['smithm'] = $this->incrementSlnoByDate('smithm', $fromDate, $addSlno);
            $summary['refineryd'] = $this->incrementChildSlnoByParentDate('refineryd', 'refinerym', $fromDate, $addSlno);
            $summary['refinerym'] = $this->incrementSlnoByDate('refinerym', $fromDate, $addSlno);
            $summary['repaird'] = $this->incrementChildSlnoByParentDate('repaird', 'repairm', $fromDate, $addSlno);
            $summary['repairm'] = $this->incrementSlnoByDate('repairm', $fromDate, $addSlno);
            $summary['itemadj'] = $this->incrementSlnoByDate('itemadj', $fromDate, $addSlno);
            $summary['daybookpart'] = $this->incrementChildSlnoByParentDate('daybookpart', 'daybook', $fromDate, $addSlno);
            $summary['daybookratewgt'] = $this->incrementSlnoByDate('daybookratewgt', $fromDate, $addSlno);
            $summary['daybook'] = $this->incrementSlnoByDate('daybook', $fromDate, $addSlno);
            $summary['collection'] = $this->incrementSlnoByDate('collection', $fromDate, $addSlno);
            $summary['barcode_islno'] = $this->incrementNamedColumn('barcode', 'islno', $addSlno);
            $summary['barcode_rslno'] = $this->incrementNamedColumn('barcode', 'rslno', $addSlno);
            $summary['barcode_dmddet'] = $this->incrementAllSlno('barcode_dmddet', $addSlno);
            $summary['cpoints'] = $this->incrementSlnoByDate('cpoints', $fromDate, $addSlno);
            $summary['kuricolln'] = $this->incrementSlnoByDate('kuricolln', $fromDate, $addSlno);
            $summary['kurifinishdet'] = $this->incrementSlnoByDate('kurifinishdet', $fromDate, $addSlno);
            $summary['kuriint'] = $this->incrementSlnoByDate('kuriint', $fromDate, $addSlno);
            $summary['loan'] = $this->incrementSlnoByDate('loan', $fromDate, $addSlno);
            $summary['loan_dates'] = $this->incrementAllSlno('loan_dates', $addSlno);
            $summary['loancolln'] = $this->incrementSlnoByDate('loancolln', $fromDate, $addSlno);
            $summary['oglist'] = $this->incrementSlnoByDate('oglist', $fromDate, $addSlno);
            $summary['oitemtrand'] = $this->incrementChildSlnoByParentDate('oitemtrand', 'oitemtranm', $fromDate, $addSlno);
            $summary['oitemtranm'] = $this->incrementSlnoByDate('oitemtranm', $fromDate, $addSlno);
            $summary['spdmddet'] = $this->incrementChildSlnoByParentDate('spdmddet', 'purchasem', $fromDate, $addSlno);
            $summary['staffwgtd'] = $this->incrementChildSlnoByParentDate('staffwgtd', 'staffwgtm', $fromDate, $addSlno);
            $summary['staffwgtm'] = $this->incrementSlnoByDate('staffwgtm', $fromDate, $addSlno);
            $summary['stkandprofit'] = $this->incrementSlnoByDate('stkandprofit', $fromDate, $addSlno);
            $summary['suspentry'] = $this->incrementSlnoByDate('suspentry', $fromDate, $addSlno);
            $summary['testdet'] = $this->incrementSlnoByDate('testdet', $fromDate, $addSlno);
            $summary['wgtrcptpmnt'] = $this->incrementSlnoByDate('wgtrcptpmnt', $fromDate, $addSlno);

            if ($this->hasTable('generali')) {
                DB::table('generali')
                    ->where('code', 'SERIALNO')
                    ->update(['cvalue' => DB::raw('cvalue + ' . $addSlno)]);
            }

            DB::commit();

            $this->logDelpart($request, 'Add Slno Updated', ['utype' => 'E', 'ttype' => 'M', 'tdate' => $fromDate]);

            return response()->json([
                'ok' => true,
                'message' => 'Updated.',
                'data' => $this->buildAddSlnoPayload($fromDate),
                'summary' => $summary,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'message' => 'Add Slno failed: ' . $e->getMessage()], 500);
        }
    }

    public function runSqlUpdate(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $action = trim((string) $request->input('action', ''));
        $actions = collect($this->sqlUpdateActions())->keyBy('key');

        if (!$actions->has($action)) {
            return response()->json(['ok' => false, 'message' => 'Unknown SQL Updt action.'], 422);
        }

        // DDL actions (ALTER TABLE etc.) cause an implicit MySQL commit, so they
        // must not be wrapped in a transaction — doing so would leave no active
        // transaction for the subsequent DB::commit() call, throwing an exception.
        $ddlActions = ['widen_salestype_prefix_cols', 'widen_billno_columns', 'widen_legacy_docno_columns'];
        $isDdl = in_array($action, $ddlActions, true);

        if (!$isDdl) {
            DB::beginTransaction();
        }
        try {
            $result = match ($action) {
                'delete_updation_logs' => $this->sqlDeleteUpdationLogs(),
                'delete_unused_customers' => $this->sqlDeleteUnusedCustomers(),
                'barcode_date_issue' => $this->sqlUpdateBarcodeDateIssue(),
                'barcode_no_disc' => $this->sqlUpdateBarcodeNoDisc(),
                'barcode_stock_refresh' => $this->sqlUpdateBarcodeStock(),
                'clients_phone_to_mobile' => $this->sqlUpdateClientsPhoneToMobile(),
                'sales_cash_account' => $this->sqlUpdateSalesCashAccount(),
                'repair_missing_sales_ledger' => $this->sqlRepairMissingSalesLedger(),
                'cleanup_cancelled_order_leftovers' => $this->sqlCleanupCancelledOrderLeftovers(trim((string) $request->input('doc_no', ''))),
                'order_pending_check' => $this->sqlUpdateOrderPendingCheck(),
                'check_order_missing_data' => $this->sqlCheckOrderMissingData(),
                'check_sales_purchase_missing_data' => $this->sqlCheckSalesPurchaseMissingData(),
                'check_receipt_payment_missing_data' => $this->sqlCheckReceiptPaymentMissingData(),
                'check_duplicate_bills' => $this->sqlCheckDuplicateBills(),
                'scheme_collection_weight' => $this->sqlUpdateSchemeCollectionWeight(),
                'all_zero_stktouch_to_100' => $this->sqlUpdateStockTouchToHundred(),
                'cleanup_stale_prefix_counters' => $this->sqlCleanupStalePrefixCounters(),
                'reset_daybook_slno' => $this->sqlResetDaybookSlno(),
                'widen_salestype_prefix_cols' => $this->sqlWidenSalestypePrefixCols(),
                'widen_billno_columns' => $this->sqlWidenBillNoColumns(),
                'widen_legacy_docno_columns' => $this->sqlWidenLegacyDocNoColumns(),
                default => throw new \RuntimeException('Action handler missing.'),
            };

            if (!$isDdl) {
                DB::commit();
            }
            $this->logDelpart($request, 'SQL Updt: ' . ($actions[$action]['label'] ?? $action), ['utype' => 'E', 'ttype' => 'M']);

            return response()->json([
                'ok' => true,
                'message' => $result['message'] ?? 'Completed.',
                'summary' => $result['summary'] ?? [],
            ]);
        } catch (\Throwable $e) {
            if (!$isDdl) {
                DB::rollBack();
            }
            return response()->json(['ok' => false, 'message' => 'SQL Updt failed: ' . $e->getMessage()], 500);
        }
    }

    private function deleteTables(array $tables): void
    {
        foreach ($tables as $table) {
            if ($this->hasTable($table)) {
                DB::table($table)->delete();
            }
        }
    }

    private function generalValue(string $table, string $code, string $default): string
    {
        if (!$this->hasTable($table)) {
            return $default;
        }

        $code = $this->normalizeGeneralCode($table, $code);
        $value = DB::table($table)->where('code', $code)->value('cvalue');
        return $value === null || $value === '' ? $default : (string) $value;
    }

    private function upsertGeneralTextCode(string $table, string $code, string $value): void
    {
        if (!$this->hasTable($table)) {
            return;
        }

        $code = $this->normalizeGeneralCode($table, $code);
        $exists = DB::table($table)->where('code', $code)->exists();
        if ($exists) {
            DB::table($table)->where('code', $code)->update(['cvalue' => $value]);
        } else {
            DB::table($table)->insert(['code' => $code, 'cvalue' => $value]);
        }
    }

    private function normalizeGeneralCode(string $table, string $code): string
    {
        $table = strtolower(trim($table));
        $code = strtoupper(trim($code));
        if ($table !== 'generals') {
            return $code;
        }

        return self::GENERAL_CODE_ALIASES[$code] ?? $code;
    }

    private function upsertGeneralIntCode(string $table, string $code, int $value): void
    {
        $this->upsertGeneralTextCode($table, $code, (string) $value);
    }

    private function upsertGeneralInt(string $code, int $value): void
    {
        $this->upsertGeneralIntCode('generali', $code, $value);
    }

    private function normalizeDateInput(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y'] as $format) {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        $time = strtotime($value);
        return $time === false ? null : date('Y-m-d', $time);
    }

    private function generalText(string $code, string $default = ''): string
    {
        return trim($this->generalValue('generals', $code, $default));
    }

    private function changeDocNoSections(): array
    {
        return [
            // ── Section 1: All Prefixes (dedicated, always visible) ──────────
            [
                'title'       => 'All Prefixes',
                'description' => 'Bill number prefix text and zero-padded length for every document type. Edit these to change how bill numbers are displayed.',
                'highlight'   => true,
                'fields'      => [
                    ['label' => 'Sales Prefix',             'input' => 'sales_prefix',                      'table' => 'generals', 'code' => 'SBPREF',   'type' => 'text', 'default' => 'SLB/', 'group' => 'Sales'],
                    ['label' => 'Sales Length',             'input' => 'sales_length',                      'table' => 'generals', 'code' => 'SBLEN',    'type' => 'text', 'default' => '5',    'group' => 'Sales'],
                    ['label' => 'Sales Return Prefix',      'input' => 'sales_return_prefix',               'table' => 'generals', 'code' => 'SRBPREF',  'type' => 'text', 'default' => 'SRB/', 'group' => 'Sales'],
                    ['label' => 'Sales Return Length',      'input' => 'sales_return_length',               'table' => 'generals', 'code' => 'SRBLEN',   'type' => 'text', 'default' => '5',    'group' => 'Sales'],
                    ['label' => 'Purchase Prefix',          'input' => 'purchase_prefix',                   'table' => 'generals', 'code' => 'PBPREF',   'type' => 'text', 'default' => 'PUB/', 'group' => 'Purchase'],
                    ['label' => 'Purchase Length',          'input' => 'purchase_length',                   'table' => 'generals', 'code' => 'PBLEN',    'type' => 'text', 'default' => '5',    'group' => 'Purchase'],
                    ['label' => 'Purchase Return Prefix',   'input' => 'purchase_return_prefix',            'table' => 'generals', 'code' => 'PRBPREF',  'type' => 'text', 'default' => 'PNB/', 'group' => 'Purchase'],
                    ['label' => 'Purchase Return Length',   'input' => 'purchase_return_length',            'table' => 'generals', 'code' => 'PRBLEN',   'type' => 'text', 'default' => '5',    'group' => 'Purchase'],
                    ['label' => 'Diamond Purchase Prefix',  'input' => 'diamond_purchase_prefix',           'table' => 'generals', 'code' => 'DPBPREF',  'type' => 'text', 'default' => 'DPB/', 'group' => 'Diamond'],
                    ['label' => 'Diamond Purchase Length',  'input' => 'diamond_purchase_length',           'table' => 'generals', 'code' => 'DPBLEN',   'type' => 'text', 'default' => '5',    'group' => 'Diamond'],
                    ['label' => 'Dmd Purchase Ret Prefix',  'input' => 'diamond_purchase_return_prefix',    'table' => 'generals', 'code' => 'DPRBPREF', 'type' => 'text', 'default' => 'DRB/', 'group' => 'Diamond'],
                    ['label' => 'Dmd Purchase Ret Length',  'input' => 'diamond_purchase_return_length',    'table' => 'generals', 'code' => 'DPRBLEN',  'type' => 'text', 'default' => '5',    'group' => 'Diamond'],
                    ['label' => 'Jewellery Issue Prefix',   'input' => 'jewellery_issue_prefix',            'table' => 'generals', 'code' => 'JBPREF',   'type' => 'text', 'default' => 'JLB/', 'group' => 'Jewellery'],
                    ['label' => 'Jewellery Issue Length',   'input' => 'jewellery_issue_length',            'table' => 'generals', 'code' => 'JBLEN',    'type' => 'text', 'default' => '5',    'group' => 'Jewellery'],
                    ['label' => 'Jewellery Rcpt Prefix',    'input' => 'jewellery_receipt_prefix',          'table' => 'generals', 'code' => 'JRBPREF',  'type' => 'text', 'default' => 'JRB/', 'group' => 'Jewellery'],
                    ['label' => 'Jewellery Rcpt Length',    'input' => 'jewellery_receipt_length',          'table' => 'generals', 'code' => 'JRBLEN',   'type' => 'text', 'default' => '5',    'group' => 'Jewellery'],
                    ['label' => 'Smith Prefix',             'input' => 'smith_prefix',                      'table' => 'generals', 'code' => 'GSBPREF',  'type' => 'text', 'default' => 'GSB/', 'group' => 'Smith'],
                    ['label' => 'Smith Length',             'input' => 'smith_length',                      'table' => 'generals', 'code' => 'GSBLEN',   'type' => 'text', 'default' => '5',    'group' => 'Smith'],
                    ['label' => 'Smith Rcpt Prefix',        'input' => 'smith_receipt_prefix',              'table' => 'generals', 'code' => 'GSRBPREF', 'type' => 'text', 'default' => 'GSR/', 'group' => 'Smith'],
                    ['label' => 'Smith Rcpt Length',        'input' => 'smith_receipt_length',              'table' => 'generals', 'code' => 'GSRBLEN',  'type' => 'text', 'default' => '5',    'group' => 'Smith'],
                    ['label' => 'Party Deposit Prefix',     'input' => 'party_deposit_prefix',              'table' => 'generals', 'code' => 'DBPREF',   'type' => 'text', 'default' => 'PDB/', 'group' => 'Deposit'],
                    ['label' => 'Party Deposit Length',     'input' => 'party_deposit_length',              'table' => 'generals', 'code' => 'DBLEN',    'type' => 'text', 'default' => '5',    'group' => 'Deposit'],
                    ['label' => 'Order Prefix',             'input' => 'order_prefix',                      'table' => 'generals', 'code' => 'ORDBPREF',   'type' => 'text', 'default' => 'OR/', 'group' => 'Order'],
                    ['label' => 'Order Length',             'input' => 'order_length',                      'table' => 'generals', 'code' => 'ORDBLEN',    'type' => 'text', 'default' => '4',    'group' => 'Order'],
                    ['label' => 'Refinery Prefix',          'input' => 'refinery_prefix',                   'table' => 'generals', 'code' => 'REFINEBPREF','type' => 'text', 'default' => 'RFB/', 'group' => 'Refinery'],
                    ['label' => 'Refinery Length',          'input' => 'refinery_length',                   'table' => 'generals', 'code' => 'REFINEBLEN', 'type' => 'text', 'default' => '5',    'group' => 'Refinery'],
                    ['label' => 'Repair Prefix',            'input' => 'repair_prefix',                     'table' => 'generals', 'code' => 'REPAIRBPREF','type' => 'text', 'default' => 'RM1/', 'group' => 'Repair'],
                    ['label' => 'Repair Length',            'input' => 'repair_length',                     'table' => 'generals', 'code' => 'REPAIRBLEN', 'type' => 'text', 'default' => '5',    'group' => 'Repair'],
                    ['label' => 'Rem. Smith(I) Prefix',     'input' => 'remake_smith_issue_prefix',         'table' => 'generals', 'code' => 'RM2PREF',    'type' => 'text', 'default' => 'RM2/', 'group' => 'Repair'],
                    ['label' => 'Rem. Smith(I) Length',     'input' => 'remake_smith_issue_length',         'table' => 'generals', 'code' => 'RM2LEN',     'type' => 'text', 'default' => '5',    'group' => 'Repair'],
                    ['label' => 'Rem. Smith(R) Prefix',     'input' => 'remake_smith_receipt_prefix',       'table' => 'generals', 'code' => 'RM3PREF',    'type' => 'text', 'default' => 'RM3/', 'group' => 'Repair'],
                    ['label' => 'Rem. Smith(R) Length',     'input' => 'remake_smith_receipt_length',       'table' => 'generals', 'code' => 'RM3LEN',     'type' => 'text', 'default' => '5',    'group' => 'Repair'],
                    ['label' => 'Rem. Return Prefix',       'input' => 'remake_return_prefix',              'table' => 'generals', 'code' => 'RM4PREF',    'type' => 'text', 'default' => 'RM4/', 'group' => 'Repair'],
                    ['label' => 'Rem. Return Length',       'input' => 'remake_return_length',              'table' => 'generals', 'code' => 'RM4LEN',     'type' => 'text', 'default' => '5',    'group' => 'Repair'],
                    ['label' => 'Voucher Rcpt Prefix',      'input' => 'voucher_receipt_prefix',            'table' => 'generals', 'code' => 'VCHNORBPREF','type' => 'text', 'default' => 'VRB/', 'group' => 'Voucher'],
                    ['label' => 'Voucher Rcpt Length',      'input' => 'voucher_receipt_length',            'table' => 'generals', 'code' => 'VCHNORBLEN', 'type' => 'text', 'default' => '5',    'group' => 'Voucher'],
                    ['label' => 'Voucher Pmnt Prefix',      'input' => 'voucher_payment_prefix',            'table' => 'generals', 'code' => 'VCHNOPBPREF','type' => 'text', 'default' => 'VPB/', 'group' => 'Voucher'],
                    ['label' => 'Voucher Pmnt Length',      'input' => 'voucher_payment_length',            'table' => 'generals', 'code' => 'VCHNOPBLEN', 'type' => 'text', 'default' => '5',    'group' => 'Voucher'],
                    ['label' => 'Voucher Journal Prefix',   'input' => 'voucher_journal_prefix',            'table' => 'generals', 'code' => 'VCHNOJBPREF','type' => 'text', 'default' => 'JLB/', 'group' => 'Voucher'],
                    ['label' => 'Voucher Journal Length',   'input' => 'voucher_journal_length',            'table' => 'generals', 'code' => 'VCHNOJBLEN', 'type' => 'text', 'default' => '5',    'group' => 'Voucher'],
                    ['label' => 'Other Item Sale Prefix',   'input' => 'other_item_sale_prefix',            'table' => 'generals', 'code' => 'OTHERTSBPREF','type' => 'text', 'default' => 'OIS/', 'group' => 'Other Items'],
                    ['label' => 'Other Item Sale Length',   'input' => 'other_item_sale_length',            'table' => 'generals', 'code' => 'OTHERTSBLEN', 'type' => 'text', 'default' => '5',    'group' => 'Other Items'],
                    ['label' => 'Other Item Purch Prefix',  'input' => 'other_item_purchase_prefix',        'table' => 'generals', 'code' => 'OTHERTPBPREF','type' => 'text', 'default' => 'OIP/', 'group' => 'Other Items'],
                    ['label' => 'Other Item Purch Length',  'input' => 'other_item_purchase_length',        'table' => 'generals', 'code' => 'OTHERTPBLEN', 'type' => 'text', 'default' => '5',    'group' => 'Other Items'],
                    ['label' => 'BC Doc Prefix',            'input' => 'bc_doc_prefix',                     'table' => 'generals', 'code' => 'BCDOCPREF',  'type' => 'text', 'default' => 'BD',   'group' => 'Barcode'],
                    ['label' => 'BC Doc Length',            'input' => 'bc_doc_length',                     'table' => 'generals', 'code' => 'BCDOCLEN',   'type' => 'text', 'default' => '6',    'group' => 'Barcode'],
                    ['label' => 'Partner Dep. Prefix',      'input' => 'partner_deposit_wgt_prefix',        'table' => 'generals', 'code' => 'WTRANPREF',  'type' => 'text', 'default' => 'WTB/', 'group' => 'Partner'],
                    ['label' => 'Partner Dep. Length',      'input' => 'partner_deposit_wgt_length',        'table' => 'generals', 'code' => 'WTRANLEN',   'type' => 'text', 'default' => '5',    'group' => 'Partner'],
                    ['label' => 'Schm/Kuri Rcpt Prefix',    'input' => 'scheme_kuri_receipt_prefix',        'table' => 'generals', 'code' => 'KRCPTPREF',  'type' => 'text', 'default' => 'KRC/', 'group' => 'Scheme'],
                    ['label' => 'Schm/Kuri Rcpt Length',    'input' => 'scheme_kuri_receipt_length',        'table' => 'generals', 'code' => 'KRCPTLEN',   'type' => 'text', 'default' => '5',    'group' => 'Scheme'],
                    ['label' => 'Kuri Rcpt Prefix',         'input' => 'kuri_receipt_prefix',               'table' => 'generals', 'code' => 'VCHNOKRBPREF','type' => 'text', 'default' => 'KRB/', 'group' => 'Scheme'],
                    ['label' => 'Kuri Rcpt Length',         'input' => 'kuri_receipt_length',               'table' => 'generals', 'code' => 'VCHNOKRBLEN', 'type' => 'text', 'default' => '5',    'group' => 'Scheme'],
                    ['label' => 'Kuri Pmnt Prefix',         'input' => 'kuri_payment_prefix',               'table' => 'generals', 'code' => 'VCHNOKPBPREF','type' => 'text', 'default' => 'KPB/', 'group' => 'Scheme'],
                    ['label' => 'Kuri Pmnt Length',         'input' => 'kuri_payment_length',               'table' => 'generals', 'code' => 'VCHNOKPBLEN', 'type' => 'text', 'default' => '5',    'group' => 'Scheme'],
                ],
            ],
            // ── Section 2: Sales & Purchase Counters ─────────────────────────
            [
                'title'       => 'Sales And Purchase Counters',
                'description' => 'Running bill number counters for sales, purchase, and related documents.',
                'fields'      => [
                    ['label' => 'Serial No.',                   'input' => 'serial_no',                     'table' => 'generali', 'code' => 'SERIALNO',  'type' => 'int', 'readonly' => true],
                    ['label' => 'Serial Start',                 'input' => 'serial_start',                  'table' => 'generals', 'code' => 'SLNOSTART', 'type' => 'text', 'default' => '0'],
                    ['label' => 'Sales Bill No.',               'input' => 'sales_bill_no',                 'table' => 'generali', 'code' => 'SALESB',    'type' => 'int'],
                    ['label' => 'Sales Tmp Bill No.',           'input' => 'sales_tmp_bill_no',             'table' => 'generali', 'code' => 'TSALESB',   'type' => 'int'],
                    ['label' => 'Sales Return Bill No.',        'input' => 'sales_return_bill_no',          'table' => 'generali', 'code' => 'SRETURNB',  'type' => 'int'],
                    ['label' => 'Purchase Bill No.',            'input' => 'purchase_bill_no',              'table' => 'generali', 'code' => 'PURCHASEB', 'type' => 'int'],
                    ['label' => 'Purchase Return Bill No.',     'input' => 'purchase_return_bill_no',       'table' => 'generali', 'code' => 'PRETURNB',  'type' => 'int'],
                    ['label' => 'Diamond Purchase Bill No.',    'input' => 'diamond_purchase_bill_no',      'table' => 'generali', 'code' => 'DPURCHASEB','type' => 'int'],
                    ['label' => 'Dmd Purchase Ret Bill No.',    'input' => 'diamond_purchase_return_bill_no','table' => 'generali','code' => 'DPRETURNB', 'type' => 'int'],
                ],
            ],
            // ── Section 3: Jewellery & Smith Counters ────────────────────────
            [
                'title'       => 'Jewellery And Smith Counters',
                'description' => 'Bill number counters for jewellery movements, smith transactions, and party deposits.',
                'fields'      => [
                    ['label' => 'Jewellery Issue Bill No.',     'input' => 'jewellery_issue_bill_no',       'table' => 'generali', 'code' => 'JEWLB',  'type' => 'int'],
                    ['label' => 'Jewellery Rcpt Bill No.',      'input' => 'jewellery_receipt_bill_no',     'table' => 'generali', 'code' => 'JEWLRB', 'type' => 'int'],
                    ['label' => 'Smith Bill No.',               'input' => 'smith_bill_no',                 'table' => 'generali', 'code' => 'SMITHB', 'type' => 'int'],
                    ['label' => 'Smith Rcpt Bill No.',          'input' => 'smith_receipt_bill_no',         'table' => 'generali', 'code' => 'SMITHRB','type' => 'int'],
                    ['label' => 'Party Deposit Bill No.',       'input' => 'party_deposit_bill_no',         'table' => 'generali', 'code' => 'PDEPB',  'type' => 'int'],
                ],
            ],
            // ── Section 4: Orders, Repair & Other Counters ───────────────────
            [
                'title'       => 'Orders, Repair And Other Counters',
                'description' => 'Counters for orders, repairs, vouchers, barcode, remake, scheme, and partner deposit documents.',
                'fields'      => [
                    ['label' => 'Refinery Bill No.',            'input' => 'refinery_bill_no',              'table' => 'generali', 'code' => 'REFINEB',  'type' => 'int'],
                    ['label' => 'Order Bill No.',               'input' => 'order_bill_no',                 'table' => 'generali', 'code' => 'ORDERB',   'type' => 'int'],
                    ['label' => 'Order Start',                  'input' => 'order_start',                   'table' => 'generals', 'code' => 'ORDBSTART','type' => 'text', 'default' => '0'],
                    ['label' => 'Repair Bill No.',              'input' => 'repair_bill_no',                'table' => 'generali', 'code' => 'REPAIRB',  'type' => 'int'],
                    ['label' => 'Voucher Rcpt Bill No.',        'input' => 'voucher_receipt_bill_no',       'table' => 'generali', 'code' => 'VCHNORB',  'type' => 'int'],
                    ['label' => 'Voucher Pmnt Bill No.',        'input' => 'voucher_payment_bill_no',       'table' => 'generali', 'code' => 'VCHNOPB',  'type' => 'int'],
                    ['label' => 'Voucher Journal Bill No.',     'input' => 'voucher_journal_bill_no',       'table' => 'generali', 'code' => 'VCHNOJB',  'type' => 'int'],
                    ['label' => 'Other Item Sale Bill No.',     'input' => 'other_item_sale_bill_no',       'table' => 'generali', 'code' => 'OTHERTSB', 'type' => 'int'],
                    ['label' => 'Other Item Purch Bill No.',    'input' => 'other_item_purchase_bill_no',   'table' => 'generali', 'code' => 'OTHERTPB', 'type' => 'int'],
                    ['label' => 'BC Doc. No.',                  'input' => 'bc_doc_no',                     'table' => 'generali', 'code' => 'BCDOCNO',  'type' => 'int'],
                    ['label' => 'Rem. Smith(I) No.',            'input' => 'remake_smith_issue_no',         'table' => 'generali', 'code' => 'RM2B',     'type' => 'int'],
                    ['label' => 'Rem. Smith(R) No.',            'input' => 'remake_smith_receipt_no',       'table' => 'generali', 'code' => 'RM3B',     'type' => 'int'],
                    ['label' => 'Rem. Return No.',              'input' => 'remake_return_no',              'table' => 'generali', 'code' => 'RM4B',     'type' => 'int'],
                    ['label' => 'Partner Dep. Wgt Bill No.',    'input' => 'partner_deposit_weight_bill_no','table' => 'generali', 'code' => 'WTRANB',   'type' => 'int'],
                    ['label' => 'Schm/Kuri Rcpt No.',           'input' => 'scheme_kuri_receipt_no',        'table' => 'generali', 'code' => 'KRCPTNO',  'type' => 'int'],
                    ['label' => 'Kuri Rcpt BNo',                'input' => 'kuri_receipt_bill_no',          'table' => 'generali', 'code' => 'VCHNOKRB', 'type' => 'int'],
                    ['label' => 'Kuri Pmnt BNo',                'input' => 'kuri_payment_bill_no',          'table' => 'generali', 'code' => 'VCHNOKPB', 'type' => 'int'],
                ],
            ],
        ];
    }

    private function loadChangeDocNoField(array $field): string
    {
        $default = (string) ($field['default'] ?? '0');
        return $this->generalValue($field['table'], $field['code'], $default);
    }

    private function buildAddSlnoPayload(?string $fromDate = null): array
    {
        return [
            'today' => $fromDate ?? now()->format('Y-m-d'),
            'max_slno' => $this->maxSlnoFrom(['daybook', 'smithm']),
            'min_slno' => $this->minSlnoFrom(['daybook', 'smithm']),
        ];
    }

    private function maxSlnoFrom(array $tables): int
    {
        $max = 0;
        foreach ($tables as $table) {
            if (!$this->hasTable($table) || !Schema::hasColumn($table, 'slno')) {
                continue;
            }

            $value = (int) (DB::table($table)->max('slno') ?? 0);
            if ($value > $max) {
                $max = $value;
            }
        }

        return $max;
    }

    private function minSlnoFrom(array $tables): int
    {
        $min = null;
        foreach ($tables as $table) {
            if (!$this->hasTable($table) || !Schema::hasColumn($table, 'slno')) {
                continue;
            }

            $value = DB::table($table)->min('slno');
            if ($value === null) {
                continue;
            }

            $value = (int) $value;
            if ($min === null || $value < $min) {
                $min = $value;
            }
        }

        return $min ?? 0;
    }

    private function incrementSlnoByDate(string $table, string $fromDate, int $addSlno): int
    {
        if (!$this->hasTable($table) || !Schema::hasColumn($table, 'slno') || !Schema::hasColumn($table, 'tdate')) {
            return 0;
        }

        return DB::table($table)
            ->whereDate('tdate', '>=', $fromDate)
            ->update(['slno' => DB::raw('slno + ' . $addSlno)]);
    }

    private function incrementChildSlnoByParentDate(string $childTable, string $parentTable, string $fromDate, int $addSlno): int
    {
        if (
            !$this->hasTable($childTable) ||
            !$this->hasTable($parentTable) ||
            !Schema::hasColumn($childTable, 'slno') ||
            !Schema::hasColumn($parentTable, 'slno') ||
            !Schema::hasColumn($parentTable, 'tdate')
        ) {
            return 0;
        }

        return DB::table($childTable)
            ->whereIn('slno', function ($query) use ($parentTable, $fromDate) {
                $query->from($parentTable)
                    ->select($parentTable . '.slno')
                    ->whereDate($parentTable . '.tdate', '>=', $fromDate);
            })
            ->update(['slno' => DB::raw('slno + ' . $addSlno)]);
    }

    private function incrementAllSlno(string $table, int $addSlno): int
    {
        if (!$this->hasTable($table) || !Schema::hasColumn($table, 'slno')) {
            return 0;
        }

        return DB::table($table)->update(['slno' => DB::raw('slno + ' . $addSlno)]);
    }

    private function incrementNamedColumn(string $table, string $column, int $addSlno): int
    {
        if (!$this->hasTable($table) || !Schema::hasColumn($table, $column)) {
            return 0;
        }

        return DB::table($table)->update([$column => DB::raw($column . ' + ' . $addSlno)]);
    }

    private function sqlUpdateActions(): array
    {
        return [
            ['key' => 'delete_updation_logs', 'label' => 'Delete Updation Logs', 'category' => 'Cleanup', 'risk' => 'medium', 'description' => 'Clears `delpart` update logs.'],
            ['key' => 'delete_unused_customers', 'label' => 'Del All Unused Customers', 'category' => 'Cleanup', 'risk' => 'high', 'description' => 'Removes customer and account rows with zero balance and no ledger usage.'],
            ['key' => 'barcode_date_issue', 'label' => 'Update Barcode Date Issue', 'category' => 'Barcode', 'risk' => 'low', 'description' => 'Sets invalid barcode dates to the system start date.'],
            ['key' => 'barcode_no_disc', 'label' => 'Update Barcode No Disc', 'category' => 'Barcode', 'risk' => 'medium', 'description' => 'Marks all barcode rows as no-discount.'],
            ['key' => 'barcode_stock_refresh', 'label' => 'Update Barcode Stock', 'category' => 'Barcode', 'risk' => 'medium', 'description' => 'Refreshes barcode stock based on sale usage.'],
            ['key' => 'clients_phone_to_mobile', 'label' => 'Update Clients Phone to Mobile', 'category' => 'Party', 'risk' => 'low', 'description' => 'Copies telephone into mobile when mobile is empty.'],
            ['key' => 'sales_cash_account', 'label' => 'Update Sales with Cash A/c', 'category' => 'Sales', 'risk' => 'low', 'description' => 'Fills missing sales cash/bank code with CASH.'],
            ['key' => 'repair_missing_sales_ledger', 'label' => 'Repair Missing Sales Ledger', 'category' => 'Sales', 'risk' => 'low', 'description' => 'Rebuilds missing `daybook` and `daybookpart` rows for sales bills that exist in `salesm` but do not appear in A/C Ledger.'],
            ['key' => 'cleanup_cancelled_order_leftovers', 'label' => 'Cleanup Cancelled Order Leftovers', 'category' => 'Order', 'risk' => 'medium', 'description' => 'Enter an already-cancelled order no and remove leftover purchase / sales-return / daybook rows still showing in reports or stock.', 'requires_doc_no' => true, 'doc_no_label' => 'Order No'],
            ['key' => 'order_pending_check', 'label' => 'Updt Order Pend Chk', 'category' => 'Order', 'risk' => 'low', 'description' => 'Marks orders with `salebill` as status 2.'],
            ['key' => 'check_order_missing_data', 'label' => 'Check Order Missing Data', 'category' => 'Order', 'risk' => 'low', 'description' => 'Checks for orders missing linked item, daybook, exchange, sales return, or gold advance rows and shows the counts.'],
            ['key' => 'check_sales_purchase_missing_data', 'label' => 'Check Sales/Purchase Missing Data', 'category' => 'Audit', 'risk' => 'low', 'description' => 'Checks sales and purchase bills for missing detail, daybook, and linked return/exchange rows and shows the counts.'],
            ['key' => 'check_receipt_payment_missing_data', 'label' => 'Check Receipt/Payment Missing Data', 'category' => 'Audit', 'risk' => 'low', 'description' => 'Checks receipt and payment vouchers for missing or incomplete linked `daybook` rows that make entries disappear from the list screen.'],
            ['key' => 'check_duplicate_bills', 'label' => 'Check Duplicate Bills', 'category' => 'Audit', 'risk' => 'low', 'description' => 'Checks sales, purchase, return, and order tables for duplicate bill/order numbers and shows duplicate group counts.'],
            ['key' => 'scheme_collection_weight', 'label' => 'Update Scheme Colln Wgt', 'category' => 'Scheme', 'risk' => 'medium', 'description' => 'Recalculates scheme collection weight from amount and rate.'],
            ['key' => 'all_zero_stktouch_to_100', 'label' => 'All 0 StkTouch(smith/Jw) to 100', 'category' => 'Goldsmith', 'risk' => 'low', 'description' => 'Updates zero stock touch to 100 in `clientsgs`.'],
            ['key' => 'cleanup_stale_prefix_counters', 'label' => 'Cleanup Stale Prefix Counters', 'category' => 'Cleanup', 'risk' => 'low', 'description' => 'Removes orphaned SALES/SRET/PURCH/PRET counter rows in `generali` that no longer match any bill prefix in `salestype`.'],
            ['key' => 'reset_daybook_slno', 'label' => 'Reset Daybook SLNO', 'category' => 'Cleanup', 'risk' => 'medium', 'description' => 'Fixes duplicate or out-of-order `slno` values in the `daybook` table by reassigning them sequentially by date.'],
            ['key' => 'widen_salestype_prefix_cols', 'label' => 'Widen Prefix Columns', 'category' => 'Schema', 'risk' => 'low', 'description' => 'Extends `salestype` prefix/name columns to VARCHAR(20)/VARCHAR(30), `generali.code` to VARCHAR(30), and `stkandprofit` value columns to DECIMAL(15,2) to support large bill amounts.'],
            ['key' => 'widen_billno_columns', 'label' => 'Widen Bill No Columns', 'category' => 'Schema', 'risk' => 'low', 'description' => 'Extends sales, sales return, purchase, and purchase return `billno` columns to VARCHAR(20) so longer bill numbers save correctly.'],
            ['key' => 'widen_legacy_docno_columns', 'label' => 'Fix Exchange DocNo Length', 'category' => 'Schema', 'risk' => 'low', 'description' => 'Extends legacy `docno` and related `billno` columns used by exchange, return, daybook, stock-profit, kuri, and PDC sync so long bill numbers save without truncation errors.'],
        ];
    }

    private function readCompanyTransferDefault(): string
    {
        $path = storage_path('app/company-dbstrf.json');
        if (!File::exists($path)) {
            return '';
        }

        $decoded = json_decode((string) File::get($path), true);
        return is_array($decoded) ? trim((string) ($decoded['dbstrf'] ?? '')) : '';
    }

    private function loadCompanySelectRegistry(): array
    {
        $path = storage_path('app/company-select.json');
        if (!File::exists($path)) {
            return [];
        }

        $decoded = json_decode((string) File::get($path), true);
        if (!is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->filter(fn ($row) => is_array($row))
            ->map(fn (array $row) => [
                'company_name' => trim((string) ($row['company_name'] ?? '')),
                'database' => trim((string) ($row['database'] ?? '')),
            ])
            ->filter(fn (array $row) => $row['database'] !== '')
            ->values()
            ->all();
    }

    private function validateDataTransferRequest(Request $request): array
    {
        $targetDatabase = trim((string) Config::get('database.connections.mysql.database', ''));
        $sourceDatabase = $targetDatabase;
        $targetDatabase = trim((string) $request->input('target_database', ''));
        $dateFrom = trim((string) $request->input('date_from', now()->format('Y-m-d')));
        $dateTo = trim((string) $request->input('date_to', now()->format('Y-m-d')));
        $modules = collect($request->input('modules', []))
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values()
            ->all();

        if (!$this->isSafeDatabaseName($sourceDatabase)) {
            return ['Current company database is not configured.', null];
        }

        if (!$this->isSafeDatabaseName($targetDatabase)) {
            return ['Select a valid target company database.', null];
        }

        if ($sourceDatabase === $targetDatabase) {
            return ['Source and target company cannot be the same.', null];
        }

        if (!$this->isValidYmdDate($dateFrom) || !$this->isValidYmdDate($dateTo)) {
            return ['Enter a valid date range.', null];
        }

        if ($dateFrom > $dateTo) {
            return ['Date From must be earlier than or equal to Date To.', null];
        }

        if ($modules === []) {
            return ['Select at least one transfer option.', null];
        }

        return [null, [
            'source_database' => $sourceDatabase,
            'target_database' => $targetDatabase,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'modules' => $modules,
        ]];
    }

    private function dataTransferModules(): array
    {
        return [
            'sales' => ['label' => 'Sales'],
            'purchase' => ['label' => 'Purchase'],
            'order' => ['label' => 'Order'],
            'order_sale' => ['label' => 'Order Sale'],
            'sales_return' => ['label' => 'Sales Return'],
            'purchase_return' => ['label' => 'Purchase Return'],
            'receipt' => ['label' => 'Receipt'],
            'payment' => ['label' => 'Payment'],
            'journal' => ['label' => 'Journal'],
            'smith' => ['label' => 'Smith'],
            'jewellery' => ['label' => 'Jewellery'],
            'barcode' => ['label' => 'Barcode'],
            'staff_master' => ['label' => 'Staff Master'],
            'items_master' => ['label' => 'Items Master'],
            'customer_master' => ['label' => 'Customer Master'],
            'supplier_master' => ['label' => 'Supplier Master'],
            'salesman_master' => ['label' => 'Salesman Master'],
            'smith_master' => ['label' => 'Smith Master'],
            'all_transactions' => ['label' => 'All Transactions'],
            'all_masters' => ['label' => 'All Masters'],
            'daybook_accounts' => ['label' => 'Daybook / Accounts Entries'],
            'detail_tables' => ['label' => 'Detail Tables'],
            'controls_counters' => ['label' => 'Controls / Counters'],
            'stock_barcode_sync' => ['label' => 'Stock / Barcode Sync'],
        ];
    }

    private function dataTransferQuickOptions(): array
    {
        return [
            [
                'title' => 'Sales & Purchase',
                'options' => [
                    ['key' => 'sales', 'label' => 'Sales'],
                    ['key' => 'sales_return', 'label' => 'Sales Return'],
                    ['key' => 'purchase', 'label' => 'Purchase'],
                    ['key' => 'purchase_return', 'label' => 'Purchase Return'],
                ],
            ],
            [
                'title' => 'Orders',
                'options' => [
                    ['key' => 'order', 'label' => 'Order'],
                    ['key' => 'order_sale', 'label' => 'Order Sale'],
                ],
            ],
            [
                'title' => 'Accounts',
                'options' => [
                    ['key' => 'receipt', 'label' => 'Receipt'],
                    ['key' => 'payment', 'label' => 'Payment'],
                    ['key' => 'journal', 'label' => 'Journal'],
                ],
            ],
            [
                'title' => 'Smith & Jewellery',
                'options' => [
                    ['key' => 'jewellery', 'label' => 'Jewellery Bill Transfer'],
                    ['key' => 'barcode', 'label' => 'Barcode'],
                ],
            ],
        ];
    }

    private function expandDataTransferModules(array $selected): array
    {
        $expanded = [];
        $append = function (array $keys) use (&$expanded) {
            foreach ($keys as $key) {
                if (!in_array($key, $expanded, true)) {
                    $expanded[] = $key;
                }
            }
        };

        foreach ($selected as $module) {
            switch ($module) {
                case 'all_transactions':
                    $append(['sales', 'purchase', 'order', 'order_sale', 'sales_return', 'purchase_return', 'receipt', 'payment', 'journal', 'barcode']);
                    break;
                case 'all_masters':
                    $append(['staff_master', 'items_master', 'salesman_master']);
                    break;
                case 'detail_tables':
                    $append(['sales', 'purchase', 'sales_return', 'purchase_return', 'jewellery']);
                    break;
                case 'controls_counters':
                    $append([]);
                    break;
                case 'stock_barcode_sync':
                    $append(['barcode', 'items_master']);
                    break;
                case 'daybook_accounts':
                    $append(['receipt', 'payment', 'journal']);
                    break;
                case 'sales':
                case 'order':
                case 'order_sale':
                case 'sales_return':
                case 'purchase':
                case 'purchase_return':
                case 'receipt':
                case 'payment':
                case 'journal':
                    $append([$module]);
                    break;
                default:
                    if (isset($this->dataTransferModules()[$module])) {
                        $append([$module]);
                    }
                    break;
            }
        }

        return $expanded;
    }

    private function buildDataTransferPreviewRow(string $sourceDb, string $moduleKey, string $dateFrom, string $dateTo): array
    {
        $label = $this->dataTransferModules()[$moduleKey]['label'] ?? ucfirst(str_replace('_', ' ', $moduleKey));

        try {
            $rows = match ($moduleKey) {
                'sales' => count($this->fetchStandaloneSalesSlnos($sourceDb, $dateFrom, $dateTo)),
                'purchase' => count($this->fetchStandalonePurchaseSlnos($sourceDb, $dateFrom, $dateTo)),
                'order' => $this->countSourceDateRows($sourceDb, 'orderm', $dateFrom, $dateTo),
                'order_sale' => count($this->fetchOrderSaleSlnos($sourceDb, $dateFrom, $dateTo)),
                'sales_return' => $this->countSourceDateRows($sourceDb, 'salesrm', $dateFrom, $dateTo),
                'purchase_return' => $this->countSourceDateRows($sourceDb, 'purchaserm', $dateFrom, $dateTo),
                'smith' => $this->countSmithByCtype($sourceDb, $dateFrom, $dateTo, 'smith'),
                'jewellery' => $this->countSmithByCtype($sourceDb, $dateFrom, $dateTo, 'jewellery'),
                'barcode' => $this->countSourceDateRowsFlexible($sourceDb, 'barcode', $dateFrom, $dateTo),
                'receipt' => $this->countSourceDaybookVoucherRows($sourceDb, 'VR', $dateFrom, $dateTo),
                'payment' => $this->countSourceDaybookVoucherRows($sourceDb, 'VP', $dateFrom, $dateTo),
                'journal' => $this->countSourceDaybookVoucherRows($sourceDb, 'JL', $dateFrom, $dateTo),
                'staff_master' => $this->countSourceRows($sourceDb, 'userm'),
                'items_master' => $this->countSourceRows($sourceDb, 'items'),
                'customer_master' => $this->countSourceClientsByType($sourceDb, 'C'),
                'supplier_master' => $this->countSourceClientsByType($sourceDb, 'S'),
                'salesman_master' => $this->countSourceRows($sourceDb, 'sman'),
                'smith_master' => $this->countSourceClientsByTypes($sourceDb, ['G', 'J']),
                default => 0,
            };

            return [
                'module' => $moduleKey,
                'label' => $label,
                'rows' => $rows,
                'status' => 'ready',
            ];
        } catch (\Throwable $e) {
            return [
                'module' => $moduleKey,
                'label' => $label,
                'rows' => 0,
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    private function runDataTransferModule(string $sourceDb, string $targetDb, string $moduleKey, string $dateFrom, string $dateTo): array
    {
        $label = $this->dataTransferModules()[$moduleKey]['label'] ?? ucfirst(str_replace('_', ' ', $moduleKey));
        $copied = 0;

        switch ($moduleKey) {
            case 'sales':
                $slnos = $this->fetchStandaloneSalesSlnos($sourceDb, $dateFrom, $dateTo);
                $copied += $this->transferTransactionsWithSyncEngine($targetDb, 'sales', $slnos);
                $copied += $this->transferRelatedMastersForSlnos($sourceDb, $targetDb, $slnos, ['salesm', 'salesd', 'salesrm', 'salesrd', 'purchasem', 'purchased', 'daybook', 'daybookpart']);
                break;
            case 'purchase':
                $slnos = $this->fetchStandalonePurchaseSlnos($sourceDb, $dateFrom, $dateTo);
                $copied += $this->transferTransactionsWithSyncEngine($targetDb, 'purchase', $slnos);
                $copied += $this->transferRelatedMastersForSlnos($sourceDb, $targetDb, $slnos, ['purchasem', 'purchased', 'purchaserm', 'purchaserd', 'daybook', 'daybookpart']);
                break;
            case 'order':
                $slnos = $this->fetchDateRangeSlnos($sourceDb, 'orderm', $dateFrom, $dateTo);
                $copied += $this->transferTransactionsWithSyncEngine($targetDb, 'order', $slnos);
                $copied += $this->transferRelatedMastersForSlnos($sourceDb, $targetDb, $slnos, ['orderm', 'orderd', 'orderdga', 'ordermodel', 'purchasem', 'purchased', 'salesrm', 'salesrd', 'daybook', 'daybookpart', 'stkandprofit', 'oglist', 'pdclist']);
                break;
            case 'order_sale':
                $slnos = $this->fetchOrderSaleSlnos($sourceDb, $dateFrom, $dateTo);
                $copied += $this->transferTransactionsWithSyncEngine($targetDb, 'order_sale', $slnos);
                $copied += $this->transferRelatedMastersForSlnos($sourceDb, $targetDb, $slnos, ['salesm', 'salesd', 'salesrm', 'salesrd', 'purchasem', 'purchased', 'orderm', 'orderd', 'orderdga', 'daybook', 'daybookpart', 'stkandprofit', 'oglist', 'pdclist']);
                break;
            case 'sales_return':
                $slnos = $this->fetchDateRangeSlnos($sourceDb, 'salesrm', $dateFrom, $dateTo);
                $copied += $this->transferSalesLike($sourceDb, $targetDb, 'salesrm', 'salesrd', $dateFrom, $dateTo);
                $copied += $this->transferRelatedMastersForSlnos($sourceDb, $targetDb, $slnos, ['salesrm', 'salesrd', 'daybook', 'daybookpart']);
                break;
            case 'purchase_return':
                $slnos = $this->fetchDateRangeSlnos($sourceDb, 'purchaserm', $dateFrom, $dateTo);
                $copied += $this->transferSalesLike($sourceDb, $targetDb, 'purchaserm', 'purchaserd', $dateFrom, $dateTo);
                $copied += $this->transferRelatedMastersForSlnos($sourceDb, $targetDb, $slnos, ['purchaserm', 'purchaserd', 'daybook', 'daybookpart']);
                break;
            case 'smith':
                $slnos = $this->fetchSmithSlnosByCtype($sourceDb, $dateFrom, $dateTo, 'smith');
                $copied += $this->transferSmithLikeByCtype($sourceDb, $targetDb, $dateFrom, $dateTo, 'smith');
                $copied += $this->transferRelatedMastersForSlnos($sourceDb, $targetDb, $slnos, ['smithm', 'smithd', 'daybook', 'daybookpart']);
                break;
            case 'jewellery':
                $slnos = $this->fetchSmithSlnosByCtype($sourceDb, $dateFrom, $dateTo, 'jewellery');
                $copied += $this->transferSmithLikeByCtype($sourceDb, $targetDb, $dateFrom, $dateTo, 'jewellery');
                $copied += $this->transferRelatedMastersForSlnos($sourceDb, $targetDb, $slnos, ['smithm', 'smithd', 'daybook', 'daybookpart']);
                break;
            case 'barcode':
                $copied = $this->transferBarcodeWithSyncEngine($sourceDb, $targetDb, $dateFrom, $dateTo);
                break;
            case 'receipt':
                $slnos = $this->fetchSourceDaybookVoucherSlnos($sourceDb, 'VR', $dateFrom, $dateTo);
                $copied += $this->transferTransactionsWithSyncEngine($targetDb, 'receipt', $slnos);
                $copied += $this->transferRelatedMastersForSlnos($sourceDb, $targetDb, $slnos, ['daybook', 'daybookpart', 'pdclist']);
                break;
            case 'payment':
                $slnos = $this->fetchSourceDaybookVoucherSlnos($sourceDb, 'VP', $dateFrom, $dateTo);
                $copied += $this->transferTransactionsWithSyncEngine($targetDb, 'payment', $slnos);
                $copied += $this->transferRelatedMastersForSlnos($sourceDb, $targetDb, $slnos, ['daybook', 'daybookpart', 'pdclist']);
                break;
            case 'journal':
                $slnos = $this->fetchSourceDaybookVoucherSlnos($sourceDb, 'JL', $dateFrom, $dateTo);
                $copied += $this->transferTransactionsWithSyncEngine($targetDb, 'journal', $slnos);
                $copied += $this->transferRelatedMastersForSlnos($sourceDb, $targetDb, $slnos, ['daybook', 'daybookpart']);
                break;
            case 'staff_master':
                $copied = $this->transferWholeTableWithSyncEngine($targetDb, 'userm', ['code']);
                break;
            case 'items_master':
                $copied = $this->transferWholeTableWithSyncEngine($targetDb, 'items', ['code']);
                break;
            case 'customer_master':
                $copied = $this->transferClientsWithSyncEngine($sourceDb, $targetDb, ['C']);
                break;
            case 'supplier_master':
                $copied = $this->transferClientsWithSyncEngine($sourceDb, $targetDb, ['S']);
                break;
            case 'salesman_master':
                $copied = $this->transferWholeTableWithSyncEngine($targetDb, 'sman', ['code']);
                break;
            case 'smith_master':
                $copied = $this->transferClientsWithSyncEngine($sourceDb, $targetDb, ['G', 'J']);
                break;
        }

        return [
            'module' => $moduleKey,
            'label' => $label,
            'copied' => $copied,
            'status' => 'done',
        ];
    }

    private function transferTransactionsWithSyncEngine(string $targetDb, string $module, array $slnos): int
    {
        if ($slnos === []) {
            return 0;
        }

        $sync = (new SecondaryDatabaseSync())->useTargetDatabase($targetDb);
        $result = $sync->syncMany($module, $slnos);

        return (int) ($result['copied'] ?? 0);
    }

    private function transferClientsWithSyncEngine(string $sourceDb, string $targetDb, array $types): int
    {
        $rows = $this->fetchSourceClientRows($sourceDb, $types);
        if ($rows === []) {
            return 0;
        }

        $sync = (new SecondaryDatabaseSync())->useTargetDatabase($targetDb);
        $copied = 0;

        foreach ($rows as $row) {
            $code = strtoupper(trim((string) ($row['code'] ?? '')));
            $type = strtoupper(trim((string) ($row['ctype'] ?? '')));
            if ($code === '') {
                continue;
            }

            $result = $sync->syncParty($code, $type);
            $copied += (int) ($result['copied'] ?? 0);
        }

        return $copied;
    }

    private function transferBarcodeWithSyncEngine(string $sourceDb, string $targetDb, string $dateFrom, string $dateTo): int
    {
        $rows = $this->fetchSourceFlexibleDateRows($sourceDb, 'barcode', $dateFrom, $dateTo);
        if ($rows === []) {
            return 0;
        }

        $bcodes = collect($rows)
            ->pluck('bcode')
            ->filter(fn ($value) => (int) $value > 0)
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values()
            ->all();

        if ($bcodes === []) {
            return 0;
        }

        $sync = (new SecondaryDatabaseSync())->useTargetDatabase($targetDb);
        $copied = 0;

        foreach ($bcodes as $bcode) {
            $result = $sync->syncBarcode($bcode);
            $copied += (int) ($result['copied'] ?? 0);
        }

        return $copied;
    }

    private function transferWholeTableWithSyncEngine(string $targetDb, string $table, array $candidateKeys): int
    {
        return (new SecondaryDatabaseSync())
            ->useTargetDatabase($targetDb)
            ->copyWholeTable($table, $candidateKeys);
    }

    private function fetchDateRangeSlnos(string $sourceDb, string $table, string $dateFrom, string $dateTo): array
    {
        return collect($this->fetchSourceTableRowsByDate($sourceDb, $table, $dateFrom, $dateTo))
            ->pluck('slno')
            ->filter(fn ($value) => (int) $value > 0)
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values()
            ->all();
    }

    private function fetchStandalonePurchaseSlnos(string $sourceDb, string $dateFrom, string $dateTo): array
    {
        $purchaseSlnos = $this->fetchDateRangeSlnos($sourceDb, 'purchasem', $dateFrom, $dateTo);
        if ($purchaseSlnos === []) {
            return $purchaseSlnos;
        }

        $linked = [];
        foreach (['salesm', 'orderm'] as $table) {
            if (!$this->sourceTableExists($sourceDb, $table)) {
                continue;
            }

            $linked = array_merge($linked, collect($this->fetchSourceRowsBySlno($sourceDb, $table, $purchaseSlnos))
                ->pluck('slno')
                ->filter(fn ($value) => (int) $value > 0)
                ->map(fn ($value) => (int) $value)
                ->unique()
                ->all());
        }

        $linked = array_values(array_unique($linked));
        if ($linked === []) {
            return $purchaseSlnos;
        }

        return array_values(array_diff($purchaseSlnos, $linked));
    }

    private function fetchStandaloneSalesSlnos(string $sourceDb, string $dateFrom, string $dateTo): array
    {
        $salesSlnos = $this->fetchDateRangeSlnos($sourceDb, 'salesm', $dateFrom, $dateTo);
        if ($salesSlnos === [] || !$this->sourceTableExists($sourceDb, 'salesm')) {
            return $salesSlnos;
        }

        $orderSaleSlnos = collect($this->fetchSourceRowsBySlno($sourceDb, 'salesm', $salesSlnos))
            ->filter(fn (array $row) => trim((string) ($row['orderno'] ?? '')) !== '')
            ->pluck('slno')
            ->filter(fn ($value) => (int) $value > 0)
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->all();

        if ($orderSaleSlnos === []) {
            return $salesSlnos;
        }

        return array_values(array_diff($salesSlnos, $orderSaleSlnos));
    }

    private function fetchOrderSaleSlnos(string $sourceDb, string $dateFrom, string $dateTo): array
    {
        $rows = $this->fetchSourceTableRowsByDate($sourceDb, 'salesm', $dateFrom, $dateTo);

        return collect($rows)
            ->filter(fn (array $row) => trim((string) ($row['orderno'] ?? '')) !== '')
            ->pluck('slno')
            ->filter(fn ($value) => (int) $value > 0)
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values()
            ->all();
    }

    private function transferRelatedMastersForSlnos(string $sourceDb, string $targetDb, array $slnos, array $tables): int
    {
        if ($slnos === []) {
            return 0;
        }

        $partyCodes = [];
        $accountCodes = [];
        $itemCodes = [];
        $salesmanCodes = [];
        $staffCodes = [];

        foreach ($tables as $table) {
            if (!$this->sourceTableExists($sourceDb, $table) || !$this->sourceColumnExists($sourceDb, $table, 'slno')) {
                continue;
            }

            foreach ($this->fetchSourceRowsBySlno($sourceDb, $table, $slnos) as $row) {
                foreach (['custcode', 'suppcode', 'smithcode', 'partycode'] as $column) {
                    $code = strtoupper(trim((string) ($row[$column] ?? '')));
                    if ($code !== '') {
                        $partyCodes[] = $code;
                        $accountCodes[] = $code;
                    }
                }

                foreach (['accode', 'opaccode', 'cbcode', 'cocode'] as $column) {
                    $code = strtoupper(trim((string) ($row[$column] ?? '')));
                    if ($code !== '') {
                        $accountCodes[] = $code;
                        $partyCodes[] = $code;
                    }
                }

                foreach (['code', 'itemcode', 'item_code'] as $column) {
                    $code = strtoupper(trim((string) ($row[$column] ?? '')));
                    if ($code !== '') {
                        $itemCodes[] = $code;
                    }
                }

                foreach (['smcode', 'salesman', 'salesmancode'] as $column) {
                    $code = strtoupper(trim((string) ($row[$column] ?? '')));
                    if ($code !== '') {
                        $salesmanCodes[] = $code;
                    }
                }

                foreach (['staff', 'staffcode'] as $column) {
                    $code = strtoupper(trim((string) ($row[$column] ?? '')));
                    if ($code !== '') {
                        $staffCodes[] = $code;
                    }
                }
            }
        }

        $partyCodes = array_values(array_unique($partyCodes));
        $accountCodes = array_values(array_unique($accountCodes));
        $itemCodes = array_values(array_unique($itemCodes));
        $salesmanCodes = array_values(array_unique($salesmanCodes));
        $staffCodes = array_values(array_unique($staffCodes));

        $copied = 0;
        $copied += $this->transferClientsByCodes($sourceDb, $targetDb, $partyCodes);
        $copied += $this->transferRowsByCodes($sourceDb, $targetDb, 'accountm', ['accode'], ['accode'], $accountCodes);
        $copied += $this->transferAccountStructuresByAccountCodes($sourceDb, $targetDb, $accountCodes);
        $copied += $this->transferRowsByCodes($sourceDb, $targetDb, 'items', ['code'], ['code'], $itemCodes);
        $copied += $this->transferRowsByCodes($sourceDb, $targetDb, 'sman', ['code'], ['code'], $salesmanCodes);
        $copied += $this->transferRowsByCodes($sourceDb, $targetDb, 'userm', ['code'], ['code'], $staffCodes);

        return $copied;
    }

    private function transferClientsByCodes(string $sourceDb, string $targetDb, array $codes): int
    {
        if ($codes === [] || !$this->sourceTableExists($sourceDb, 'clients') || !$this->sourceTableExists($targetDb, 'clients')) {
            return 0;
        }

        $clientRows = $this->fetchSourceRowsByCodes($sourceDb, 'clients', 'code', $codes);
        if ($clientRows === []) {
            return 0;
        }

        $clientCodes = collect($clientRows)
            ->pluck('code')
            ->filter(fn ($value) => trim((string) $value) !== '')
            ->map(fn ($value) => trim((string) $value))
            ->unique()
            ->values()
            ->all();

        $copied = $this->upsertRowsToDatabase($targetDb, 'clients', $clientRows, ['code']);
        $copied += $this->transferRowsByCodes($sourceDb, $targetDb, 'accountm', ['accode'], ['accode'], $clientCodes);
        $copied += $this->transferAccountStructuresByAccountCodes($sourceDb, $targetDb, $clientCodes);
        $copied += $this->transferRowsByCodes($sourceDb, $targetDb, 'clients_advanced', ['code'], ['code'], $clientCodes);
        $copied += $this->transferRowsByCodes($sourceDb, $targetDb, 'clientspict', ['code'], ['code'], $clientCodes);
        $copied += $this->transferRowsByCodes($sourceDb, $targetDb, 'clientsgs', ['code'], ['code'], $clientCodes);
        $copied += $this->transferRowsByCodes($sourceDb, $targetDb, 'clients_kuridet', [['code', 'startdate'], ['code']], ['code'], $clientCodes);

        return $copied;
    }

    private function transferAccountStructuresByAccountCodes(string $sourceDb, string $targetDb, array $accountCodes): int
    {
        if (
            $accountCodes === []
            || !$this->sourceTableExists($sourceDb, 'accountm')
            || !$this->sourceTableExists($targetDb, 'accountm')
        ) {
            return 0;
        }

        $accountRows = $this->fetchSourceRowsByCodes($sourceDb, 'accountm', 'accode', $accountCodes);
        if ($accountRows === []) {
            return 0;
        }

        $groupCodes = collect($accountRows)
            ->pluck('grcode')
            ->merge(collect($accountRows)->pluck('shedgrp'))
            ->filter(fn ($value) => trim((string) $value) !== '')
            ->map(fn ($value) => trim((string) $value))
            ->unique()
            ->values()
            ->all();

        $bsHeadCodes = collect($accountRows)
            ->pluck('bshead')
            ->filter(fn ($value) => trim((string) $value) !== '')
            ->map(fn ($value) => trim((string) $value))
            ->unique()
            ->values()
            ->all();

        $copied = 0;
        $copied += $this->transferRowsByCodes($sourceDb, $targetDb, 'accountg', ['grcode'], ['grcode'], $groupCodes);
        $copied += $this->transferRowsByCodes($sourceDb, $targetDb, 'accountgbs', ['hcode'], ['hcode'], $bsHeadCodes);

        return $copied;
    }

    private function transferSalesLike(string $sourceDb, string $targetDb, string $headerTable, string $detailTable, string $dateFrom, string $dateTo): int
    {
        if (!$this->hasTable($headerTable) || !$this->sourceTableExists($sourceDb, $headerTable) || !$this->sourceTableExists($targetDb, $headerTable)) {
            return 0;
        }

        $headerRows = $this->fetchSourceTableRowsByDate($sourceDb, $headerTable, $dateFrom, $dateTo);
        if ($headerRows === []) {
            return 0;
        }

        $slnos = collect($headerRows)->pluck('slno')->filter()->map(fn ($v) => (int) $v)->values()->all();
        $this->cleanupRowsBySlnoInDatabase($targetDb, $detailTable, $slnos);
        $this->cleanupRowsBySlnoInDatabase($targetDb, $headerTable, $slnos);

        $copied = $this->upsertRowsToDatabase($targetDb, $headerTable, $headerRows, ['slno']);

        if ($slnos !== [] && $this->hasTable($detailTable) && $this->sourceTableExists($sourceDb, $detailTable) && $this->sourceTableExists($targetDb, $detailTable)) {
            $detailRows = $this->fetchSourceRowsBySlno($sourceDb, $detailTable, $slnos);
            $copied += $this->upsertRowsToDatabase($targetDb, $detailTable, $detailRows, [['slno', 'itemslno'], ['slno', 'sl'], ['slno', 'code'], ['slno']]);
        }

        return $copied;
    }

    private function fetchSmithSlnosByCtype(string $sourceDb, string $dateFrom, string $dateTo, string $variant): array
    {
        if (!$this->sourceTableExists($sourceDb, 'smithm')) {
            return [];
        }
        $query = $this->buildSmithCtypeQuery($sourceDb, $variant);
        if ($query === null) {
            if ($variant === 'jewellery') {
                return [];
            }
            $query = DB::table(DB::raw($this->qualifiedTable($sourceDb, 'smithm')));
        }
        $dateColumn = $this->sourceDateColumn($sourceDb, 'smithm');
        if ($dateColumn !== null) {
            $query->whereBetween('smithm.' . $dateColumn, [$dateFrom, $dateTo]);
        }

        return $query->pluck('smithm.slno')
            ->filter(fn ($v) => (int) $v > 0)
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();
    }

    private function countSmithByCtype(string $sourceDb, string $dateFrom, string $dateTo, string $variant): int
    {
        if (!$this->sourceTableExists($sourceDb, 'smithm')) {
            return 0;
        }
        $query = $this->buildSmithCtypeQuery($sourceDb, $variant);
        if ($query === null) {
            if ($variant === 'jewellery') {
                return 0;
            }
            $query = DB::table(DB::raw($this->qualifiedTable($sourceDb, 'smithm')));
        }
        $dateColumn = $this->sourceDateColumn($sourceDb, 'smithm');
        if ($dateColumn !== null) {
            $query->whereBetween('smithm.' . $dateColumn, [$dateFrom, $dateTo]);
        }

        return (int) $query->count();
    }

    private function buildSmithCtypeQuery(string $sourceDb, string $variant)
    {
        if (!$this->sourceTableExists($sourceDb, 'clients')) {
            return null;
        }
        $smithmTable = $this->qualifiedTable($sourceDb, 'smithm');
        $clientsTable = $this->qualifiedTable($sourceDb, 'clients');

        $query = DB::table(DB::raw($smithmTable . ' as smithm'))
            ->leftJoin(DB::raw($clientsTable . ' as clients'), 'clients.code', '=', 'smithm.smithcode');

        if ($variant === 'jewellery') {
            $prefixes = $this->jewelleryTransferDocPrefixes($sourceDb);
            $query->where(function ($inner) use ($prefixes) {
                foreach ($prefixes as $prefix) {
                    $inner->orWhere('smithm.docno', 'like', $prefix . '%');
                }
            });
        } else {
            $prefixes = $this->jewelleryTransferDocPrefixes($sourceDb);
            $query->where(function ($inner) use ($prefixes) {
                foreach ($prefixes as $prefix) {
                    $inner->where('smithm.docno', 'not like', $prefix . '%');
                }
            });
        }

        return $query;
    }

    private function jewelleryTransferDocPrefixes(?string $database = null): array
    {
        $database = trim((string) $database);
        $prefixes = [
            'JI/',
            'JLB/',
            'JLE/',
            'JRB/',
            $this->sourceGeneralText($database, 'JBPREF', 'JLB/'),
            $this->sourceGeneralText($database, 'JEPREF', 'JLE/'),
            $this->sourceGeneralText($database, 'JRBPREF', 'JRB/'),
        ];

        return collect($prefixes)
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function sourceGeneralText(?string $database, string $code, string $default = ''): string
    {
        $database = trim((string) $database);
        if ($database === '' || !$this->sourceTableExists($database, 'generals')) {
            return $this->generalText($code, $default);
        }

        try {
            $value = DB::table(DB::raw($this->qualifiedTable($database, 'generals')))
                ->whereRaw('TRIM(code) = ?', [$code])
                ->value('cvalue');
        } catch (\Throwable $e) {
            return $this->generalText($code, $default);
        }

        $value = trim((string) ($value ?? ''));
        return $value !== '' ? $value : $default;
    }

    private function transferSmithLikeByCtype(string $sourceDb, string $targetDb, string $dateFrom, string $dateTo, string $variant): int
    {
        if (!$this->hasTable('smithm') || !$this->sourceTableExists($sourceDb, 'smithm') || !$this->sourceTableExists($targetDb, 'smithm')) {
            Log::warning('data-transfer.smith-like.skipped', [
                'variant' => $variant,
                'source_database' => $sourceDb,
                'target_database' => $targetDb,
                'reason' => 'smithm table missing in source or target',
            ]);
            return 0;
        }

        $slnos = $this->fetchSmithSlnosByCtype($sourceDb, $dateFrom, $dateTo, $variant);
        if ($slnos === []) {
            Log::info('data-transfer.smith-like.empty', [
                'variant' => $variant,
                'source_database' => $sourceDb,
                'target_database' => $targetDb,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ]);
            return 0;
        }

        $headerRows = $this->fetchSourceRowsBySlno($sourceDb, 'smithm', $slnos);
        if ($headerRows === []) {
            Log::warning('data-transfer.smith-like.no-header-rows', [
                'variant' => $variant,
                'source_database' => $sourceDb,
                'target_database' => $targetDb,
                'slnos' => $slnos,
            ]);
            return 0;
        }

        $detailRows = ($this->hasTable('smithd') && $this->sourceTableExists($sourceDb, 'smithd') && $this->sourceTableExists($targetDb, 'smithd'))
            ? $this->fetchSourceRowsBySlno($sourceDb, 'smithd', $slnos)
            : [];
        $daybookRows = ($this->hasTable('daybook') && $this->sourceTableExists($sourceDb, 'daybook') && $this->sourceTableExists($targetDb, 'daybook'))
            ? $this->fetchSourceRowsBySlno($sourceDb, 'daybook', $slnos)
            : [];
        $daybookPartRows = ($this->hasTable('daybookpart') && $this->sourceTableExists($sourceDb, 'daybookpart') && $this->sourceTableExists($targetDb, 'daybookpart'))
            ? $this->fetchSourceRowsBySlno($sourceDb, 'daybookpart', $slnos)
            : [];

        $slnoMap = $this->buildTargetSlnoMapForSmithLike($targetDb, $headerRows);
        $targetSlnos = collect($slnoMap)->values()->map(fn ($value) => (int) $value)->unique()->values()->all();
        $docMap = collect($headerRows)
            ->map(fn (array $row) => [
                'source_slno' => (int) ($row['slno'] ?? 0),
                'target_slno' => (int) ($slnoMap[(int) ($row['slno'] ?? 0)] ?? 0),
                'docno' => trim((string) ($row['docno'] ?? '')),
            ])
            ->values()
            ->all();

        Log::info('data-transfer.smith-like.mapping', [
            'variant' => $variant,
            'source_database' => $sourceDb,
            'target_database' => $targetDb,
            'source_slnos' => $slnos,
            'target_slnos' => $targetSlnos,
            'doc_map' => $docMap,
            'source_header_rows' => count($headerRows),
            'source_detail_rows' => count($detailRows),
            'source_daybook_rows' => count($daybookRows),
            'source_daybookpart_rows' => count($daybookPartRows),
        ]);

        $headerRows = $this->remapRowsBySlno($headerRows, $slnoMap);
        $detailRows = $this->remapRowsBySlno($detailRows, $slnoMap);
        $daybookRows = $this->remapRowsBySlno($daybookRows, $slnoMap);
        $daybookPartRows = $this->remapRowsBySlno($daybookPartRows, $slnoMap);

        $this->cleanupRowsBySlnoInDatabase($targetDb, 'smithd', $targetSlnos);
        $this->cleanupRowsBySlnoInDatabase($targetDb, 'smithm', $targetSlnos);
        $this->cleanupRowsBySlnoInDatabase($targetDb, 'daybook', $targetSlnos);
        $this->cleanupRowsBySlnoInDatabase($targetDb, 'daybookpart', $targetSlnos);

        $copied = $this->upsertRowsToDatabase($targetDb, 'smithm', $headerRows, ['slno']);

        if ($detailRows !== []) {
            $copied += $this->upsertRowsToDatabase($targetDb, 'smithd', $detailRows, [['slno', 'itemslno'], ['slno', 'sl'], ['slno', 'code'], ['slno']]);
        }

        if ($daybookRows !== []) {
            $copied += $this->upsertRowsToDatabase($targetDb, 'daybook', $daybookRows, [['slno', 'accode', 'opaccode'], ['slno', 'accode'], ['slno']]);
        }

        if ($daybookPartRows !== []) {
            $copied += $this->upsertRowsToDatabase($targetDb, 'daybookpart', $daybookPartRows, [['slno', 'particular', 'ttime'], ['slno', 'particular'], ['slno']]);
        }

        $this->syncTargetSerialNo($targetDb, $targetSlnos);

        Log::info('data-transfer.smith-like.done', [
            'variant' => $variant,
            'source_database' => $sourceDb,
            'target_database' => $targetDb,
            'copied' => $copied,
            'target_smithm_count' => $this->countRowsBySlnos($targetDb, 'smithm', $targetSlnos),
            'target_smithd_count' => $this->countRowsBySlnos($targetDb, 'smithd', $targetSlnos),
            'target_daybook_count' => $this->countRowsBySlnos($targetDb, 'daybook', $targetSlnos),
            'target_daybookpart_count' => $this->countRowsBySlnos($targetDb, 'daybookpart', $targetSlnos),
        ]);

        return $copied;
    }

    private function buildTargetSlnoMapForSmithLike(string $targetDb, array $headerRows): array
    {
        $nextSlno = $this->nextTargetTransferSlno($targetDb);
        $reserved = [];
        $map = [];

        foreach ($headerRows as $row) {
            $sourceSlno = (int) ($row['slno'] ?? 0);
            if ($sourceSlno <= 0) {
                continue;
            }

            $docNo = trim((string) ($row['docno'] ?? ''));
            $targetSlno = 0;

            if ($docNo !== '') {
                $targetSlno = $this->findTargetSlnoByDocNo($targetDb, 'smithm', $docNo);
            }

            if ($targetSlno <= 0) {
                $conflict = $this->targetSlnoExistsInAny($targetDb, $sourceSlno, ['smithm', 'daybook', 'daybookpart']);
                if (!$conflict && !in_array($sourceSlno, $reserved, true)) {
                    $targetSlno = $sourceSlno;
                } else {
                    while (in_array($nextSlno, $reserved, true) || $this->targetSlnoExistsInAny($targetDb, $nextSlno, ['smithm', 'daybook', 'daybookpart'])) {
                        $nextSlno++;
                    }
                    $targetSlno = $nextSlno;
                    $nextSlno++;
                }
            }

            $reserved[] = $targetSlno;
            $map[$sourceSlno] = $targetSlno;
        }

        return $map;
    }

    private function remapRowsBySlno(array $rows, array $slnoMap): array
    {
        if ($rows === [] || $slnoMap === []) {
            return $rows;
        }

        return collect($rows)
            ->map(function (array $row) use ($slnoMap) {
                $sourceSlno = (int) ($row['slno'] ?? 0);
                if ($sourceSlno > 0 && isset($slnoMap[$sourceSlno])) {
                    $row['slno'] = $slnoMap[$sourceSlno];
                }
                return $row;
            })
            ->values()
            ->all();
    }

    private function findTargetSlnoByDocNo(string $database, string $table, string $docNo): int
    {
        if ($docNo === '' || !$this->sourceTableExists($database, $table) || !$this->sourceColumnExists($database, $table, 'docno')) {
            return 0;
        }

        return (int) (DB::table(DB::raw($this->qualifiedTable($database, $table)))
            ->whereRaw('TRIM(docno) = ?', [$docNo])
            ->value('slno') ?? 0);
    }

    private function targetSlnoExistsInAny(string $database, int $slno, array $tables): bool
    {
        if ($slno <= 0) {
            return false;
        }

        foreach ($tables as $table) {
            if (!$this->sourceTableExists($database, $table) || !$this->sourceColumnExists($database, $table, 'slno')) {
                continue;
            }

            $exists = DB::table(DB::raw($this->qualifiedTable($database, $table)))
                ->where('slno', $slno)
                ->exists();

            if ($exists) {
                return true;
            }
        }

        return false;
    }

    private function nextTargetTransferSlno(string $database): int
    {
        $max = 0;

        if ($this->sourceTableExists($database, 'generali')) {
            $max = max($max, (int) (DB::table(DB::raw($this->qualifiedTable($database, 'generali')))
                ->whereRaw('TRIM(code) = ?', ['SERIALNO'])
                ->value('cvalue') ?? 0));
        }

        foreach (['daybook', 'smithm', 'salesm', 'salesrm', 'purchasem', 'purchaserm', 'orderm', 'refinerym', 'repairm'] as $table) {
            if ($this->sourceTableExists($database, $table) && $this->sourceColumnExists($database, $table, 'slno')) {
                $max = max($max, (int) (DB::table(DB::raw($this->qualifiedTable($database, $table)))->max('slno') ?? 0));
            }
        }

        return $max + 1;
    }

    private function syncTargetSerialNo(string $database, array $slnos): void
    {
        $maxSlno = collect($slnos)->map(fn ($value) => (int) $value)->max();
        if (!$maxSlno || !$this->sourceTableExists($database, 'generali')) {
            return;
        }

        $table = DB::raw($this->qualifiedTable($database, 'generali'));
        $exists = DB::table($table)->whereRaw('TRIM(code) = ?', ['SERIALNO'])->exists();

        if ($exists) {
            DB::table($table)
                ->whereRaw('TRIM(code) = ?', ['SERIALNO'])
                ->update(['cvalue' => DB::raw('GREATEST(COALESCE(cvalue,0), ' . (int) $maxSlno . ')')]);
            return;
        }

        DB::table($table)->insert(['code' => 'SERIALNO', 'cvalue' => (int) $maxSlno]);
    }

    private function countRowsBySlnos(string $database, string $table, array $slnos): int
    {
        if ($slnos === [] || !$this->sourceTableExists($database, $table) || !$this->sourceColumnExists($database, $table, 'slno')) {
            return 0;
        }

        return (int) DB::table(DB::raw($this->qualifiedTable($database, $table)))
            ->whereIn('slno', $slnos)
            ->count();
    }

    private function transferSalesBundle(string $sourceDb, string $targetDb, string $dateFrom, string $dateTo): int
    {
        if (!$this->hasTable('salesm') || !$this->sourceTableExists($sourceDb, 'salesm') || !$this->sourceTableExists($targetDb, 'salesm')) {
            return 0;
        }

        $headerRows = $this->fetchSourceTableRowsByDate($sourceDb, 'salesm', $dateFrom, $dateTo);
        if ($headerRows === []) {
            return 0;
        }

        $slnos = collect($headerRows)
            ->pluck('slno')
            ->filter()
            ->map(fn ($value) => (int) $value)
            ->values()
            ->all();

        if ($slnos === []) {
            return 0;
        }

        $barcodeCodes = collect($this->fetchSourceRowsBySlno($sourceDb, 'salesd', $slnos))
            ->pluck('bcode')
            ->merge(collect($this->fetchSourceRowsBySlno($sourceDb, 'salesrd', $slnos))->pluck('bcode'))
            ->filter(fn ($value) => trim((string) $value) !== '')
            ->map(fn ($value) => trim((string) $value))
            ->unique()
            ->values()
            ->all();

        $this->cleanupRowsBySlnoInDatabase($targetDb, 'daybookpart', $slnos);
        $this->cleanupRowsBySlnoInDatabase($targetDb, 'daybookratewgt', $slnos);
        $this->cleanupRowsBySlnoInDatabase($targetDb, 'spdmddet', $slnos);
        $this->cleanupRowsBySlnoInDatabase($targetDb, 'purchased_dmddet', $slnos);
        $this->cleanupRowsBySlnoInDatabase($targetDb, 'salesd', $slnos);
        $this->cleanupRowsBySlnoInDatabase($targetDb, 'purchased', $slnos);
        $this->cleanupRowsBySlnoInDatabase($targetDb, 'salesrd', $slnos);
        $this->cleanupRowsBySlnoInDatabase($targetDb, 'oglist', $slnos);
        $this->cleanupRowsBySlnoInDatabase($targetDb, 'kuricolln', $slnos);
        $this->cleanupRowsBySlnoInDatabase($targetDb, 'pdclist', $slnos);
        $this->cleanupRowsBySlnoInDatabase($targetDb, 'stkandprofit', $slnos);
        $this->cleanupRowsBySlnoInDatabase($targetDb, 'daybook', $slnos);
        $this->cleanupRowsBySlnoInDatabase($targetDb, 'purchasem', $slnos);
        $this->cleanupRowsBySlnoInDatabase($targetDb, 'salesrm', $slnos);
        $this->cleanupRowsBySlnoInDatabase($targetDb, 'salesm', $slnos);

        if ($barcodeCodes !== []) {
            $this->cleanupRowsByCodesInDatabase($targetDb, 'barcode', ['bcode', 'barcode', 'barcodeno'], $barcodeCodes);
        }

        $copied = $this->upsertRowsToDatabase($targetDb, 'salesm', $headerRows, ['slno']);
        $copied += $this->transferRowsBySlno($sourceDb, $targetDb, 'salesd', $slnos, [['slno', 'itemslno'], ['slno', 'sl'], ['slno', 'code'], ['slno']]);
        $copied += $this->transferRowsBySlno($sourceDb, $targetDb, 'purchasem', $slnos, ['slno']);
        $copied += $this->transferRowsBySlno($sourceDb, $targetDb, 'purchased', $slnos, [['slno', 'itemslno'], ['slno', 'sl'], ['slno', 'code'], ['slno']]);
        $copied += $this->transferRowsBySlno($sourceDb, $targetDb, 'purchased_dmddet', $slnos, [['slno', 'sno'], ['slno']]);
        $copied += $this->transferRowsBySlno($sourceDb, $targetDb, 'salesrm', $slnos, ['slno']);
        $copied += $this->transferRowsBySlno($sourceDb, $targetDb, 'salesrd', $slnos, [['slno', 'itemslno'], ['slno', 'sl'], ['slno', 'code'], ['slno']]);
        $copied += $this->transferRowsBySlno($sourceDb, $targetDb, 'daybook', $slnos, ['slno']);
        $copied += $this->transferRowsBySlno($sourceDb, $targetDb, 'daybookpart', $slnos, [['slno', 'accode', 'vchno'], ['slno', 'accode'], ['slno']]);
        $copied += $this->transferRowsBySlno($sourceDb, $targetDb, 'daybookratewgt', $slnos, [['slno', 'accode'], ['slno', 'docno'], ['slno']]);
        $copied += $this->transferRowsBySlno($sourceDb, $targetDb, 'spdmddet', $slnos, [['slno', 'sno'], ['slno']]);
        $copied += $this->transferRowsBySlno($sourceDb, $targetDb, 'oglist', $slnos, [['slno', 'docno'], ['slno', 'accode'], ['slno']]);
        $copied += $this->transferRowsBySlno($sourceDb, $targetDb, 'kuricolln', $slnos, [['slno', 'docno'], ['slno']]);
        $copied += $this->transferRowsBySlno($sourceDb, $targetDb, 'pdclist', $slnos, [['slno', 'docno'], ['slno']]);
        $copied += $this->transferRowsBySlno($sourceDb, $targetDb, 'stkandprofit', $slnos, [['slno', 'docno'], ['slno', 'bcode'], ['slno']]);

        if ($barcodeCodes !== []) {
            $copied += $this->transferRowsByCodes(
                $sourceDb,
                $targetDb,
                'barcode',
                [['bcode'], ['barcode'], ['barcodeno']],
                ['bcode', 'barcode', 'barcodeno'],
                $barcodeCodes
            );
        }

        return $copied;
    }

    private function transferDaybookVoucherRows(string $sourceDb, string $targetDb, string $prefix, string $dateFrom, string $dateTo): int
    {
        if (!$this->hasTable('daybook') || !$this->hasTable('daybookpart') || !$this->sourceTableExists($targetDb, 'daybook') || !$this->sourceTableExists($targetDb, 'daybookpart')) {
            return 0;
        }

        $slnos = $this->fetchSourceDaybookVoucherSlnos($sourceDb, $prefix, $dateFrom, $dateTo);
        if ($slnos === []) {
            return 0;
        }

        $copied = 0;
        $copied += $this->upsertRowsToDatabase($targetDb, 'daybook', $this->fetchSourceRowsBySlno($sourceDb, 'daybook', $slnos), ['slno']);
        $copied += $this->upsertRowsToDatabase($targetDb, 'daybookpart', $this->fetchSourceRowsBySlno($sourceDb, 'daybookpart', $slnos), ['slno', 'accode', 'vchno']);
        return $copied;
    }

    private function cleanupRowsBySlnoInDatabase(string $database, string $table, array $slnos): void
    {
        if ($slnos === [] || !$this->sourceTableExists($database, $table) || !$this->sourceColumnExists($database, $table, 'slno')) {
            return;
        }

        DB::table(DB::raw($this->qualifiedTable($database, $table)))->whereIn('slno', $slnos)->delete();
    }

    private function cleanupRowsByCodesInDatabase(string $database, string $table, array $columns, array $codes): void
    {
        if ($codes === [] || !$this->sourceTableExists($database, $table)) {
            return;
        }

        $validColumns = collect($columns)
            ->filter(fn ($column) => $this->sourceColumnExists($database, $table, $column))
            ->values()
            ->all();

        if ($validColumns === []) {
            return;
        }

        DB::table(DB::raw($this->qualifiedTable($database, $table)))
            ->where(function ($query) use ($validColumns, $codes) {
                foreach ($validColumns as $index => $column) {
                    if ($index === 0) {
                        $query->whereIn($column, $codes);
                    } else {
                        $query->orWhereIn($column, $codes);
                    }
                }
            })
            ->delete();
    }

    private function transferRowsBySlno(string $sourceDb, string $targetDb, string $table, array $slnos, array $candidateKeys): int
    {
        if ($slnos === [] || !$this->hasTable($table) || !$this->sourceTableExists($sourceDb, $table) || !$this->sourceTableExists($targetDb, $table)) {
            return 0;
        }

        return $this->upsertRowsToDatabase($targetDb, $table, $this->fetchSourceRowsBySlno($sourceDb, $table, $slnos), $candidateKeys);
    }

    private function transferRowsByCodes(string $sourceDb, string $targetDb, string $table, array $candidateKeys, array $sourceColumns, array $codes): int
    {
        if ($codes === [] || !$this->hasTable($table) || !$this->sourceTableExists($sourceDb, $table) || !$this->sourceTableExists($targetDb, $table)) {
            return 0;
        }

        $rows = [];
        foreach ($sourceColumns as $column) {
            if (!$this->sourceColumnExists($sourceDb, $table, $column)) {
                continue;
            }

            $rows = array_merge($rows, $this->fetchSourceRowsByCodes($sourceDb, $table, $column, $codes));
        }

        if ($rows === []) {
            return 0;
        }

        $uniqueRows = collect($rows)
            ->unique(function (array $row) {
                foreach (['bcode', 'barcode', 'barcodeno', 'slno'] as $key) {
                    if (isset($row[$key]) && trim((string) $row[$key]) !== '') {
                        return $key . ':' . trim((string) $row[$key]);
                    }
                }

                return md5(json_encode($row));
            })
            ->values()
            ->all();

        return $this->upsertRowsToDatabase($targetDb, $table, $uniqueRows, $candidateKeys);
    }

    private function transferBarcode(string $sourceDb, string $targetDb, string $dateFrom, string $dateTo): int
    {
        if (!$this->hasTable('barcode') || !$this->sourceTableExists($sourceDb, 'barcode') || !$this->sourceTableExists($targetDb, 'barcode')) {
            return 0;
        }

        $rows = $this->fetchSourceFlexibleDateRows($sourceDb, 'barcode', $dateFrom, $dateTo);
        return $this->upsertRowsToDatabase($targetDb, 'barcode', $rows, [['barcode'], ['barcodeno'], ['slno'], ['docno']]);
    }

    private function transferWholeTableByKey(string $sourceDb, string $targetDb, string $table, array $keys): int
    {
        if (!$this->hasTable($table) || !$this->sourceTableExists($sourceDb, $table) || !$this->sourceTableExists($targetDb, $table)) {
            return 0;
        }

        return $this->upsertRowsToDatabase($targetDb, $table, $this->fetchSourceAllRows($sourceDb, $table), $keys);
    }

    private function transferClientsByType(string $sourceDb, string $targetDb, array $types, bool $withGoldsmith = false): int
    {
        if (!$this->hasTable('clients') || !$this->sourceTableExists($sourceDb, 'clients') || !$this->sourceTableExists($targetDb, 'clients')) {
            return 0;
        }

        $rows = $this->fetchSourceClientRows($sourceDb, $types);
        $copied = $this->upsertRowsToDatabase($targetDb, 'clients', $rows, ['code']);

        if ($this->hasTable('accountm') && $this->sourceTableExists($sourceDb, 'accountm') && $this->sourceTableExists($targetDb, 'accountm')) {
            $codes = collect($rows)->pluck('code')->filter()->map(fn ($v) => trim((string) $v))->all();
            if ($codes !== []) {
                $copied += $this->upsertRowsToDatabase($targetDb, 'accountm', $this->fetchSourceRowsByCodes($sourceDb, 'accountm', 'accode', $codes), ['accode']);
            }
        }

        if ($withGoldsmith && $this->hasTable('clientsgs') && $this->sourceTableExists($sourceDb, 'clientsgs') && $this->sourceTableExists($targetDb, 'clientsgs')) {
            $codes = collect($rows)->pluck('code')->filter()->map(fn ($v) => trim((string) $v))->all();
            if ($codes !== []) {
                $copied += $this->upsertRowsToDatabase($targetDb, 'clientsgs', $this->fetchSourceRowsByCodes($sourceDb, 'clientsgs', 'code', $codes), ['code']);
            }
        }

        return $copied;
    }

    private function upsertRowsToDatabase(string $database, string $table, array $rows, array $candidateKeys): int
    {
        if ($rows === [] || !$this->sourceTableExists($database, $table)) {
            return 0;
        }

        $targetColumns = collect($this->databaseColumnListing($database, $table))
            ->map(fn ($column) => strtolower((string) $column))
            ->values()
            ->all();

        $keyGroups = collect($candidateKeys)
            ->map(function ($group) {
                $items = is_array($group) ? $group : [$group];
                return collect($items)
                    ->map(fn ($key) => strtolower((string) $key))
                    ->filter()
                    ->values()
                    ->all();
            })
            ->filter(fn ($group) => $group !== [])
            ->all();

        if ($keyGroups === []) {
            return 0;
        }

        // Opening-balance columns must never be overwritten when a master row already
        // exists in the target DB. Transferring sales/purchase would otherwise carry
        // the source's opening values and wipe out what the user entered in the target.
        $openingColumns = [
            'opbal', 'opbalb',
            'opbalance', 'opbalanceb',
            'opqty', 'opqtyb',
            'opweight', 'opweightb',
            'opstonewgt', 'opstonewgtb',
            'opstoneamt', 'opstoneamtb',
            'opdmdwgt',
            'opstock',
        ];

        $copied = 0;
        foreach ($rows as $row) {
            $filtered = [];
            foreach ($row as $column => $value) {
                $column = strtolower((string) $column);
                if (in_array($column, $targetColumns, true)) {
                    $filtered[$column] = $value;
                }
            }

            if ($filtered === []) {
                continue;
            }

            $where = [];
            foreach ($keyGroups as $group) {
                $candidate = [];
                foreach ($group as $key) {
                    if (!in_array($key, $targetColumns, true) || !array_key_exists($key, $filtered)) {
                        $candidate = [];
                        break;
                    }
                    $candidate[$key] = $filtered[$key];
                }
                if ($candidate !== []) {
                    $where = $candidate;
                    break;
                }
            }

            if ($where === []) {
                continue;
            }

            $qualified = $this->qualifiedTable($database, $table);
            $existing = DB::table(DB::raw($qualified))->where($where)->first();

            if ($existing !== null) {
                $updatePayload = $filtered;
                foreach ($openingColumns as $col) {
                    unset($updatePayload[$col]);
                }
                foreach (array_keys($where) as $keyCol) {
                    unset($updatePayload[$keyCol]);
                }
                if ($updatePayload !== []) {
                    DB::table(DB::raw($qualified))->where($where)->update($updatePayload);
                }
            } else {
                DB::table(DB::raw($qualified))->insert($filtered);
            }
            $copied++;
        }

        return $copied;
    }

    private function fetchSourceAllRows(string $sourceDb, string $table): array
    {
        if (!$this->sourceTableExists($sourceDb, $table)) {
            return [];
        }

        return $this->normalizeRows(
            DB::table(DB::raw($this->qualifiedTable($sourceDb, $table)))->get()->all()
        );
    }

    private function fetchSourceTableRowsByDate(string $sourceDb, string $table, string $dateFrom, string $dateTo): array
    {
        if (!$this->sourceTableExists($sourceDb, $table)) {
            return [];
        }

        $query = DB::table(DB::raw($this->qualifiedTable($sourceDb, $table)));
        $dateColumn = $this->sourceDateColumn($sourceDb, $table);
        if ($dateColumn !== null) {
            $query->whereBetween($dateColumn, [$dateFrom, $dateTo]);
        }

        return $this->normalizeRows($query->get()->all());
    }

    private function fetchSourceFlexibleDateRows(string $sourceDb, string $table, string $dateFrom, string $dateTo): array
    {
        return $this->fetchSourceTableRowsByDate($sourceDb, $table, $dateFrom, $dateTo);
    }

    private function fetchSourceRowsBySlno(string $sourceDb, string $table, array $slnos): array
    {
        if ($slnos === [] || !$this->sourceTableExists($sourceDb, $table)) {
            return [];
        }

        return $this->normalizeRows(
            DB::table(DB::raw($this->qualifiedTable($sourceDb, $table)))
                ->whereIn('slno', $slnos)
                ->get()
                ->all()
        );
    }

    private function fetchSourceRowsByCodes(string $sourceDb, string $table, string $column, array $codes): array
    {
        if ($codes === [] || !$this->sourceTableExists($sourceDb, $table)) {
            return [];
        }

        return $this->normalizeRows(
            DB::table(DB::raw($this->qualifiedTable($sourceDb, $table)))
                ->whereIn($column, $codes)
                ->get()
                ->all()
        );
    }

    private function fetchSourceClientRows(string $sourceDb, array $types): array
    {
        if (!$this->sourceTableExists($sourceDb, 'clients')) {
            return [];
        }

        return $this->normalizeRows(
            DB::table(DB::raw($this->qualifiedTable($sourceDb, 'clients')))
                ->whereIn('ctype', $types)
                ->get()
                ->all()
        );
    }

    private function fetchSourceDaybookVoucherSlnos(string $sourceDb, string $prefix, string $dateFrom, string $dateTo): array
    {
        if (
            !$this->sourceTableExists($sourceDb, 'daybook')
            || !$this->sourceTableExists($sourceDb, 'daybookpart')
        ) {
            return [];
        }

        return DB::table(DB::raw($this->qualifiedTable($sourceDb, 'daybook') . ' as daybook'))
            ->join(DB::raw($this->qualifiedTable($sourceDb, 'daybookpart') . ' as daybookpart'), 'daybookpart.slno', '=', 'daybook.slno')
            ->whereBetween('daybook.tdate', [$dateFrom, $dateTo])
            ->whereRaw("LEFT(TRIM(daybookpart.vchno), 2) = ?", [$prefix])
            ->distinct()
            ->pluck('daybook.slno')
            ->map(fn ($value) => (int) $value)
            ->values()
            ->all();
    }

    private function countSourceRows(string $sourceDb, string $table): int
    {
        if (!$this->sourceTableExists($sourceDb, $table)) {
            return 0;
        }

        return (int) DB::table(DB::raw($this->qualifiedTable($sourceDb, $table)))->count();
    }

    private function countSourceDateRows(string $sourceDb, string $table, string $dateFrom, string $dateTo): int
    {
        if (!$this->sourceTableExists($sourceDb, $table)) {
            return 0;
        }

        $query = DB::table(DB::raw($this->qualifiedTable($sourceDb, $table)));
        $dateColumn = $this->sourceDateColumn($sourceDb, $table);
        if ($dateColumn !== null) {
            $query->whereBetween($dateColumn, [$dateFrom, $dateTo]);
        }

        return (int) $query->count();
    }

    private function countSourceDateRowsFlexible(string $sourceDb, string $table, string $dateFrom, string $dateTo): int
    {
        return $this->countSourceDateRows($sourceDb, $table, $dateFrom, $dateTo);
    }

    private function countSourceClientsByType(string $sourceDb, string $type): int
    {
        return $this->countSourceClientsByTypes($sourceDb, [$type]);
    }

    private function countSourceClientsByTypes(string $sourceDb, array $types): int
    {
        if (!$this->sourceTableExists($sourceDb, 'clients')) {
            return 0;
        }

        return (int) DB::table(DB::raw($this->qualifiedTable($sourceDb, 'clients')))
            ->whereIn('ctype', $types)
            ->count();
    }

    private function countSourceMasterGroup(string $sourceDb, array $tables): int
    {
        $count = 0;
        foreach ($tables as $table) {
            $count += $this->countSourceRows($sourceDb, $table);
        }

        return $count;
    }

    private function countSourceDaybookVoucherRows(string $sourceDb, string $prefix, string $dateFrom, string $dateTo): int
    {
        return count($this->fetchSourceDaybookVoucherSlnos($sourceDb, $prefix, $dateFrom, $dateTo));
    }

    private function normalizeRows(array $rows): array
    {
        return collect($rows)
            ->map(function ($row) {
                $array = (array) $row;
                $normalized = [];
                foreach ($array as $key => $value) {
                    $normalized[strtolower((string) $key)] = $value;
                }
                return $normalized;
            })
            ->values()
            ->all();
    }

    private function databaseColumnListing(string $database, string $table): array
    {
        if (!$this->sourceTableExists($database, $table)) {
            return [];
        }

        return collect(DB::select(
            'SELECT column_name FROM information_schema.columns WHERE table_schema = ? AND table_name = ? ORDER BY ordinal_position',
            [$database, $table]
        ))
            ->map(function ($row) {
                $normalized = [];
                foreach ((array) $row as $key => $value) {
                    $normalized[strtolower((string) $key)] = $value;
                }
                return strtolower((string) ($normalized['column_name'] ?? ''));
            })
            ->filter()
            ->values()
            ->all();
    }

    private function sourceTableExists(string $sourceDb, string $table): bool
    {
        if (!$this->isSafeDatabaseName($sourceDb) || !$this->isSafeTableName($table)) {
            return false;
        }

        return count(DB::select(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = ? LIMIT 1',
            [$sourceDb, $table]
        )) > 0;
    }

    private function sourceDateColumn(string $sourceDb, string $table): ?string
    {
        foreach (['tdate', 'date', 'edate', 'created_at'] as $column) {
            if ($this->sourceColumnExists($sourceDb, $table, $column)) {
                return $column;
            }
        }

        return null;
    }

    private function sourceColumnExists(string $sourceDb, string $table, string $column): bool
    {
        if (
            !$this->isSafeDatabaseName($sourceDb)
            || !$this->isSafeTableName($table)
            || !$this->isSafeTableName($column)
        ) {
            return false;
        }

        return count(DB::select(
            'SELECT 1 FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ? LIMIT 1',
            [$sourceDb, $table, $column]
        )) > 0;
    }

    private function qualifiedTable(string $database, string $table): string
    {
        return sprintf('`%s`.`%s`', $database, $table);
    }

    private function isSafeDatabaseName(string $name): bool
    {
        return $name !== '' && preg_match('/^[A-Za-z0-9_]+$/', $name) === 1;
    }

    private function isSafeTableName(string $name): bool
    {
        return $name !== '' && preg_match('/^[A-Za-z0-9_]+$/', $name) === 1;
    }

    private function isValidYmdDate(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
    }

    private function sqlUpdateAiRecommendations(): array
    {
        return [
            [
                'issue' => 'Update logs are too large or old maintenance history is not needed',
                'recommended_action' => 'delete_updation_logs',
                'why' => 'Clears only the update log table and frees the log history area.',
            ],
            [
                'issue' => 'Mobile numbers are blank but telephone numbers are filled',
                'recommended_action' => 'clients_phone_to_mobile',
                'why' => 'Copies telephone to mobile only when mobile is empty.',
            ],
            [
                'issue' => 'Barcode items show wrong in-stock status after imports or fixes',
                'recommended_action' => 'barcode_stock_refresh',
                'why' => 'Rebuilds barcode stock flag from sales usage.',
            ],
            [
                'issue' => 'Old barcodes have null or invalid dates',
                'recommended_action' => 'barcode_date_issue',
                'why' => 'Sets missing barcode dates to the configured start date.',
            ],
            [
                'issue' => 'Orders are completed but still look pending',
                'recommended_action' => 'order_pending_check',
                'why' => 'Updates order status when a sales bill already exists.',
            ],
            [
                'issue' => 'Sales bill is visible in the bill screen but missing in A/C Ledger or customer ledger',
                'recommended_action' => 'repair_missing_sales_ledger',
                'why' => 'Recreates the missing customer and RS `daybook` posting, plus the `daybookpart` description row, for old sales bills that were saved without ledger entries.',
            ],
            [
                'issue' => 'Cancelled order is gone from order entry, but its old gold or linked rows still appear in stock ledger or reports',
                'recommended_action' => 'cleanup_cancelled_order_leftovers',
                'why' => 'Removes leftover linked purchase, sales-return, and daybook rows for a specific cancelled order number without touching unrelated bills.',
            ],
            [
                'issue' => 'Some old order-linked rows look missing after edit or update',
                'recommended_action' => 'check_order_missing_data',
                'why' => 'Runs a safe integrity check and reports missing linked order rows without changing any data.',
            ],
            [
                'issue' => 'Sales or purchase bills are not showing full data in related reports or linked screens',
                'recommended_action' => 'check_sales_purchase_missing_data',
                'why' => 'Checks whether sales and purchase headers are missing details, daybook rows, or linked exchange/return rows.',
            ],
            [
                'issue' => 'Receipt or payment vouchers are not showing in the entry list',
                'recommended_action' => 'check_receipt_payment_missing_data',
                'why' => 'Checks whether voucher rows exist in `daybookpart` but linked `daybook` rows are missing or incomplete, which prevents them from appearing in the screen list.',
            ],
            [
                'issue' => 'Scheme collection weight values do not match collection amount and rate',
                'recommended_action' => 'scheme_collection_weight',
                'why' => 'Recomputes scheme weights using the same rule as the legacy utility.',
            ],
            [
                'issue' => 'Bill prefix was changed and old counter keys are accumulating in settings',
                'recommended_action' => 'cleanup_stale_prefix_counters',
                'why' => 'Removes orphaned SALES/SRET/PURCH/PRET keys from `generali` that no longer match any current prefix.',
            ],
            [
                'issue' => 'Saving bill prefix fails with data too long or truncation error',
                'recommended_action' => 'widen_salestype_prefix_cols',
                'why' => 'Extends prefix columns to VARCHAR(20) so prefixes like go/26-27/ or b2b/go/26-27/ save without error.',
            ],
            [
                'issue' => 'Sales, purchase, or return bill saving fails because bill number is too long',
                'recommended_action' => 'widen_billno_columns',
                'why' => 'Extends legacy `billno` columns in sales and purchase master tables to VARCHAR(20) so longer bill numbers fit.',
            ],
            [
                'issue' => 'Sales save fails only when exchange is given and the error says `docno` is too long',
                'recommended_action' => 'widen_legacy_docno_columns',
                'why' => 'Extends legacy exchange-sync `docno` and `billno` columns like `purchasem.docno`, daybook, PDC, and related tables used only during exchange and return sync.',
            ],
            [
                'issue' => 'Daybook has duplicate or out-of-order serial numbers',
                'recommended_action' => 'reset_daybook_slno',
                'why' => 'Reassigns `slno` sequentially by date to eliminate duplicates.',
            ],
            [
                'issue' => 'Bills or orders appear more than once with the same number',
                'recommended_action' => 'check_duplicate_bills',
                'why' => 'Scans legacy sales, purchase, return, and order tables and reports duplicate bill/order number groups.',
            ],
        ];
    }

    private function sqlDeleteUpdationLogs(): array
    {
        if (!$this->hasTable('delpart')) {
            return ['message' => '`delpart` table is not available.', 'summary' => ['deleted' => 0]];
        }

        $deleted = DB::table('delpart')->delete();
        return ['message' => 'Updation logs deleted.', 'summary' => ['deleted' => $deleted]];
    }

    private function sqlDeleteUnusedCustomers(): array
    {
        if (!$this->hasTable('clients') || !$this->hasTable('accountm')) {
            return ['message' => 'Required customer tables are not available.', 'summary' => ['deleted' => 0]];
        }

        $rows = DB::table('clients')
            ->select(['code', 'opbalance', 'opbalanceb'])
            ->get();

        $deleted = 0;
        foreach ($rows as $row) {
            $code = trim((string) $row->code);
            if ($code === '') {
                continue;
            }

            $balance = (float) ($row->opbalance ?? 0);
            if (abs($balance) < 0.0001) {
                $balance = (float) ($row->opbalanceb ?? 0);
            }

            $ledgerCount = $this->hasTable('daybook')
                ? (int) (DB::table('daybook')->where('accode', $code)->count() ?? 0)
                : 0;

            $ledgerSum = $this->hasTable('daybook')
                ? (float) (DB::table('daybook')->where('accode', $code)->sum('amount') ?? 0)
                : 0;

            if (abs($balance + $ledgerSum) < 0.0001 && $ledgerCount === 0) {
                $deleted += DB::table('clients')->where('code', $code)->delete();
                DB::table('accountm')->where('accode', $code)->delete();
            }
        }

        return ['message' => 'Unused customers cleanup completed.', 'summary' => ['deleted' => $deleted]];
    }

    private function sqlUpdateBarcodeDateIssue(): array
    {
        if (!$this->hasTable('barcode') || !Schema::hasColumn('barcode', 'tdate')) {
            return ['message' => '`barcode.tdate` is not available.', 'summary' => ['updated' => 0]];
        }

        $date = $this->generalValue('generals', 'STARTDT', now()->format('Y-m-d'));
        $date = $this->normalizeDateInput($date) ?? now()->format('Y-m-d');

        $updated = DB::table('barcode')
            ->where(function ($q) {
                $q->whereNull('tdate')->orWhereRaw('YEAR(tdate) < 2000');
            })
            ->update(['tdate' => $date]);

        return ['message' => 'Barcode date issue updated.', 'summary' => ['updated' => $updated]];
    }

    private function sqlUpdateBarcodeNoDisc(): array
    {
        if (!$this->hasTable('barcode') || !Schema::hasColumn('barcode', 'nodisc')) {
            return ['message' => '`barcode.nodisc` is not available.', 'summary' => ['updated' => 0]];
        }

        $updated = DB::table('barcode')->update(['nodisc' => 'Y']);
        return ['message' => 'Barcode no-discount flag updated.', 'summary' => ['updated' => $updated]];
    }

    private function sqlUpdateBarcodeStock(): array
    {
        if (!$this->hasTable('barcode') || !Schema::hasColumn('barcode', 'stk')) {
            return ['message' => '`barcode.stk` is not available.', 'summary' => ['updated' => 0]];
        }

        DB::table('barcode')->update(['stk' => 'Y']);

        $soldBcodes = [];
        if ($this->hasTable('salesd') && Schema::hasColumn('salesd', 'bcode')) {
            $soldBcodes = DB::table('salesd')->distinct()->pluck('bcode')->filter()->all();
        }

        $updated = 0;
        if (!empty($soldBcodes)) {
            $updated = DB::table('barcode')->whereIn('bcode', $soldBcodes)->update(['stk' => 'N']);
        }

        return ['message' => 'Barcode stock refreshed.', 'summary' => ['sold_marked' => $updated]];
    }

    private function sqlUpdateClientsPhoneToMobile(): array
    {
        if (!$this->hasTable('clients') || !Schema::hasColumn('clients', 'mobile') || !Schema::hasColumn('clients', 'telephone')) {
            return ['message' => '`clients.mobile` or `clients.telephone` is not available.', 'summary' => ['updated' => 0]];
        }

        $updated = DB::table('clients')
            ->where(function ($q) {
                $q->whereNull('mobile')->orWhere('mobile', '');
            })
            ->update(['mobile' => DB::raw('telephone')]);

        return ['message' => 'Client phone copied to mobile where needed.', 'summary' => ['updated' => $updated]];
    }

    private function sqlUpdateSalesCashAccount(): array
    {
        if (!$this->hasTable('salesm') || !Schema::hasColumn('salesm', 'cbcode')) {
            return ['message' => '`salesm.cbcode` is not available.', 'summary' => ['updated' => 0]];
        }

        $updated = DB::table('salesm')
            ->where(function ($q) {
                $q->whereNull('cbcode')->orWhere('cbcode', '');
            })
            ->update(['cbcode' => 'CASH']);

        return ['message' => 'Sales cash account updated.', 'summary' => ['updated' => $updated]];
    }

    private function sqlRepairMissingSalesLedger(): array
    {
        if (!$this->hasTable('salesm') || !$this->hasTable('daybook') || !$this->hasTable('daybookpart')) {
            return ['message' => 'Required legacy sales/daybook tables are not available.', 'summary' => ['repaired_bills' => 0]];
        }

        $salesRows = DB::table('salesm as s')
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
            ->whereNull('d.slno')
            ->whereRaw('TRIM(COALESCE(s.custcode, "")) <> ""')
            ->whereRaw('COALESCE(s.netamt, 0) <> 0')
            ->orderBy('s.tdate')
            ->orderBy('s.slno')
            ->get();

        $repaired = 0;
        $daybookRows = 0;
        $partRows = 0;

        foreach ($salesRows as $sale) {
            $slno = (int) ($sale->slno ?? 0);
            $billNo = trim((string) ($sale->billno ?? ''));
            $tdate = (string) ($sale->tdate ?? '');
            $ttime = trim((string) ($sale->ttime ?? ''));
            $custCode = mb_substr(trim((string) ($sale->custcode ?? '')), 0, 8);
            $custName = trim((string) ($sale->custname ?? ''));
            $netAmt = round((float) ($sale->netamt ?? 0), 2);
            $control = max(1, (int) ($sale->control ?? 1));

            if ($slno <= 0 || $custCode === '' || $tdate === '' || $netAmt == 0.0) {
                continue;
            }

            DB::transaction(function () use ($slno, $billNo, $tdate, $ttime, $custCode, $custName, $netAmt, $control, &$daybookRows, &$partRows) {
                if (!DB::table('daybookpart')->where('slno', $slno)->exists()) {
                    DB::table('daybookpart')->insert([
                        'slno' => $slno,
                        'vchno' => mb_substr($billNo, 0, 10),
                        'particular' => mb_substr('By Sales (' . $billNo . ') To ' . $custName, 0, 100),
                        'ic' => '',
                        'uid' => '',
                        'ttime' => $ttime !== '' ? $ttime : null,
                        'rate' => 0,
                    ]);
                    $partRows++;
                }

                if (!DB::table('daybook')->where('slno', $slno)->where('sno', 1)->exists()) {
                    DB::table('daybook')->insert([
                        'slno' => $slno,
                        'sno' => 1,
                        'tdate' => $tdate,
                        'accode' => $custCode,
                        'amount' => -$netAmt,
                        'control' => $control,
                        'opaccode' => 'RS',
                    ]);
                    $daybookRows++;
                }

                if (!DB::table('daybook')->where('slno', $slno)->where('sno', 2)->exists()) {
                    DB::table('daybook')->insert([
                        'slno' => $slno,
                        'sno' => 2,
                        'tdate' => $tdate,
                        'accode' => 'RS',
                        'amount' => $netAmt,
                        'control' => $control,
                        'opaccode' => $custCode,
                    ]);
                    $daybookRows++;
                }
            });

            $repaired++;
        }

        return [
            'message' => $repaired > 0
                ? 'Missing sales ledger repaired.'
                : 'No missing sales ledger postings found.',
            'summary' => [
                'repaired_bills' => $repaired,
                'daybook_rows' => $daybookRows,
                'daybookpart_rows' => $partRows,
            ],
        ];
    }

    private function sqlCleanupCancelledOrderLeftovers(string $docNo): array
    {
        $docNo = trim($docNo);
        if ($docNo === '') {
            return ['message' => 'Enter an order number.', 'summary' => ['removed_rows' => 0]];
        }

        if (!$this->hasTable('orderm')) {
            return ['message' => '`orderm` table is not available.', 'summary' => ['removed_rows' => 0]];
        }

        $order = DB::table('orderm')
            ->whereRaw('TRIM(ordno)=?', [$docNo])
            ->first();

        if (!$order) {
            return ['message' => 'Order not found.', 'summary' => ['removed_rows' => 0]];
        }

        if ((int) ($order->status ?? 1) !== 0) {
            return ['message' => 'This order is not cancelled. Cancel it from Order Entry first.', 'summary' => ['removed_rows' => 0]];
        }

        $linkedSlnos = collect();

        if ($this->hasTable('purchasem')) {
            $purchaseSlnos = DB::table('purchasem')
                ->where(function ($q) use ($docNo) {
                    $q->whereRaw('TRIM(COALESCE(docno, ""))=?', [$docNo]);
                    if (Schema::hasColumn('purchasem', 'billno')) {
                        $q->orWhereRaw('TRIM(COALESCE(billno, ""))=?', [$docNo]);
                    }
                })
                ->pluck('slno');
            $linkedSlnos = $linkedSlnos->merge($purchaseSlnos);
        }

        if ($this->hasTable('salesrm')) {
            $salesReturnSlnos = DB::table('salesrm')
                ->where(function ($q) use ($docNo) {
                    $q->whereRaw('TRIM(COALESCE(billno, ""))=?', [$docNo]);
                    if (Schema::hasColumn('salesrm', 'docno')) {
                        $q->orWhereRaw('TRIM(COALESCE(docno, ""))=?', [$docNo]);
                    }
                })
                ->pluck('slno');
            $linkedSlnos = $linkedSlnos->merge($salesReturnSlnos);
        }

        $targetSlnos = $linkedSlnos
            ->filter(fn ($value) => (int) $value > 0)
            ->map(fn ($value) => (int) $value)
            ->reject(fn (int $value) => $value === (int) ($order->slno ?? 0))
            ->unique()
            ->values()
            ->all();

        if ($targetSlnos === []) {
            return ['message' => 'No leftover linked rows found for this cancelled order.', 'summary' => ['removed_rows' => 0, 'slnos' => 0]];
        }

        $deleted = 0;
        $tables = [
            'purchased', 'purchasem',
            'salesrd', 'salesrm',
            'daybook', 'daybookpart', 'daybookratewgt',
            'stkandprofit', 'oglist', 'pdclist',
        ];

        foreach ($tables as $table) {
            if (!$this->hasTable($table)) {
                continue;
            }
            $deleted += DB::table($table)->whereIn('slno', $targetSlnos)->delete();
        }

        return [
            'message' => 'Cancelled order leftovers cleaned.',
            'summary' => [
                'order_no' => $docNo,
                'slnos' => count($targetSlnos),
                'removed_rows' => $deleted,
            ],
        ];
    }

    private function sqlUpdateOrderPendingCheck(): array
    {
        if (!$this->hasTable('orderm') || !Schema::hasColumn('orderm', 'salebill') || !Schema::hasColumn('orderm', 'status')) {
            return ['message' => '`orderm` required columns are not available.', 'summary' => ['updated' => 0]];
        }

        $updated = DB::table('orderm')
            ->whereNotNull('salebill')
            ->where('salebill', '<>', '')
            ->update(['status' => 2]);

        return ['message' => 'Order pending check updated.', 'summary' => ['updated' => $updated]];
    }

    private function sqlCheckOrderMissingData(): array
    {
        if (!$this->hasTable('orderm')) {
            return ['message' => '`orderm` table is not available.', 'summary' => ['orders' => 0]];
        }

        $base = DB::table('orderm as o')
            ->whereRaw('TRIM(COALESCE(o.ordno, "")) <> ""')
            ->where(function ($q) {
                $q->whereNull('o.status')->orWhere('o.status', '<>', 0);
            });

        $summary = [
            'orders' => (clone $base)->count('o.slno'),
            'missing_orderd' => 0,
            'missing_daybookpart' => 0,
            'missing_daybook' => 0,
            'missing_exchange_master' => 0,
            'missing_exchange_detail' => 0,
            'missing_sales_return_master' => 0,
            'missing_sales_return_detail' => 0,
            'missing_gold_advance' => 0,
            'billamt_mismatch' => 0,
        ];

        if ($this->hasTable('orderd')) {
            $summary['missing_orderd'] = (clone $base)
                ->select('o.slno')
                ->leftJoin('orderd as d', 'd.slno', '=', 'o.slno')
                ->groupBy('o.slno')
                ->havingRaw('COUNT(d.slno) = 0')
                ->get()
                ->count();

            $summary['billamt_mismatch'] = (clone $base)
                ->select('o.slno', 'o.billamt')
                ->leftJoin('orderd as dsum', 'dsum.slno', '=', 'o.slno')
                ->groupBy('o.slno', 'o.billamt')
                ->havingRaw('ABS(COALESCE(SUM(dsum.amount), 0) - COALESCE(o.billamt, 0)) > 0.01')
                ->get()
                ->count();
        }

        if ($this->hasTable('daybookpart')) {
            $summary['missing_daybookpart'] = (clone $base)
                ->leftJoin('daybookpart as dp', 'dp.slno', '=', 'o.slno')
                ->whereNull('dp.slno')
                ->count('o.slno');
        }

        if ($this->hasTable('daybook')) {
            $summary['missing_daybook'] = (clone $base)
                ->select('o.slno')
                ->leftJoin('daybook as db', 'db.slno', '=', 'o.slno')
                ->groupBy('o.slno')
                ->havingRaw('COUNT(db.slno) = 0')
                ->get()
                ->count();
        }

        if ($this->hasTable('purchasem')) {
            $summary['missing_exchange_master'] = (clone $base)
                ->leftJoin('purchasem as pm', function ($join) {
                    $join->on('pm.slno', '=', 'o.slno')
                        ->whereRaw('TRIM(COALESCE(pm.pr, "")) = "E"');
                })
                ->whereRaw('COALESCE(o.eamt, 0) > 0')
                ->whereNull('pm.slno')
                ->count('o.slno');
        }

        if ($this->hasTable('purchased')) {
            $summary['missing_exchange_detail'] = (clone $base)
                ->select('o.slno')
                ->leftJoin('purchased as pd', 'pd.slno', '=', 'o.slno')
                ->whereRaw('COALESCE(o.eamt, 0) > 0')
                ->groupBy('o.slno')
                ->havingRaw('COUNT(pd.slno) = 0')
                ->get()
                ->count();
        }

        if ($this->hasTable('salesrm')) {
            $summary['missing_sales_return_master'] = (clone $base)
                ->leftJoin('salesrm as sm', 'sm.slno', '=', 'o.slno')
                ->whereRaw('COALESCE(o.sretamt, 0) > 0')
                ->whereNull('sm.slno')
                ->count('o.slno');
        }

        if ($this->hasTable('salesrd')) {
            $summary['missing_sales_return_detail'] = (clone $base)
                ->select('o.slno')
                ->leftJoin('salesrd as sd', 'sd.slno', '=', 'o.slno')
                ->whereRaw('COALESCE(o.sretamt, 0) > 0')
                ->groupBy('o.slno')
                ->havingRaw('COUNT(sd.slno) = 0')
                ->get()
                ->count();
        }

        if ($this->hasTable('orderdga')) {
            $summary['missing_gold_advance'] = (clone $base)
                ->select('o.slno')
                ->leftJoin('orderdga as ga', 'ga.slno', '=', 'o.slno')
                ->whereRaw('COALESCE(o.gadvance, 0) > 0')
                ->groupBy('o.slno')
                ->havingRaw('COUNT(ga.slno) = 0')
                ->get()
                ->count();
        }

        $issueCount = 0;
        foreach ($summary as $key => $value) {
            if ($key !== 'orders') {
                $issueCount += (int) $value;
            }
        }

        return [
            'message' => $issueCount > 0
                ? 'Order missing-data check completed. Review the summary counts.'
                : 'Order missing-data check completed. No missing linked data found.',
            'summary' => $summary,
        ];
    }

    private function sqlCheckSalesPurchaseMissingData(): array
    {
        $summary = [
            'sales' => 0,
            'sales_missing_detail' => 0,
            'sales_missing_daybookpart' => 0,
            'sales_missing_daybook' => 0,
            'sales_exchange_missing_master' => 0,
            'sales_exchange_missing_detail' => 0,
            'sales_return_missing_master' => 0,
            'sales_return_missing_detail' => 0,
            'purchase' => 0,
            'purchase_missing_detail' => 0,
            'purchase_missing_daybookpart' => 0,
            'purchase_missing_daybook' => 0,
            'purchase_return_missing_master' => 0,
            'purchase_return_missing_detail' => 0,
        ];

        if ($this->hasTable('salesm')) {
            $salesBase = DB::table('salesm as s')
                ->whereRaw("TRIM(COALESCE(s.billno, '')) <> ''")
                ->where(function ($q) {
                    $q->whereNull('s.status')->orWhere('s.status', '<>', 0);
                });

            $summary['sales'] = (clone $salesBase)->count('s.slno');

            if ($this->hasTable('salesd')) {
                $summary['sales_missing_detail'] = (clone $salesBase)
                    ->select('s.slno')
                    ->leftJoin('salesd as d', 'd.slno', '=', 's.slno')
                    ->groupBy('s.slno')
                    ->havingRaw('COUNT(d.slno) = 0')
                    ->get()
                    ->count();
            }

            if ($this->hasTable('daybookpart')) {
                $summary['sales_missing_daybookpart'] = (clone $salesBase)
                    ->leftJoin('daybookpart as dp', 'dp.slno', '=', 's.slno')
                    ->whereNull('dp.slno')
                    ->count('s.slno');
            }

            if ($this->hasTable('daybook')) {
                $summary['sales_missing_daybook'] = (clone $salesBase)
                    ->select('s.slno')
                    ->leftJoin('daybook as db', 'db.slno', '=', 's.slno')
                    ->groupBy('s.slno')
                    ->havingRaw('COUNT(db.slno) = 0')
                    ->get()
                    ->count();
            }

            if ($this->hasTable('purchasem')) {
                $summary['sales_exchange_missing_master'] = (clone $salesBase)
                    ->leftJoin('purchasem as pm', function ($join) {
                        $join->on('pm.slno', '=', 's.slno')
                            ->whereRaw("TRIM(COALESCE(pm.pr, '')) = 'E'");
                    })
                    ->whereRaw('COALESCE(s.eamt, 0) > 0')
                    ->whereNull('pm.slno')
                    ->count('s.slno');
            }

            if ($this->hasTable('purchased')) {
                $summary['sales_exchange_missing_detail'] = (clone $salesBase)
                    ->select('s.slno')
                    ->leftJoin('purchased as pd', 'pd.slno', '=', 's.slno')
                    ->whereRaw('COALESCE(s.eamt, 0) > 0')
                    ->groupBy('s.slno')
                    ->havingRaw('COUNT(pd.slno) = 0')
                    ->get()
                    ->count();
            }

            if ($this->hasTable('salesrm')) {
                $summary['sales_return_missing_master'] = (clone $salesBase)
                    ->leftJoin('salesrm as sm', 'sm.slno', '=', 's.slno')
                    ->whereRaw('COALESCE(s.sretamt, 0) > 0')
                    ->whereNull('sm.slno')
                    ->count('s.slno');
            }

            if ($this->hasTable('salesrd')) {
                $summary['sales_return_missing_detail'] = (clone $salesBase)
                    ->select('s.slno')
                    ->leftJoin('salesrd as sd', 'sd.slno', '=', 's.slno')
                    ->whereRaw('COALESCE(s.sretamt, 0) > 0')
                    ->groupBy('s.slno')
                    ->havingRaw('COUNT(sd.slno) = 0')
                    ->get()
                    ->count();
            }
        }

        if ($this->hasTable('purchasem')) {
            $purchaseBase = DB::table('purchasem as p')
                ->whereRaw("TRIM(COALESCE(p.docno, '')) <> ''")
                ->whereRaw("TRIM(COALESCE(p.pr, 'P')) = 'P'")
                ->where(function ($q) {
                    $q->whereNull('p.status')->orWhere('p.status', '<>', 0);
                });

            $summary['purchase'] = (clone $purchaseBase)->count('p.slno');

            if ($this->hasTable('purchased')) {
                $summary['purchase_missing_detail'] = (clone $purchaseBase)
                    ->select('p.slno')
                    ->leftJoin('purchased as pd', 'pd.slno', '=', 'p.slno')
                    ->groupBy('p.slno')
                    ->havingRaw('COUNT(pd.slno) = 0')
                    ->get()
                    ->count();
            }

            if ($this->hasTable('daybookpart')) {
                $summary['purchase_missing_daybookpart'] = (clone $purchaseBase)
                    ->leftJoin('daybookpart as dp', 'dp.slno', '=', 'p.slno')
                    ->whereNull('dp.slno')
                    ->count('p.slno');
            }

            if ($this->hasTable('daybook')) {
                $summary['purchase_missing_daybook'] = (clone $purchaseBase)
                    ->select('p.slno')
                    ->leftJoin('daybook as db', 'db.slno', '=', 'p.slno')
                    ->groupBy('p.slno')
                    ->havingRaw('COUNT(db.slno) = 0')
                    ->get()
                    ->count();
            }

            if ($this->hasTable('purchaserm')) {
                $summary['purchase_return_missing_master'] = (clone $purchaseBase)
                    ->leftJoin('purchaserm as prm', function ($join) {
                        $join->on('prm.slno', '=', 'p.slno')
                            ->whereRaw("TRIM(COALESCE(prm.pr, '')) = 'R'");
                    })
                    ->whereRaw('COALESCE(p.pretamt, 0) > 0')
                    ->whereNull('prm.slno')
                    ->count('p.slno');
            }

            if ($this->hasTable('purchaserd')) {
                $summary['purchase_return_missing_detail'] = (clone $purchaseBase)
                    ->select('p.slno')
                    ->leftJoin('purchaserd as prd', 'prd.slno', '=', 'p.slno')
                    ->whereRaw('COALESCE(p.pretamt, 0) > 0')
                    ->groupBy('p.slno')
                    ->havingRaw('COUNT(prd.slno) = 0')
                    ->get()
                    ->count();
            }
        }

        $issueCount = 0;
        foreach ($summary as $key => $value) {
            if (!in_array($key, ['sales', 'purchase'], true)) {
                $issueCount += (int) $value;
            }
        }

        return [
            'message' => $issueCount > 0
                ? 'Sales/purchase missing-data check completed. Review the summary counts.'
                : 'Sales/purchase missing-data check completed. No missing linked data found.',
            'summary' => $summary,
        ];
    }

    private function sqlUpdateSchemeCollectionWeight(): array
    {
        if (!$this->hasTable('kuricolln') || !$this->hasTable('clients_kuridet')) {
            return ['message' => 'Scheme tables are not available.', 'summary' => ['updated' => 0]];
        }

        $rows = DB::table('kuricolln')
            ->select(['slno', 'code', 'amount', 'grate'])
            ->get();

        $updated = 0;
        foreach ($rows as $row) {
            $show = trim((string) (DB::table('clients_kuridet')->where('code', $row->code)->value('showwgtdet') ?? 'N'));
            $amount = (float) ($row->amount ?? 0);
            $rate = (float) ($row->grate ?? 0);
            $weight = 0;

            if ($show === 'Y' && $rate > 0) {
                $weight = round($amount / $rate, 3);
            }

            $updated += DB::table('kuricolln')->where('slno', $row->slno)->update(['wgt' => $weight]);
        }

        return ['message' => 'Scheme collection weight updated.', 'summary' => ['updated' => $updated]];
    }

    private function sqlCheckDuplicateBills(): array
    {
        $checks = [
            ['table' => 'salesm', 'column' => 'billno', 'label' => 'sales'],
            ['table' => 'salesrm', 'column' => 'billno', 'label' => 'sales_return'],
            ['table' => 'purchasem', 'column' => 'billno', 'label' => 'purchase'],
            ['table' => 'purchaserm', 'column' => 'billno', 'label' => 'purchase_return'],
            ['table' => 'orderm', 'column' => 'ordno', 'label' => 'order'],
            ['table' => 'daybookpart', 'column' => 'vchno', 'label' => 'receipt_payment'],
            ['table' => 'pdclist', 'column' => 'docno', 'label' => 'receipt_payment_pdc'],
        ];

        $summary = [];
        $duplicateGroups = 0;

        foreach ($checks as $check) {
            if (!$this->hasTable($check['table']) || !Schema::hasColumn($check['table'], $check['column'])) {
                $summary[$check['label']] = 'n/a';
                continue;
            }

            $groups = DB::table($check['table'])
                ->selectRaw('TRIM(' . $check['column'] . ') as docno, COUNT(*) as duplicate_count')
                ->whereNotNull($check['column'])
                ->whereRaw("TRIM({$check['column']}) <> ''")
                ->groupByRaw('TRIM(' . $check['column'] . ')')
                ->havingRaw('COUNT(*) > 1')
                ->get();

            $groupCount = $groups->count();
            $summary[$check['label']] = $groupCount;
            $duplicateGroups += $groupCount;
        }

        if ($duplicateGroups === 0) {
            return ['message' => 'No duplicate bill/order entries found.', 'summary' => $summary];
        }

        return [
            'message' => "Duplicate bill/order entries found: {$duplicateGroups} group(s).",
            'summary' => $summary,
        ];
    }

    private function sqlCheckReceiptPaymentMissingData(): array
    {
        if (!$this->hasTable('daybookpart')) {
            return ['message' => '`daybookpart` table is not available.', 'summary' => ['vouchers' => 0]];
        }

        $summary = [
            'vouchers' => 0,
            'receipts' => 0,
            'payments' => 0,
            'missing_daybook_rows' => 0,
            'single_side_entries' => 0,
            'receipt_missing_negative_cashbank' => 0,
            'receipt_missing_positive_party' => 0,
            'payment_missing_positive_cashbank' => 0,
            'payment_missing_negative_party' => 0,
            'pdc_receipts' => 0,
            'pdc_payments' => 0,
        ];

        $voucherBase = DB::table('daybookpart')
            ->whereNotNull('vchno')
            ->whereRaw("TRIM(COALESCE(vchno, '')) <> ''")
            ->where(function ($q) {
                $q->whereRaw("LEFT(TRIM(vchno), 2) = 'VR'")
                    ->orWhereRaw("LEFT(TRIM(vchno), 2) = 'VP'");
            });

        $summary['vouchers'] = (clone $voucherBase)->count();
        $summary['receipts'] = (clone $voucherBase)->whereRaw("LEFT(TRIM(vchno), 2) = 'VR'")->count();
        $summary['payments'] = (clone $voucherBase)->whereRaw("LEFT(TRIM(vchno), 2) = 'VP'")->count();

        if ($this->hasTable('daybook')) {
            $aggregate = DB::table('daybookpart as dp')
                ->leftJoin('daybook as db', 'db.slno', '=', 'dp.slno')
                ->whereNotNull('dp.vchno')
                ->whereRaw("TRIM(COALESCE(dp.vchno, '')) <> ''")
                ->where(function ($q) {
                    $q->whereRaw("LEFT(TRIM(dp.vchno), 2) = 'VR'")
                        ->orWhereRaw("LEFT(TRIM(dp.vchno), 2) = 'VP'");
                })
                ->selectRaw("
                    dp.slno,
                    MAX(TRIM(dp.vchno)) as vchno,
                    COUNT(db.slno) as daybook_rows,
                    SUM(CASE WHEN db.amount > 0 THEN 1 ELSE 0 END) as positive_rows,
                    SUM(CASE WHEN db.amount < 0 THEN 1 ELSE 0 END) as negative_rows
                ")
                ->groupBy('dp.slno')
                ->get();

            $summary['missing_daybook_rows'] = $aggregate->filter(fn ($row) => (int) ($row->daybook_rows ?? 0) === 0)->count();
            $summary['single_side_entries'] = $aggregate->filter(fn ($row) => (int) ($row->daybook_rows ?? 0) === 1)->count();
            $summary['receipt_missing_negative_cashbank'] = $aggregate->filter(function ($row) {
                $vch = strtoupper(trim((string) ($row->vchno ?? '')));
                return str_starts_with($vch, 'VR') && (int) ($row->negative_rows ?? 0) === 0;
            })->count();
            $summary['receipt_missing_positive_party'] = $aggregate->filter(function ($row) {
                $vch = strtoupper(trim((string) ($row->vchno ?? '')));
                return str_starts_with($vch, 'VR') && (int) ($row->positive_rows ?? 0) === 0;
            })->count();
            $summary['payment_missing_positive_cashbank'] = $aggregate->filter(function ($row) {
                $vch = strtoupper(trim((string) ($row->vchno ?? '')));
                return str_starts_with($vch, 'VP') && (int) ($row->positive_rows ?? 0) === 0;
            })->count();
            $summary['payment_missing_negative_party'] = $aggregate->filter(function ($row) {
                $vch = strtoupper(trim((string) ($row->vchno ?? '')));
                return str_starts_with($vch, 'VP') && (int) ($row->negative_rows ?? 0) === 0;
            })->count();
        } else {
            $summary['missing_daybook_rows'] = 'n/a';
            $summary['single_side_entries'] = 'n/a';
            $summary['receipt_missing_negative_cashbank'] = 'n/a';
            $summary['receipt_missing_positive_party'] = 'n/a';
            $summary['payment_missing_positive_cashbank'] = 'n/a';
            $summary['payment_missing_negative_party'] = 'n/a';
        }

        if ($this->hasTable('pdclist') && Schema::hasColumn('pdclist', 'rp')) {
            $summary['pdc_receipts'] = DB::table('pdclist')->where('rp', 'R')->count();
            $summary['pdc_payments'] = DB::table('pdclist')->where('rp', 'P')->count();
        }

        $issueCount = 0;
        foreach ([
            'missing_daybook_rows',
            'single_side_entries',
            'receipt_missing_negative_cashbank',
            'receipt_missing_positive_party',
            'payment_missing_positive_cashbank',
            'payment_missing_negative_party',
        ] as $key) {
            $value = $summary[$key] ?? 0;
            if (is_numeric($value)) {
                $issueCount += (int) $value;
            }
        }

        return [
            'message' => $issueCount > 0
                ? 'Receipt/payment missing-data check completed. Review the summary counts.'
                : 'Receipt/payment missing-data check completed. No missing linked data found.',
            'summary' => $summary,
        ];
    }

    private function sqlUpdateStockTouchToHundred(): array
    {
        if (!$this->hasTable('clientsgs') || !Schema::hasColumn('clientsgs', 'stocktouch')) {
            return ['message' => '`clientsgs.stocktouch` is not available.', 'summary' => ['updated' => 0]];
        }

        $updated = DB::table('clientsgs')->where('stocktouch', 0)->update(['stocktouch' => 100]);
        return ['message' => 'Zero stock touch updated to 100.', 'summary' => ['updated' => $updated]];
    }

    private function sqlWidenSalestypePrefixCols(): array
    {
        $altered = 0;

        if ($this->hasTable('salestype')) {
            DB::statement("ALTER TABLE salestype
                MODIFY prefix   VARCHAR(20) NOT NULL DEFAULT '',
                MODIFY pprefix  VARCHAR(20) NOT NULL DEFAULT '',
                MODIFY srprefix VARCHAR(20) NOT NULL DEFAULT '',
                MODIFY prprefix VARCHAR(20) NOT NULL DEFAULT '',
                MODIFY formno   VARCHAR(20) NOT NULL DEFAULT '',
                MODIFY name     VARCHAR(30)");
            $altered++;
        }

        // generali.code must hold keys like SALESB2B/GO/26-27/ (SALES + 20 chars = 25 chars)
        if ($this->hasTable('generali')) {
            DB::statement("ALTER TABLE generali MODIFY code VARCHAR(30) NOT NULL DEFAULT ''");
            $altered++;
        }

        // stkandprofit.stkvalue must hold large bill amounts (e.g. 2500g × 7000 = 17.5M)
        if ($this->hasTable('stkandprofit')) {
            DB::statement("ALTER TABLE stkandprofit MODIFY stkvalue DECIMAL(15,2) NOT NULL DEFAULT 0, MODIFY profit DECIMAL(15,2) NOT NULL DEFAULT 0");
            $altered++;
        }

        if ($altered === 0) {
            return ['message' => '`salestype` table not found.', 'summary' => ['altered' => 0]];
        }

        return ['message' => 'salestype prefix columns widened to VARCHAR(20), name to VARCHAR(30), generali.code to VARCHAR(30), stkandprofit value columns to DECIMAL(15,2).', 'summary' => ['altered' => $altered]];
    }

    private function sqlWidenBillNoColumns(): array
    {
        $targets = [
            'salesm',
            'salesrm',
            'purchasem',
            'purchaserm',
        ];

        $altered = 0;
        $skipped = 0;

        foreach ($targets as $table) {
            if (!$this->hasTable($table) || !Schema::hasColumn($table, 'billno')) {
                $skipped++;
                continue;
            }

            DB::statement("ALTER TABLE `{$table}` MODIFY `billno` VARCHAR(20) NULL");
            $altered++;
        }

        if ($altered === 0) {
            return ['message' => 'No bill number columns were updated.', 'summary' => ['altered' => 0, 'skipped' => $skipped]];
        }

        return [
            'message' => 'Sales, sales return, purchase, and purchase return bill number columns widened to VARCHAR(20).',
            'summary' => ['altered' => $altered, 'skipped' => $skipped],
        ];
    }

    private function sqlWidenLegacyDocNoColumns(): array
    {
        $targets = [
            ['table' => 'salesm', 'columns' => ['billno', 'docno']],
            ['table' => 'salesrm', 'columns' => ['billno', 'docno']],
            ['table' => 'purchasem', 'columns' => ['billno', 'docno']],
            ['table' => 'purchaserm', 'columns' => ['billno', 'docno']],
            ['table' => 'daybook', 'columns' => ['docno']],
            ['table' => 'stkandprofit', 'columns' => ['docno']],
            ['table' => 'kuricolln', 'columns' => ['docno']],
            ['table' => 'pdclist', 'columns' => ['docno']],
        ];

        $altered = 0;
        $skipped = 0;

        foreach ($targets as $target) {
            $table = $target['table'];
            if (!$this->hasTable($table)) {
                $skipped += count($target['columns']);
                continue;
            }

            foreach ($target['columns'] as $column) {
                if (!Schema::hasColumn($table, $column)) {
                    $skipped++;
                    continue;
                }

                DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` VARCHAR(30) NULL");
                $altered++;
            }
        }

        if ($altered === 0) {
            return ['message' => 'No legacy docno/billno columns were updated.', 'summary' => ['altered' => 0, 'skipped' => $skipped]];
        }

        return [
            'message' => 'Legacy exchange/docno related columns widened to VARCHAR(30).',
            'summary' => ['altered' => $altered, 'skipped' => $skipped],
        ];
    }

    private function sqlCleanupStalePrefixCounters(): array
    {
        if (!$this->hasTable('generali') || !$this->hasTable('salestype')) {
            return ['message' => '`generali` or `salestype` table not found.', 'summary' => ['deleted' => 0]];
        }

        $types = DB::table('salestype')->get();
        $validKeys = [];
        foreach ($types as $t) {
            if (($v = strtoupper(trim((string) ($t->prefix   ?? '')))) !== '') $validKeys[] = 'SALES' . $v;
            if (($v = strtoupper(trim((string) ($t->srprefix ?? '')))) !== '') $validKeys[] = 'SRET'  . $v;
            if (($v = strtoupper(trim((string) ($t->pprefix  ?? '')))) !== '') $validKeys[] = 'PURCH' . $v;
            if (($v = strtoupper(trim((string) ($t->prprefix ?? '')))) !== '') $validKeys[] = 'PRET'  . $v;
        }

        $deleted = DB::table('generali')
            ->where(function ($q) {
                $q->where('code', 'LIKE', 'SALES%')
                  ->orWhere('code', 'LIKE', 'SRET%')
                  ->orWhere('code', 'LIKE', 'PURCH%')
                  ->orWhere('code', 'LIKE', 'PRET%');
            })
            ->when(!empty($validKeys), fn ($q) => $q->whereNotIn('code', $validKeys))
            ->delete();

        return ['message' => 'Stale prefix counters removed.', 'summary' => ['deleted' => $deleted]];
    }

    private function sqlResetDaybookSlno(): array
    {
        if (!$this->hasTable('daybook')) {
            return ['message' => '`daybook` table not found.', 'summary' => ['updated' => 0]];
        }
        $dateCol = Schema::hasColumn('daybook', 'tdate') ? 'tdate' : 'billdate';

        // Count duplicates
        $dupes = (int) DB::table('daybook')
            ->select('slno')
            ->groupBy('slno')
            ->havingRaw('COUNT(*) > 1')
            ->get()->count();

        if ($dupes === 0) {
            return ['message' => 'No duplicate `slno` values found in `daybook`.', 'summary' => ['updated' => 0]];
        }

        // Reassign slno sequentially ordered by date then existing slno
        $rows = DB::table('daybook')->orderBy($dateCol)->orderBy('slno')->pluck('slno', 'slno');
        $updated = 0;
        $newSlno = 1;
        foreach (DB::table('daybook')->orderBy($dateCol)->orderBy('slno')->select('slno')->cursor() as $row) {
            DB::table('daybook')->where('slno', $row->slno)->limit(1)->update(['slno' => $newSlno]);
            $newSlno++;
            $updated++;
        }

        return ['message' => "Daybook slno reset. {$dupes} duplicate(s) found.", 'summary' => ['updated' => $updated]];
    }

    private function buildRunningDocNo(string $prefix, int $number, int $length = 5): string
    {
        return $prefix . str_pad((string) $number, max(1, $length), '0', STR_PAD_LEFT);
    }

    private function updateDaybookParticular(int $slno, string $particular, ?string $vchno = null): void
    {
        if (!$this->hasTable('daybookpart')) {
            return;
        }

        $data = ['particular' => mb_substr($particular, 0, 40)];
        if ($vchno !== null && Schema::hasColumn('daybookpart', 'vchno')) {
            $data['vchno'] = $vchno;
        }

        DB::table('daybookpart')->where('slno', $slno)->update($data);
    }

    private function rearrangeSales(int $startNo, string $fromDate, int $control): int
    {
        if (!$this->hasTable('salesm')) {
            return 0;
        }

        $billTypeWise = strtoupper($this->generalText('BillTypewiseBillNo', 'N')) === 'Y';
        $defaultPrefix = $control === 1
            ? $this->generalText('SBPREF', 'SLB/')
            : $this->generalText('SEPREF', 'SLE/');

        $rows = DB::table('salesm')
            ->select(['slno', 'custname', 'billtype'])
            ->where('control', $control)
            ->whereDate('tdate', '>=', $fromDate)
            ->whereRaw("LEFT(TRIM(billno), 2) <> 'SO'")
            ->orderBy('tdate')
            ->orderBy('slno')
            ->get();

        $current = max(0, $startNo - 1);
        $updated = 0;

        foreach ($rows as $row) {
            if ($billTypeWise && $control === 1 && $this->hasTable('salestype')) {
                $prefix = trim((string) (DB::table('salestype')->where('code', $row->billtype)->value('prefix') ?? ''));
                if ($prefix === '') {
                    $prefix = $defaultPrefix;
                }
                $code = 'SALES' . $prefix;
                $current = (int) (DB::table('generali')->where('code', $code)->value('cvalue') ?? 0) + 1;
                $docNo = $this->buildRunningDocNo($prefix, $current);
                $this->upsertGeneralInt($code, $current);
            } else {
                $current++;
                $docNo = $this->buildRunningDocNo($defaultPrefix, $current);
            }

            DB::table('salesm')->where('slno', $row->slno)->update(['billno' => $docNo]);
            if ($this->hasTable('salesrm')) {
                DB::table('salesrm')->where('slno', $row->slno)->update(['billno' => $docNo]);
            }
            if ($this->hasTable('purchasem')) {
                DB::table('purchasem')->where('slno', $row->slno)->update(['billno' => $docNo]);
            }

            $particular = 'By Sales (' . $docNo . ')';
            $party = trim((string) ($row->custname ?? ''));
            if ($party !== '') {
                $particular .= ' To ' . $party;
            }
            $this->updateDaybookParticular((int) $row->slno, $particular);
            $updated++;
        }

        if (!$billTypeWise) {
            $this->upsertGeneralInt($control === 1 ? 'SALESB' : 'SALESE', $current);
        }

        return $updated;
    }

    private function rearrangeSalesReturn(int $startNo, string $fromDate, int $control): int
    {
        if (!$this->hasTable('salesrm')) {
            return 0;
        }

        $prefix = $control === 1 ? 'SRB/' : 'SRE/';
        $counterCode = $control === 1 ? 'SALESRB' : 'SALESRE';
        $current = max(0, $startNo - 1);
        $updated = 0;

        $rows = DB::table('salesrm')
            ->select(['slno', 'custname'])
            ->where('control', $control)
            ->whereDate('tdate', '>=', $fromDate)
            ->where('sr', 'R')
            ->orderBy('tdate')
            ->orderBy('billno')
            ->get();

        foreach ($rows as $row) {
            $current++;
            $docNo = $this->buildRunningDocNo($prefix, $current);
            DB::table('salesrm')->where('slno', $row->slno)->update(['billno' => $docNo]);

            $particular = 'By Sales Return (' . $docNo . ')';
            $party = trim((string) ($row->custname ?? ''));
            if ($party !== '') {
                $particular .= ' From ' . $party;
            }
            $this->updateDaybookParticular((int) $row->slno, $particular);
            $updated++;
        }

        $this->upsertGeneralInt($counterCode, $current);
        return $updated;
    }

    private function rearrangePurchase(int $startNo, string $fromDate, int $control, bool $diamondOnly): int
    {
        if (!$this->hasTable('purchasem')) {
            return 0;
        }

        $prefix = $diamondOnly
            ? ($control === 1 ? 'DPB/' : 'DPE/')
            : ($control === 1 ? $this->generalText('PBPREF', 'PB/') : $this->generalText('PEPREF', 'PE/'));
        $counterCode = $diamondOnly
            ? ($control === 1 ? 'DPURCHASEB' : 'DPURCHASEE')
            : ($control === 1 ? 'PURCHASEB' : 'PURCHASEE');

        $query = DB::table('purchasem')
            ->select(['slno', 'name'])
            ->where('control', $control)
            ->whereDate('tdate', '>=', $fromDate);

        if ($diamondOnly) {
            $query->where('pr', 'P')->whereRaw("LEFT(TRIM(docno), 2) = 'DP'");
        } else {
            $query->whereRaw("LEFT(TRIM(docno), 2) <> 'DP'");
        }

        $rows = $query->orderBy('tdate')->orderBy('slno')->get();
        $current = max(0, $startNo - 1);
        $updated = 0;

        foreach ($rows as $row) {
            $current++;
            $docNo = $this->buildRunningDocNo($prefix, $current);
            DB::table('purchasem')->where('slno', $row->slno)->update(['docno' => $docNo]);
            if ($this->hasTable('purchaserm')) {
                DB::table('purchaserm')->where('slno', $row->slno)->update(['docno' => $docNo]);
            }

            $particular = 'By Purchase - ' . $docNo;
            $party = trim((string) ($row->name ?? ''));
            if ($party !== '') {
                $particular .= ' From ' . $party;
            }
            $this->updateDaybookParticular((int) $row->slno, $particular);
            $updated++;
        }

        $this->upsertGeneralInt($counterCode, $current);
        return $updated;
    }

    private function rearrangePurchaseReturn(int $startNo, string $fromDate, int $control): int
    {
        if (!$this->hasTable('purchaserm')) {
            return 0;
        }

        $prefix = $control === 1 ? 'PNB/' : 'PNE/';
        $counterCode = $control === 1 ? 'PRETURNB' : 'PRETURNE';
        $rows = DB::table('purchaserm')
            ->select(['slno', 'name'])
            ->where('control', $control)
            ->whereDate('tdate', '>=', $fromDate)
            ->where('pr', 'R')
            ->orderBy('tdate')
            ->orderBy('slno')
            ->get();

        $current = max(0, $startNo - 1);
        $updated = 0;
        foreach ($rows as $row) {
            $current++;
            $docNo = $this->buildRunningDocNo($prefix, $current);
            DB::table('purchaserm')->where('slno', $row->slno)->update(['docno' => $docNo]);

            $particular = 'By Purchase Return - ' . $docNo;
            $party = trim((string) ($row->name ?? ''));
            if ($party !== '') {
                $particular .= ' To ' . $party;
            }
            $this->updateDaybookParticular((int) $row->slno, $particular);
            $updated++;
        }

        $this->upsertGeneralInt($counterCode, $current);
        return $updated;
    }

    private function rearrangeOrder(int $startNo, string $fromDate, int $control): int
    {
        if (!$this->hasTable('orderm')) {
            return 0;
        }

        $prefix = $control === 1 ? 'ODB/' : 'ODE/';
        $counterCode = $control === 1 ? 'ORDERB' : 'ORDERE';
        $rows = DB::table('orderm')
            ->select(['slno', 'custname'])
            ->where('control', $control)
            ->whereDate('tdate', '>=', $fromDate)
            ->orderBy('tdate')
            ->orderBy('slno')
            ->get();

        $current = max(0, $startNo - 1);
        $updated = 0;
        foreach ($rows as $row) {
            $current++;
            $docNo = $this->buildRunningDocNo($prefix, $current);
            DB::table('orderm')->where('slno', $row->slno)->update(['ordno' => $docNo]);
            $particular = 'By Order - ' . $docNo;
            $party = trim((string) ($row->custname ?? ''));
            if ($party !== '') {
                $particular .= ' From ' . $party;
            }
            $this->updateDaybookParticular((int) $row->slno, $particular);
            $updated++;
        }

        $this->upsertGeneralInt($counterCode, $current);
        return $updated;
    }

    private function rearrangeRefinery(int $startNo, string $fromDate, int $control): int
    {
        if (!$this->hasTable('refinerym')) {
            return 0;
        }

        $prefix = $control === 1 ? 'RFB/' : 'RFE/';
        $counterCode = $control === 1 ? 'REFINEB' : 'REFINEE';
        $rows = DB::table('refinerym')
            ->select(['slno'])
            ->where('control', $control)
            ->whereDate('tdate', '>=', $fromDate)
            ->orderBy('tdate')
            ->orderBy('slno')
            ->get();

        $current = max(0, $startNo - 1);
        $updated = 0;
        foreach ($rows as $row) {
            $current++;
            $docNo = $this->buildRunningDocNo($prefix, $current);
            DB::table('refinerym')->where('slno', $row->slno)->update(['docno' => $docNo]);
            $this->updateDaybookParticular((int) $row->slno, 'By Refinery Entry - ' . $docNo);
            $updated++;
        }

        $this->upsertGeneralInt($counterCode, $current);
        return $updated;
    }

    private function rearrangeSmith(int $issueStart, int $receiptStart, string $fromDate, int $control, string $type, bool $trnBased): int
    {
        if (!$this->hasTable('smithm')) {
            return 0;
        }

        $rows = DB::table('smithm')
            ->select(['slno', 'docno', 'smithcode'])
            ->where('control', $control)
            ->whereDate('tdate', '>=', $fromDate)
            ->whereRaw('LEFT(TRIM(docno), 1) = ?', [$type])
            ->orderBy('tdate')
            ->orderBy('slno')
            ->get();

        $issueNo = max(0, $issueStart - 1);
        $receiptNo = max(0, $receiptStart - 1);
        $updated = 0;

        foreach ($rows as $row) {
            $party = '';
            if ($this->hasTable('clients')) {
                $party = trim((string) (DB::table('clients')->where('code', $row->smithcode)->value('name') ?? ''));
            }

            $isReceipt = false;
            if ($trnBased && $this->hasTable('smithd')) {
                $isReceipt = DB::table('smithd')
                    ->where('slno', $row->slno)
                    ->where('givrec', 'R')
                    ->exists();
            } else {
                $docNo = strtoupper(trim((string) ($row->docno ?? '')));
                $isReceipt = str_starts_with($docNo, $type . 'R');
            }

            if ($isReceipt) {
                $receiptNo++;
                $docNo = $this->buildRunningDocNo($type . 'RB/', $receiptNo);
            } else {
                $issueNo++;
                $docNo = $type === 'J'
                    ? $this->buildRunningDocNo($control === 1 ? $this->generalText('JBPREF', 'JLB/') : $this->generalText('JEPREF', 'JLE/'), $issueNo)
                    : $this->buildRunningDocNo($control === 1 ? $this->generalText('GSBPREF', 'GSB/') : $this->generalText('GSEPREF', 'GSE/'), $issueNo);
            }

            DB::table('smithm')->where('slno', $row->slno)->update(['docno' => $docNo]);
            $particular = ($type === 'J' ? 'By Jewellery Entry-' : 'By Smith Entry-') . trim($docNo);
            if ($party !== '') {
                $particular .= '-' . $party;
            }
            $this->updateDaybookParticular((int) $row->slno, $particular);
            $updated++;
        }

        if ($type === 'J') {
            $this->upsertGeneralInt($control === 1 ? 'JEWLB' : 'JEWLE', $issueNo);
            if ($trnBased) {
                $this->upsertGeneralInt('JEWLRB', $receiptNo);
            }
        } else {
            $this->upsertGeneralInt($control === 1 ? 'SMITHB' : 'SMITHE', $issueNo);
            if ($trnBased) {
                $this->upsertGeneralInt('SMITHRB', $receiptNo);
            }
        }

        return $updated;
    }

    private function rearrangeSmithReceiptOnly(int $startNo, string $fromDate, int $control, string $type): int
    {
        if (!$this->hasTable('smithm')) {
            return 0;
        }

        $rows = DB::table('smithm')
            ->select(['slno', 'smithcode'])
            ->where('control', $control)
            ->whereDate('tdate', '>=', $fromDate)
            ->whereRaw('LEFT(TRIM(docno), 2) = ?', [$type . 'R'])
            ->orderBy('tdate')
            ->orderBy('slno')
            ->get();

        $current = max(0, $startNo - 1);
        $updated = 0;
        foreach ($rows as $row) {
            $current++;
            $docNo = $this->buildRunningDocNo($type . 'RB/', $current);
            DB::table('smithm')->where('slno', $row->slno)->update(['docno' => $docNo]);
            $party = '';
            if ($this->hasTable('clients')) {
                $party = trim((string) (DB::table('clients')->where('code', $row->smithcode)->value('name') ?? ''));
            }
            $particular = ($type === 'J' ? 'By Jewellery Entry-' : 'By Smith Entry-') . trim($docNo);
            if ($party !== '') {
                $particular .= '-' . $party;
            }
            $this->updateDaybookParticular((int) $row->slno, $particular);
            $updated++;
        }

        $this->upsertGeneralInt($type === 'J' ? 'JEWLRB' : 'SMITHRB', $current);
        return $updated;
    }

    private function rearrangeRepair(int $startNo, string $fromDate, int $control, string $givRec): int
    {
        if (!$this->hasTable('repairm')) {
            return 0;
        }

        $rows = DB::table('repairm')
            ->select(['slno', 'custcode'])
            ->where('control', $control)
            ->whereDate('tdate', '>=', $fromDate)
            ->where('givrec', $givRec)
            ->orderBy('tdate')
            ->orderBy('slno')
            ->get();

        $current = max(0, $startNo - 1);
        $updated = 0;
        $prefix = $givRec === 'R' ? 'RM1/' : 'RM4/';
        $counterCode = $givRec === 'R' ? 'REPAIRB' : 'RM4B';

        foreach ($rows as $row) {
            $current++;
            $docNo = $this->buildRunningDocNo($prefix, $current);
            DB::table('repairm')->where('slno', $row->slno)->update(['billno' => $docNo]);
            $party = '';
            if ($this->hasTable('clients')) {
                $party = trim((string) (DB::table('clients')->where('code', $row->custcode)->value('name') ?? ''));
            }
            $particular = $givRec === 'R' ? 'By Repair - ' . $docNo : 'By Remake Returns - ' . $docNo;
            if ($givRec === 'R' && $party !== '') {
                $particular .= ' From ' . $party;
            }
            $this->updateDaybookParticular((int) $row->slno, $particular);
            $updated++;
        }

        $this->upsertGeneralInt($counterCode, $current);
        return $updated;
    }

    private function rearrangeVoucherNumbers(
        int $startNo,
        string $fromDate,
        int $control,
        string $leftCode,
        string $counterCodeB,
        string $counterCodeE,
        string $prefixB,
        string $prefixE,
        ?string $syncTable = null,
        ?string $syncColumn = null
    ): int {
        if (!$this->hasTable('daybookpart') || !$this->hasTable('daybook')) {
            return 0;
        }

        $rows = DB::table('daybookpart')
            ->join('daybook', 'daybook.slno', '=', 'daybookpart.slno')
            ->select('daybookpart.slno', 'daybookpart.vchno', 'daybookpart.particular', 'daybook.tdate')
            ->where('daybook.control', $control)
            ->whereDate('daybook.tdate', '>=', $fromDate)
            ->whereRaw('LEFT(TRIM(daybookpart.vchno), 2) = ?', [$leftCode])
            ->distinct()
            ->orderBy('daybook.tdate')
            ->orderBy('daybookpart.slno')
            ->get();

        $current = max(0, $startNo - 1);
        $updated = 0;

        foreach ($rows as $row) {
            $current++;
            $newVoucher = $this->buildRunningDocNo($control === 1 ? $prefixB : $prefixE, $current);
            $oldVoucher = trim((string) ($row->vchno ?? ''));
            $particular = (string) ($row->particular ?? '');
            if ($oldVoucher !== '' && str_contains($particular, $oldVoucher)) {
                $particular = str_replace($oldVoucher, $newVoucher, $particular);
            }

            $this->updateDaybookParticular((int) $row->slno, $particular, $newVoucher);

            if ($syncTable !== null && $syncColumn !== null && $this->hasTable($syncTable) && Schema::hasColumn($syncTable, $syncColumn)) {
                DB::table($syncTable)->where('slno', $row->slno)->update([$syncColumn => $newVoucher]);
            }

            $updated++;
        }

        $this->upsertGeneralInt($control === 1 ? $counterCodeB : $counterCodeE, $current);
        return $updated;
    }

    private function deleteClientsByType(bool $enabled, string $type, bool $extraCustomer, bool $deleteGs, bool $deletePict): void
    {
        if (!$enabled) {
            return;
        }

        if ($this->hasTable('clients')) {
            DB::table('clients')->where('ctype', $type)->delete();
        }
        if ($extraCustomer) {
            $this->deleteTables(['clients_advanced', 'clients_kuridet']);
        }
        if ($deleteGs && $this->hasTable('clientsgs')) {
            DB::table('clientsgs')->where('ctype', $type)->delete();
        }
        if ($this->hasTable('accountm')) {
            DB::table('accountm')->where('actype2', $type)->delete();
        }
        if ($deletePict) {
            $this->deleteTables(['clientspict']);
        }
    }
}
