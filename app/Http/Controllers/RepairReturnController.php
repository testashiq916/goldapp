<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\LogsDelpartAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class RepairReturnController extends Controller
{
    use LogsDelpartAudit;

    // ─── View ────────────────────────────────────────────────────────────────

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

        // Gold / Silver rates
        $grate = 0;
        $srate = 0;
        if ($this->hasTable('generald')) {
            $grate = (float) (DB::table('generald')->where('code', 'GRATE')->value('cvalue') ?? 0);
            $srate = (float) (DB::table('generald')->where('code', 'SRATE')->value('cvalue') ?? 0);
        }

        // Default stktype for repair
        $defstktype = '';
        if ($this->hasTable('stktype')) {
            $r = DB::table('stktype')->where('code', 'R')->first(['code']);
            $defstktype = $r ? 'R' : '';
        }
        if ($defstktype === '' && $this->hasTable('generals') && Schema::hasColumn('generals', 'code') && Schema::hasColumn('generals', 'cvalue')) {
            $defstktype = trim((string) (DB::table('generals')->where('code', 'DEFSTKTYPE')->value('cvalue') ?? ''));
        }

        $cashBanks = $this->cashBankAccounts();

        return view('repair-return.index', [
            'title'       => 'Repair Return Details',
            'mode'        => $mode,
            'smen'        => $smen,
            'salesmen'    => $smen,
            'grate'       => $grate,
            'srate'       => $srate,
            'defstktype'  => $defstktype,
            'shopInfo'    => $this->shopInfo(),
            'cashBanks'   => $cashBanks,
        ]);
    }

    public function picker(Request $request, string $action = 'edit'): View
    {
        $action = strtolower($action);
        if (!in_array($action, ['edit', 'cancel', 'reprint'], true)) {
            $action = 'edit';
        }

        $titles = [
            'edit' => 'Edit Repair Return',
            'cancel' => 'Cancel Repair Return',
            'reprint' => 'Repair Return Reprint',
        ];

        return view('purchase-bill.doc-picker', [
            'title' => (string) $request->query('title', $titles[$action]),
            'actionMode' => $action,
            'docType' => 'repair-return',
            'searchUrl' => url('/api/repair-return/picker-search'),
            'resolveUrl' => url('/api/repair-return/picker-resolve'),
            'targetBaseUrl' => url('/repair-return'),
            'showViewOption' => $action === 'reprint',
        ]);
    }

    public function pickerSearch(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);

        if (!$this->hasTable('repairm')) {
            return response()->json(['ok' => true, 'rows' => []]);
        }

        $q = trim((string) $request->query('q', ''));
        $tdate = $this->parsePickerDate((string) $request->query('tdate', ''));

        $rows = DB::table('repairm')
            ->where('givrec', 'G')
            ->when($q !== '', function ($qb) use ($q) {
                $qb->where(function ($w) use ($q) {
                    $w->where('billno', 'like', $q . '%')
                      ->orWhere('custcode', 'like', "%{$q}%")
                      ->orWhere('custname', 'like', "%{$q}%");
                });
            })
            ->when($tdate, fn ($qb) => $qb->whereDate('tdate', $tdate))
            ->orderByDesc('slno')
            ->get(['slno', 'billno', 'tdate', 'custname'])
            ->map(fn ($r) => [
                'slno' => (int) ($r->slno ?? 0),
                'doc_no' => trim((string) ($r->billno ?? '')),
                'tdate' => !empty($r->tdate) ? date('d/m/Y', strtotime((string) $r->tdate)) : '',
                'party_name' => trim((string) ($r->custname ?? '')),
            ])
            ->values();

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    public function pickerResolve(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);

        $billNo = strtoupper(trim((string) $request->input('doc_no', '')));
        $tdate = $this->parsePickerDate((string) $request->input('tdate', ''));
        $action = strtolower(trim((string) $request->input('action', 'edit')));
        $viewOnly = filter_var($request->input('view_only', false), FILTER_VALIDATE_BOOLEAN);

        if ($billNo === '') {
            return response()->json(['ok' => false, 'message' => 'Bill no is required.'], 422);
        }
        if (!$this->hasTable('repairm')) {
            return response()->json(['ok' => false, 'message' => 'Repair return table not found.'], 404);
        }

        $query = DB::table('repairm')
            ->where('givrec', 'G')
            ->whereRaw('UPPER(TRIM(billno)) = ?', [$billNo]);
        if ($tdate) {
            $query->whereDate('tdate', $tdate);
        }
        $row = $query->orderByDesc('slno')->first(['billno']);

        if (!$row) {
            return response()->json(['ok' => false, 'message' => 'This bill number does not exist...'], 404);
        }

        $queryArgs = ['bill_no' => trim((string) ($row->billno ?? ''))];
        if ($action === 'reprint' && !$viewOnly) {
            $queryArgs['autoprint'] = '1';
        }

        return response()->json([
            'ok' => true,
            'doc_no' => trim((string) ($row->billno ?? '')),
            'url' => url('/repair-return/' . $action . '?' . http_build_query($queryArgs)),
        ]);
    }

    // ─── API: Next document number ──────────────────────────────────────────

    public function nextDoc(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);
        $n = $this->genInt('RM4B') + 1;
        return response()->json([
            'ok'     => true,
            'doc_no' => 'RM4/' . str_pad((string) $n, 5, '0', STR_PAD_LEFT),
        ]);
    }

    // ─── API: Search repair return bills ────────────────────────────────────

    public function navigate(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);

        if (!$this->hasTable('repairm')) {
            return response()->json(['ok' => false, 'message' => 'repairm missing'], 500);
        }

        $direction = strtolower(trim((string) $request->query('direction', 'next')));
        $slno = (int) $request->query('slno', 0);
        $billNo = strtoupper(trim((string) $request->query('bill_no', '')));

        if ($slno <= 0 && $billNo !== '') {
            $slno = (int) (DB::table('repairm')->where('givrec', 'G')->whereRaw('TRIM(billno)=?', [$billNo])->value('slno') ?? 0);
        }

        $query = DB::table('repairm')->where('givrec', 'G');
        if ($direction === 'first') {
            $row = $query->orderBy('slno')->first(['slno', 'billno']);
        } elseif ($direction === 'previous' || $direction === 'prev') {
            $row = $query->where('slno', '<', $slno)->orderByDesc('slno')->first(['slno', 'billno']);
        } elseif ($direction === 'last') {
            $row = $query->orderByDesc('slno')->first(['slno', 'billno']);
        } else {
            $row = $query->where('slno', '>', $slno)->orderBy('slno')->first(['slno', 'billno']);
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

    public function search(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);

        if (!$this->hasTable('repairm')) {
            return response()->json(['ok' => true, 'rows' => []]);
        }

        $q = trim((string) $request->query('q', ''));
        $rows = DB::table('repairm')
            ->where('givrec', 'G')
            ->when($q !== '', function ($qb) use ($q) {
                $qb->where(function ($w) use ($q) {
                    $w->where('billno', 'like', "%{$q}%")
                      ->orWhere('custcode', 'like', "%{$q}%")
                      ->orWhere('custname', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('slno')
            ->limit(80)
            ->get(['slno', 'billno', 'tdate', 'custcode', 'custname', 'amount', 'status'])
            ->map(fn($r) => [
                'slno'     => (int) ($r->slno ?? 0),
                'billno'   => trim((string) ($r->billno ?? '')),
                'tdate'    => (string) ($r->tdate ?? ''),
                'custcode' => trim((string) ($r->custcode ?? '')),
                'custname' => trim((string) ($r->custname ?? '')),
                'amount'   => (float) ($r->amount ?? 0),
                'status'   => (int) ($r->status ?? 0),
            ])
            ->values()
            ->all();

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    // ─── API: Get repair return bill ────────────────────────────────────────

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
                ->get()
                ->map(fn($r) => [
                    'itemcode'    => trim((string) ($r->code ?? '')),
                    'itemname'    => trim((string) ($r->name ?? '')),
                    'qty'         => (int) ($r->qty ?? 0),
                    'weight'      => (float) ($r->weight ?? 0),
                    'stonewgt'    => (float) ($r->stonewgt ?? 0),
                    'netwgt'      => (float) ($r->netwgt ?? 0),
                    'wastage'     => (float) ($r->wastage ?? 0),
                    'mcharge'     => (float) ($r->mcharge ?? 0),
                    'amount'      => (float) ($r->amount ?? 0),
                    'rate'        => (float) ($r->rate ?? 0),
                    'oldwgt'      => (float) ($r->addwgt ?? 0) > 0 ? 0 : (float) ($r->weight ?? 0),
                    'oldstwgt'    => (float) ($r->stonewgt ?? 0),
                    'cost'        => (float) ($r->cost ?? 0),
                    'stktype'     => trim((string) ($r->stktype ?? '')),
                ])->values()->all()
            : [];

        // Try to get old weights from original receipt
        $rbillno = trim((string) ($m->rbillno ?? ''));
        if ($rbillno !== '' && $this->hasTable('repaird')) {
            $origSlno = (int) (DB::table('repairm')->whereRaw('TRIM(billno)=?', [$rbillno])->value('slno') ?? 0);
            if ($origSlno > 0) {
                $origRows = DB::table('repaird')->where('slno', $origSlno)->orderBy('sno')->get();
                foreach ($rows as $i => &$row) {
                    if (isset($origRows[$i])) {
                        $row['oldwgt']   = (float) ($origRows[$i]->weight ?? 0);
                        $row['oldstwgt'] = (float) ($origRows[$i]->stonewgt ?? 0);
                    }
                }
                unset($row);
            }
        }

        return response()->json([
            'ok'       => true,
            'master'   => [
                'slno'     => (int) ($m->slno ?? 0),
                'billno'   => trim((string) ($m->billno ?? '')),
                'tdate'    => (string) ($m->tdate ?? ''),
                'custcode' => trim((string) ($m->custcode ?? '')),
                'custname' => trim((string) ($m->custname ?? '')),
                'addr'     => $this->customerAddress(trim((string) ($m->custcode ?? ''))),
                'phone'    => $this->customerPhone(trim((string) ($m->custcode ?? ''))),
                'sman'     => trim((string) ($m->sman ?? '')),
                'amount'   => (float) ($m->amount ?? 0),
                'discount' => (float) ($m->discount ?? 0),
                'rcvd'     => (float) ($m->rcvd ?? 0),
                'taxperc'  => (float) ($m->taxperc ?? 0),
                'taxamt'   => (float) ($m->taxamt ?? 0),
                'rbillno'  => $rbillno,
                'cashbank_code' => $this->cashBankCodeForSlno((int) ($m->slno ?? 0)),
                'status'   => (int) ($m->status ?? 0),
            ],
            'rows' => $rows,
        ]);
    }

    // ─── API: Load original repair receipt items ────────────────────────────

    public function loadReceipt(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);

        $billno = strtoupper(trim((string) $request->query('billno', '')));
        if ($billno === '') {
            return response()->json(['ok' => false, 'message' => 'Remake receipt no required']);
        }

        if (!$this->hasTable('repairm') || !$this->hasTable('repaird')) {
            return response()->json(['ok' => false, 'message' => 'Tables missing']);
        }

        $m = DB::table('repairm')->whereRaw('TRIM(billno)=?', [$billno])->first();
        if (!$m) {
            return response()->json(['ok' => false, 'message' => 'Remake receipt not found']);
        }

        if (Schema::hasColumn('repairm', 'rbillno')) {
            $alreadyUsed = DB::table('repairm')
                ->where('givrec', 'G')
                ->whereRaw('UPPER(TRIM(rbillno)) = ?', [$billno])
                ->exists();
            if ($alreadyUsed) {
                return response()->json(['ok' => false, 'message' => 'This remake receipt is already selected/saved.']);
            }
        }

        // Get gold/silver rates
        $grate = 0;
        $srate = 0;
        if ($this->hasTable('generald')) {
            $grate = (float) (DB::table('generald')->where('code', 'GRATE')->value('cvalue') ?? 0);
            $srate = (float) (DB::table('generald')->where('code', 'SRATE')->value('cvalue') ?? 0);
        }

        $items = DB::table('repaird')->where('slno', $m->slno)->orderBy('sno')
            ->get(['code', 'name', 'qty', 'weight', 'stonewgt', 'stktype', 'sno'])
            ->map(function ($r) use ($grate, $srate) {
                $code = trim((string) ($r->code ?? ''));
                // Get item type
                $itype = '';
                $cost  = 0;
                if ($this->hasTable('items')) {
                    $it = DB::table('items')->whereRaw('TRIM(code)=?', [$code])->first(['itype', 'cost']);
                    if ($it) {
                        $itype = trim((string) ($it->itype ?? ''));
                        $cost  = (float) ($it->cost ?? 0);
                    }
                }
                $rate = 0;
                if ($itype === 'G') $rate = $grate;
                elseif ($itype === 'S') $rate = $srate;

                return [
                    'itemcode' => $code,
                    'itemname' => trim((string) ($r->name ?? '')),
                    'qty'      => (int) ($r->qty ?? 0),
                    'weight'   => (float) ($r->weight ?? 0),
                    'stonewgt' => (float) ($r->stonewgt ?? 0),
                    'oldwgt'   => (float) ($r->weight ?? 0),
                    'oldstwgt' => (float) ($r->stonewgt ?? 0),
                    'rate'     => $rate,
                    'cost'     => $cost,
                    'stktype'  => trim((string) ($r->stktype ?? '')),
                    'sno'      => (int) ($r->sno ?? 0),
                ];
            })->values()->all();

        return response()->json([
            'ok'       => true,
            'custcode' => trim((string) ($m->custcode ?? '')),
            'custname' => trim((string) ($m->custname ?? '')),
            'sman'     => trim((string) ($m->sman ?? '')),
            'addr'     => $this->customerAddress(trim((string) ($m->custcode ?? ''))),
            'phone'    => $this->customerPhone(trim((string) ($m->custcode ?? ''))),
            'control'  => (int) ($m->control ?? 1),
            'items'    => $items,
        ]);
    }

    // ─── API: Search remake receipts (for pre-dialog) ───────────────────────

    public function searchReceipts(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);

        $q = trim((string) $request->query('q', ''));

        if (!$this->hasTable('repairm')) {
            return response()->json(['ok' => true, 'rows' => []]);
        }

        $query = DB::table('repairm')->where('givrec', 'R');

        if (Schema::hasColumn('repairm', 'rbillno')) {
            $query->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('repairm as used')
                    ->where('used.givrec', 'G')
                    ->whereRaw('UPPER(TRIM(used.rbillno)) = UPPER(TRIM(repairm.billno))');
            });
        }

        $rows = $query
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
                'slno'     => (int) ($r->slno ?? 0),
                'billno'   => trim((string) ($r->billno ?? '')),
                'tdate'    => (string) ($r->tdate ?? ''),
                'custcode' => trim((string) ($r->custcode ?? '')),
                'custname' => trim((string) ($r->custname ?? '')),
                'status'   => (int) ($r->status ?? 0),
            ])
            ->values()
            ->all();

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    // ─── API: Save ──────────────────────────────────────────────────────────

    public function save(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);

        if (!$this->hasTable('repairm') || !$this->hasTable('repaird')) {
            return response()->json(['ok' => false, 'message' => 'Repair tables missing'], 500);
        }

        $mode     = strtolower(trim((string) $request->input('mode', 'new')));
        $billNo   = strtoupper(trim((string) $request->input('bill_no', '')));
        $slno     = (int) $request->input('slno', 0);
        $tdate    = $this->toSqlDate((string) $request->input('tdate', '')) ?? date('Y-m-d');
        $custCode = strtoupper(trim((string) $request->input('custcode', '')));
        $custName = trim((string) $request->input('custname', ''));
        $sman     = strtoupper(trim((string) $request->input('sman', '')));
        $rbillno  = strtoupper(trim((string) $request->input('rbillno', '')));
        $amount   = (float) $request->input('amount', 0);
        $discount = (float) $request->input('discount', 0);
        $rcvd     = (float) $request->input('rcvd', 0);
        $cashBank = strtoupper(trim((string) $request->input('cashbank_code', '')));
        if ($cashBank === '') $cashBank = 'CASH';
        $taxperc  = (float) $request->input('taxperc', 0);
        $taxamt   = (float) $request->input('taxamt', 0);
        $rows     = $request->input('rows', []);
        if (!is_array($rows)) $rows = [];

        $gisemi = (int) ($request->session()->get('semi', 1));

        $normRows = [];
        foreach ($rows as $r) {
            $code = strtoupper(trim((string) ($r['itemcode'] ?? '')));
            if ($code === '') continue;
            $weight = (float) ($r['weight'] ?? 0);
            if ($weight <= 0) {
                return response()->json(['ok' => false, 'message' => "Check Weight ({$code}). You can't save..."], 422);
            }
            $normRows[] = [
                'itemcode' => $code,
                'itemname' => trim((string) ($r['itemname'] ?? '')),
                'qty'      => (int) ($r['qty'] ?? 0),
                'weight'   => round($weight, 3),
                'stonewgt' => round((float) ($r['stonewgt'] ?? 0), 3),
                'netwgt'   => round((float) ($r['netwgt'] ?? 0), 3),
                'wastage'  => round((float) ($r['wastage'] ?? 0), 3),
                'mcharge'  => round((float) ($r['mcharge'] ?? 0), 2),
                'amount'   => round((float) ($r['amount'] ?? 0), 2),
                'rate'     => round((float) ($r['rate'] ?? 0), 2),
                'cost'     => round((float) ($r['cost'] ?? 0), 2),
                'stktype'  => trim((string) ($r['stktype'] ?? '')),
            ];
        }

        if (empty($normRows)) {
            return response()->json(['ok' => false, 'message' => 'No item rows to save'], 422);
        }

        if ($sman === '') {
            return response()->json(['ok' => false, 'message' => 'Select salesman'], 422);
        }

        if ($amount <= 0) {
            return response()->json(['ok' => false, 'message' => "It is an empty bill. You can't save..."], 422);
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
                $oldMaster = DB::table('repairm')->where('slno', $slno)->first();
                $oldControl = (int) ($oldMaster->control ?? 1);
                if ($oldMaster) {
                    $oldCustCode = trim((string) ($oldMaster->custcode ?? ''));
                    $oldBalance = round((float) ($oldMaster->amount ?? 0) + (float) ($oldMaster->taxamt ?? 0) - (float) ($oldMaster->discount ?? 0) - (float) ($oldMaster->rcvd ?? 0), 2);
                    $this->adjustClientBalance($oldCustCode, -$oldBalance);
                }
                // Reverse stock (re-add items that were previously decreased)
                $this->reverseStock($slno, $oldControl, 'increase');
                DB::table('repaird')->where('slno', $slno)->delete();
                DB::table('repairm')->where('slno', $slno)->delete();
                // Remove old daybook entries
                if ($this->hasTable('daybook')) {
                    DB::table('daybook')->where('slno', $slno)->delete();
                }
                if ($this->hasTable('daybookpart')) {
                    DB::table('daybookpart')->where('slno', $slno)->delete();
                }
            } else {
                $slno   = $this->incrementGenInt('SERIALNO');
                $billNo = $this->nextDocNo();
            }

            DB::table('repairm')->insert($this->f('repairm', [
                'slno'     => $slno,
                'billno'   => $billNo,
                'tdate'    => $tdate,
                'custcode' => $custCode,
                'custname' => $custName,
                'amount'   => $amount,
                'discount' => $discount,
                'rcvd'     => $rcvd,
                'givrec'   => 'G',
                'control'  => $gisemi,
                'status'   => 1,
                'rbillno'  => $rbillno,
                'sman'     => $sman,
                'taxperc'  => $taxperc,
                'taxamt'   => $taxamt,
                'ic'       => 1,
            ]));

            $sno = 1;
            foreach ($normRows as $r) {
                // Decrease stock (items going back to customer)
                $this->decreaseItemStock($r['itemcode'], $r['qty'], $r['weight'], $r['stonewgt'], $r['stktype'], $gisemi);

                DB::table('repaird')->insert($this->f('repaird', [
                    'slno'      => $slno,
                    'code'      => $r['itemcode'],
                    'name'      => $r['itemname'],
                    'qty'       => $r['qty'],
                    'weight'    => $r['weight'],
                    'stonewgt'  => $r['stonewgt'],
                    'netwgt'    => $r['netwgt'],
                    'wastage'   => $r['wastage'],
                    'mcharge'   => $r['mcharge'],
                    'amount'    => $r['amount'],
                    'rate'      => $r['rate'],
                    'cost'      => $r['cost'],
                    'givrec'    => 'G',
                    'sno'       => $sno++,
                    'stktype'   => $r['stktype'],
                    'addwgt'    => $r['netwgt'],
                ]));
            }

            // Update client balance
            $balance = round($amount + $taxamt - $discount - $rcvd, 2);
            $this->adjustClientBalance($custCode, $balance);

            $this->insertFinancialEntries($slno, $billNo, $tdate, $custCode, $custName, $amount, $taxamt, $discount, $rcvd, $cashBank, $gisemi);

            DB::commit();
            $this->logDelpart($request, 'Repair Return(' . $billNo . ') ' . ($mode === 'edit' ? 'Updated' : 'Saved'), ['utype' => $mode === 'edit' ? 'E' : 'A', 'ttype' => 'T', 'slno' => $slno, 'tdate' => $tdate, 'control' => $gisemi]);
            return response()->json(['ok' => true, 'message' => 'Saved', 'slno' => $slno, 'bill_no' => $billNo]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'message' => 'Save failed: ' . $e->getMessage()]);
        }
    }

    // ─── API: Cancel ────────────────────────────────────────────────────────

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
            // Reverse stock (re-add items since we're undoing the return)
            $this->reverseStock((int) $m->slno, (int) ($m->control ?? 1), 'increase');

            // Reverse client balance
            $custCode = trim((string) ($m->custcode ?? ''));
            $balance  = round((float) ($m->amount ?? 0) + (float) ($m->taxamt ?? 0) - (float) ($m->discount ?? 0) - (float) ($m->rcvd ?? 0), 2);
            $this->adjustClientBalance($custCode, -$balance);

            // Remove daybook
            if ($this->hasTable('daybook')) {
                DB::table('daybook')->where('slno', $m->slno)->delete();
            }
            if ($this->hasTable('daybookpart')) {
                DB::table('daybookpart')->where('slno', $m->slno)->delete();
            }

            DB::table('repaird')->where('slno', $m->slno)->delete();
            DB::table('repairm')->where('slno', $m->slno)->delete();

            DB::commit();
            $this->logDelpart($request, 'Repair Return(' . trim((string) ($m->billno ?? '')) . ') Cancelled', ['utype' => 'D', 'ttype' => 'T', 'slno' => (int) ($m->slno ?? 0), 'tdate' => (string) ($m->tdate ?? date('Y-m-d')), 'control' => (int) ($m->control ?? 1)]);
            return response()->json(['ok' => true, 'message' => 'Bill cancelled']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'message' => 'Cancel failed: ' . $e->getMessage()]);
        }
    }

    // ─── API: Customer lookup ───────────────────────────────────────────────

    public function lookupCustomer(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('user_code'), 401);

        $code = strtoupper(trim((string) $request->query('code', '')));
        if ($code === '') return response()->json(['ok' => false], 422);
        if (!$this->hasTable('clients')) return response()->json(['ok' => false, 'message' => 'clients table missing'], 500);

        $c = DB::table('clients')->whereRaw('TRIM(code)=?', [$code])->first($this->clientSelectColumns(['code', 'name', 'addr1', 'addr2', 'addr3', 'city', 'mobile', 'telephone']));
        if (!$c) return response()->json(['ok' => false, 'message' => 'Invalid customer']);

        return response()->json([
            'ok'   => true,
            'code' => trim((string) ($c->code ?? '')),
            'name' => trim((string) ($c->name ?? '')),
            'addr' => $this->formatClientAddress($c),
            'phone' => trim((string) (($c->mobile ?? '') ?: ($c->telephone ?? ''))),
        ]);
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    private function nextDocNo(): string
    {
        $n = $this->incrementGenInt('RM4B');
        return 'RM4/' . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }

    private function parsePickerDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') return null;
        foreach (['d/m/Y', 'Y-m-d'] as $format) {
            $dt = \DateTime::createFromFormat($format, $value);
            if ($dt && $dt->format($format) === $value) {
                return $dt->format('Y-m-d');
            }
        }
        return null;
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
        $next    = $current + 1;
        $updated = DB::table('generali')->where('code', $code)->update(['cvalue' => $next]);
        if ($updated === 0) DB::table('generali')->insert(['code' => $code, 'cvalue' => $next]);
        return $next;
    }

    private function insertFinancialEntries(int $slno, string $billNo, string $tdate, string $custCode, string $custName, float $amount, float $taxamt, float $discount, float $rcvd, string $cashBank, int $control): void
    {
        if ($this->hasTable('daybookpart')) {
            DB::table('daybookpart')->insert($this->f('daybookpart', [
                'slno' => $slno,
                'vchno' => $billNo,
                'particular' => mb_substr('By Repair Voucher (' . $billNo . ') To ' . $custName, 0, 100),
                'ic' => '',
                'uid' => '',
                'ttime' => date('H:i:s'),
                'taxamt' => round($taxamt, 2),
            ]));
        }

        $netCharge = round($amount + $taxamt - $discount, 2);
        $repairAc = 'RS';
        $sno = 1;

        if ($custCode !== '' && $netCharge != 0.0) {
            $this->insertDaybookRow($slno, $sno++, $tdate, $custCode, -$netCharge, $control, $repairAc);
        }
        if ($rcvd != 0.0) {
            $cashBank = $cashBank !== '' ? $cashBank : 'CASH';
            $this->insertDaybookRow($slno, $sno++, $tdate, $cashBank, -$rcvd, $control, $custCode ?: $repairAc);
            if ($custCode !== '') {
                $this->insertDaybookRow($slno, $sno++, $tdate, $custCode, $rcvd, $control, $cashBank);
            }
        }
        if ($netCharge != 0.0) {
            $this->insertDaybookRow($slno, $sno++, $tdate, $repairAc, $netCharge, $control, $custCode ?: ($cashBank ?: 'CASH'));
        }

        if ($this->hasTable('daybook')) {
            $sum = round((float) (DB::table('daybook')->where('slno', $slno)->sum('amount') ?? 0), 2);
            if ($sum != 0.0) {
                $this->insertDaybookRow($slno, $sno, $tdate, 'ROUND', -$sum, $control, $repairAc);
            }
        }
    }

    private function insertDaybookRow(int $slno, int $sno, string $tdate, string $accode, float $amount, int $control, string $opaccode): void
    {
        if (!$this->hasTable('daybook')) {
            return;
        }

        DB::table('daybook')->insert($this->f('daybook', [
            'slno' => $slno,
            'sno' => $sno,
            'tdate' => $tdate,
            'accode' => mb_substr($accode, 0, 20),
            'amount' => round($amount, 2),
            'control' => $control,
            'opaccode' => mb_substr($opaccode, 0, 20),
            'ttype' => 'RM4',
        ]));
    }

    private function cashBankAccounts(): array
    {
        if (
            !$this->hasTable('accountm') ||
            !Schema::hasColumn('accountm', 'accode') ||
            !Schema::hasColumn('accountm', 'actype2')
        ) {
            return [];
        }

        $select = ['accode as code', 'actype2'];
        if (Schema::hasColumn('accountm', 'name')) {
            $select[] = 'name';
        }

        return DB::table('accountm')
            ->whereIn('actype2', ['H', 'B'])
            ->orderByRaw("CASE WHEN actype2='H' THEN 0 ELSE 1 END, accode")
            ->get($select)
            ->map(fn ($r) => [
                'code' => trim((string) ($r->code ?? '')),
                'name' => trim((string) ($r->name ?? '')),
                'type' => trim((string) ($r->actype2 ?? '')),
            ])
            ->values()
            ->all();
    }

    private function cashBankCodeForSlno(int $slno): string
    {
        if ($slno <= 0 || !$this->hasTable('daybook') || !$this->hasTable('accountm')) {
            return '';
        }

        $row = DB::table('daybook')
            ->where('daybook.slno', $slno)
            ->join('accountm', 'accountm.accode', '=', 'daybook.accode')
            ->whereIn('accountm.actype2', ['H', 'B'])
            ->orderBy('daybook.sno')
            ->first(['daybook.accode']);

        return $row ? trim((string) ($row->accode ?? '')) : '';
    }

    private function adjustClientBalance(string $custCode, float $amount): void
    {
        $custCode = trim($custCode);
        if ($custCode === '' || $amount == 0.0) {
            return;
        }

        $balanceColumn = $this->clientBalanceColumn();
        if ($balanceColumn === null) {
            return;
        }

        DB::table('clients')->whereRaw('TRIM(code)=?', [$custCode])
            ->update([$balanceColumn => DB::raw('COALESCE(`' . $balanceColumn . '`,0) + ' . round($amount, 2))]);
    }

    private function decreaseItemStock(string $code, float $qty, float $weight, float $stoneWgt, string $stkType, int $control): void
    {
        if (!$this->hasTable('items')) return;

        if ($control === 1) {
            DB::table('items')->whereRaw('TRIM(code)=?', [$code])->update($this->f('items', [
                'weight'     => DB::raw('COALESCE(weight,0) - ' . $weight),
                'qty'        => DB::raw('COALESCE(qty,0) - ' . $qty),
                'weightb'    => DB::raw('COALESCE(weightb,0) - ' . $weight),
                'qtyb'       => DB::raw('COALESCE(qtyb,0) - ' . $qty),
                'stonewgt'   => DB::raw('COALESCE(stonewgt,0) - ' . $stoneWgt),
                'stonewgtb'  => DB::raw('COALESCE(stonewgtb,0) - ' . $stoneWgt),
            ]));
        } else {
            DB::table('items')->whereRaw('TRIM(code)=?', [$code])->update($this->f('items', [
                'weightb'    => DB::raw('COALESCE(weightb,0) - ' . $weight),
                'qtyb'       => DB::raw('COALESCE(qtyb,0) - ' . $qty),
                'stonewgtb'  => DB::raw('COALESCE(stonewgtb,0) - ' . $stoneWgt),
            ]));
        }

        if ($stkType !== '' && $this->hasTable('itemsstk')) {
            if ($control === 1) {
                DB::table('itemsstk')
                    ->whereRaw('TRIM(code)=?', [$code])
                    ->where('stktype', $stkType)
                    ->update($this->f('itemsstk', [
                        'weight'    => DB::raw('COALESCE(weight,0) - ' . $weight),
                        'qty'       => DB::raw('COALESCE(qty,0) - ' . $qty),
                        'weightb'   => DB::raw('COALESCE(weightb,0) - ' . $weight),
                        'qtyb'      => DB::raw('COALESCE(qtyb,0) - ' . $qty),
                        'stonewgt'  => DB::raw('COALESCE(stonewgt,0) - ' . $stoneWgt),
                        'stonewgtb' => DB::raw('COALESCE(stonewgtb,0) - ' . $stoneWgt),
                    ]));
            } else {
                DB::table('itemsstk')
                    ->whereRaw('TRIM(code)=?', [$code])
                    ->where('stktype', $stkType)
                    ->update($this->f('itemsstk', [
                        'weightb'   => DB::raw('COALESCE(weightb,0) - ' . $weight),
                        'qtyb'      => DB::raw('COALESCE(qtyb,0) - ' . $qty),
                        'stonewgtb' => DB::raw('COALESCE(stonewgtb,0) - ' . $stoneWgt),
                    ]));
            }
        }
    }

    private function reverseStock(int $slno, int $control, string $direction = 'increase'): void
    {
        $rows = DB::table('repaird')->where('slno', $slno)->get(['code', 'qty', 'weight', 'stonewgt', 'stktype']);
        $sign = $direction === 'increase' ? '+' : '-';

        foreach ($rows as $r) {
            $code    = trim((string) ($r->code ?? ''));
            if ($code === '') continue;
            $qty     = (float) ($r->qty ?? 0);
            $w       = round((float) ($r->weight ?? 0), 3);
            $st      = round((float) ($r->stonewgt ?? 0), 3);
            $stktype = trim((string) ($r->stktype ?? ''));

            if ($control === 1) {
                DB::table('items')->whereRaw('TRIM(code)=?', [$code])->update($this->f('items', [
                    'weight'    => DB::raw("COALESCE(weight,0) {$sign} {$w}"),
                    'qty'       => DB::raw("COALESCE(qty,0) {$sign} {$qty}"),
                    'weightb'   => DB::raw("COALESCE(weightb,0) {$sign} {$w}"),
                    'qtyb'      => DB::raw("COALESCE(qtyb,0) {$sign} {$qty}"),
                    'stonewgt'  => DB::raw("COALESCE(stonewgt,0) {$sign} {$st}"),
                    'stonewgtb' => DB::raw("COALESCE(stonewgtb,0) {$sign} {$st}"),
                ]));
                if ($stktype !== '' && $this->hasTable('itemsstk')) {
                    DB::table('itemsstk')->whereRaw('TRIM(code)=?', [$code])->where('stktype', $stktype)
                        ->update($this->f('itemsstk', [
                            'weight'    => DB::raw("COALESCE(weight,0) {$sign} {$w}"),
                            'qty'       => DB::raw("COALESCE(qty,0) {$sign} {$qty}"),
                            'weightb'   => DB::raw("COALESCE(weightb,0) {$sign} {$w}"),
                            'qtyb'      => DB::raw("COALESCE(qtyb,0) {$sign} {$qty}"),
                            'stonewgt'  => DB::raw("COALESCE(stonewgt,0) {$sign} {$st}"),
                            'stonewgtb' => DB::raw("COALESCE(stonewgtb,0) {$sign} {$st}"),
                        ]));
                }
            } else {
                DB::table('items')->whereRaw('TRIM(code)=?', [$code])->update($this->f('items', [
                    'weightb'   => DB::raw("COALESCE(weightb,0) {$sign} {$w}"),
                    'qtyb'      => DB::raw("COALESCE(qtyb,0) {$sign} {$qty}"),
                    'stonewgtb' => DB::raw("COALESCE(stonewgtb,0) {$sign} {$st}"),
                ]));
                if ($stktype !== '' && $this->hasTable('itemsstk')) {
                    DB::table('itemsstk')->whereRaw('TRIM(code)=?', [$code])->where('stktype', $stktype)
                        ->update($this->f('itemsstk', [
                            'weightb'   => DB::raw("COALESCE(weightb,0) {$sign} {$w}"),
                            'qtyb'      => DB::raw("COALESCE(qtyb,0) {$sign} {$qty}"),
                            'stonewgtb' => DB::raw("COALESCE(stonewgtb,0) {$sign} {$st}"),
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

    private function clientBalanceColumn(): ?string
    {
        if (!$this->hasTable('clients')) {
            return null;
        }

        foreach (['balance', 'clamt', 'opbalance'] as $column) {
            if (Schema::hasColumn('clients', $column)) {
                return $column;
            }
        }

        return null;
    }

    private function shopInfo(): array
    {
        $info = ['name' => '', 'address' => '', 'phone' => '', 'mobile' => '', 'gstin' => ''];

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

        if ($this->hasTable('generals') && Schema::hasColumn('generals', 'code')) {
            $valueColumn = Schema::hasColumn('generals', 'cvalue')
                ? 'cvalue'
                : (Schema::hasColumn('generals', 'value') ? 'value' : null);
            if ($valueColumn === null) {
                return $info;
            }

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

        $addressLines = preg_split('/\R+/', $info['address']) ?: [];
        $info['address'] = implode("\n", array_values(array_unique(array_filter(
            array_map(fn ($value) => trim((string) $value), $addressLines),
            fn ($value) => $value !== ''
        ))));

        return $info;
    }

    private function customerAddress(string $code): string
    {
        $customer = $this->customerRow($code);
        return $customer ? $this->formatClientAddress($customer) : '';
    }

    private function customerPhone(string $code): string
    {
        $customer = $this->customerRow($code);
        return $customer ? trim((string) (($customer->mobile ?? '') ?: ($customer->telephone ?? ''))) : '';
    }

    private function customerRow(string $code): ?object
    {
        $code = trim($code);
        if ($code === '' || !$this->hasTable('clients')) {
            return null;
        }

        return DB::table('clients')
            ->whereRaw('TRIM(code)=?', [$code])
            ->first($this->clientSelectColumns(['code', 'name', 'addr1', 'addr2', 'addr3', 'city', 'mobile', 'telephone']));
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
}
