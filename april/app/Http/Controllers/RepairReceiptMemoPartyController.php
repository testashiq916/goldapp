<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class RepairReceiptMemoPartyController extends Controller
{
    public function index(Request $request, ?string $mode = null): View
    {
        abort_unless($request->session()->has('user_code'), 401);

        $mode = strtolower($mode ?: (string) $request->query('mode', 'new'));
        if (!in_array($mode, ['new', 'edit', 'cancel', 'reprint'], true)) {
            $mode = 'new';
        }

        $smen = $this->hasTable('sman')
            ? DB::table('sman')->orderBy('name')->get(['code', 'name'])->values()
            : collect();

        return view('repair-receipt-memo-party.index', [
            'title' => 'Remake Receipt Memo to Party',
            'mode' => $mode,
            'smen' => $smen,
            'nextDocNo' => $this->peekNextDocNo('B'),
        ]);
    }

    public function nextDoc(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);
        return response()->json(['ok' => true, 'doc_no' => $this->peekNextDocNo('B')]);
    }

    public function search(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);

        if (!$this->hasTable('repairm')) {
            return response()->json(['ok' => true, 'rows' => []]);
        }

        $q = trim((string) $request->query('q', ''));
        $rows = DB::table('repairm')
            ->when($q !== '', function ($qb) use ($q) {
                $qb->where(function ($w) use ($q) {
                    $w->where('billno', 'like', "%{$q}%")
                      ->orWhere('custcode', 'like', "%{$q}%")
                      ->orWhere('custname', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('slno')
            ->limit(80)
            ->get(['slno', 'billno', 'tdate', 'custcode', 'custname', 'status'])
            ->map(fn($r) => [
                'slno' => (int) ($r->slno ?? 0),
                'billno' => trim((string) ($r->billno ?? '')),
                'tdate' => (string) ($r->tdate ?? ''),
                'custcode' => trim((string) ($r->custcode ?? '')),
                'custname' => trim((string) ($r->custname ?? '')),
                'status' => (int) ($r->status ?? 0),
            ])
            ->values()
            ->all();

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    public function get(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);

        $billNo = strtoupper(trim((string) $request->query('bill_no', '')));
        if ($billNo === '') {
            return response()->json(['ok' => false, 'message' => 'Bill no required'], 422);
        }

        if (!$this->hasTable('repairm')) {
            return response()->json(['ok' => false, 'message' => 'repairm missing'], 500);
        }

        $m = DB::table('repairm')->whereRaw('TRIM(billno)=?', [$billNo])->first();
        if (!$m) {
            return response()->json(['ok' => false, 'message' => 'Bill not found'], 404);
        }

        $rows = $this->hasTable('repaird')
            ? DB::table('repaird')->where('slno', $m->slno)->orderBy('sno')
                ->get(['code', 'name', 'qty', 'weight', 'stonewgt', 'complaint', 'cost', 'netwgt', 'purity', 'stktype'])
                ->map(fn($r) => [
                    'itemcode' => trim((string) ($r->code ?? '')),
                    'itemname' => trim((string) ($r->name ?? '')),
                    'qty' => (float) ($r->qty ?? 0),
                    'weight' => (float) ($r->weight ?? 0),
                    'stonewgt' => (float) ($r->stonewgt ?? 0),
                    'complaint' => trim((string) ($r->complaint ?? '')),
                    'cost' => (float) ($r->cost ?? 0),
                    'netwgt' => (float) ($r->netwgt ?? 0),
                    'purity' => trim((string) ($r->purity ?? '')),
                    'stktype' => trim((string) ($r->stktype ?? '')),
                ])->values()->all()
            : [];

        return response()->json([
            'ok' => true,
            'master' => [
                'slno' => (int) ($m->slno ?? 0),
                'billno' => trim((string) ($m->billno ?? '')),
                'tdate' => (string) ($m->tdate ?? ''),
                'duedate' => (string) ($m->duedate ?? ''),
                'custcode' => trim((string) ($m->custcode ?? '')),
                'custname' => trim((string) ($m->custname ?? '')),
                'sman' => trim((string) ($m->sman ?? '')),
                'addr' => trim((string) ($m->addr ?? '')),
                'status' => (int) ($m->status ?? 0),
            ],
            'rows' => $rows,
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);

        if (!$this->hasTable('repairm') || !$this->hasTable('repaird')) {
            return response()->json(['ok' => false, 'message' => 'repair tables missing'], 500);
        }

        $mode = strtolower(trim((string) $request->input('mode', 'new')));
        $billNo = strtoupper(trim((string) $request->input('bill_no', '')));
        $slno = (int) $request->input('slno', 0);

        $tdate = $this->toSqlDate((string) $request->input('tdate', '')) ?? date('Y-m-d');
        $ddate = $this->toSqlDate((string) $request->input('duedate', ''));
        $custCode = strtoupper(trim((string) $request->input('custcode', '')));
        $custName = trim((string) $request->input('custname', ''));
        $sman = strtoupper(trim((string) $request->input('sman', '')));
        $addr = trim((string) $request->input('addr', ''));
        $rows = $request->input('rows', []);
        if (!is_array($rows)) $rows = [];

        $normRows = [];
        foreach ($rows as $r) {
            $code = strtoupper(trim((string) ($r['itemcode'] ?? '')));
            if ($code === '') continue;
            $weight = (float) ($r['weight'] ?? 0);
            $stone = (float) ($r['stonewgt'] ?? 0);
            $net = (float) ($r['netwgt'] ?? ($weight - $stone));
            if ($weight <= 0) {
                return response()->json(['ok' => false, 'message' => "Please check weight ({$code})"], 422);
            }
            $normRows[] = [
                'itemcode' => $code,
                'itemname' => trim((string) ($r['itemname'] ?? '')),
                'qty' => (float) ($r['qty'] ?? 0),
                'weight' => round($weight, 3),
                'stonewgt' => round($stone, 3),
                'netwgt' => round($net, 3),
                'complaint' => trim((string) ($r['complaint'] ?? '')),
                'cost' => (float) ($r['cost'] ?? 0),
                'purity' => trim((string) ($r['purity'] ?? '')),
                'stktype' => trim((string) ($r['stktype'] ?? '')),
            ];
        }

        if (empty($normRows)) {
            return response()->json(['ok' => false, 'message' => 'No item rows to save'], 422);
        }
        if ($sman === '') {
            return response()->json(['ok' => false, 'message' => 'Select salesman'], 422);
        }

        DB::beginTransaction();
        try {
            if ($mode === 'edit') {
                if ($slno <= 0 && $billNo !== '') {
                    $slno = (int) (DB::table('repairm')->whereRaw('TRIM(billno)=?', [$billNo])->value('slno') ?? 0);
                }
                if ($slno <= 0) {
                    DB::rollBack();
                    return response()->json(['ok' => false, 'message' => 'Bill not found for edit'], 404);
                }
                $oldControl = (int) (DB::table('repairm')->where('slno', $slno)->value('control') ?? 1);
                $this->reverseStock($slno, $oldControl);
                DB::table('repaird')->where('slno', $slno)->delete();
                DB::table('repairm')->where('slno', $slno)->delete();
            } else {
                $slno = $this->nextSerialNo();
                $billNo = $this->nextDocNo('B');
            }

            DB::table('repairm')->insert($this->f('repairm', [
                'slno' => $slno,
                'billno' => $billNo,
                'tdate' => $tdate,
                'duedate' => $ddate,
                'custcode' => $custCode,
                'custname' => $custName,
                'givrec' => 'R',
                'control' => 1,
                'status' => 1,
                'sman' => $sman,
                'addr' => $addr,
                'ic' => 1,
            ]));

            $sno = 1;
            foreach ($normRows as $r) {
                $this->increaseItemStock($r['itemcode'], $r['qty'], $r['weight'], $r['stonewgt'], $r['stktype']);

                DB::table('repaird')->insert($this->f('repaird', [
                    'slno' => $slno,
                    'code' => $r['itemcode'],
                    'name' => $r['itemname'],
                    'qty' => $r['qty'],
                    'weight' => $r['weight'],
                    'stonewgt' => $r['stonewgt'],
                    'complaint' => $r['complaint'],
                    'givrec' => 'R',
                    'cost' => $r['cost'],
                    'sno' => $sno++,
                    'netwgt' => $r['netwgt'],
                    'purity' => $r['purity'],
                    'stktype' => $r['stktype'],
                ]));
            }

            DB::commit();
            return response()->json(['ok' => true, 'message' => 'Saved', 'slno' => $slno, 'bill_no' => $billNo]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'message' => 'Save failed: ' . $e->getMessage()]);
        }
    }

    public function cancel(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);

        $billNo = strtoupper(trim((string) $request->input('bill_no', '')));
        if ($billNo === '') {
            return response()->json(['ok' => false, 'message' => 'Bill no required'], 422);
        }

        $m = DB::table('repairm')->whereRaw('TRIM(billno)=?', [$billNo])->first();
        if (!$m) {
            return response()->json(['ok' => false, 'message' => 'Bill not found'], 404);
        }

        DB::beginTransaction();
        try {
            $this->reverseStock((int)$m->slno, (int)($m->control ?? 1));
            DB::table('repaird')->where('slno', $m->slno)->delete();
            DB::table('repairm')->where('slno', $m->slno)->delete();
            DB::commit();
            return response()->json(['ok' => true, 'message' => 'Cancelled']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'message' => 'Cancel failed: ' . $e->getMessage()]);
        }
    }

    public function lookupItem(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);

        $code = strtoupper(trim((string) $request->query('code', '')));
        if ($code === '') return response()->json(['ok' => false], 422);
        $it = DB::table('items')->whereRaw('TRIM(code)=?', [$code])->first(['code', 'name', 'cost']);
        if (!$it) return response()->json(['ok' => false, 'message' => 'Invalid item']);
        return response()->json([
            'ok' => true,
            'item' => [
                'code' => trim((string)$it->code),
                'name' => trim((string)$it->name),
                'cost' => (float)($it->cost ?? 0),
            ],
        ]);
    }

    public function searchItems(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);

        if (!$this->hasTable('items')) {
            return response()->json(['ok' => true, 'rows' => []]);
        }

        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json(['ok' => true, 'rows' => []]);
        }

        $limit = max(1, min(25, (int) $request->query('limit', 12)));
        $like = '%' . $q . '%';

        $rows = DB::table('items')
            ->where(function ($qb) use ($like) {
                $qb->where('code', 'like', $like)
                    ->orWhere('name', 'like', $like);
            })
            ->orderBy('name')
            ->limit($limit)
            ->get(['code', 'name', 'cost'])
            ->map(fn ($item) => [
                'code' => trim((string) ($item->code ?? '')),
                'name' => trim((string) ($item->name ?? '')),
                'cost' => (float) ($item->cost ?? 0),
            ])
            ->values()
            ->all();

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    public function lookupCustomer(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);

        $code = strtoupper(trim((string) $request->query('code', '')));
        if ($code === '') return response()->json(['ok' => false], 422);
        if (!$this->hasTable('clients')) return response()->json(['ok' => false, 'message' => 'clients table missing'], 500);

        $clientCols = array_map('strtolower', $this->columnList('clients'));
        $query = DB::table('clients')->whereRaw('TRIM(code)=?', [$code]);
        if (in_array('ctype', $clientCols, true)) {
            $query->where('ctype', 'C');
        }

        $c = $query->first(['code', 'name', 'addr1', 'addr2']);
        if (!$c) return response()->json(['ok' => false, 'message' => 'Invalid customer']);

        return response()->json([
            'ok' => true,
            'customer' => [
                'code' => trim((string) ($c->code ?? '')),
                'name' => trim((string) ($c->name ?? '')),
                'addr' => trim(trim((string) ($c->addr1 ?? '')) . ' ' . trim((string) ($c->addr2 ?? ''))),
            ],
        ]);
    }

    public function searchCustomers(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);

        if (!$this->hasTable('clients')) {
            return response()->json(['ok' => true, 'rows' => []]);
        }

        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json(['ok' => true, 'rows' => []]);
        }

        $limit = max(1, min(25, (int) $request->query('limit', 12)));
        $like = '%' . $q . '%';

        $clientCols = array_map('strtolower', $this->columnList('clients'));

        $rows = DB::table('clients')
            ->when(in_array('ctype', $clientCols, true), fn ($qb) => $qb->where('ctype', 'C'))
            ->where(function ($qb) use ($like, $clientCols) {
                $qb->where('code', 'like', $like)
                    ->orWhere('name', 'like', $like);

                if (in_array('mobile', $clientCols, true)) {
                    $qb->orWhere('mobile', 'like', $like);
                }
                if (in_array('telephone', $clientCols, true)) {
                    $qb->orWhere('telephone', 'like', $like);
                }
            })
            ->orderBy('name')
            ->limit($limit)
            ->get(['code', 'name', 'addr1', 'addr2'])
            ->map(fn ($c) => [
                'code' => trim((string) ($c->code ?? '')),
                'name' => trim((string) ($c->name ?? '')),
                'addr' => trim(trim((string) ($c->addr1 ?? '')) . ' ' . trim((string) ($c->addr2 ?? ''))),
            ])
            ->values()
            ->all();

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    private function peekNextDocNo(string $seb = 'B'): string
    {
        $n = $this->genInt('REPAIR' . $seb) + 1;
        return $seb === 'B' ? ('RM1/' . str_pad((string) $n, 5, '0', STR_PAD_LEFT)) : ('RM1 ' . str_pad((string) $n, 5, '0', STR_PAD_LEFT));
    }

    private function nextDocNo(string $seb = 'B'): string
    {
        $n = $this->incrementGenInt('REPAIR' . $seb);
        return $seb === 'B' ? ('RM1/' . str_pad((string) $n, 5, '0', STR_PAD_LEFT)) : ('RM1 ' . str_pad((string) $n, 5, '0', STR_PAD_LEFT));
    }

    private function nextSerialNo(): int
    {
        return $this->incrementGenInt('SERIALNO');
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
        if ($code === 'SERIALNO') {
            $maxUsed = 0;
            foreach (['salesm', 'salesrm', 'purchasem', 'purchaserm', 'daybook', 'daybookpart', 'orderm', 'smithm', 'refinerym', 'repairm'] as $table) {
                if ($this->hasTable($table) && Schema::hasColumn($table, 'slno')) {
                    $maxUsed = max($maxUsed, (int) (DB::table($table)->max('slno') ?? 0));
                }
            }
            $current = max($current, $maxUsed);
        }
        $next = $current + 1;
        $updated = DB::table('generali')->where('code', $code)->update(['cvalue' => $next]);
        if ($updated === 0) DB::table('generali')->insert(['code' => $code, 'cvalue' => $next]);
        return $next;
    }

    private function increaseItemStock(string $code, float $qty, float $weight, float $stoneWgt, string $stkType): void
    {
        if (!$this->hasTable('items')) return;

        DB::table('items')->whereRaw('TRIM(code)=?', [$code])->update($this->f('items', [
            'weight' => DB::raw('COALESCE(weight,0) + ' . $weight),
            'qty' => DB::raw('COALESCE(qty,0) + ' . $qty),
            'weightb' => DB::raw('COALESCE(weightb,0) + ' . $weight),
            'qtyb' => DB::raw('COALESCE(qtyb,0) + ' . $qty),
            'stonewgt' => DB::raw('COALESCE(stonewgt,0) + ' . $stoneWgt),
            'stonewgtb' => DB::raw('COALESCE(stonewgtb,0) + ' . $stoneWgt),
        ]));

        if ($stkType !== '' && $this->hasTable('itemsstk')) {
            DB::table('itemsstk')
                ->whereRaw('TRIM(code)=?', [$code])
                ->where('stktype', $stkType)
                ->update($this->f('itemsstk', [
                    'weight' => DB::raw('COALESCE(weight,0) + ' . $weight),
                    'qty' => DB::raw('COALESCE(qty,0) + ' . $qty),
                    'weightb' => DB::raw('COALESCE(weightb,0) + ' . $weight),
                    'qtyb' => DB::raw('COALESCE(qtyb,0) + ' . $qty),
                    'stonewgt' => DB::raw('COALESCE(stonewgt,0) + ' . $stoneWgt),
                    'stonewgtb' => DB::raw('COALESCE(stonewgtb,0) + ' . $stoneWgt),
                ]));
        }
    }

    private function reverseStock(int $slno, int $control): void
    {
        $rows = DB::table('repaird')->where('slno', $slno)->get(['code', 'qty', 'weight', 'stonewgt', 'stktype']);
        foreach ($rows as $r) {
            $code = trim((string)($r->code ?? ''));
            if ($code === '') continue;
            $qty = (float)($r->qty ?? 0);
            $w = round((float)($r->weight ?? 0), 3);
            $st = round((float)($r->stonewgt ?? 0), 3);
            $stktype = trim((string)($r->stktype ?? ''));

            if ($control === 1) {
                DB::table('items')->whereRaw('TRIM(code)=?', [$code])->update($this->f('items', [
                    'weight' => DB::raw('COALESCE(weight,0) - ' . $w),
                    'qty' => DB::raw('COALESCE(qty,0) - ' . $qty),
                    'weightb' => DB::raw('COALESCE(weightb,0) - ' . $w),
                    'qtyb' => DB::raw('COALESCE(qtyb,0) - ' . $qty),
                    'stonewgt' => DB::raw('COALESCE(stonewgt,0) - ' . $st),
                    'stonewgtb' => DB::raw('COALESCE(stonewgtb,0) - ' . $st),
                ]));

                if ($stktype !== '' && $this->hasTable('itemsstk')) {
                    DB::table('itemsstk')->whereRaw('TRIM(code)=?', [$code])->where('stktype', $stktype)
                        ->update($this->f('itemsstk', [
                            'weight' => DB::raw('COALESCE(weight,0) - ' . $w),
                            'qty' => DB::raw('COALESCE(qty,0) - ' . $qty),
                            'weightb' => DB::raw('COALESCE(weightb,0) - ' . $w),
                            'qtyb' => DB::raw('COALESCE(qtyb,0) - ' . $qty),
                            'stonewgt' => DB::raw('COALESCE(stonewgt,0) - ' . $st),
                            'stonewgtb' => DB::raw('COALESCE(stonewgtb,0) - ' . $st),
                        ]));
                }
            } else {
                DB::table('items')->whereRaw('TRIM(code)=?', [$code])->update($this->f('items', [
                    'weightb' => DB::raw('COALESCE(weightb,0) - ' . $w),
                    'qtyb' => DB::raw('COALESCE(qtyb,0) - ' . $qty),
                    'stonewgtb' => DB::raw('COALESCE(stonewgtb,0) - ' . $st),
                ]));
                if ($stktype !== '' && $this->hasTable('itemsstk')) {
                    DB::table('itemsstk')->whereRaw('TRIM(code)=?', [$code])->where('stktype', $stktype)
                        ->update($this->f('itemsstk', [
                            'weightb' => DB::raw('COALESCE(weightb,0) - ' . $w),
                            'qtyb' => DB::raw('COALESCE(qtyb,0) - ' . $qty),
                            'stonewgtb' => DB::raw('COALESCE(stonewgtb,0) - ' . $st),
                        ]));
                }
            }
        }
    }

    private function toSqlDate(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') return null;
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw)) return $raw;
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $raw, $m)) return "{$m[3]}-{$m[2]}-{$m[1]}";
        return null;
    }

    private function f(string $table, array $data): array
    {
        static $cols = [];
        if (!isset($cols[$table])) {
            $cols[$table] = $this->hasTable($table) ? array_map('strtolower', $this->columnList($table)) : [];
        }
        return array_filter($data, fn($k) => in_array(strtolower($k), $cols[$table], true), ARRAY_FILTER_USE_KEY);
    }
}
