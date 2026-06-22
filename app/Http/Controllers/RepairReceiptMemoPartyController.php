<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RepairReceiptMemoPartyController extends Controller
{
    public function index(Request $request, ?string $mode = null): View|RedirectResponse
    {
        if (!$this->isAuthorized($request)) {
            return redirect('/login');
        }

        $mode = strtolower($mode ?: (string) $request->query('mode', 'new'));
        if (!in_array($mode, ['new', 'edit', 'cancel', 'reprint'], true)) {
            $mode = 'new';
        }

        $smen = $this->hasTable('sman')
            ? DB::table('sman')->orderBy('name')->get(['code', 'name'])->values()
            : collect();

        return view('repair-receipt-memo-party.index', [
            'title' => trim((string) $request->query('title', '')) ?: 'Remake Receipt Memo to Party',
            'mode' => $mode,
            'smen' => $smen,
            'nextDocNo' => $this->peekNextDocNo('B'),
            'shopInfo' => $this->shopInfo(),
        ]);
    }

    public function nextDoc(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        return response()->json(['ok' => true, 'doc_no' => $this->peekNextDocNo('B')]);
    }

    public function search(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);

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

    public function navigate(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);

        if (!$this->hasTable('repairm')) {
            return response()->json(['ok' => false, 'message' => 'repairm missing'], 500);
        }

        $direction = strtolower(trim((string) $request->query('direction', 'next')));
        $billNo = strtoupper(trim((string) $request->query('bill_no', '')));
        $slno = (int) $request->query('slno', 0);

        if ($slno <= 0 && $billNo !== '') {
            $slno = (int) (DB::table('repairm')->whereRaw('TRIM(billno)=?', [$billNo])->value('slno') ?? 0);
        }

        $query = DB::table('repairm');
        if ($direction === 'previous' || $direction === 'prev') {
            if ($slno > 0) {
                $query->where('slno', '<', $slno);
            }
            $row = $query->orderByDesc('slno')->first(['slno', 'billno']);
        } else {
            if ($slno > 0) {
                $query->where('slno', '>', $slno);
            }
            $row = $query->orderBy('slno')->first(['slno', 'billno']);
        }

        if (!$row) {
            return response()->json(['ok' => false, 'message' => 'No more records']);
        }

        return response()->json([
            'ok' => true,
            'slno' => (int) ($row->slno ?? 0),
            'bill_no' => trim((string) ($row->billno ?? '')),
        ]);
    }

    public function get(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);

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
                'addr' => $this->customerAddress((string) ($m->custcode ?? ''), (string) ($m->addr ?? '')),
                'status' => (int) ($m->status ?? 0),
                'refbill' => trim((string) ($m->refbillno ?? $m->refbill ?? '')),
                'recvamt' => (float) ($m->pamt ?? $m->ramt ?? $m->recvamt ?? 0),
                'cbcode' => trim((string) ($m->cbcode ?? '')),
                'sale_type' => (float) ($m->pamt ?? $m->ramt ?? $m->recvamt ?? 0) > 0 ? 'CASH' : 'CREDIT',
                'note' => trim((string) ($m->note ?? $m->remark ?? '')),
            ],
            'rows' => $rows,
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);

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
        $refBill = strtoupper(trim((string) $request->input('refbill', '')));
        $recvAmt = (float) $request->input('recvamt', 0);
        $cbCode = strtoupper(trim((string) $request->input('cbcode', '')));
        $saleType = strtoupper(trim((string) $request->input('sale_type', 'CASH')));
        if (!in_array($saleType, ['CASH', 'CREDIT'], true)) {
            $saleType = 'CREDIT';
        }
        if ($saleType === 'CREDIT') {
            $recvAmt = 0.0;
            $cbCode = '';
        } elseif ($cbCode === '') {
            $cbCode = 'CASH';
        }
        $note = trim((string) $request->input('note', ''));
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
                DB::table('repaird')->where('slno', $slno)->delete();
                DB::table('repairm')->where('slno', $slno)->delete();
                if ($this->hasTable('daybook')) DB::table('daybook')->where('slno', $slno)->delete();
                if ($this->hasTable('daybookpart')) DB::table('daybookpart')->where('slno', $slno)->delete();
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
                'refbillno' => $refBill,
                'refbill' => $refBill,
                'pamt' => $recvAmt,
                'ramt' => $recvAmt,
                'recvamt' => $recvAmt,
                'cbcode' => $cbCode,
                'saletype' => $saleType,
                'sale_type' => $saleType,
                'note' => $note,
                'remark' => $note,
            ]));

            $sno = 1;
            foreach ($normRows as $r) {
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

            $this->writeCashSaleDaybook($slno, $tdate, $billNo, $custCode, $custName, $cbCode, $recvAmt);

            DB::commit();
            return response()->json(['ok' => true, 'message' => 'Saved', 'slno' => $slno, 'bill_no' => $billNo]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'message' => 'Save failed: ' . $e->getMessage()]);
        }
    }

    public function cancel(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);

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
            DB::table('repaird')->where('slno', $m->slno)->delete();
            DB::table('repairm')->where('slno', $m->slno)->delete();
            if ($this->hasTable('daybook')) DB::table('daybook')->where('slno', $m->slno)->delete();
            if ($this->hasTable('daybookpart')) DB::table('daybookpart')->where('slno', $m->slno)->delete();
            DB::commit();
            return response()->json(['ok' => true, 'message' => 'Cancelled']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'message' => 'Cancel failed: ' . $e->getMessage()]);
        }
    }

    public function lookupItem(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);

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
        if (!$this->isAuthorized($request)) return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);

        if (!$this->hasTable('items')) {
            return response()->json(['ok' => true, 'rows' => []]);
        }

        $limit = max(1, min(25, (int) $request->query('limit', 12)));
        $q = trim((string) $request->query('q', ''));
        $like = '%' . $q . '%';

        $rows = DB::table('items')
            ->when($q !== '', function ($query) use ($like) {
                $query->where(function ($qb) use ($like) {
                $qb->where('code', 'like', $like)
                    ->orWhere('name', 'like', $like);
                });
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
        if (!$this->isAuthorized($request)) return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);

        $code = strtoupper(trim((string) $request->query('code', '')));
        if ($code === '') return response()->json(['ok' => false], 422);
        if (!$this->hasTable('clients')) return response()->json(['ok' => false, 'message' => 'clients table missing'], 500);

        $clientCols = array_map('strtolower', $this->columnList('clients'));
        $query = DB::table('clients')->whereRaw('TRIM(code)=?', [$code]);
        if (in_array('ctype', $clientCols, true)) {
            $query->whereRaw('UPPER(TRIM(ctype)) = ?', ['C']);
        }

        $c = $query->first($this->clientSelectColumns(['code', 'name', 'addr1', 'addr2', 'addr3', 'city', 'mobile', 'telephone']));
        if (!$c) return response()->json(['ok' => false, 'message' => 'Invalid customer']);

        return response()->json([
            'ok' => true,
            'customer' => [
                'code' => trim((string) ($c->code ?? '')),
                'name' => trim((string) ($c->name ?? '')),
                'addr' => $this->formatClientAddress($c),
                'phone' => trim((string) ($c->mobile ?? $c->telephone ?? '')),
            ],
        ]);
    }

    public function searchCustomers(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);

        if (!$this->hasTable('clients')) {
            return response()->json(['ok' => true, 'rows' => []]);
        }

        $limit = max(1, min(25, (int) $request->query('limit', 12)));
        $q = trim((string) $request->query('q', ''));
        $like = '%' . $q . '%';

        $clientCols = array_map('strtolower', $this->columnList('clients'));

        $rows = DB::table('clients')
            ->when(in_array('ctype', $clientCols, true), fn ($qb) => $qb->whereRaw('UPPER(TRIM(ctype)) = ?', ['C']))
            ->when($q !== '', function ($query) use ($like, $clientCols) {
                $query->where(function ($qb) use ($like, $clientCols) {
                $qb->where('code', 'like', $like)
                    ->orWhere('name', 'like', $like);

                if (in_array('mobile', $clientCols, true)) {
                    $qb->orWhere('mobile', 'like', $like);
                }
                if (in_array('telephone', $clientCols, true)) {
                    $qb->orWhere('telephone', 'like', $like);
                }
                });
            })
            ->orderBy('name')
            ->limit($limit)
            ->get($this->clientSelectColumns(['code', 'name', 'addr1', 'addr2', 'addr3', 'city', 'mobile', 'telephone']))
            ->map(fn ($c) => [
                'code' => trim((string) ($c->code ?? '')),
                'name' => trim((string) ($c->name ?? '')),
                'addr' => $this->formatClientAddress($c),
                'phone' => trim((string) ($c->mobile ?? $c->telephone ?? '')),
            ])
            ->values()
            ->all();

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    public function cashBanks(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        if (!$this->hasTable('accountm')) {
            return response()->json(['ok' => true, 'rows' => []]);
        }
        $rows = DB::table('accountm')
            ->whereIn('actype2', ['H', 'B'])
            ->orderByRaw("CASE WHEN actype2='H' THEN 0 ELSE 1 END, accode")
            ->get(['accode as code', 'name', 'actype2'])
            ->map(fn ($r) => [
                'code' => trim((string) ($r->code ?? '')),
                'name' => trim((string) ($r->name ?? '')),
                'type' => trim((string) ($r->actype2 ?? '')),
            ])->values()->all();
        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    public function loadIssueBill(Request $request): JsonResponse
    {
        if (!$this->isAuthorized($request)) return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);

        $docNo = strtoupper(trim((string) $request->query('doc_no', '')));
        if ($docNo === '') {
            return response()->json(['ok' => false, 'message' => 'Doc no required'], 422);
        }
        if (!$this->hasTable('smithm')) {
            return response()->json(['ok' => false, 'message' => 'smithm table missing'], 404);
        }

        $m = DB::table('smithm')->whereRaw('UPPER(TRIM(docno)) = ?', [$docNo])->first();
        if (!$m) {
            return response()->json(['ok' => false, 'message' => 'Issue bill not found'], 404);
        }

        $custCode = trim((string) ($m->smithcode ?? $m->custcode ?? ''));
        $custName = '';
        $addr = '';
        if ($custCode !== '' && $this->hasTable('clients')) {
            $c = DB::table('clients')->whereRaw('TRIM(code)=?', [$custCode])
                ->first($this->clientSelectColumns(['name', 'addr1', 'addr2', 'addr3', 'city', 'mobile', 'telephone']));
            if ($c) {
                $custName = trim((string) ($c->name ?? ''));
                $addr = $this->formatClientAddress($c);
            }
        }

        $rows = $this->hasTable('smithd')
            ? DB::table('smithd')->where('slno', $m->slno)->orderBy('sno')->get()
                ->map(function ($r) {
                    $weight = (float) ($r->weight ?? 0);
                    $stone = (float) ($r->stonewgt ?? 0);
                    return [
                        'itemcode' => trim((string) ($r->code ?? '')),
                        'itemname' => trim((string) ($r->name ?? $r->itemname ?? '')),
                        'qty' => (float) ($r->qty ?? 0),
                        'weight' => round($weight, 3),
                        'stonewgt' => round($stone, 3),
                        'netwgt' => round((float) ($r->netwgt ?? max($weight - $stone, 0)), 3),
                        'complaint' => trim((string) ($r->complaint ?? $r->remark ?? '')),
                        'cost' => (float) ($r->cost ?? 0),
                        'purity' => trim((string) ($r->purity ?? '')),
                        'stktype' => trim((string) ($r->stktype ?? '')),
                    ];
                })->values()->all()
            : [];

        return response()->json([
            'ok' => true,
            'master' => [
                'docno' => trim((string) ($m->docno ?? '')),
                'tdate' => (string) ($m->tdate ?? ''),
                'custcode' => $custCode,
                'custname' => $custName,
                'addr' => $addr,
            ],
            'rows' => $rows,
        ]);
    }

    private function peekNextDocNo(string $seb = 'B'): string
    {
        $n = $this->genInt('REPAIR' . $seb) + 1;
        $num = str_pad((string) $n, 4, '0', STR_PAD_LEFT);
        return $seb === 'B' ? ('RP/' . $num) : ('RP ' . $num);
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
            // DB values below can still populate the print header.
        }

        if ($this->hasTable('generals')) {
            $rows = DB::table('generals')
                ->whereIn('code', ['SHOPNM', 'SHOPADDR', 'SHOPPHONE', 'Mobile', 'MOBILE', 'GSTIN', 'GSTNO', 'KGST'])
                ->get(['code', 'cvalue']);
            foreach ($rows as $row) {
                $code = strtoupper(trim((string) ($row->code ?? '')));
                $value = trim((string) ($row->cvalue ?? ''));
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

        $addressLines = preg_split('/\R+/', $info['address']) ?: [];
        $info['address'] = implode("\n", array_values(array_unique(array_filter(
            array_map(fn ($value) => trim((string) $value), $addressLines),
            fn ($value) => $value !== ''
        ))));

        return $info;
    }

    private function customerAddress(string $code, string $fallback = ''): string
    {
        $fallback = trim($fallback);
        $code = trim($code);
        if ($fallback !== '' || $code === '' || !$this->hasTable('clients')) {
            return $fallback;
        }

        $customer = DB::table('clients')
            ->whereRaw('TRIM(code)=?', [$code])
            ->first($this->clientSelectColumns(['addr1', 'addr2', 'addr3', 'city', 'mobile', 'telephone']));

        return $customer ? $this->formatClientAddress($customer) : '';
    }

    private function formatClientAddress(object $client): string
    {
        $parts = [];
        foreach (['addr1', 'addr2', 'addr3', 'city'] as $column) {
            $value = trim((string) ($client->{$column} ?? ''));
            if ($value !== '') {
                $parts[] = $value;
            }
        }

        return implode(', ', array_values(array_unique($parts)));
    }

    private function clientSelectColumns(array $preferred): array
    {
        if (!$this->hasTable('clients')) {
            return $preferred;
        }

        $available = array_map('strtolower', $this->columnList('clients'));
        return array_values(array_filter($preferred, fn ($column) => in_array(strtolower($column), $available, true)));
    }

    private function isAuthorized(Request $request): bool
    {
        if ((string) ($request->session()->get('user_code') ?? '') !== '') {
            return true;
        }

        $legacySessionId = preg_replace('/[^a-zA-Z0-9,-]/', '', (string) ($_COOKIE['PHPSESSID'] ?? ''));
        if ($legacySessionId === '') {
            return false;
        }

        $savePath = (string) ini_get('session.save_path');
        if ($savePath === '') {
            return false;
        }
        if (str_contains($savePath, ';')) {
            $parts = explode(';', $savePath);
            $savePath = (string) end($parts);
        }
        $savePath = trim($savePath);
        if ($savePath === '') {
            return false;
        }

        $sessionFile = rtrim($savePath, "/\\") . DIRECTORY_SEPARATOR . 'sess_' . $legacySessionId;
        if (!is_file($sessionFile) || !is_readable($sessionFile)) {
            return false;
        }

        $raw = (string) @file_get_contents($sessionFile);
        return $raw !== '' && str_contains($raw, 'user_code|');
    }

    private function nextDocNo(string $seb = 'B'): string
    {
        $n = $this->incrementGenInt('REPAIR' . $seb);
        $num = str_pad((string) $n, 4, '0', STR_PAD_LEFT);
        return $seb === 'B' ? ('RP/' . $num) : ('RP ' . $num);
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

    private function writeCashSaleDaybook(int $slno, string $tdate, string $billNo, string $custCode, string $custName, string $cbCode, float $recvAmt): void
    {
        if ($recvAmt <= 0 || $custCode === '' || $cbCode === '' || !$this->hasTable('daybook')) {
            return;
        }

        $particular = mb_substr('Repair Slip - ' . trim($billNo) . ($custName !== '' ? ' - ' . $custName : ''), 0, 40);

        if ($this->hasTable('daybookpart')) {
            DB::table('daybookpart')->insert($this->f('daybookpart', [
                'slno' => $slno,
                'particular' => $particular,
                'vchno' => $billNo,
                'ic' => 1,
                'uid' => 1,
                'ttime' => date('H:i:s'),
            ]));
        }

        DB::table('daybook')->insert($this->f('daybook', [
            'slno' => $slno,
            'tdate' => $tdate,
            'accode' => $custCode,
            'amount' => round($recvAmt, 2),
            'control' => 1,
            'opaccode' => $cbCode,
        ]));

        DB::table('daybook')->insert($this->f('daybook', [
            'slno' => $slno,
            'tdate' => $tdate,
            'accode' => $cbCode,
            'amount' => -round($recvAmt, 2),
            'control' => 1,
            'opaccode' => $custCode,
        ]));
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
