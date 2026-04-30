<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class CustomerBillwiseRcptController extends Controller
{
    // ── Helpers ──────────────────────────────────────────────────────────────

    private function requireLogin(Request $request): void
    {
        if (!$request->session()->has('user_code')) {
            abort(redirect('/login'));
        }
    }

    private function ok(array $extra = []): JsonResponse
    {
        return response()->json(array_merge(['ok' => true], $extra));
    }

    private function err(string $msg): JsonResponse
    {
        return response()->json(['ok' => false, 'msg' => $msg]);
    }

    private function trimUpper($v): string
    {
        return strtoupper(trim((string) $v));
    }

    private function genInt(string $code): int
    {
        if (!$this->hasTable('generali')) return 0;
        return (int) (DB::table('generali')->where('code', $code)->value('cvalue') ?? 0);
    }

    private function incrementGenInt(string $code): int
    {
        if (!$this->hasTable('generali')) return 1;
        $current = $this->genInt($code);
        $next    = $current + 1;
        $updated = DB::table('generali')->where('code', $code)->update(['cvalue' => $next]);
        if ($updated === 0) {
            DB::table('generali')->insert(['code' => $code, 'cvalue' => $next]);
        }
        return $next;
    }

    private function nextSerialNo(): int
    {
        if (!$this->hasTable('generali')) return 1;
        $current = (int) (DB::table('generali')->where('code', 'SERIALNO')->value('cvalue') ?? 0);
        $maxUsed = 0;
        foreach (['salesm', 'salesrm', 'purchasem', 'purchaserm', 'daybook', 'daybookpart', 'orderm', 'smithm', 'refinerym', 'repairm'] as $table) {
            if ($this->hasTable($table) && Schema::hasColumn($table, 'slno')) {
                $maxUsed = max($maxUsed, (int) (DB::table($table)->max('slno') ?? 0));
            }
        }
        $next = max($current, $maxUsed) + 1;
        DB::table('generali')->updateOrInsert(['code' => 'SERIALNO'], ['cvalue' => $next]);
        return $next;
    }

    private function generateVchno(int $control): string
    {
        if ($control === 1) {
            $n = $this->incrementGenInt('VCHNORB');
            return 'VRB/' . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
        }
        $n = $this->incrementGenInt('VCHNORE');
        return 'VRE/' . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }

    private function generateJournalVchno(int $control): string
    {
        if ($control === 1) {
            $n = $this->incrementGenInt('VCHNOJB');
            return 'JLB/' . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
        }
        $n = $this->incrementGenInt('VCHNOJE');
        return 'JLE/' . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }

    /** Next preview (without incrementing) */
    private function nextVchnoPreview(int $control): string
    {
        $code = ($control === 1) ? 'VCHNORB' : 'VCHNORE';
        $pfx  = ($control === 1) ? 'VRB/' : 'VRE/';
        $n    = $this->genInt($code) + 1;
        return $pfx . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }

    private function parseDate(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '' || $raw === '00/00/0000') return null;
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $raw, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        return null;
    }

    private function formatDate(?string $db): string
    {
        if (!$db) return '';
        try {
            $d = new \DateTime($db);
            return $d->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $db;
        }
    }

    /** Filter insert/update array to only include columns that exist in table */
    private function filterCols(string $table, array $data): array
    {
        $cols = array_map('strtolower', $this->columnList($table));
        return array_filter($data, fn($k) => in_array(strtolower($k), $cols, true), ARRAY_FILTER_USE_KEY);
    }

    private function hasCol(string $table, string $col): bool
    {
        static $cache = [];
        $key = $table . ':' . strtolower($col);
        if (!isset($cache[$key])) {
            $cache[$key] = Schema::hasColumn($table, $col);
        }
        return $cache[$key];
    }

    // ── Entry points ──────────────────────────────────────────────────────────

    public function index(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }
        return view('account.customer-billwise-rcpt');
    }

    public function api(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return $this->err('Unauthorized');
        }

        $action  = (string) ($request->input('action', ''));
        $control = (int) ($request->session()->get('gilevel', 1));
        if ($control < 1 || $control > 2) $control = 1;

        return match ($action) {
            'init'            => $this->actionInit($request, $control),
            'load_customer'   => $this->actionLoadCustomer($request, $control),
            'customer_search' => $this->actionCustomerSearch($request),
            'save'            => $this->actionSave($request, $control),
            default           => $this->err('Unknown action'),
        };
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    private function actionInit(Request $request, int $control): JsonResponse
    {
        $today  = date('d/m/Y');
        $vchno  = $this->nextVchnoPreview($control);

        // Default gold rate
        $grate = 0.0;
        try {
            $grate = (float) (DB::table('generald')->where('code', 'GRATE')->value('dvalue') ?? 0);
        } catch (\Throwable) {}

        // Salesmen
        $salesmen = [];
        if ($this->hasTable('sman')) {
            $salesmen = DB::table('sman')
                ->select('code', 'name')
                ->orderBy('name')
                ->get()
                ->map(fn($r) => ['code' => (string) $r->code, 'name' => (string) $r->name])
                ->values()
                ->all();
        }

        return $this->ok([
            'vchno'    => $vchno,
            'today'    => $today,
            'grate'    => $grate,
            'control'  => $control,
            'salesmen' => $salesmen,
        ]);
    }

    private function actionLoadCustomer(Request $request, int $control): JsonResponse
    {
        $code  = $this->trimUpper($request->input('code', ''));
        $grate = (float) $request->input('grate', 0);

        if ($code === '') return $this->err('Customer code required');
        if (!$this->hasTable('clients')) return $this->err('clients table not found');

        $client = DB::table('clients')
            ->select('code', 'name')
            ->whereRaw('UPPER(TRIM(code)) = ?', [$code])
            ->first();

        if (!$client) return $this->err('This code does not exist...');

        // Account balance
        $balance = 0.0;
        if ($this->hasTable('accountm')) {
            $balCol = ($control === 1) ? 'opbal' : 'opbalb';
            $opbal  = 0.0;
            if ($this->hasCol('accountm', $balCol)) {
                $opbal = (float) (DB::table('accountm')
                    ->whereRaw('UPPER(TRIM(accode)) = ?', [$code])
                    ->value($balCol) ?? 0);
            }
            $daySum = 0.0;
            if ($this->hasTable('daybook') && $this->hasCol('daybook', 'control')) {
                $daySum = (float) (DB::table('daybook')
                    ->whereRaw('UPPER(TRIM(accode)) = ?', [$code])
                    ->where('control', '<=', $control)
                    ->sum('amount') ?? 0);
            } elseif ($this->hasTable('daybook')) {
                $daySum = (float) (DB::table('daybook')
                    ->whereRaw('UPPER(TRIM(accode)) = ?', [$code])
                    ->sum('amount') ?? 0);
            }
            $balance = -($opbal + $daySum);
        }

        // Update balwgt on salesm (schema-guarded)
        $this->recalcBalwgt($code, $grate, $control);

        // Retrieve bill list
        $bills = $this->getBills($code, $control);

        return $this->ok([
            'name'    => (string) $client->name,
            'balance' => round($balance, 2),
            'bills'   => $bills,
        ]);
    }

    private function actionCustomerSearch(Request $request): JsonResponse
    {
        if (!$this->hasTable('clients')) return $this->ok(['data' => []]);

        $q = trim((string) $request->input('q', ''));
        if ($q === '') return $this->ok(['data' => []]);

        $rows = DB::table('clients')
            ->select('code', 'name')
            ->where(function ($qb) use ($q): void {
                $qb->where('code', 'like', $q . '%')
                   ->orWhere('name', 'like', '%' . $q . '%');
            })
            ->orderBy('code')
            ->limit(30)
            ->get()
            ->map(fn($r) => ['code' => (string) $r->code, 'name' => (string) $r->name])
            ->all();

        return $this->ok(['data' => $rows]);
    }

    private function actionSave(Request $request, int $control): JsonResponse
    {
        $tdate    = $this->parseDate($request->input('tdate'));
        $grate    = (float) $request->input('grate', 0);
        $custcode = $this->trimUpper($request->input('custcode', ''));
        $smcode   = trim((string) $request->input('smcode', ''));
        $part     = trim((string) $request->input('part', ''));
        $showwgt  = (bool) $request->input('showwgt', false);
        $rawItems = $request->input('items', []);
        $items    = is_string($rawItems) ? (json_decode($rawItems, true) ?? []) : (is_array($rawItems) ? $rawItems : []);

        if (!$tdate) return $this->err('Invalid date');
        if ($custcode === '') return $this->err('No customer selected');

        // Validate amounts
        foreach ($items as $item) {
            $alocamt = (float) ($item['alocamt'] ?? 0);
            $balance = (float) ($item['balance'] ?? 0);
            $billno  = (string) ($item['billno'] ?? '');
            if ($alocamt > $balance + 0.005) {
                return $this->err("Allocated amt ({$billno}) is greater than balance. You can't Save...");
            }
        }

        try {
            $vchno = '';
            DB::transaction(function () use (
                $tdate, $grate, $custcode, $smcode, $part, $showwgt,
                $items, $control, &$vchno
            ): void {
                $lslno      = $this->nextSerialNo();
                $vchno      = $this->generateVchno($control);
                $rcvdTotal  = 0.0;
                $discTotal  = 0.0;
                $hasAllocations = false;
                $userId     = ''; // can be extended via session

                foreach ($items as $item) {
                    $alocamt  = round((float) ($item['alocamt']  ?? 0), 2);
                    $discamt  = round((float) ($item['discamt']  ?? 0), 2);
                    $selected = (bool) ($item['selected'] ?? false);

                    if (!$selected && $alocamt == 0 && $discamt == 0) continue;

                    $hasAllocations = true;
                    $islno    = (int) ($item['slno']   ?? 0);
                    $billno   = trim((string) ($item['billno'] ?? ''));
                    $duedate  = $this->parseDate($item['duedate'] ?? '') ?? $tdate;
                    $billGrate = (float) ($item['grate'] ?? 0);

                    // Effective grate: higher of bill rate or session rate
                    $effGrate = max($grate, $billGrate);
                    if ($alocamt == 0 && $discamt != 0) {
                        $effGrate = 0;
                    }

                    // Update salesm.ramtafter
                    if ($islno > 0 && $this->hasCol('salesm', 'ramtafter')) {
                        $upd = ['ramtafter' => DB::raw("ramtafter + {$alocamt}")];
                        if ($this->hasCol('salesm', 'duedate')) {
                            $upd['duedate'] = $duedate;
                        }
                        DB::table('salesm')->where('slno', $islno)->update($upd);
                    }

                    // Insert into collection
                    if ($this->hasTable('collection')) {
                        $colData = $this->filterCols('collection', [
                            'slno'    => $lslno,
                            'code'    => $custcode,
                            'tdate'   => $tdate,
                            'billno'  => $billno,
                            'tranamt' => $alocamt,
                            'discount'=> $discamt,
                            'duedate' => $duedate,
                            'control' => $control,
                            'islno'   => $islno,
                            'grate'   => $effGrate,
                            'grate2'  => $grate,
                        ]);
                        if ($colData) DB::table('collection')->insert($colData);
                    }

                    $rcvdTotal += $alocamt;
                    $discTotal += $discamt;
                }

                if (!$hasAllocations && $rcvdTotal == 0 && $discTotal == 0) {
                    return; // nothing to save
                }

                // Particulars
                if ($part === '') {
                    $part = ($rcvdTotal == 0 && $discTotal != 0)
                        ? 'Adjustment Entry ' . $custcode . ' - ' . $vchno
                        : 'Receipt from ' . $custcode . ' - ' . $vchno;
                }
                $part = mb_substr($part, 0, 40);

                // daybookpart
                $dbpData = $this->filterCols('daybookpart', [
                    'slno'      => $lslno,
                    'vchno'     => $vchno,
                    'particular'=> $part,
                    'staff'     => $smcode,
                    'tdate'     => $tdate,
                    'ic'        => $smcode,
                    'uid'       => $userId,
                    'control'   => $control,
                ]);
                if ($dbpData) DB::table('daybookpart')->insert($dbpData);

                // daybookratewgt
                if ($showwgt && $grate > 0 && $this->hasTable('daybookratewgt')) {
                    $dwgt = round($rcvdTotal / $grate, 3);
                    $drwData = $this->filterCols('daybookratewgt', [
                        'slno'    => $lslno,
                        'rate'    => $grate,
                        'mcp'     => 0,
                        'wgt'     => $dwgt,
                        'code'    => $custcode,
                        'tdate'   => $tdate,
                        'control' => $control,
                    ]);
                    if ($drwData) DB::table('daybookratewgt')->insert($drwData);
                }

                // Daybook double entry (receipt)
                if ($rcvdTotal != 0) {
                    $dbBase = ['slno' => $lslno, 'tdate' => $tdate, 'control' => $control];

                    $row1 = $this->filterCols('daybook', array_merge($dbBase, [
                        'accode' => $custcode,
                        'amount' => $rcvdTotal,
                    ]));
                    if ($row1) DB::table('daybook')->insert($row1);

                    $row2 = $this->filterCols('daybook', array_merge($dbBase, [
                        'accode' => 'CASH',
                        'amount' => -$rcvdTotal,
                    ]));
                    if ($row2) DB::table('daybook')->insert($row2);
                }

                // Discount journal entry
                if ($discTotal != 0) {
                    $lslno2 = $this->nextSerialNo();
                    $vchno2 = $this->generateJournalVchno($control);
                    $part2  = 'Adjustment Entry ' . $custcode . ' - ' . $vchno2;
                    $part2  = mb_substr($part2, 0, 40);

                    $dbp2 = $this->filterCols('daybookpart', [
                        'slno'      => $lslno2,
                        'vchno'     => $vchno2,
                        'particular'=> $part2,
                        'staff'     => $smcode,
                        'tdate'     => $tdate,
                        'ic'        => $smcode,
                        'uid'       => $userId,
                        'control'   => $control,
                    ]);
                    if ($dbp2) DB::table('daybookpart')->insert($dbp2);

                    $dbBase2 = ['slno' => $lslno2, 'tdate' => $tdate, 'control' => $control];
                    $d1 = $this->filterCols('daybook', array_merge($dbBase2, [
                        'accode' => $custcode, 'amount' => $discTotal,
                    ]));
                    if ($d1) DB::table('daybook')->insert($d1);

                    $d2 = $this->filterCols('daybook', array_merge($dbBase2, [
                        'accode' => 'DISC', 'amount' => -$discTotal,
                    ]));
                    if ($d2) DB::table('daybook')->insert($d2);
                }
            });
        } catch (\Throwable $e) {
            return $this->err('Save failed: ' . $e->getMessage());
        }

        return $this->ok([
            'vchno'   => $vchno,
            'message' => 'Receipt saved successfully',
        ]);
    }

    // ── Internal helpers ──────────────────────────────────────────────────────

    private function recalcBalwgt(string $code, float $grate, int $control): void
    {
        if (!$this->hasTable('salesm')) return;
        if (!$this->hasCol('salesm', 'balwgt')) return;
        if (!$this->hasCol('salesm', 'ramt')) return;

        // Read WgtBalAfterDuedate setting
        $wgtAfterDue = false;
        try {
            $cfg = DB::table('generals')->where('code', 'WGTBALDUE')->value('cvalue');
            $wgtAfterDue = strtoupper(trim((string) $cfg)) === 'Y';
        } catch (\Throwable) {}

        $today = date('Y-m-d');

        // Reset balwgt = 0
        DB::table('salesm')
            ->whereRaw('UPPER(TRIM(custcode)) = ?', [$code])
            ->update(['balwgt' => 0]);

        if ($grate > 0 && $this->hasCol('salesm', 'netamt')) {
            $q = DB::table('salesm')
                ->whereRaw('UPPER(TRIM(custcode)) = ?', [$code]);
            if ($wgtAfterDue && $this->hasCol('salesm', 'duedate')) {
                $q->where('duedate', '<=', $today);
            }
            // balwgt = (netamt - ramt) / grate
            $q->update(['balwgt' => DB::raw("(netamt - ramt) / {$grate}")]);
        }

        // Subtract ramtafter weight
        if ($this->hasCol('salesm', 'ramtafter')) {
            DB::table('salesm')
                ->whereRaw('UPPER(TRIM(custcode)) = ?', [$code])
                ->update(['ramtafter' => DB::raw('COALESCE(ramtafter, 0)')]);

            // balwgt -= ramtafter / grate (approximate; skip if grate=0)
            if ($grate > 0) {
                DB::table('salesm')
                    ->whereRaw('UPPER(TRIM(custcode)) = ?', [$code])
                    ->update(['balwgt' => DB::raw("balwgt - COALESCE(ramtafter,0) / {$grate}")]);
            }
        }

        // Null → 0
        DB::table('salesm')
            ->whereRaw('UPPER(TRIM(custcode)) = ?', [$code])
            ->whereNull('balwgt')
            ->update(['balwgt' => 0]);
    }

    private function getBills(string $code, int $control): array
    {
        if (!$this->hasTable('salesm')) return [];

        $collectionExists = $this->hasTable('collection');
        $hasControl       = $this->hasCol('salesm', 'control');
        $hasDuedate       = $this->hasCol('salesm', 'duedate');
        $hasBalwgt        = $this->hasCol('salesm', 'balwgt');
        $hasNetamt        = $this->hasCol('salesm', 'netamt');
        $hasGrate         = $this->hasCol('salesm', 'grate');
        $hasRamt          = $this->hasCol('salesm', 'ramt');
        $hasTdate         = $this->hasCol('salesm', 'tdate');
        $hasBilldate      = $this->hasCol('salesm', 'billdate');

        // Build select
        $dateCol = $hasTdate ? 's.tdate' : ($hasBilldate ? 's.billdate' : DB::raw("'1900-01-01'"));
        $amtExpr = $hasNetamt ? 'COALESCE(s.netamt,0)'
                              : 'COALESCE(s.billamt,0)';

        $selectRaw = "
            s.slno,
            {$dateCol} AS tdate,
            s.billno,
            {$amtExpr} AS netamt,
            " . ($hasGrate   ? 'COALESCE(s.grate,0)'   : '0') . " AS grate,
            " . ($hasRamt    ? 'COALESCE(s.ramt,0)'    : '0') . " AS ramt,
            " . ($hasDuedate ? 'COALESCE(s.duedate, s.tdate)' : ($hasTdate ? 's.tdate' : "''")) . " AS duedate,
            COALESCE(s.custname,'') AS custname,
            " . ($hasBalwgt ? 'COALESCE(s.balwgt,0)' : '0') . " AS balwgt
        ";

        if ($collectionExists) {
            $selectRaw .= ",
            COALESCE((SELECT SUM(c.tranamt) FROM collection c WHERE c.islno = s.slno AND c.control <= {$control}),0) AS collectedamt,
            COALESCE((SELECT SUM(c.discount) FROM collection c WHERE c.islno = s.slno AND c.control <= {$control}),0) AS tdiscamt";
        } else {
            $selectRaw .= ", 0 AS collectedamt, 0 AS tdiscamt";
        }

        $query = DB::table('salesm AS s')->selectRaw(trim($selectRaw))
            ->whereRaw('UPPER(TRIM(s.custcode)) = ?', [$code]);

        if ($hasControl) {
            $query->where('s.control', '<=', $control);
        }

        if ($hasTdate) {
            $query->orderBy('s.tdate')->orderBy('s.slno');
        } else {
            $query->orderBy('s.slno');
        }

        $rows = $query->get();

        return $rows->map(function ($r) {
            $netamt     = (float) $r->netamt;
            $tdiscamt   = (float) $r->tdiscamt;
            $ramt       = (float) $r->ramt;
            $collected  = (float) $r->collectedamt;
            $netbillamt = $netamt - $tdiscamt;
            $rcvdamt    = round($ramt + $collected, 2);
            $balance    = round($netbillamt, 2) - $rcvdamt;

            return [
                'slno'       => (int)    $r->slno,
                'tdate'      => $this->formatDate($r->tdate),
                'billno'     => (string) $r->billno,
                'netamt'     => round($netamt, 2),
                'grate'      => round((float) $r->grate, 2),
                'rcvdamt'    => $rcvdamt,
                'balance'    => $balance,
                'duedate'    => $this->formatDate($r->duedate),
                'custname'   => (string) $r->custname,
                'balwgt'     => round((float) $r->balwgt, 3),
                // editable fields (sent back on save)
                'alocamt'    => 0.0,
                'discamt'    => 0.0,
                'selected'   => false,
            ];
        })->all();
    }
}
