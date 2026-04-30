<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class WgtRcptPmntController extends Controller
{
    // ── Helpers ──

    private function trimUpper($v): string
    {
        return strtoupper(trim((string) $v));
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

    private function f(string $table, array $data): array
    {
        if (!$this->hasTable($table)) {
            return $data;
        }
        $cols = array_flip(array_map('strtolower', $this->columnList($table)));
        return array_filter(
            $data,
            static fn($v, $k) => isset($cols[strtolower((string) $k)]),
            ARRAY_FILTER_USE_BOTH
        );
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
        $next = $current + 1;
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

    private function touchCounter(string $code): void
    {
        if (!$this->hasTable('generali')) return;
        if (!DB::table('generali')->where('code', $code)->exists()) {
            DB::table('generali')->insert(['code' => $code, 'cvalue' => 0]);
        }
        DB::table('generali')->where('code', $code)->update(['cvalue' => DB::raw('cvalue + 1')]);
    }

    private function resolveGisemi(Request $request): int
    {
        $q = trim((string) $request->query('gisemi', ''));
        if ($q !== '' && is_numeric($q)) {
            return max(1, (int) $q);
        }
        $fromSession = (string) ($request->session()->get('semi')
            ?? $request->session()->get('control')
            ?? '2');
        return is_numeric($fromSession) ? max(1, (int) $fromSession) : 2;
    }

    private function normDate(string $d): ?string
    {
        $d = trim($d);
        if ($d === '') return null;
        $t = strtotime($d);
        if ($t === false || (int) date('Y', $t) < 1990) return null;
        return date('Y-m-d', $t);
    }

    private function json(array $payload, int $status = 200): JsonResponse
    {
        return response()->json($payload, $status);
    }

    private function logDelpart(string $message, int $control): void
    {
        if (!$this->hasTable('delpart')) return;
        DB::table('delpart')->insert($this->f('delpart', [
            'tdate' => date('Y-m-d'),
            'part' => mb_substr($message, 0, 60),
            'control' => $control,
            'updtdate' => date('Y-m-d'),
            'updttime' => date('H:i:s'),
        ]));
    }

    private function adjustItemStock(
        string $code,
        int $qty,
        float $weight,
        float $stonewgt,
        string $stktype,
        int $control
    ): void {
        $updates = $control === 1
            ? [
                'qty' => DB::raw('qty + (' . $qty . ')'),
                'weight' => DB::raw('weight + (' . $weight . ')'),
                'qtyb' => DB::raw('qtyb + (' . $qty . ')'),
                'weightb' => DB::raw('weightb + (' . $weight . ')'),
                'stonewgt' => DB::raw('stonewgt + (' . $stonewgt . ')'),
                'stonewgtb' => DB::raw('stonewgtb + (' . $stonewgt . ')'),
            ]
            : [
                'qtyb' => DB::raw('qtyb + (' . $qty . ')'),
                'weightb' => DB::raw('weightb + (' . $weight . ')'),
                'stonewgtb' => DB::raw('stonewgtb + (' . $stonewgt . ')'),
            ];

        DB::table('items')
            ->whereRaw('UPPER(TRIM(code)) = ?', [$code])
            ->update($this->f('items', $updates));

        if ($stktype === '' || !$this->hasTable('itemsstk')) return;

        $exists = DB::table('itemsstk')
            ->where('code', $code)
            ->where('stktype', $stktype)
            ->exists();

        if (!$exists) {
            DB::table('itemsstk')->insert($this->f('itemsstk', [
                'code' => $code,
                'stktype' => $stktype,
                'qty' => 0, 'weight' => 0, 'stonewgt' => 0,
                'qtyb' => 0, 'weightb' => 0, 'stonewgtb' => 0,
            ]));
        }

        $stkUpdates = $control === 1
            ? [
                'qty' => DB::raw('qty + (' . $qty . ')'),
                'weight' => DB::raw('weight + (' . $weight . ')'),
                'qtyb' => DB::raw('qtyb + (' . $qty . ')'),
                'weightb' => DB::raw('weightb + (' . $weight . ')'),
                'stonewgt' => DB::raw('stonewgt + (' . $stonewgt . ')'),
                'stonewgtb' => DB::raw('stonewgtb + (' . $stonewgt . ')'),
            ]
            : [
                'qtyb' => DB::raw('qtyb + (' . $qty . ')'),
                'weightb' => DB::raw('weightb + (' . $weight . ')'),
                'stonewgtb' => DB::raw('stonewgtb + (' . $stonewgt . ')'),
            ];

        DB::table('itemsstk')
            ->where('code', $code)
            ->where('stktype', $stktype)
            ->update($this->f('itemsstk', $stkUpdates));
    }

    // ── Page ──

    public function index(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }
        return view('wgt-rcpt-pmnt.index');
    }

    // ── API dispatch ──

    public function api(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return $this->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        $action = strtolower(trim((string) $request->input('action', '')));
        $control = $this->resolveGisemi($request);

        switch ($action) {
            case 'init':
                return $this->actionInit($request, $control);
            case 'account_search':
                return $this->actionAccountSearch($request);
            case 'item_search':
                return $this->actionItemSearch($request);
            case 'item_load':
                return $this->actionItemLoad($request);
            case 'save':
                return $this->actionSave($request, $control);
            case 'load':
                return $this->actionLoad($request);
            case 'list':
                return $this->actionList($request);
            case 'delete':
                return $this->actionDelete($request, $control);
            case 'navigate':
                return $this->actionNavigate($request);
            default:
                return $this->json(['success' => false, 'error' => 'Invalid action'], 400);
        }
    }

    // ── Action: init ──

    private function actionInit(Request $request, int $control): JsonResponse
    {
        $counterCode = ($control === 1) ? 'WTRANB' : 'WTRANE';
        $prefix = ($control === 1) ? 'WRB/' : 'WRE/';
        $nextNum = $this->genInt($counterCode) + 1;
        $nextDocno = $prefix . str_pad($nextNum, 5, '0', STR_PAD_LEFT);

        $salesmen = [];
        if ($this->hasTable('sman')) {
            $salesmen = DB::table('sman')
                ->selectRaw('TRIM(code) AS code, TRIM(name) AS name')
                ->orderBy('code')
                ->get()
                ->all();
        }

        $stockTypes = [];
        $defaultStkType = '';
        if ($this->hasTable('stktype')) {
            $stockTypes = DB::table('stktype')
                ->orderByDesc('def')->orderBy('code')
                ->get(['code', 'name', 'def'])
                ->all();
            foreach ($stockTypes as $row) {
                if ((int) ($row->def ?? 0) === 1) {
                    $defaultStkType = trim((string) ($row->code ?? ''));
                    break;
                }
            }
            if ($defaultStkType === '' && isset($stockTypes[0])) {
                $defaultStkType = trim((string) ($stockTypes[0]->code ?? ''));
            }
        }

        // Gold rate from generald
        $grate = 0;
        if ($this->hasTable('generald')) {
            $grate = (float) (DB::table('generald')->where('code', 'GRATE')->value('cvalue') ?? 0);
        }

        return $this->json([
            'success' => true,
            'nextDocno' => $nextDocno,
            'control' => $control,
            'salesmen' => $salesmen,
            'stockTypes' => $stockTypes,
            'defaultStkType' => $defaultStkType,
            'grate' => $grate,
            'today' => date('Y-m-d'),
        ]);
    }

    // ── Action: account_search ──

    private function actionAccountSearch(Request $request): JsonResponse
    {
        if (!$this->hasTable('accountm')) {
            return $this->json(['success' => true, 'data' => []]);
        }

        $search = trim((string) $request->input('search', ''));
        $query = DB::table('accountm')
            ->selectRaw('TRIM(accode) AS accode, TRIM(name) AS name')
            ->when($this->hasCol('accountm', 'removed'), function ($q) {
                $q->whereRaw('(removed <> 1 OR removed IS NULL)');
            });

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like) {
                $q->where('accode', 'like', $like)
                  ->orWhere('name', 'like', $like);
            });
        }

        $rows = $query->orderBy('accode')->limit(200)->get()->all();
        return $this->json(['success' => true, 'data' => $rows]);
    }

    // ── Action: item_search ──

    private function actionItemSearch(Request $request): JsonResponse
    {
        if (!$this->hasTable('items')) {
            return $this->json(['success' => true, 'data' => []]);
        }

        $search = $this->trimUpper((string) $request->input('search', ''));
        if ($search === '') {
            return $this->json(['success' => true, 'data' => []]);
        }

        $like = '%' . $search . '%';
        $rows = DB::table('items')
            ->selectRaw('TRIM(code) AS code, TRIM(name) AS name')
            ->where('disabled', 0)
            ->where(function ($q) use ($like) {
                $q->where('code', 'like', $like)
                  ->orWhere('name', 'like', $like);
            })
            ->orderBy('code')
            ->limit(50)
            ->get()
            ->all();

        return $this->json(['success' => true, 'data' => $rows]);
    }

    // ── Action: item_load ──

    private function actionItemLoad(Request $request): JsonResponse
    {
        $code = $this->trimUpper((string) $request->input('code', ''));
        if ($code === '' || !$this->hasTable('items')) {
            return $this->json(['success' => false, 'error' => 'Item not found'], 404);
        }

        $row = DB::table('items')
            ->whereRaw('UPPER(TRIM(code)) = ?', [$code])
            ->where('disabled', 0)
            ->first(['code', 'name', 'defstktype', 'stktouch']);

        if (!$row) {
            return $this->json(['success' => false, 'error' => 'Item not found'], 404);
        }

        return $this->json([
            'success' => true,
            'item' => [
                'code' => trim((string) ($row->code ?? '')),
                'name' => trim((string) ($row->name ?? '')),
                'defstktype' => trim((string) ($row->defstktype ?? '')),
                'stktouch' => (float) ($row->stktouch ?? 0),
            ],
        ]);
    }

    // ── Action: save ──

    private function actionSave(Request $request, int $control): JsonResponse
    {
        $editSlno = (int) $request->input('edit_slno', 0);

        $icode = $this->trimUpper((string) $request->input('icode', ''));
        $tdate = $this->normDate((string) $request->input('tdate', '')) ?? date('Y-m-d');
        $grate = (float) $request->input('grate', 0);
        $pcode = $this->trimUpper((string) $request->input('pcode', ''));
        $pname = trim((string) $request->input('pname', ''));
        $smcode = $this->trimUpper((string) $request->input('smcode', ''));
        $qty = (int) $request->input('qty', 0);
        $weight = round((float) $request->input('weight', 0), 3);
        $stwgt = round((float) $request->input('stwgt', 0), 3);
        $touch = round((float) $request->input('touch', 0), 3);
        $ctouch = round((float) $request->input('ctouch', 0), 3);
        $stktype = $this->trimUpper((string) $request->input('stktype', ''));
        $ttype = strtoupper(trim((string) $request->input('ttype', 'R')));
        $note = trim((string) $request->input('note', ''));

        if ($icode === '') {
            return $this->json(['success' => false, 'error' => 'Item code is required'], 422);
        }
        if (!DB::table('items')->whereRaw('UPPER(TRIM(code)) = ?', [$icode])->where('disabled', 0)->exists()) {
            return $this->json(['success' => false, 'error' => 'Item does not exist'], 422);
        }
        if ($qty <= 0) {
            return $this->json(['success' => false, 'error' => 'Qty must be greater than 0'], 422);
        }
        if ($weight <= 0) {
            return $this->json(['success' => false, 'error' => 'Weight must be greater than 0'], 422);
        }
        if (!in_array($ttype, ['R', 'P'], true)) {
            return $this->json(['success' => false, 'error' => 'Type must be Receipt or Payment'], 422);
        }

        // Net weight = ((weight - stwgt) * touch) / ctouch
        $netwgt = 0;
        if ($ctouch > 0) {
            $netwgt = round((($weight - $stwgt) * $touch) / $ctouch, 3);
        }

        DB::beginTransaction();
        try {
            // If editing, reverse old stock first and delete old records
            if ($editSlno > 0) {
                $oldWgt = DB::table('wgtrcptpmnt')->where('slno', $editSlno)->first();
                $oldAdj = DB::table('itemadj')->where('slno', $editSlno)->first();

                if ($oldAdj) {
                    $this->reverseStock($oldAdj, $control);
                    DB::table('itemadj')->where('slno', $editSlno)->delete();
                }
                if ($oldWgt) {
                    DB::table('wgtrcptpmnt')->where('slno', $editSlno)->delete();
                }

                $this->logDelpart('Wgt Rcpt/Pmnt #' . $editSlno . ' edited', $control);
            }

            $this->touchCounter('BLOCK');
            $slno = $this->nextSerialNo();

            // Generate doc number
            $counterCode = ($control === 1) ? 'WTRANB' : 'WTRANE';
            $prefix = ($control === 1) ? 'WRB/' : 'WRE/';
            $docNum = $this->incrementGenInt($counterCode);
            $docno = $prefix . str_pad($docNum, 5, '0', STR_PAD_LEFT);

            // Determine itemadj from/to based on Receipt or Payment
            if ($ttype === 'R') {
                $fromcode = '';
                $tocode = $icode;
                $fromqty = 0; $toqty = $qty;
                $fromwgt = 0; $towgt = $weight;
                $fromstwgt = 0; $tostwgt = $stwgt;
            } else {
                $fromcode = $icode;
                $tocode = '';
                $fromqty = $qty; $toqty = 0;
                $fromwgt = $weight; $towgt = 0;
                $fromstwgt = $stwgt; $tostwgt = 0;
            }

            $ttime = date('H:i:s');
            $userCode = (string) $request->session()->get('user_code', '');

            // Insert itemadj
            DB::table('itemadj')->insert($this->f('itemadj', [
                'slno' => $slno,
                'fromcode' => $fromcode,
                'fromqty' => $fromqty,
                'fromwgt' => $fromwgt,
                'fromcost' => 0,
                'tocode' => $tocode,
                'toqty' => $toqty,
                'towgt' => $towgt,
                'tocost' => 0,
                'particular' => mb_substr($note !== '' ? $note : ($ttype === 'R' ? 'Wgt Receipt' : 'Wgt Payment'), 0, 30),
                'tdate' => $tdate,
                'ttime' => $ttime,
                'control' => $control,
                'smcode' => $smcode,
                'fromstwgt' => $fromstwgt,
                'tostwgt' => $tostwgt,
                'al' => ' ',
                'fromstktype' => ($ttype === 'P') ? $stktype : '',
                'tostktype' => ($ttype === 'R') ? $stktype : '',
                'ichange' => 0,
                'fromstamt' => 0,
                'tostamt' => 0,
                'ic' => $userCode,
                'fromstktouch' => ($ttype === 'P') ? $touch : 0,
                'tostktouch' => ($ttype === 'R') ? $touch : 0,
            ]));

            // Insert wgtrcptpmnt
            DB::table('wgtrcptpmnt')->insert($this->f('wgtrcptpmnt', [
                'slno' => $slno,
                'docno' => $docno,
                'tdate' => $tdate,
                'grate' => $grate,
                'pcode' => $pcode,
                'pname' => $pname,
                'icode' => $icode,
                'qty' => $qty,
                'weight' => $weight,
                'stwgt' => $stwgt,
                'stktype' => $stktype,
                'ttype' => $ttype,
                'note' => mb_substr($note, 0, 60),
                'control' => $control,
                'smcode' => $smcode,
                'netwgt' => $netwgt,
                'touch' => $touch,
                'ctouch' => $ctouch,
                'finamt' => 0,
                'rpamt' => 0,
                'intperc' => 0,
                'intamt' => 0,
                'fin' => '',
                'pend' => '',
                'idocno' => '',
            ]));

            // Adjust item stock
            if ($ttype === 'R') {
                $this->adjustItemStock($icode, $qty, $weight, $stwgt, $stktype, $control);
            } else {
                $this->adjustItemStock($icode, -$qty, -$weight, -$stwgt, $stktype, $control);
            }

            DB::commit();

            return $this->json([
                'success' => true,
                'message' => ($ttype === 'R' ? 'Receipt' : 'Payment') . ' saved. Doc: ' . $docno,
                'slno' => $slno,
                'docno' => $docno,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->json(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    // ── Action: load ──

    private function actionLoad(Request $request): JsonResponse
    {
        $slno = (int) $request->input('slno', 0);
        if ($slno <= 0) {
            return $this->json(['success' => false, 'error' => 'Invalid Sl.No'], 422);
        }

        if (!$this->hasTable('wgtrcptpmnt')) {
            return $this->json(['success' => false, 'error' => 'Table not found'], 404);
        }

        $row = DB::table('wgtrcptpmnt')->where('slno', $slno)->first();
        if (!$row) {
            return $this->json(['success' => false, 'error' => 'Record not found'], 404);
        }

        $itemName = '';
        if ($this->hasTable('items')) {
            $itemName = trim((string) (DB::table('items')
                ->whereRaw('UPPER(TRIM(code)) = ?', [$this->trimUpper((string) ($row->icode ?? ''))])
                ->value('name') ?? ''));
        }

        return $this->json([
            'success' => true,
            'record' => [
                'slno' => (int) ($row->slno ?? 0),
                'docno' => trim((string) ($row->docno ?? '')),
                'tdate' => (string) ($row->tdate ?? ''),
                'grate' => (float) ($row->grate ?? 0),
                'pcode' => trim((string) ($row->pcode ?? '')),
                'pname' => trim((string) ($row->pname ?? '')),
                'icode' => trim((string) ($row->icode ?? '')),
                'iname' => $itemName,
                'qty' => (int) ($row->qty ?? 0),
                'weight' => round((float) ($row->weight ?? 0), 3),
                'stwgt' => round((float) ($row->stwgt ?? 0), 3),
                'stktype' => trim((string) ($row->stktype ?? '')),
                'ttype' => trim((string) ($row->ttype ?? 'R')),
                'note' => trim((string) ($row->note ?? '')),
                'control' => (int) ($row->control ?? 1),
                'smcode' => trim((string) ($row->smcode ?? '')),
                'netwgt' => round((float) ($row->netwgt ?? 0), 3),
                'touch' => round((float) ($row->touch ?? 0), 3),
                'ctouch' => round((float) ($row->ctouch ?? 0), 3),
            ],
        ]);
    }

    // ── Action: list ──

    private function actionList(Request $request): JsonResponse
    {
        if (!$this->hasTable('wgtrcptpmnt')) {
            return $this->json(['success' => true, 'data' => []]);
        }

        $search = trim((string) $request->input('search', ''));
        $dateFrom = $this->normDate((string) $request->input('date_from', ''));
        $dateTo = $this->normDate((string) $request->input('date_to', ''));

        $query = DB::table('wgtrcptpmnt as w')
            ->leftJoin('items as i', DB::raw('UPPER(TRIM(i.code))'), '=', DB::raw('UPPER(TRIM(w.icode))'));

        if ($dateFrom && $dateTo) {
            $query->whereBetween('w.tdate', [$dateFrom, $dateTo]);
        } elseif ($dateFrom) {
            $query->where('w.tdate', '>=', $dateFrom);
        } elseif ($dateTo) {
            $query->where('w.tdate', '<=', $dateTo);
        }

        if ($search !== '') {
            $like = '%' . strtoupper($search) . '%';
            $query->where(function ($q) use ($like, $search) {
                $q->where('w.docno', 'like', $like)
                  ->orWhere('w.icode', 'like', $like)
                  ->orWhere('w.pcode', 'like', $like)
                  ->orWhere('w.pname', 'like', $like)
                  ->orWhere('w.note', 'like', $like);
                if (is_numeric($search)) {
                    $q->orWhere('w.slno', '=', (int) $search);
                }
            });
        }

        $rows = $query->orderByDesc('w.slno')->limit(500)->get([
            'w.slno', 'w.docno', 'w.tdate', 'w.grate', 'w.pcode', 'w.pname',
            'w.icode', 'w.qty', 'w.weight', 'w.stwgt', 'w.stktype', 'w.ttype',
            'w.note', 'w.control', 'w.smcode', 'w.netwgt', 'w.touch', 'w.ctouch',
            'i.name as iname',
        ]);

        $data = $rows->map(function ($r) {
            return [
                'slno' => (int) ($r->slno ?? 0),
                'docno' => trim((string) ($r->docno ?? '')),
                'tdate' => (string) ($r->tdate ?? ''),
                'grate' => (float) ($r->grate ?? 0),
                'pcode' => trim((string) ($r->pcode ?? '')),
                'pname' => trim((string) ($r->pname ?? '')),
                'icode' => trim((string) ($r->icode ?? '')),
                'iname' => trim((string) ($r->iname ?? '')),
                'qty' => (int) ($r->qty ?? 0),
                'weight' => round((float) ($r->weight ?? 0), 3),
                'stwgt' => round((float) ($r->stwgt ?? 0), 3),
                'stktype' => trim((string) ($r->stktype ?? '')),
                'ttype' => trim((string) ($r->ttype ?? 'R')),
                'note' => trim((string) ($r->note ?? '')),
                'netwgt' => round((float) ($r->netwgt ?? 0), 3),
                'touch' => round((float) ($r->touch ?? 0), 3),
                'ctouch' => round((float) ($r->ctouch ?? 0), 3),
            ];
        })->values();

        return $this->json(['success' => true, 'data' => $data]);
    }

    // ── Action: delete (cancel) ──

    private function actionDelete(Request $request, int $control): JsonResponse
    {
        $slno = (int) $request->input('slno', 0);
        if ($slno <= 0) {
            return $this->json(['success' => false, 'error' => 'Invalid Sl.No'], 422);
        }

        $oldAdj = DB::table('itemadj')->where('slno', $slno)->first();
        if (!$oldAdj) {
            return $this->json(['success' => false, 'error' => 'Record not found in itemadj'], 404);
        }

        $gisemi = (int) ($oldAdj->control ?? $control);

        DB::beginTransaction();
        try {
            // Reverse stock
            $this->reverseStock($oldAdj, $gisemi);

            // Delete from both tables
            DB::table('itemadj')->where('slno', $slno)->delete();
            if ($this->hasTable('wgtrcptpmnt')) {
                DB::table('wgtrcptpmnt')->where('slno', $slno)->delete();
            }

            // Log
            $fromcode = trim((string) ($oldAdj->fromcode ?? ''));
            $tocode = trim((string) ($oldAdj->tocode ?? ''));
            $itemCode = $fromcode !== '' ? $fromcode : $tocode;
            $this->logDelpart('Wgt Rcpt/Pmnt(' . $itemCode . ') Canceled', $gisemi);

            DB::commit();
            return $this->json(['success' => true, 'message' => 'Record #' . $slno . ' cancelled']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->json(['success' => false, 'error' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // ── Action: navigate ──

    private function actionNavigate(Request $request): JsonResponse
    {
        if (!$this->hasTable('wgtrcptpmnt')) {
            return $this->json(['success' => false, 'error' => 'Table not found'], 404);
        }

        $currentSlno = (int) $request->input('slno', 0);
        $direction = strtolower(trim((string) $request->input('direction', 'next')));

        if ($direction === 'prev') {
            $row = DB::table('wgtrcptpmnt')
                ->where('slno', '<', $currentSlno)
                ->orderByDesc('slno')
                ->first(['slno']);
        } else {
            $row = DB::table('wgtrcptpmnt')
                ->where('slno', '>', $currentSlno)
                ->orderBy('slno')
                ->first(['slno']);
        }

        if (!$row) {
            return $this->json(['success' => false, 'error' => 'No more records']);
        }

        // Load the full record via actionLoad
        $request->merge(['slno' => (int) $row->slno]);
        return $this->actionLoad($request);
    }

    // ── Stock reversal helper ──

    private function reverseStock(object $oldAdj, int $control): void
    {
        $fromcode = $this->trimUpper((string) ($oldAdj->fromcode ?? ''));
        $tocode = $this->trimUpper((string) ($oldAdj->tocode ?? ''));
        $fromqty = (int) ($oldAdj->fromqty ?? 0);
        $toqty = (int) ($oldAdj->toqty ?? 0);
        $fromwgt = (float) ($oldAdj->fromwgt ?? 0);
        $towgt = (float) ($oldAdj->towgt ?? 0);
        $fromstwgt = (float) ($oldAdj->fromstwgt ?? 0);
        $tostwgt = (float) ($oldAdj->tostwgt ?? 0);
        $fromstktype = $this->trimUpper((string) ($oldAdj->fromstktype ?? ''));
        $tostktype = $this->trimUpper((string) ($oldAdj->tostktype ?? ''));

        // Reverse FROM: add back what was subtracted
        if ($fromcode !== '' && $fromcode !== 'AL') {
            $this->adjustItemStock($fromcode, $fromqty, $fromwgt, $fromstwgt, $fromstktype, $control);
        }
        // Reverse TO: subtract what was added
        if ($tocode !== '' && $tocode !== 'AL') {
            $this->adjustItemStock($tocode, -$toqty, -$towgt, -$tostwgt, $tostktype, $control);
        }
    }
}
