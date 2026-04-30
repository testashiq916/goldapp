<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class OtherItemTransController extends Controller
{
    private function json(array $p, int $s = 200): JsonResponse { return response()->json($p, $s); }

    private function f(string $table, array $data): array
    {
        if (!$this->hasTable($table)) return $data;
        $cols = array_flip(array_map('strtolower', $this->columnList($table)));
        return array_filter($data, static fn($v, $k) => isset($cols[strtolower((string)$k)]), ARRAY_FILTER_USE_BOTH);
    }

    private function genInt(string $code): int
    {
        if (!$this->hasTable('generali')) return 0;
        return (int)(DB::table('generali')->where('code', $code)->value('cvalue') ?? 0);
    }

    private function incrementGenInt(string $code): int
    {
        if (!$this->hasTable('generali')) return 1;
        $cur = $this->genInt($code);
        $next = $cur + 1;
        $upd = DB::table('generali')->where('code', $code)->update(['cvalue' => $next]);
        if ($upd === 0) DB::table('generali')->insert(['code' => $code, 'cvalue' => $next]);
        return $next;
    }

    private function nextSerialNo(): int
    {
        if (!$this->hasTable('generali')) return 1;
        $current = (int)(DB::table('generali')->where('code', 'SERIALNO')->value('cvalue') ?? 0);
        $maxUsed = 0;
        foreach (['salesm', 'salesrm', 'purchasem', 'purchaserm', 'daybook', 'daybookpart', 'orderm', 'smithm', 'refinerym', 'repairm'] as $table) {
            if ($this->hasTable($table) && Schema::hasColumn($table, 'slno')) {
                $maxUsed = max($maxUsed, (int)(DB::table($table)->max('slno') ?? 0));
            }
        }
        $next = max($current, $maxUsed) + 1;
        DB::table('generali')->updateOrInsert(['code' => 'SERIALNO'], ['cvalue' => $next]);
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

    private function normDate(string $d): ?string
    {
        $d = trim($d);
        if ($d === '') return null;
        $t = strtotime($d);
        return ($t === false || (int)date('Y', $t) < 1990) ? null : date('Y-m-d', $t);
    }

    // ── Pages ──

    public function index(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) return redirect('/login');
        return view('other-item-trans.index');
    }

    public function picker(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('user_code')) return redirect('/login');
        return view('other-item-trans.picker');
    }

    // ── API ──

    public function api(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code'))
            return $this->json(['success' => false, 'error' => 'Unauthorized'], 401);

        $action = strtolower(trim((string)$request->input('action', '')));
        $control = $this->resolveControl($request);

        return match ($action) {
            'init'        => $this->actionInit($request, $control),
            'item_search' => $this->actionItemSearch($request),
            'item_load'   => $this->actionItemLoad($request),
            'client_search' => $this->actionClientSearch($request),
            'save'        => $this->actionSave($request, $control),
            'load'        => $this->actionLoad($request),
            'list'        => $this->actionList($request),
            'delete'      => $this->actionDelete($request, $control),
            default       => $this->json(['success' => false, 'error' => 'Invalid action'], 400),
        };
    }

    // ── init ──

    private function actionInit(Request $request, int $control): JsonResponse
    {
        $sp = strtoupper(trim((string)$request->input('sp', 'S')));
        $eb = ($control === 1) ? 'B' : 'E';

        $counterCode = ($sp === 'P') ? ('OTHERTP' . $eb) : ('OTHERTS' . $eb);
        $prefix = match (true) {
            $sp === 'S' && $eb === 'B' => 'OTSB/',
            $sp === 'S' && $eb === 'E' => 'OTSE/',
            $sp === 'P' && $eb === 'B' => 'OTPB/',
            default                     => 'OTPE/',
        };
        $nextNum = $this->genInt($counterCode) + 1;
        $nextDocno = $prefix . str_pad($nextNum, 5, '0', STR_PAD_LEFT);

        $salesmen = [];
        if ($this->hasTable('sman')) {
            $salesmen = DB::table('sman')->selectRaw('TRIM(code) AS code, TRIM(name) AS name')
                ->orderBy('code')->get()->all();
        }

        return $this->json([
            'success' => true,
            'nextDocno' => $nextDocno,
            'control' => $control,
            'salesmen' => $salesmen,
            'today' => date('Y-m-d'),
            'sp' => $sp,
        ]);
    }

    // ── item_search ──

    private function actionItemSearch(Request $request): JsonResponse
    {
        if (!$this->hasTable('itemsothers')) return $this->json(['success' => true, 'data' => []]);
        $s = strtoupper(trim((string)$request->input('search', '')));
        if ($s === '') return $this->json(['success' => true, 'data' => []]);
        $like = '%' . $s . '%';
        $rows = DB::table('itemsothers')
            ->selectRaw('TRIM(code) AS code, TRIM(name) AS name')
            ->where(fn($q) => $q->where('code', 'like', $like)->orWhere('name', 'like', $like))
            ->orderBy('code')->limit(50)->get()->all();
        return $this->json(['success' => true, 'data' => $rows]);
    }

    // ── item_load ──

    private function actionItemLoad(Request $request): JsonResponse
    {
        $code = strtoupper(trim((string)$request->input('code', '')));
        $sp = strtoupper(trim((string)$request->input('sp', 'S')));
        if ($code === '' || !$this->hasTable('itemsothers'))
            return $this->json(['success' => false, 'error' => 'Item not found'], 404);

        $row = DB::table('itemsothers')->whereRaw('TRIM(code) = ?', [$code])
            ->first(['code', 'name', 'srate', 'prate', 'cost', 'stock', 'keepstk']);
        if (!$row) return $this->json(['success' => false, 'error' => 'Item not found'], 404);

        $rate = ($sp === 'P') ? (float)($row->prate ?? 0) : (float)($row->srate ?? 0);
        return $this->json([
            'success' => true,
            'item' => [
                'code' => trim((string)($row->code ?? '')),
                'name' => trim((string)($row->name ?? '')),
                'rate' => round($rate, 2),
                'cost' => round((float)($row->cost ?? 0), 2),
            ],
        ]);
    }

    // ── client_search ──

    private function actionClientSearch(Request $request): JsonResponse
    {
        $s = trim((string)$request->input('search', ''));
        $results = [];

        if ($this->hasTable('clients') && $s !== '') {
            $like = '%' . $s . '%';
            $results = DB::table('clients')
                ->selectRaw("TRIM(code) AS code, TRIM(name) AS name, 'C' AS src")
                ->where(fn($q) => $q->where('code', 'like', $like)->orWhere('name', 'like', $like))
                ->orderBy('name')->limit(50)->get()->all();
        }
        if ($this->hasTable('accountm') && $s !== '' && count($results) < 50) {
            $like = '%' . $s . '%';
            $more = DB::table('accountm')
                ->selectRaw("TRIM(accode) AS code, TRIM(name) AS name, 'A' AS src")
                ->where(fn($q) => $q->where('accode', 'like', $like)->orWhere('name', 'like', $like))
                ->orderBy('name')->limit(50)->get()->all();
            $results = array_merge($results, $more);
        }

        return $this->json(['success' => true, 'data' => $results]);
    }

    // ── save ──

    private function actionSave(Request $request, int $control): JsonResponse
    {
        $editSlno = (int)$request->input('edit_slno', 0);
        $sp = strtoupper(trim((string)$request->input('sp', 'S')));
        $tdate = $this->normDate((string)$request->input('tdate', '')) ?? date('Y-m-d');
        $pcode = strtoupper(trim((string)$request->input('pcode', '')));
        $pname = trim((string)$request->input('pname', ''));
        $smcode = strtoupper(trim((string)$request->input('smcode', '')));
        $billamt = round((float)$request->input('billamt', 0), 2);
        $addamt = round((float)$request->input('addamt', 0), 2);
        $lessamt = round((float)$request->input('lessamt', 0), 2);
        $ramt = round((float)$request->input('ramt', 0), 2);
        $items = $request->input('items', []);
        $userCode = (string)$request->session()->get('user_code', '');
        $userId = (string)$request->session()->get('user_id', $userCode);

        if (!is_array($items) || count($items) === 0)
            return $this->json(['success' => false, 'error' => 'No items entered'], 422);

        $eb = ($control === 1) ? 'B' : 'E';
        $netamt = $billamt + $addamt - $lessamt;
        $ttime = date('H:i:s');

        DB::beginTransaction();
        try {
            // If editing, reverse old stock and delete old records
            if ($editSlno > 0) {
                $this->reverseOldTransaction($editSlno, $sp);

                $oldTdate = DB::table('oitemtranm')->where('slno', $editSlno)->value('tdate');

                DB::table('oitemtranm')->where('slno', $editSlno)->delete();
                DB::table('oitemtrand')->where('slno', $editSlno)->delete();
                DB::table('daybook')->where('slno', $editSlno)->delete();
                DB::table('daybookpart')->where('slno', $editSlno)->delete();

                // Log edit in delpart
                if ($this->hasTable('delpart')) {
                    $docno = trim((string)$request->input('docno', ''));
                    DB::table('delpart')->insert($this->f('delpart', [
                        'tdate' => $tdate,
                        'part' => mb_substr('Other Item Tran Entry(' . $docno . ') Edited -' . ($oldTdate ?? ''), 0, 60),
                        'control' => $control,
                        'slno' => $editSlno,
                        'utype' => 'E',
                        'ttype' => 'O',
                        'updtdate' => date('Y-m-d'),
                        'updttime' => $ttime,
                        'uid' => $userId,
                    ]));
                }
            }

            $this->touchCounter('BLOCK');

            $slno = ($editSlno > 0) ? $editSlno : $this->nextSerialNo();

            // Generate doc number for new entries
            $docno = trim((string)$request->input('docno', ''));
            if ($editSlno <= 0) {
                $counterCode = ($sp === 'P') ? ('OTHERTP' . $eb) : ('OTHERTS' . $eb);
                $prefix = match (true) {
                    $sp === 'S' && $eb === 'B' => 'OTSB/',
                    $sp === 'S' && $eb === 'E' => 'OTSE/',
                    $sp === 'P' && $eb === 'B' => 'OTPB/',
                    default                     => 'OTPE/',
                };
                $docNum = $this->incrementGenInt($counterCode);
                $docno = $prefix . str_pad($docNum, 5, '0', STR_PAD_LEFT);
            }

            // Insert oitemtranm
            DB::table('oitemtranm')->insert($this->f('oitemtranm', [
                'slno' => $slno,
                'docno' => $docno,
                'tdate' => $tdate,
                'pcode' => $pcode,
                'pname' => $pname,
                'smcode' => $smcode,
                'billamt' => $billamt,
                'addamt' => $addamt,
                'lessamt' => $lessamt,
                'ramt' => $ramt,
                'sp' => $sp,
                'tr' => $sp,
                'control' => $control,
                'ic' => $userCode,
            ]));

            // Insert oitemtrand and update stock
            $sno = 0;
            foreach ($items as $item) {
                $icode = strtoupper(trim((string)($item['code'] ?? '')));
                if ($icode === '') continue;
                $sno++;
                $iname = trim((string)($item['name'] ?? ''));
                $iqty = (int)($item['qty'] ?? 0);
                $irate = round((float)($item['rate'] ?? 0), 2);
                $iamount = round((float)($item['amount'] ?? 0), 2);
                $icost = round((float)($item['cost'] ?? 0), 2);

                DB::table('oitemtrand')->insert($this->f('oitemtrand', [
                    'slno' => $slno,
                    'code' => $icode,
                    'name' => $iname,
                    'qty' => $iqty,
                    'rate' => $irate,
                    'amount' => $iamount,
                    'cost' => $icost,
                    'sno' => $sno,
                ]));

                // Update stock in itemsothers
                $this->updateOtherItemStock($icode, $iqty, $icost, $sp);
            }

            // Insert daybookpart
            $sacpart = ($sp === 'S')
                ? 'By Other Item Sales - ' . $docno . ($pname !== '' ? ' To ' . $pname : '')
                : 'By Other Item Purchase - ' . $docno . ($pname !== '' ? ' From ' . $pname : '');
            $sacpart = mb_substr($sacpart, 0, 40);

            if ($this->hasTable('daybookpart')) {
                DB::table('daybookpart')->insert($this->f('daybookpart', [
                    'slno' => $slno,
                    'particular' => $sacpart,
                    'vchno' => '',
                    'ic' => $userCode,
                    'uid' => $userId,
                ]));
            }

            // Insert daybook entries
            if ($this->hasTable('daybook')) {
                $this->insertDaybookEntries($slno, $tdate, $control, $sp, $pcode, $billamt, $addamt, $lessamt, $ramt, $netamt);
            }

            DB::commit();
            return $this->json([
                'success' => true,
                'message' => ($sp === 'S' ? 'Sales' : 'Purchase') . ' saved. Doc: ' . $docno,
                'slno' => $slno,
                'docno' => $docno,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->json(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    private function insertDaybookEntries(int $slno, string $tdate, int $control, string $sp, string $pcode, float $billamt, float $addamt, float $lessamt, float $ramt, float $netamt): void
    {
        $sopaccode = '';

        // OS/OP entry (bill amount)
        if ($billamt != 0) {
            $saccode = ($sp === 'S') ? 'OS' : 'OP';
            $dacamt = ($sp === 'S') ? $billamt : -$billamt;
            $sopaccode = ($ramt != 0) ? 'CASH' : $pcode;

            DB::table('daybook')->insert($this->f('daybook', [
                'slno' => $slno, 'accode' => $saccode, 'amount' => round($dacamt, 2),
                'control' => $control, 'tdate' => $tdate, 'opaccode' => $sopaccode,
            ]));
            $sopaccode = $saccode;
        }

        // Cash entry
        if ($ramt != 0) {
            $dacamt = ($sp === 'S') ? -$ramt : $ramt;
            DB::table('daybook')->insert($this->f('daybook', [
                'slno' => $slno, 'accode' => 'CASH', 'amount' => round($dacamt, 2),
                'control' => $control, 'tdate' => $tdate, 'opaccode' => $sopaccode,
            ]));
        }

        // Party entry
        if ($pcode !== '') {
            $dacamt = ($sp === 'S') ? -$netamt : $netamt;
            DB::table('daybook')->insert($this->f('daybook', [
                'slno' => $slno, 'accode' => $pcode, 'amount' => round($dacamt, 2),
                'control' => $control, 'tdate' => $tdate, 'opaccode' => $sopaccode,
            ]));

            if ($ramt != 0) {
                $dacamt = ($sp === 'S') ? $ramt : -$ramt;
                DB::table('daybook')->insert($this->f('daybook', [
                    'slno' => $slno, 'accode' => $pcode, 'amount' => round($dacamt, 2),
                    'control' => $control, 'tdate' => $tdate, 'opaccode' => $sopaccode,
                ]));
            }
        }

        // Add amount entry
        if ($addamt != 0) {
            $dacamt = ($sp === 'S') ? $addamt : -$addamt;
            DB::table('daybook')->insert($this->f('daybook', [
                'slno' => $slno, 'accode' => 'ADD', 'amount' => round($dacamt, 2),
                'control' => $control, 'tdate' => $tdate, 'opaccode' => $sopaccode,
            ]));
        }

        // Less/discount entry
        if ($lessamt != 0) {
            $dacamt = ($sp === 'S') ? -$lessamt : $lessamt;
            DB::table('daybook')->insert($this->f('daybook', [
                'slno' => $slno, 'accode' => 'DISC', 'amount' => round($dacamt, 2),
                'control' => $control, 'tdate' => $tdate, 'opaccode' => $sopaccode,
            ]));
        }
    }

    private function updateOtherItemStock(string $code, int $qty, float $cost, string $sp): void
    {
        if (!$this->hasTable('itemsothers')) return;
        $row = DB::table('itemsothers')->whereRaw('TRIM(code) = ?', [$code])
            ->first(['stock', 'cost', 'keepstk']);
        if (!$row || (int)($row->keepstk ?? 0) !== 1) return;

        $curStock = (int)($row->stock ?? 0);
        $curCost = (float)($row->cost ?? 0);

        if ($sp === 'S') {
            // Sales: reduce stock
            DB::table('itemsothers')->whereRaw('TRIM(code) = ?', [$code])
                ->update(['stock' => DB::raw('stock - ' . $qty)]);
        } else {
            // Purchase: add stock, calculate weighted average cost
            $newStock = $curStock + $qty;
            $wacost = ($newStock > 0)
                ? round(($curStock * $curCost + $qty * $cost) / $newStock, 2)
                : $cost;
            DB::table('itemsothers')->whereRaw('TRIM(code) = ?', [$code])
                ->update(['stock' => DB::raw('stock + ' . $qty), 'cost' => $wacost]);
        }
    }

    private function reverseOldTransaction(int $slno, string $sp): void
    {
        if (!$this->hasTable('oitemtrand') || !$this->hasTable('itemsothers')) return;

        $rows = DB::table('oitemtrand')->where('slno', $slno)->get(['code', 'qty', 'cost']);
        foreach ($rows as $r) {
            $code = strtoupper(trim((string)($r->code ?? '')));
            $qty = (int)($r->qty ?? 0);
            $cost = (float)($r->cost ?? 0);
            if ($code === '' || $qty <= 0) continue;

            $item = DB::table('itemsothers')->whereRaw('TRIM(code) = ?', [$code])
                ->first(['stock', 'cost', 'keepstk']);
            if (!$item || (int)($item->keepstk ?? 0) !== 1) continue;

            if ($sp === 'S') {
                // Reverse sales: add back stock
                DB::table('itemsothers')->whereRaw('TRIM(code) = ?', [$code])
                    ->update(['stock' => DB::raw('stock + ' . $qty)]);
            } else {
                // Reverse purchase: remove stock, recalc cost
                $curStock = (int)($item->stock ?? 0);
                $curCost = (float)($item->cost ?? 0);
                $newStock = $curStock - $qty;
                $wacost = ($newStock > 0)
                    ? round(($curStock * $curCost - $qty * $cost) / $newStock, 2)
                    : $curCost;
                DB::table('itemsothers')->whereRaw('TRIM(code) = ?', [$code])
                    ->update(['stock' => DB::raw('stock - ' . $qty), 'cost' => $wacost]);
            }
        }
    }

    // ── load ──

    private function actionLoad(Request $request): JsonResponse
    {
        $slno = (int)$request->input('slno', 0);
        $docno = strtoupper(trim((string)$request->input('docno', '')));

        if ($slno <= 0 && $docno !== '') {
            $slno = (int)(DB::table('oitemtranm')->whereRaw('TRIM(docno) = ?', [$docno])->max('slno') ?? 0);
        }
        if ($slno <= 0) return $this->json(['success' => false, 'error' => 'Record not found'], 404);

        $m = DB::table('oitemtranm')->where('slno', $slno)->first();
        if (!$m) return $this->json(['success' => false, 'error' => 'Record not found'], 404);

        $details = DB::table('oitemtrand')->where('slno', $slno)->orderBy('sno')
            ->get(['code', 'name', 'qty', 'rate', 'amount', 'cost']);

        $items = $details->map(fn($r) => [
            'code' => trim((string)($r->code ?? '')),
            'name' => trim((string)($r->name ?? '')),
            'qty' => (int)($r->qty ?? 0),
            'rate' => round((float)($r->rate ?? 0), 2),
            'amount' => round((float)($r->amount ?? 0), 2),
            'cost' => round((float)($r->cost ?? 0), 2),
        ])->values();

        return $this->json([
            'success' => true,
            'record' => [
                'slno' => (int)$m->slno,
                'docno' => trim((string)($m->docno ?? '')),
                'tdate' => (string)($m->tdate ?? ''),
                'pcode' => trim((string)($m->pcode ?? '')),
                'pname' => trim((string)($m->pname ?? '')),
                'smcode' => trim((string)($m->smcode ?? '')),
                'billamt' => round((float)($m->billamt ?? 0), 2),
                'addamt' => round((float)($m->addamt ?? 0), 2),
                'lessamt' => round((float)($m->lessamt ?? 0), 2),
                'ramt' => round((float)($m->ramt ?? 0), 2),
                'sp' => trim((string)($m->sp ?? 'S')),
                'control' => (int)($m->control ?? 1),
            ],
            'items' => $items,
        ]);
    }

    // ── list ──

    private function actionList(Request $request): JsonResponse
    {
        if (!$this->hasTable('oitemtranm')) return $this->json(['success' => true, 'data' => []]);

        $sp = strtoupper(trim((string)$request->input('sp', '')));
        $search = trim((string)$request->input('search', ''));

        $query = DB::table('oitemtranm');
        if ($sp !== '') $query->where('sp', $sp);

        if ($search !== '') {
            $like = '%' . strtoupper($search) . '%';
            $query->where(fn($q) => $q->where('docno', 'like', $like)
                ->orWhere('pcode', 'like', $like)
                ->orWhere('pname', 'like', $like));
        }

        $rows = $query->orderByDesc('slno')->limit(200)
            ->get(['slno', 'docno', 'tdate', 'pcode', 'pname', 'billamt', 'ramt', 'sp', 'control']);

        $data = $rows->map(fn($r) => [
            'slno' => (int)$r->slno,
            'docno' => trim((string)($r->docno ?? '')),
            'tdate' => (string)($r->tdate ?? ''),
            'pcode' => trim((string)($r->pcode ?? '')),
            'pname' => trim((string)($r->pname ?? '')),
            'billamt' => round((float)($r->billamt ?? 0), 2),
            'ramt' => round((float)($r->ramt ?? 0), 2),
            'sp' => trim((string)($r->sp ?? '')),
        ])->values();

        return $this->json(['success' => true, 'data' => $data]);
    }

    // ── delete (cancel) ──

    private function actionDelete(Request $request, int $control): JsonResponse
    {
        $slno = (int)$request->input('slno', 0);
        $docno = strtoupper(trim((string)$request->input('docno', '')));

        if ($slno <= 0 && $docno !== '') {
            $slno = (int)(DB::table('oitemtranm')->whereRaw('TRIM(docno) = ?', [$docno])->max('slno') ?? 0);
        }
        if ($slno <= 0) return $this->json(['success' => false, 'error' => 'Record not found'], 422);

        $m = DB::table('oitemtranm')->where('slno', $slno)->first();
        if (!$m) return $this->json(['success' => false, 'error' => 'Record not found'], 404);

        $sp = trim((string)($m->sp ?? 'S'));
        $gisemi = (int)($m->control ?? $control);
        $userId = (string)$request->session()->get('user_id', $request->session()->get('user_code', ''));

        DB::beginTransaction();
        try {
            $this->reverseOldTransaction($slno, $sp);

            DB::table('oitemtranm')->where('slno', $slno)->delete();
            DB::table('oitemtrand')->where('slno', $slno)->delete();
            DB::table('daybook')->where('slno', $slno)->delete();
            DB::table('daybookpart')->where('slno', $slno)->delete();

            if ($this->hasTable('delpart')) {
                DB::table('delpart')->insert($this->f('delpart', [
                    'tdate' => date('Y-m-d'),
                    'part' => mb_substr('Other Item Tran(' . trim((string)($m->docno ?? '')) . ') Canceled', 0, 60),
                    'control' => $gisemi,
                    'slno' => $slno,
                    'utype' => 'D',
                    'ttype' => 'O',
                    'updtdate' => date('Y-m-d'),
                    'updttime' => date('H:i:s'),
                    'uid' => $userId,
                ]));
            }

            DB::commit();
            return $this->json(['success' => true, 'message' => 'Transaction #' . $slno . ' cancelled']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->json(['success' => false, 'error' => 'Error: ' . $e->getMessage()], 500);
        }
    }
}
