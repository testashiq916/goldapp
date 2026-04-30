<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class SupplierBillwisePaymentController extends Controller
{
    // ── Helpers ──────────────────────────────────────────────────────────────

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
            $n = $this->incrementGenInt('VCHNOPB');
            return 'VPB/' . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
        }
        $n = $this->incrementGenInt('VCHNOPE');
        return 'VPE/' . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
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

    private function nextVchnoPreview(int $control): string
    {
        $code = ($control === 1) ? 'VCHNOPB' : 'VCHNOPE';
        $pfx  = ($control === 1) ? 'VPB/' : 'VPE/';
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
        return view('account.supplier-billwise-payment');
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
            'load_supplier'   => $this->actionLoadSupplier($request, $control),
            'supplier_search' => $this->actionSupplierSearch($request),
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

        return $this->ok([
            'vchno'   => $vchno,
            'today'   => $today,
            'grate'   => $grate,
            'control' => $control,
        ]);
    }

    private function actionLoadSupplier(Request $request, int $control): JsonResponse
    {
        $code  = $this->trimUpper($request->input('code', ''));
        $grate = (float) $request->input('grate', 0);

        if ($code === '') return $this->err('Supplier code required');
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
            // For supplier: balance = opbal + daySum (positive = we owe them)
            $balance = $opbal + $daySum;
        }

        // Retrieve bill list
        $bills = $this->getBills($code, $control);

        return $this->ok([
            'name'    => (string) $client->name,
            'balance' => round($balance, 2),
            'bills'   => $bills,
        ]);
    }

    private function actionSupplierSearch(Request $request): JsonResponse
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
        $suppcode = $this->trimUpper($request->input('suppcode', ''));
        $part     = trim((string) $request->input('part', ''));
        $cbcode   = trim((string) $request->input('cbcode', 'CASH'));
        $rawItems = $request->input('items', []);
        $items    = is_string($rawItems) ? (json_decode($rawItems, true) ?? []) : (is_array($rawItems) ? $rawItems : []);

        if (!$tdate) return $this->err('Invalid date');
        if ($suppcode === '') return $this->err('No supplier selected');
        if ($cbcode === '') $cbcode = 'CASH';

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
                $tdate, $grate, $suppcode, $part, $cbcode,
                $items, $control, &$vchno
            ): void {
                $lslno      = $this->nextSerialNo();
                $vchno      = $this->generateVchno($control);
                $paidTotal  = 0.0;
                $discTotal  = 0.0;
                $hasAllocations = false;

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

                    $effGrate = max($grate, $billGrate);
                    if ($alocamt == 0 && $discamt != 0) $effGrate = 0;

                    // Update purchasem.pamtafter
                    if ($islno > 0 && $this->hasCol('purchasem', 'pamtafter')) {
                        $upd = ['pamtafter' => DB::raw("pamtafter + {$alocamt}")];
                        if ($this->hasCol('purchasem', 'duedate')) {
                            $upd['duedate'] = $duedate;
                        }
                        DB::table('purchasem')->where('slno', $islno)->update($upd);
                    }

                    // Insert into collection
                    if ($this->hasTable('collection')) {
                        $colData = $this->filterCols('collection', [
                            'slno'    => $lslno,
                            'code'    => $suppcode,
                            'tdate'   => $tdate,
                            'billno'  => $billno,
                            'tranamt' => $alocamt,
                            'discount'=> $discamt,
                            'duedate' => $duedate,
                            'control' => $control,
                            'islno'   => $islno,
                            'grate'   => $effGrate,
                            'grate2'  => $grate,
                            'cbcode'  => $cbcode,
                        ]);
                        if ($colData) DB::table('collection')->insert($colData);
                    }

                    $paidTotal += $alocamt;
                    $discTotal += $discamt;
                }

                if (!$hasAllocations && $paidTotal == 0 && $discTotal == 0) {
                    return;
                }

                // Particulars
                if ($part === '') {
                    $part = ($paidTotal == 0 && $discTotal != 0)
                        ? 'Adjustment Entry ' . $suppcode . ' - ' . $vchno
                        : 'Payment to ' . $suppcode . ' - ' . $vchno;
                }
                $part = mb_substr($part, 0, 40);

                // daybookpart
                $dbpData = $this->filterCols('daybookpart', [
                    'slno'      => $lslno,
                    'vchno'     => $vchno,
                    'particular'=> $part,
                    'tdate'     => $tdate,
                    'ic'        => '',
                    'uid'       => '',
                    'control'   => $control,
                ]);
                if ($dbpData) DB::table('daybookpart')->insert($dbpData);

                // Daybook double entry (payment)
                if ($paidTotal != 0) {
                    $dbBase = ['slno' => $lslno, 'tdate' => $tdate, 'control' => $control];

                    // Supplier account: negative (debit = reduce payable)
                    $row1 = $this->filterCols('daybook', array_merge($dbBase, [
                        'accode' => $suppcode,
                        'amount' => -$paidTotal,
                        'sno'    => 1,
                    ]));
                    if ($row1) DB::table('daybook')->insert($row1);

                    // Cash/Bank: positive (credit = outflow)
                    $row2 = $this->filterCols('daybook', array_merge($dbBase, [
                        'accode' => $cbcode,
                        'amount' => $paidTotal,
                        'sno'    => 2,
                    ]));
                    if ($row2) DB::table('daybook')->insert($row2);
                }

                // Discount journal entry
                if ($discTotal != 0) {
                    $lslno2 = $this->nextSerialNo();
                    $vchno2 = $this->generateJournalVchno($control);
                    $part2  = 'Adjustment Entry ' . $suppcode . ' - ' . $vchno2;
                    $part2  = mb_substr($part2, 0, 40);

                    $dbp2 = $this->filterCols('daybookpart', [
                        'slno'      => $lslno2,
                        'vchno'     => $vchno2,
                        'particular'=> $part2,
                        'tdate'     => $tdate,
                        'ic'        => '',
                        'uid'       => '',
                        'control'   => $control,
                    ]);
                    if ($dbp2) DB::table('daybookpart')->insert($dbp2);

                    $dbBase2 = ['slno' => $lslno2, 'tdate' => $tdate, 'control' => $control];
                    $d1 = $this->filterCols('daybook', array_merge($dbBase2, [
                        'accode' => $suppcode, 'amount' => -$discTotal, 'sno' => 1,
                    ]));
                    if ($d1) DB::table('daybook')->insert($d1);

                    $d2 = $this->filterCols('daybook', array_merge($dbBase2, [
                        'accode' => 'PDISC', 'amount' => $discTotal, 'sno' => 2,
                    ]));
                    if ($d2) DB::table('daybook')->insert($d2);
                }
            });
        } catch (\Throwable $e) {
            return $this->err('Save failed: ' . $e->getMessage());
        }

        return $this->ok([
            'vchno'   => $vchno,
            'message' => 'Payment saved successfully',
        ]);
    }

    // ── Internal helpers ──────────────────────────────────────────────────────

    private function getBills(string $code, int $control): array
    {
        if (!$this->hasTable('purchasem')) return [];

        $collectionExists = $this->hasTable('collection');
        $hasControl       = $this->hasCol('purchasem', 'control');
        $hasDuedate       = $this->hasCol('purchasem', 'duedate');
        $hasNetamt        = $this->hasCol('purchasem', 'netamt');
        $hasGrate         = $this->hasCol('purchasem', 'rate');
        $hasPamt          = $this->hasCol('purchasem', 'pamt');
        $hasTdate         = $this->hasCol('purchasem', 'tdate');

        $amtExpr = $hasNetamt ? 'COALESCE(p.netamt,0)' : 'COALESCE(p.billamt,0)';

        $selectRaw = "
            p.slno,
            " . ($hasTdate ? 'p.tdate' : "''") . " AS tdate,
            COALESCE(p.docno,'') AS docno,
            COALESCE(p.billno,'') AS billno,
            {$amtExpr} AS netamt,
            " . ($hasGrate  ? 'COALESCE(p.rate,0)' : '0') . " AS grate,
            " . ($hasPamt   ? 'COALESCE(p.pamt,0)' : '0') . " AS pamt,
            " . ($hasDuedate ? 'COALESCE(p.duedate, p.tdate)' : ($hasTdate ? 'p.tdate' : "''")) . " AS duedate,
            COALESCE(p.name,'') AS suppname
        ";

        if ($collectionExists) {
            $selectRaw .= ",
            COALESCE((SELECT SUM(c.tranamt) FROM collection c WHERE c.islno = p.slno AND c.control <= {$control}),0) AS collectedamt,
            COALESCE((SELECT SUM(c.discount) FROM collection c WHERE c.islno = p.slno AND c.control <= {$control}),0) AS tdiscamt";
        } else {
            $selectRaw .= ", 0 AS collectedamt, 0 AS tdiscamt";
        }

        $query = DB::table('purchasem AS p')->selectRaw(trim($selectRaw))
            ->whereRaw('UPPER(TRIM(p.suppcode)) = ?', [$code]);

        if ($hasControl) {
            $query->where('p.control', '<=', $control);
        }

        if ($hasTdate) {
            $query->orderBy('p.tdate')->orderBy('p.slno');
        } else {
            $query->orderBy('p.slno');
        }

        $rows = $query->get();

        return $rows->map(function ($r) {
            $netamt     = (float) $r->netamt;
            $tdiscamt   = (float) $r->tdiscamt;
            $pamt       = (float) $r->pamt;
            $collected  = (float) $r->collectedamt;
            $netbillamt = $netamt - $tdiscamt;
            $paidamt    = round($pamt + $collected, 2);
            $balance    = round($netbillamt, 2) - $paidamt;

            return [
                'slno'       => (int)    $r->slno,
                'tdate'      => $this->formatDate($r->tdate),
                'docno'      => trim((string) $r->docno),
                'billno'     => trim((string) $r->billno),
                'netamt'     => round($netamt, 2),
                'grate'      => round((float) $r->grate, 2),
                'paidamt'    => $paidamt,
                'balance'    => $balance,
                'duedate'    => $this->formatDate($r->duedate),
                'suppname'   => trim((string) $r->suppname),
                'alocamt'    => 0.0,
                'discamt'    => 0.0,
                'selected'   => false,
            ];
        })->all();
    }
}
