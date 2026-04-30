<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Stock Suspense (Model) Entry — Issue / Receipt / Cancel / Reprint.
 *
 * Legacy PB window: w_modeltrans
 * Tables: modelm (master), itemadj (stock adjustment), items, itemsstk, barcode, delpart
 *
 * gr = G → Issue mode (item → MOD)
 * gr = R → Receipt mode (MOD → item)
 * Return checkbox flips the direction.
 */
class StockSuspenseEntryController extends Controller
{
    private function json(array $p, int $s = 200): JsonResponse { return response()->json($p, $s); }

    private function f(string $table, array $data): array
    {
        if (!$this->hasTable($table)) return $data;
        $cols = array_flip(array_map('strtolower', $this->columnList($table)));
        return array_filter($data, static fn($v, $k) => isset($cols[strtolower((string)$k)]), ARRAY_FILTER_USE_BOTH);
    }

    private function nextSerialNo(): int
    {
        if (!$this->hasTable('generali')) return 1;
        if (!DB::table('generali')->where('code', 'SERIALNO')->exists())
            DB::table('generali')->insert(['code' => 'SERIALNO', 'cvalue' => 0]);
        $current = (int)(DB::table('generali')->where('code', 'SERIALNO')->value('cvalue') ?? 0);
        $maxUsed = 0;
        foreach (['salesm', 'salesrm', 'purchasem', 'purchaserm', 'daybook', 'daybookpart', 'orderm', 'smithm', 'refinerym', 'repairm'] as $table) {
            if ($this->hasTable($table) && Schema::hasColumn($table, 'slno')) {
                $maxUsed = max($maxUsed, (int)(DB::table($table)->max('slno') ?? 0));
            }
        }
        $next = max($current, $maxUsed) + 1;
        DB::table('generali')->where('code', 'SERIALNO')->update(['cvalue' => $next]);
        return $next;
    }

    private function touchCounter(string $code): void
    {
        if (!$this->hasTable('generali')) return;
        if (!DB::table('generali')->where('code', $code)->exists())
            DB::table('generali')->insert(['code' => $code, 'cvalue' => 0]);
        DB::table('generali')->where('code', $code)->update(['cvalue' => DB::raw('cvalue + 1')]);
    }

    private function resolveControl(Request $request): int
    {
        $q = trim((string)$request->query('gisemi', ''));
        if ($q !== '' && is_numeric($q)) return max(1, (int)$q);
        $s = (string)($request->session()->get('semi') ?? $request->session()->get('control') ?? '2');
        return is_numeric($s) ? max(1, (int)$s) : 2;
    }

    /**
     * Adjust items stock (matches ItemAdjustmentController pattern).
     */
    private function adjustItemStock(string $code, int $qty, float $weight, float $stonewgt, string $stktype, int $control): void
    {
        if (!$this->hasTable('items')) return;

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

        DB::table('items')->whereRaw('TRIM(code) = ?', [$code])->update($this->f('items', $updates));

        if ($stktype !== '' && $this->hasTable('itemsstk')) {
            $exists = DB::table('itemsstk')->where('code', $code)->where('stktype', $stktype)->exists();
            if (!$exists) {
                DB::table('itemsstk')->insert($this->f('itemsstk', [
                    'code' => $code, 'stktype' => $stktype,
                    'qty' => 0, 'weight' => 0, 'stonewgt' => 0,
                    'qtyb' => 0, 'weightb' => 0, 'stonewgtb' => 0,
                ]));
            }
            DB::table('itemsstk')->where('code', $code)->where('stktype', $stktype)
                ->update($this->f('itemsstk', $updates));
        }
    }

    // ── Pages ──

