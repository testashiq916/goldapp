<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class RemakeIssueMemoPartyController extends Controller
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

        return view('remake-issue-memo-party.index', [
            'title' => 'Remake Issue Memo to Party',
            'mode' => $mode,
            'smen' => $smen,
            'nextDocNo' => $this->peekNextDocNo(),
            'shopInfo' => $this->shopInfo(),
        ]);
    }

    public function nextDoc(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);
        return response()->json(['ok' => true, 'doc_no' => $this->peekNextDocNo()]);
    }

    public function search(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);

        if (!$this->hasTable('smithm')) {
            return response()->json(['ok' => true, 'rows' => []]);
        }

        $q = trim((string) $request->query('q', ''));
        $rows = DB::table('smithm')
            ->where('docno', 'like', 'RM2%')
            ->when($q !== '', function ($qb) use ($q) {
                $qb->where(function ($w) use ($q) {
                    $w->where('docno', 'like', "%{$q}%")
                      ->orWhere('smithcode', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('slno')
            ->limit(80)
            ->get(['slno', 'docno', 'tdate', 'smithcode', 'status'])
            ->map(fn($r) => [
                'slno' => (int) ($r->slno ?? 0),
                'billno' => trim((string) ($r->docno ?? '')),
                'tdate' => (string) ($r->tdate ?? ''),
                'custcode' => trim((string) ($r->smithcode ?? '')),
                'custname' => trim((string) ($r->smithcode ?? '')),
                'status' => (int) ($r->status ?? 0),
            ])
            ->values()
            ->all();

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    public function navigate(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);

        if (!$this->hasTable('smithm')) {
            return response()->json(['ok' => false, 'message' => 'smithm missing'], 500);
        }

        $direction = strtolower(trim((string) $request->query('direction', 'next')));
        $billNo = strtoupper(trim((string) $request->query('bill_no', '')));
        $slno = (int) $request->query('slno', 0);

        if ($slno <= 0 && $billNo !== '') {
            $slno = (int) (DB::table('smithm')->whereRaw('TRIM(docno)=?', [$billNo])->value('slno') ?? 0);
        }

        $query = DB::table('smithm')->where('docno', 'like', 'RM2%');
        if ($direction === 'previous' || $direction === 'prev') {
            if ($slno > 0) $query->where('slno', '<', $slno);
            $row = $query->orderByDesc('slno')->first(['slno', 'docno']);
        } else {
            if ($slno > 0) $query->where('slno', '>', $slno);
            $row = $query->orderBy('slno')->first(['slno', 'docno']);
        }

        if (!$row) {
            return response()->json(['ok' => false, 'message' => 'No more records']);
        }

        return response()->json([
            'ok' => true,
            'slno' => (int) ($row->slno ?? 0),
            'bill_no' => trim((string) ($row->docno ?? '')),
        ]);
    }

    public function get(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);

        $billNo = strtoupper(trim((string) $request->query('bill_no', '')));
        if ($billNo === '') {
            return response()->json(['ok' => false, 'message' => 'Bill no required'], 422);
        }

        if (!$this->hasTable('smithm')) {
            return response()->json(['ok' => false, 'message' => 'smithm missing'], 500);
        }

        $m = DB::table('smithm')->whereRaw('TRIM(docno)=?', [$billNo])->first();
        if (!$m) {
            return response()->json(['ok' => false, 'message' => 'Bill not found'], 404);
        }

        $custCode = trim((string) ($m->smithcode ?? ''));
        $custName = '';
        $addr = '';
        if ($custCode !== '' && $this->hasTable('clients')) {
            $c = DB::table('clients')->whereRaw('TRIM(code)=?', [$custCode])->first(['name', 'addr1', 'addr2']);
            if ($c) {
                $custName = trim((string) ($c->name ?? ''));
                $addr = trim(trim((string) ($c->addr1 ?? '')) . ' ' . trim((string) ($c->addr2 ?? '')));
            }
        }

        $rows = $this->hasTable('smithd')
            ? DB::table('smithd')->where('slno', $m->slno)->orderBy('sno')->get()
                ->map(function ($r) {
                    $weight = (float) ($r->weight ?? 0);
                    $stone = (float) ($r->stonewgt ?? 0);
                    return [
                        'itemcode' => trim((string) ($r->code ?? '')),
                        'itemname' => trim((string) ($r->name ?? '')),
                        'qty' => (float) ($r->qty ?? 0),
                        'weight' => round($weight, 3),
                        'stonewgt' => round($stone, 3),
                        'netwgt' => round((float) ($r->netwgt ?? max($weight - $stone, 0)), 3),
                        'complaint' => trim((string) ($r->remark ?? '')),
                        'cost' => (float) ($r->cost ?? 0),
                        'purity' => '',
                        'stktype' => trim((string) ($r->stktype ?? '')),
                    ];
                })->values()->all()
            : [];

        return response()->json([
            'ok' => true,
            'master' => [
                'slno' => (int) ($m->slno ?? 0),
                'billno' => trim((string) ($m->docno ?? '')),
                'tdate' => (string) ($m->tdate ?? ''),
                'duedate' => (string) ($m->duedate ?? ''),
                'custcode' => $custCode,
                'custname' => $custName,
                'sman' => trim((string) ($m->smcode ?? '')),
                'addr' => $addr,
                'status' => (int) ($m->status ?? 0),
            ],
            'rows' => $rows,
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);

        if (!$this->hasTable('smithm') || !$this->hasTable('smithd')) {
            return response()->json(['ok' => false, 'message' => 'smith tables missing'], 500);
        }

        $mode = strtolower(trim((string) $request->input('mode', 'new')));
        $billNo = strtoupper(trim((string) $request->input('bill_no', '')));
        $slno = (int) $request->input('slno', 0);

        $tdate = $this->toSqlDate((string) $request->input('tdate', '')) ?? date('Y-m-d');
        $ddate = $this->toSqlDate((string) $request->input('duedate', ''));
        $custCode = strtoupper(trim((string) $request->input('custcode', '')));
        $sman = strtoupper(trim((string) $request->input('sman', '')));
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
                'remark' => trim((string) ($r['complaint'] ?? '')),
                'cost' => (float) ($r['cost'] ?? 0),
                'stktype' => trim((string) ($r['stktype'] ?? '')),
            ];
        }

        if (empty($normRows)) {
            return response()->json(['ok' => false, 'message' => 'No item rows to save'], 422);
        }

        DB::beginTransaction();
        try {
            if ($mode === 'edit') {
                if ($slno <= 0 && $billNo !== '') {
                    $slno = (int) (DB::table('smithm')->whereRaw('TRIM(docno)=?', [$billNo])->value('slno') ?? 0);
                }
                if ($slno <= 0) {
                    DB::rollBack();
                    return response()->json(['ok' => false, 'message' => 'Bill not found for edit'], 404);
                }
                DB::table('smithd')->where('slno', $slno)->delete();
                DB::table('smithm')->where('slno', $slno)->delete();
            } else {
                $slno = $this->nextSerialNo();
                $billNo = $this->nextDocNo();
            }

            DB::table('smithm')->insert($this->f('smithm', [
                'slno' => $slno,
                'docno' => $billNo,
                'tdate' => $tdate,
                'duedate' => $ddate,
                'smithcode' => $custCode,
                'smcode' => $sman,
                'status' => 1,
                'control' => 1,
                'ic' => 1,
                'doctype' => 'Remake Issue',
            ]));

            $sno = 1;
            foreach ($normRows as $r) {
                DB::table('smithd')->insert($this->f('smithd', [
                    'slno' => $slno,
                    'sno' => $sno++,
                    'code' => $r['itemcode'],
                    'name' => $r['itemname'],
                    'qty' => $r['qty'],
                    'weight' => $r['weight'],
                    'stonewgt' => $r['stonewgt'],
                    'netwgt' => $r['netwgt'],
                    'givrec' => 'G',
                    'cost' => $r['cost'],
                    'stktype' => $r['stktype'],
                    'remark' => $r['remark'],
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

        $m = DB::table('smithm')->whereRaw('TRIM(docno)=?', [$billNo])->first();
        if (!$m) {
            return response()->json(['ok' => false, 'message' => 'Bill not found'], 404);
        }

        DB::beginTransaction();
        try {
            DB::table('smithd')->where('slno', $m->slno)->delete();
            DB::table('smithm')->where('slno', $m->slno)->delete();
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
        if ($code === '' || !$this->hasTable('items')) {
            return response()->json(['ok' => false]);
        }
        $r = DB::table('items')->whereRaw('TRIM(code)=?', [$code])->first(['code', 'name', 'cost']);
        if (!$r) return response()->json(['ok' => false]);
        return response()->json([
            'ok' => true,
            'item' => [
                'code' => trim((string) ($r->code ?? '')),
                'name' => trim((string) ($r->name ?? '')),
                'cost' => (float) ($r->cost ?? 0),
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
        $like = '%' . $q . '%';
        $rows = DB::table('items')
            ->when($q !== '', function ($qb) use ($like) {
                $qb->where(function ($w) use ($like) {
                    $w->where('code', 'like', $like)->orWhere('name', 'like', $like);
                });
            })
            ->orderBy('code')->limit(30)
            ->get(['code', 'name', 'cost'])
            ->map(fn($r) => [
                'code' => trim((string) ($r->code ?? '')),
                'name' => trim((string) ($r->name ?? '')),
                'cost' => (float) ($r->cost ?? 0),
            ])->values()->all();
        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    public function lookupCustomer(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);
        $code = strtoupper(trim((string) $request->query('code', '')));
        if ($code === '' || !$this->hasTable('clients')) {
            return response()->json(['ok' => false]);
        }
        $r = DB::table('clients')->whereRaw('TRIM(code)=?', [$code])->first(['code', 'name', 'addr1', 'addr2']);
        if (!$r) return response()->json(['ok' => false]);
        return response()->json([
            'ok' => true,
            'customer' => [
                'code' => trim((string) ($r->code ?? '')),
                'name' => trim((string) ($r->name ?? '')),
                'addr' => trim(trim((string) ($r->addr1 ?? '')) . ' ' . trim((string) ($r->addr2 ?? ''))),
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
        $like = '%' . $q . '%';
        $rows = DB::table('clients')
            ->when($q !== '', function ($qb) use ($like) {
                $qb->where(function ($w) use ($like) {
                    $w->where('code', 'like', $like)->orWhere('name', 'like', $like);
                });
            })
            ->orderBy('name')->limit(30)
            ->get(['code', 'name', 'addr1', 'addr2'])
            ->map(fn($c) => [
                'code' => trim((string) ($c->code ?? '')),
                'name' => trim((string) ($c->name ?? '')),
                'addr' => trim(trim((string) ($c->addr1 ?? '')) . ' ' . trim((string) ($c->addr2 ?? ''))),
            ])->values()->all();
        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    private function peekNextDocNo(): string
    {
        $n = $this->genInt('ISSUEPARTY') + 1;
        return 'RM2/' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    private function nextDocNo(): string
    {
        $n = $this->incrementGenInt('ISSUEPARTY');
        return 'RM2/' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
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

    protected function hasTable(string $name): bool
    {
        return Schema::hasTable($name);
    }

    protected function columnList(string $table): array
    {
        return Schema::getColumnListing($table);
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

    private function shopInfo(): array
    {
        $info = [
            'name' => '',
            'address' => '',
            'phone' => '',
            'mobile' => '',
            'gstin' => '',
        ];

        try {
            $iniPath = storage_path('app/software-settings.ini');
            $settings = is_file($iniPath) ? parse_ini_file($iniPath, true, INI_SCANNER_RAW) : [];
            $company = is_array($settings['Company'] ?? null) ? $settings['Company'] : [];
            $info['name'] = trim((string) ($company['Name'] ?? ''));
            $info['phone'] = trim((string) ($company['Phone'] ?? ''));
            $info['mobile'] = trim((string) ($company['Mobile'] ?? ''));
            $info['gstin'] = trim((string) ($company['GSTIN'] ?? ($company['KGST'] ?? '')));
            $info['address'] = implode("\n", array_values(array_filter([
                trim((string) ($company['Addr'] ?? ($company['Addr1'] ?? ''))),
                trim((string) ($company['Addr2'] ?? ($company['Address2'] ?? ''))),
            ], fn ($value) => $value !== '')));
        } catch (\Throwable $e) {
            // Keep defaults; DB values below may still populate the header.
        }

        if ($this->hasTable('generals') && Schema::hasColumn('generals', 'code')) {
            $valueColumn = Schema::hasColumn('generals', 'cvalue')
                ? 'cvalue'
                : (Schema::hasColumn('generals', 'value') ? 'value' : null);

            if ($valueColumn !== null) {
                $rows = DB::table('generals')
                    ->whereIn('code', ['SHOPNM', 'SHOPADDR', 'SHOPPHONE', 'Mobile', 'MOBILE', 'GSTIN', 'GSTNO', 'KGST'])
                    ->get(['code', $valueColumn]);

                foreach ($rows as $row) {
                    $code = strtoupper(trim((string) ($row->code ?? '')));
                    $value = trim((string) ($row->{$valueColumn} ?? ''));
                    if ($value === '') {
                        continue;
                    }
                    if ($code === 'SHOPNM') {
                        $info['name'] = $value;
                    } elseif ($code === 'SHOPADDR') {
                        $info['address'] = $value . ($info['address'] !== '' && $info['address'] !== $value ? "\n" . $info['address'] : '');
                    } elseif ($code === 'SHOPPHONE') {
                        $info['phone'] = $value;
                    } elseif ($code === 'MOBILE') {
                        $info['mobile'] = $value;
                    } elseif ($info['gstin'] === '' && in_array($code, ['GSTIN', 'GSTNO', 'KGST'], true)) {
                        $info['gstin'] = $value;
                    }
                }
            }
        }

        $addressLines = preg_split('/\R+/', $info['address']) ?: [];
        $info['address'] = implode("\n", array_values(array_unique(array_filter(
            array_map(fn ($value) => trim((string) $value), $addressLines),
            fn ($value) => $value !== ''
        ))));

        return $info;
    }
}