    public function index(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) return redirect('/login');
        return view('stock-suspense-entry.index');
    }

    public function picker(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) return redirect('/login');
        return view('stock-suspense-entry.picker');
    }

    // ── API ──

    public function api(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code'))
            return $this->json(['success' => false, 'error' => 'Unauthorized'], 401);

        $action = strtolower(trim((string)$request->input('action', '')));
        $control = $this->resolveControl($request);

        return match ($action) {
            'init'          => $this->actionInit($request, $control),
            'item_search'   => $this->actionItemSearch($request),
            'item_load'     => $this->actionItemLoad($request),
            'barcode_load'  => $this->actionBarcodeLoad($request),
            'client_search' => $this->actionClientSearch($request),
            'save'          => $this->actionSave($request, $control),
            'load'          => $this->actionLoad($request),
            'list'          => $this->actionList($request),
            'delete'        => $this->actionDelete($request, $control),
            default         => $this->json(['success' => false, 'error' => 'Invalid action'], 400),
        };
    }

    // ── init ──

    private function actionInit(Request $request, int $control): JsonResponse
    {
        $salesmen = [];
        if ($this->hasTable('sman')) {
            $salesmen = DB::table('sman')->selectRaw('TRIM(code) AS code, TRIM(name) AS name')
                ->orderBy('code')->get()->all();
        }

        $stkTypes = [];
        if ($this->hasTable('stktype')) {
            $stkTypes = DB::table('stktype')->selectRaw('TRIM(code) AS code, TRIM(name) AS name')
                ->orderBy('code')->get()->all();
        }

        return $this->json([
            'success' => true,
            'salesmen' => $salesmen,
            'stkTypes' => $stkTypes,
            'today' => date('Y-m-d'),
            'control' => $control,
        ]);
    }

    // ── item_search ──

    private function actionItemSearch(Request $request): JsonResponse
    {
        if (!$this->hasTable('items')) return $this->json(['success' => true, 'data' => []]);
        $s = strtoupper(trim((string)$request->input('search', '')));
        if ($s === '') return $this->json(['success' => true, 'data' => []]);
        $like = '%' . $s . '%';
        $rows = DB::table('items')
            ->selectRaw('TRIM(code) AS code, TRIM(name) AS name')
            ->where(fn($q) => $q->where('code', 'like', $like)->orWhere('name', 'like', $like))
            ->orderBy('code')->limit(50)->get()->all();
        return $this->json(['success' => true, 'data' => $rows]);
    }

    // ── item_load ──

    private function actionItemLoad(Request $request): JsonResponse
    {
        $code = strtoupper(trim((string)$request->input('code', '')));
        if ($code === '' || !$this->hasTable('items'))
            return $this->json(['success' => false, 'error' => 'Item not found'], 404);

        $row = DB::table('items')->whereRaw('TRIM(code) = ?', [$code])
            ->first(['code', 'name', 'defstktype']);
        if (!$row) return $this->json(['success' => false, 'error' => 'Item not found'], 404);

        return $this->json([
            'success' => true,
            'item' => [
                'code' => trim((string)($row->code ?? '')),
                'name' => trim((string)($row->name ?? '')),
                'defstktype' => trim((string)($row->defstktype ?? '')),
            ],
        ]);
    }

    // ── barcode_load ──

    private function actionBarcodeLoad(Request $request): JsonResponse
    {
        $bcode = (int)$request->input('bcode', 0);
        if ($bcode <= 0 || !$this->hasTable('barcode'))
            return $this->json(['success' => false, 'error' => 'Barcode not found'], 404);

        $row = DB::table('barcode')->where('bcode', $bcode)
            ->first(['icode', 'qty', 'weight', 'stweight', 'stk']);
        if (!$row)
            return $this->json(['success' => false, 'error' => 'Barcode does not exist'], 404);

        // Also load item name
        $iname = '';
        $defstktype = '';
        if ($this->hasTable('items')) {
            $item = DB::table('items')->whereRaw('TRIM(code) = ?', [trim((string)($row->icode ?? ''))])
                ->first(['name', 'defstktype']);
            if ($item) {
                $iname = trim((string)($item->name ?? ''));
                $defstktype = trim((string)($item->defstktype ?? ''));
            }
        }

        return $this->json([
            'success' => true,
            'barcode' => [
                'icode' => trim((string)($row->icode ?? '')),
                'iname' => $iname,
                'qty' => (int)($row->qty ?? 0),
                'weight' => round((float)($row->weight ?? 0), 3),
                'stweight' => round((float)($row->stweight ?? 0), 3),
                'stk' => trim((string)($row->stk ?? '')),
                'defstktype' => $defstktype,
            ],
        ]);
    }

    // ── client_search ──

    private function actionClientSearch(Request $request): JsonResponse
    {
        if (!$this->hasTable('clients')) return $this->json(['success' => true, 'data' => []]);
        $s = trim((string)$request->input('search', ''));
        if ($s === '') return $this->json(['success' => true, 'data' => []]);
        $like = '%' . $s . '%';
        $rows = DB::table('clients')
            ->selectRaw('TRIM(code) AS code, TRIM(name) AS name')
            ->where(fn($q) => $q->where('code', 'like', $like)->orWhere('name', 'like', $like))
            ->orderBy('name')->limit(50)->get()->all();
        return $this->json(['success' => true, 'data' => $rows]);
    }

    // ── save ──

    private function actionSave(Request $request, int $control): JsonResponse
    {
        $gr = strtoupper(trim((string)$request->input('gr', 'G'))); // G=Issue mode, R=Receipt mode
        $isReturn = (bool)$request->input('is_return', false);
        $tdate = trim((string)$request->input('tdate', ''));
        if ($tdate === '' || strtotime($tdate) === false) $tdate = date('Y-m-d');
        else $tdate = date('Y-m-d', strtotime($tdate));

        $smcode = strtoupper(trim((string)$request->input('smcode', '')));
        $pcode = strtoupper(trim((string)$request->input('pcode', '')));
        $pname = trim((string)$request->input('pname', ''));
        $icode = strtoupper(trim((string)$request->input('icode', '')));
        $bcode = (int)$request->input('bcode', 0);
        $qty = (int)$request->input('qty', 0);
        $weight = round((float)$request->input('weight', 0), 3);
        $stwgt = round((float)$request->input('stwgt', 0), 3);
        $stktype = strtoupper(trim((string)$request->input('stktype', '')));
        $note = trim((string)$request->input('note', ''));
        $retSlno = (int)$request->input('ret_slno', 0); // return reference slno
        $userCode = (string)$request->session()->get('user_code', '');

        if ($icode === '') return $this->json(['success' => false, 'error' => 'Item code is required'], 422);
        if ($qty <= 0 && $weight <= 0) return $this->json(['success' => false, 'error' => 'Enter qty or weight'], 422);

        // Determine ir (Issue/Receipt direction)
        // gr=G: normal=I(issue), return=R(receipt)
        // gr=R: normal=R(receipt), return=I(issue)
        if ($gr === 'G') {
            $ir = $isReturn ? 'R' : 'I';
        } else {
            $ir = $isReturn ? 'I' : 'R';
        }

        // Pending logic
        $pend = 'Y';
        if ($retSlno > 0) $pend = 'N';
        if ($gr === 'G' && $ir === 'R') $pend = 'N';
        if ($gr === 'R' && $ir === 'I') $pend = 'N'; // corrected from PB: gr=R, sir=G → N

        if ($note === '') {
            $note = ($ir === 'I' ? 'Model Issued' : 'Model Returned');
        }

        $ttime = date('H:i:s');

        DB::beginTransaction();
        try {
            $this->touchCounter('BLOCK');

            $slno = $this->nextSerialNo();

            // If returning, mark old record as not pending
            if ($retSlno > 0 && $this->hasTable('modelm')) {
                DB::table('modelm')->where('slno', $retSlno)->update(['pend' => 'N']);
            }

            // Insert modelm
            if ($this->hasTable('modelm')) {
                DB::table('modelm')->insert($this->f('modelm', [
                    'slno' => $slno,
                    'tdate' => $tdate,
                    'pcode' => $pcode,
                    'pname' => $pname,
                    'smcode' => $smcode,
                    'icode' => $icode,
                    'bcode' => $bcode,
                    'qty' => $qty,
                    'weight' => $weight,
                    'stwgt' => $stwgt,
                    'stktype' => $stktype,
                    'ir' => $ir,
                    'pend' => $pend,
                    'islno' => $retSlno,
                    'note' => mb_substr($note, 0, 30),
                    'control' => $control,
                    'gr' => $gr,
                ]));
            }

            // Insert itemadj
            if ($this->hasTable('itemadj')) {
                if ($ir === 'I') {
                    // Issue: from item → to MOD
                    DB::table('itemadj')->insert($this->f('itemadj', [
                        'slno' => $slno,
                        'fromcode' => $icode,
                        'fromqty' => $qty,
                        'fromwgt' => $weight,
                        'tocode' => 'MOD',
                        'toqty' => $qty,
                        'towgt' => $weight,
                        'particular' => mb_substr($note, 0, 40),
                        'tdate' => $tdate,
                        'ttime' => $ttime,
                        'control' => $control,
                        'fromcost' => 0,
                        'tocost' => 0,
                        'smcode' => $smcode,
                        'fromstwgt' => $stwgt,
                        'tostwgt' => $stwgt,
                        'al' => ' ',
                        'fromstktype' => $stktype,
                        'tostktype' => $stktype,
                        'ichange' => 1,
                        'fromstamt' => 0,
                        'tostamt' => 0,
                        'ic' => $userCode,
                        'fromstktouch' => 0,
                        'tostktouch' => 0,
                        'bcode' => $bcode,
                    ]));
                } else {
                    // Receipt: from MOD → to item
                    DB::table('itemadj')->insert($this->f('itemadj', [
                        'slno' => $slno,
                        'fromcode' => 'MOD',
                        'fromqty' => $qty,
                        'fromwgt' => $weight,
                        'tocode' => $icode,
                        'toqty' => $qty,
                        'towgt' => $weight,
                        'particular' => mb_substr($note, 0, 40),
                        'tdate' => $tdate,
                        'ttime' => $ttime,
                        'control' => $control,
                        'fromcost' => 0,
                        'tocost' => 0,
                        'smcode' => $smcode,
                        'fromstwgt' => $stwgt,
                        'tostwgt' => $stwgt,
                        'al' => ' ',
                        'fromstktype' => $stktype,
                        'tostktype' => $stktype,
                        'ichange' => 1,
                        'fromstamt' => 0,
                        'tostamt' => 0,
                        'ic' => $userCode,
                        'fromstktouch' => 0,
                        'tostktouch' => 0,
                        'bcode' => $bcode,
                    ]));
                }
            }

            // Update items stock
            if ($ir === 'I') {
                // Issue: subtract from item, add to MOD
                $this->adjustItemStock($icode, -$qty, -$weight, -$stwgt, $stktype, $control);
                $this->adjustItemStock('MOD', $qty, $weight, $stwgt, $stktype, $control);
            } else {
                // Receipt: subtract from MOD, add to item
                $this->adjustItemStock('MOD', -$qty, -$weight, -$stwgt, $stktype, $control);
                $this->adjustItemStock($icode, $qty, $weight, $stwgt, $stktype, $control);
            }

            // Update barcode stk flag
            if ($bcode > 0 && $this->hasTable('barcode')) {
                $bstk = ($ir === 'I') ? 'N' : 'Y';
                DB::table('barcode')->where('bcode', $bcode)->update(['stk' => $bstk]);
            }

            DB::commit();

            $label = ($ir === 'I') ? 'Issued' : 'Received';
            return $this->json([
                'success' => true,
                'message' => 'Model ' . $label . ' saved. Slno: ' . $slno,
                'slno' => $slno,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->json(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    // ── load ──

    private function actionLoad(Request $request): JsonResponse
    {
        $slno = (int)$request->input('slno', 0);
        if ($slno <= 0 || !$this->hasTable('modelm'))
            return $this->json(['success' => false, 'error' => 'Record not found'], 404);

        $m = DB::table('modelm')->where('slno', $slno)->first();
        if (!$m) return $this->json(['success' => false, 'error' => 'Record not found'], 404);

        $iname = '';
        if ($this->hasTable('items')) {
            $iname = (string)(DB::table('items')->whereRaw('TRIM(code) = ?', [trim((string)($m->icode ?? ''))])->value('name') ?? '');
        }

        return $this->json([
            'success' => true,
            'record' => [
                'slno' => (int)$m->slno,
                'tdate' => (string)($m->tdate ?? ''),
                'pcode' => trim((string)($m->pcode ?? '')),
                'pname' => trim((string)($m->pname ?? '')),
                'smcode' => trim((string)($m->smcode ?? '')),
                'icode' => trim((string)($m->icode ?? '')),
                'iname' => trim($iname),
                'bcode' => (int)($m->bcode ?? 0),
                'qty' => (int)($m->qty ?? 0),
                'weight' => round((float)($m->weight ?? 0), 3),
                'stwgt' => round((float)($m->stwgt ?? 0), 3),
                'stktype' => trim((string)($m->stktype ?? '')),
                'ir' => trim((string)($m->ir ?? '')),
                'pend' => trim((string)($m->pend ?? '')),
                'islno' => (int)($m->islno ?? 0),
                'note' => trim((string)($m->note ?? '')),
                'control' => (int)($m->control ?? 0),
                'gr' => trim((string)($m->gr ?? '')),
            ],
        ]);
    }

    // ── list ──

    private function actionList(Request $request): JsonResponse
    {
        if (!$this->hasTable('modelm')) return $this->json(['success' => true, 'data' => []]);

        $search = trim((string)$request->input('search', ''));
        $pend = trim((string)$request->input('pend', ''));

        $query = DB::table('modelm')->orderByDesc('slno');

        if ($pend !== '') $query->where('pend', $pend);

        if ($search !== '') {
            $like = '%' . strtoupper($search) . '%';
            $query->where(fn($q) => $q
                ->where('icode', 'like', $like)
                ->orWhere('pcode', 'like', $like)
                ->orWhere('pname', 'like', $like)
                ->orWhere('note', 'like', $like)
                ->orWhereRaw('CAST(slno AS CHAR) LIKE ?', [$like])
            );
        }

        $rows = $query->limit(200)->get(['slno', 'tdate', 'pcode', 'pname', 'icode', 'qty', 'weight', 'ir', 'pend', 'gr', 'bcode', 'note']);

        $data = $rows->map(fn($r) => [
            'slno' => (int)$r->slno,
            'tdate' => (string)($r->tdate ?? ''),
            'pcode' => trim((string)($r->pcode ?? '')),
            'pname' => trim((string)($r->pname ?? '')),
            'icode' => trim((string)($r->icode ?? '')),
            'qty' => (int)($r->qty ?? 0),
            'weight' => round((float)($r->weight ?? 0), 3),
            'ir' => trim((string)($r->ir ?? '')),
            'pend' => trim((string)($r->pend ?? '')),
            'gr' => trim((string)($r->gr ?? '')),
            'note' => trim((string)($r->note ?? '')),
        ])->values();

        return $this->json(['success' => true, 'data' => $data]);
    }

    // ── delete (cancel) ──

    private function actionDelete(Request $request, int $control): JsonResponse
    {
        $slno = (int)$request->input('slno', 0);
        if ($slno <= 0) return $this->json(['success' => false, 'error' => 'Invalid slno'], 422);

        if (!$this->hasTable('modelm'))
            return $this->json(['success' => false, 'error' => 'modelm table not found'], 500);

        $m = DB::table('modelm')->where('slno', $slno)->first();
        if (!$m) return $this->json(['success' => false, 'error' => 'Record not found'], 404);

        $ir = trim((string)($m->ir ?? ''));
        $islno = (int)($m->islno ?? 0);
        $bcode = (int)($m->bcode ?? 0);
        $userId = (string)$request->session()->get('user_id', $request->session()->get('user_code', ''));

        DB::beginTransaction();
        try {
            // Reverse barcode stk flag
            if ($bcode > 0 && $this->hasTable('barcode')) {
                $bstk = ($ir === 'I') ? 'Y' : 'N';
                DB::table('barcode')->where('bcode', $bcode)->update(['stk' => $bstk]);
            }

            // If this was a return, re-mark the original as pending
            if ($islno > 0) {
                DB::table('modelm')->where('slno', $islno)->update(['pend' => 'Y']);
            }

            // Delete from modelm
            DB::table('modelm')->where('slno', $slno)->delete();

            // Reverse stock from itemadj
            if ($this->hasTable('itemadj')) {
                $adj = DB::table('itemadj')->where('slno', $slno)->first();
                if ($adj) {
                    $fromcode = strtoupper(trim((string)($adj->fromcode ?? '')));
                    $tocode = strtoupper(trim((string)($adj->tocode ?? '')));
                    $fromqty = (int)($adj->fromqty ?? 0);
                    $fromwgt = (float)($adj->fromwgt ?? 0);
                    $fromstwgt = (float)($adj->fromstwgt ?? 0);
                    $toqty = (int)($adj->toqty ?? 0);
                    $towgt = (float)($adj->towgt ?? 0);
                    $tostwgt = (float)($adj->tostwgt ?? 0);
                    $fromstktype = trim((string)($adj->fromstktype ?? ''));
                    $tostktype = trim((string)($adj->tostktype ?? ''));
                    $adjControl = (int)($adj->control ?? $control);

                    // Reverse: add back to fromcode, subtract from tocode
                    if ($fromcode !== '') {
                        $this->adjustItemStock($fromcode, $fromqty, $fromwgt, $fromstwgt, $fromstktype, $adjControl);
                    }
                    if ($tocode !== '') {
                        $this->adjustItemStock($tocode, -$toqty, -$towgt, -$tostwgt, $tostktype, $adjControl);
                    }

                    DB::table('itemadj')->where('slno', $slno)->delete();
                }
            }

            // Log in delpart
            if ($this->hasTable('delpart')) {
                $fromcode = trim((string)($adj->fromcode ?? ''));
                $tocode = trim((string)($adj->tocode ?? ''));
                $part = 'Model Transfer Entry(' . $fromcode . ' to ' . $tocode . ') Canceled';
                DB::table('delpart')->insert($this->f('delpart', [
                    'tdate' => date('Y-m-d'),
                    'part' => mb_substr($part, 0, 60),
                    'control' => $control,
                    'slno' => $slno,
                    'utype' => 'D',
                    'ttype' => 'M',
                    'updtdate' => date('Y-m-d'),
                    'updttime' => date('H:i:s'),
                    'uid' => $userId,
                ]));
            }

            DB::commit();
            return $this->json(['success' => true, 'message' => 'Model transfer #' . $slno . ' cancelled']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->json(['success' => false, 'error' => 'Error: ' . $e->getMessage()], 500);
        }
    }
}
