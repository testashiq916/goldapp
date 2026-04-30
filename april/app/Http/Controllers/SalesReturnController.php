<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesSecondarySeriesPrefix;
use App\Http\Controllers\Concerns\LogsDelpartAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SalesReturnController extends Controller
{
    use HandlesSecondarySeriesPrefix;
    use LogsDelpartAudit;

    private function nextSerialNo(): int
    {
        if (!$this->hasTable('generali')) {
            return 1;
        }

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

    // ── Auth guard ────────────────────────────────────────────────────────────

    private function auth(Request $request): bool
    {
        return $request->session()->has('user_code');
    }

    private function unauth(): \Illuminate\Http\JsonResponse
    {
        return response()->json(['success' => false, 'error' => 'Unauthorized'], 401);
    }

    // ── Main View ─────────────────────────────────────────────────────────────

    public function index(Request $request, string $mode = 'bill')
    {
        if (!$this->auth($request)) {
            return redirect('/login');
        }

        $validModes = ['bill', 'edit', 'cancel', 'reprint'];
        if (!in_array($mode, $validModes, true)) {
            $mode = 'bill';
        }

        $titles = [
            'bill'    => 'Enter Sales Return Bill',
            'edit'    => 'Edit Sales Return Bill',
            'cancel'  => 'Cancel Sales Return Bill',
            'reprint' => 'Reprint Sales Return Bill',
        ];
        $title = $titles[$mode];

        return view('sales-return.index', compact('mode', 'title'));
    }

    public function picker(Request $request, string $action = 'edit')
    {
        $action = strtolower($action);
        if (!in_array($action, ['edit', 'cancel', 'reprint'], true)) {
            $action = 'edit';
        }

        $titles = [
            'edit' => 'Edit Sales Return Bill',
            'cancel' => 'Sales Return Cancellation',
            'reprint' => 'Sales Return Reprint',
        ];

        return view('purchase-bill.doc-picker', [
            'title' => (string) $request->query('title', $titles[$action]),
            'actionMode' => $action,
            'docType' => 'sales-return',
            'searchUrl' => url('/api/sales-return/picker-search'),
            'resolveUrl' => url('/api/sales-return/picker-resolve'),
            'targetBaseUrl' => url('/sales-return'),
            'showViewOption' => $action === 'reprint',
        ]);
    }

    public function pickerSearch(Request $request)
    {
        if (!$this->auth($request)) return $this->unauth();
        if (!$this->hasTable('salesrm')) {
            return response()->json(['ok' => true, 'rows' => []]);
        }

        $q = trim((string) $request->query('q', ''));
        $tdate = $this->parseDate((string) $request->query('tdate', ''));

        $rows = DB::table('salesrm')
            ->where('sr', 'R')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('billno', 'like', $q . '%')
                        ->orWhere('custname', 'like', '%' . $q . '%');
                });
            })
            ->when($tdate, fn ($query) => $query->whereDate('tdate', $tdate))
            ->orderByDesc('tdate')
            ->orderByDesc('slno')
            ->limit(50)
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

    public function pickerResolve(Request $request)
    {
        if (!$this->auth($request)) return $this->unauth();
        if (!$this->hasTable('salesrm')) {
            return response()->json(['ok' => false, 'message' => 'Sales return table not found.'], 404);
        }

        $billNo = strtoupper(trim((string) $request->input('doc_no', '')));
        $tdate = $this->parseDate((string) $request->input('tdate', ''));
        $action = strtolower(trim((string) $request->input('action', 'edit')));

        if ($billNo === '') {
            return response()->json(['ok' => false, 'message' => 'Bill no is required.'], 422);
        }

        $query = DB::table('salesrm')
            ->where('sr', 'R')
            ->whereRaw('UPPER(TRIM(billno)) = ?', [$billNo]);
        if ($tdate) {
            $query->whereDate('tdate', $tdate);
        }

        $row = $query->orderByDesc('slno')->first(['slno', 'billno']);
        if (!$row) {
            return response()->json(['ok' => false, 'message' => 'This bill number does not exist...'], 404);
        }

        return response()->json([
            'ok' => true,
            'doc_no' => trim((string) ($row->billno ?? '')),
            'url' => url('/sales-return/' . $action . '?' . http_build_query([
                'slno' => (int) ($row->slno ?? 0),
            ])),
        ]);
    }

    // ── API: Customers ────────────────────────────────────────────────────────

    public function customers(Request $request)
    {
        if (!$this->auth($request)) return $this->unauth();

        $table = $this->hasTable('clients') ? 'clients' : 'customermaster';
        $cols  = ['code', 'name'];
        if (Schema::hasColumn($table, 'mobno')) $cols[] = 'mobno';

        $q = DB::table($table)->orderBy('name')->limit(500);
        if (Schema::hasColumn($table, 'disabled')) $q->where('disabled', 0);

        return response()->json(['success' => true, 'data' => $q->get($cols)]);
    }

    // ── API: Salesmen ─────────────────────────────────────────────────────────

    public function salesmen(Request $request)
    {
        if (!$this->auth($request)) return $this->unauth();

        if (!$this->hasTable('sman')) {
            return response()->json(['success' => true, 'data' => []]);
        }

        return response()->json(['success' => true, 'data' => DB::table('sman')->orderBy('name')->get(['code', 'name'])]);
    }

    // ── API: Items ────────────────────────────────────────────────────────────

    public function items(Request $request)
    {
        if (!$this->auth($request)) return $this->unauth();

        $allCols = array_map('strtolower', $this->columnList('items'));
        $cols = [];
        foreach (['code', 'name', 'itype', 'rate', 'wastage', 'mcharge', 'vaperc', 'stkinnos', 'qty', 'weight', 'cost', 'qtyb', 'weightb', 'taxinternal', 'stonewgt'] as $col) {
            if (in_array($col, $allCols, true)) $cols[] = $col;
        }
        if (!in_array('code', $cols, true)) $cols[] = 'code';
        if (!in_array('name', $cols, true)) $cols[] = 'name';

        $q = DB::table('items');
        if (in_array('name', $allCols, true)) $q->orderBy('name');
        else $q->orderBy('code');
        if (Schema::hasColumn('items', 'disabled')) $q->where('disabled', 0);

        return response()->json(['success' => true, 'data' => $q->get($cols)]);
    }

    // ── API: Bill Types ───────────────────────────────────────────────────────

    public function billTypes(Request $request)
    {
        if (!$this->auth($request)) return $this->unauth();

        if (!$this->hasTable('salestype')) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $allCols = array_map('strtolower', $this->columnList('salestype'));
        $cols = [];
        foreach (['code', 'name', 'taxperc', 'srprefix'] as $c) {
            if (in_array($c, $allCols, true)) $cols[] = $c;
        }
        if (!in_array('code', $cols, true)) $cols[] = 'code';
        if (!in_array('name', $cols, true) && in_array('mname', $allCols, true)) $cols[] = 'mname as name';
        if (!in_array('name', $cols, true)) $cols[] = DB::raw('code as name');

        $q = DB::table('salestype');
        if (in_array('name', $allCols, true)) $q->orderBy('name');
        else $q->orderBy('code');

        return response()->json(['success' => true, 'data' => $q->get($cols)]);
    }

    // ── API: Gold Rate ────────────────────────────────────────────────────────

    public function goldRate(Request $request)
    {
        if (!$this->auth($request)) return $this->unauth();

        $rate = 0.0;
        if ($this->hasTable('generald')) {
            $val = DB::table('generald')->where('code', 'GRATE')->value('cvalue');
            if ($val !== null && $val !== '') {
                $rate = $this->toNum($val);
            }
        }
        if ($this->hasTable('rates')) {
            $rCols = array_map('strtolower', $this->columnList('rates'));
            $q = DB::table('rates');
            if (in_array('tdate', $rCols, true)) $q->orderBy('tdate', 'desc');
            elseif (in_array('id', $rCols, true)) $q->orderBy('id', 'desc');
            $row = $q->first();
            if ($row) {
                $dbRate = $this->toNum($row->grate ?? $row->rate ?? 0);
                if ($dbRate > 0) {
                    $rate = $dbRate;
                }
            }
        }
        if ($rate == 0 && $this->hasTable('generals')) {
            $val = DB::table('generals')->where('code', 'GRATE')->value('cvalue');
            if ($val !== null && $val !== '') {
                $rate = $this->toNum($val);
            }
        }

        return response()->json(['success' => true, 'rate' => $rate]);
    }

    private function toNum($value): float
    {
        if ($value === null) return 0.0;
        $s = trim((string) $value);
        if ($s === '') return 0.0;
        $s = str_replace([',', ' '], '', $s);
        return is_numeric($s) ? (float) $s : 0.0;
    }

    private function parseDate(string $value): ?string
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

    // ── API: Next Bill Number ─────────────────────────────────────────────────

    public function nextNumber(Request $request)
    {
        if (!$this->auth($request)) return $this->unauth();

        $type         = $request->query('type', 'B');
        $billTypeCode = trim((string)$request->query('bill_type', ''));

        if ($type === 'B' && $this->shouldUseSecondaryPrefix('salesreturn')) {
            $secPrefix = $this->secondaryPrefixFor('salesreturn');
            $row     = DB::table('generali')->where('code', 'SRETURN' . $type)->first();
            $counter = $row ? (int)$row->cvalue : 0;
            $next    = $counter + 1;
            $billNo  = $secPrefix . str_pad($next, 5, '0', STR_PAD_LEFT);
            if ($this->hasTable('salesrm')) {
                while (DB::table('salesrm')->where('billno', $billNo)->where('sr', 'R')->exists()) {
                    $next++;
                    $billNo = $secPrefix . str_pad($next, 5, '0', STR_PAD_LEFT);
                }
            }
            return response()->json(['success' => true, 'billno' => $billNo]);
        }

        // Check BillTypewiseBillNo setting from software-settings.ini
        $billTypeWise = false;
        $iniPath = storage_path('app/software-settings.ini');
        if (file_exists($iniPath)) {
            $inSection = false;
            foreach (preg_split('/\r\n|\r|\n/', file_get_contents($iniPath)) as $ln) {
                $ln = trim($ln);
                if ($ln === '[Software]') { $inSection = true; continue; }
                if ($inSection && str_starts_with($ln, '[')) break;
                if ($inSection && stripos($ln, 'BillTypewiseBillNo=') === 0) {
                    $billTypeWise = strtoupper(trim(explode('=', $ln, 2)[1] ?? '')) === 'Y';
                    break;
                }
            }
        }

        if ($billTypeWise && $billTypeCode !== '' && $this->hasTable('salestype')) {
            $st       = DB::table('salestype')->where('code', $billTypeCode)->first();
            $srprefix = trim((string)($st->srprefix ?? ''));
            if ($srprefix !== '') {
                $counterCode = 'SRET' . $srprefix;
                $counter = (int)(DB::table('generali')->where('code', $counterCode)->value('cvalue') ?? 0);
                $next    = $counter + 1;
                $billNo  = $srprefix . str_pad($next, 5, '0', STR_PAD_LEFT);
                if ($this->hasTable('salesrm')) {
                    while (DB::table('salesrm')->where('billno', $billNo)->where('sr', 'R')->exists()) {
                        $next++;
                        $billNo = $srprefix . str_pad($next, 5, '0', STR_PAD_LEFT);
                    }
                }
                return response()->json(['success' => true, 'billno' => $billNo]);
            }
        }

        $codeKey = 'SRETURN' . $type;
        $prefix  = $type === 'B' ? 'SRB/' : 'SRE/';
        $len     = 5;

        $row     = DB::table('generali')->where('code', $codeKey)->first();
        $counter = $row ? (int)$row->cvalue : 0;
        $next    = $counter + 1;
        $billNo  = $prefix . str_pad($next, $len, '0', STR_PAD_LEFT);

        if ($this->hasTable('salesrm')) {
            while (DB::table('salesrm')->where('billno', $billNo)->where('sr', 'R')->exists()) {
                $next++;
                $billNo = $prefix . str_pad($next, $len, '0', STR_PAD_LEFT);
            }
        }

        return response()->json(['success' => true, 'billno' => $billNo]);
    }

    // ── API: List ─────────────────────────────────────────────────────────────

    public function getList(Request $request)
    {
        if (!$this->auth($request)) return $this->unauth();

        if (!$this->hasTable('salesrm')) {
            return response()->json(['success' => true, 'data' => []]);
        }

        if ($this->hasTable('salesrd')) {
            $rows = DB::select("
                SELECT m.slno, m.billno, m.custname, m.tdate, m.billamt, m.netamt,
                       m.pamt, m.control, m.billtype,
                       COUNT(d.slno) as item_count,
                       COALESCE(SUM(d.weight), 0) as total_weight
                FROM salesrm m
                LEFT JOIN salesrd d ON d.slno = m.slno
                WHERE m.sr = 'R'
                GROUP BY m.slno, m.billno, m.custname, m.tdate, m.billamt,
                         m.netamt, m.pamt, m.control, m.billtype
                ORDER BY m.tdate DESC, m.slno DESC
                LIMIT 200
            ");
            $rows = array_map(fn ($r) => (array) $r, $rows);
        } else {
            $rows = DB::table('salesrm')->where('sr', 'R')
                ->orderByDesc('tdate')->orderByDesc('slno')->limit(200)
                ->select(['slno', 'billno', 'custname', 'tdate', 'billamt', 'netamt', 'pamt', 'control', 'billtype'])
                ->get()->map(fn ($r) => array_merge((array) $r, ['item_count' => 0, 'total_weight' => 0]))->toArray();
        }

        return response()->json(['success' => true, 'data' => $rows]);
    }

    // ── API: Get Single Record ────────────────────────────────────────────────

    public function get(Request $request)
    {
        if (!$this->auth($request)) return $this->unauth();

        $slno = (int) $request->query('slno', 0);
        if (!$slno || !$this->hasTable('salesrm')) {
            return response()->json(['success' => false, 'error' => 'Not found']);
        }

        $master = DB::table('salesrm')->where('slno', $slno)->first();
        if (!$master) {
            return response()->json(['success' => false, 'error' => 'Record not found']);
        }

        $details = [];
        if ($this->hasTable('salesrd')) {
            $details = DB::select("
                SELECT rd.*, i.name as itemname2, i.itype as itemtype
                FROM salesrd rd
                LEFT JOIN items i ON i.code = rd.code
                WHERE rd.slno = ?
                ORDER BY rd.sno
            ", [$slno]);
            $details = array_map(fn ($r) => (array) $r, $details);
        }

        return response()->json(['success' => true, 'master' => $master, 'details' => $details]);
    }

    // ── API: Search Original Sale Bill ────────────────────────────────────────

    public function searchSaleBill(Request $request)
    {
        if (!$this->auth($request)) return $this->unauth();

        $billno = trim($request->query('billno', ''));
        if (!$billno || !$this->hasTable('salesm')) {
            return response()->json(['success' => false, 'error' => 'Bill not found']);
        }

        $master = DB::table('salesm')
            ->whereRaw('UPPER(TRIM(billno)) = ?', [strtoupper($billno)])
            ->first();
        if (!$master) {
            $master = DB::table('salesm')
                ->where('billno', 'like', $billno . '%')
                ->orderByDesc('slno')
                ->first();
        }
        if (!$master) {
            return response()->json(['success' => false, 'error' => 'Bill not found']);
        }

        $detailTable = $this->hasTable('salesd') ? 'salesd' : 'salesdetail';
        $details = DB::select("
            SELECT d.code, d.qty, d.weight,
                   COALESCE(d.stonewgt,0) as stonewgt,
                   COALESCE(d.stoneprice,0) as stoneprice,
                   d.mcharge, d.wastage, d.amount, d.rate,
                   COALESCE(d.stktype,'') as stktype,
                   COALESCE(d.fr,0) as fr,
                   d.sno,
                   COALESCE(d.vaperc,0) as vaperc,
                   COALESCE(d.stktouch,100) as stktouch,
                   i.name as itemname, i.itype as itemtype,
                   COALESCE(i.stkinnos,'N') as stkinnos,
                   COALESCE(i.cost,0) as cost
            FROM {$detailTable} d
            JOIN items i ON i.code = d.code
            WHERE d.slno = ?
            ORDER BY d.sno
        ", [$master->slno]);

        return response()->json(['success' => true, 'master' => $master, 'details' => $details]);
    }

    public function searchSaleBills(Request $request)
    {
        if (!$this->auth($request)) return $this->unauth();

        if (!$this->hasTable('salesm')) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $q = trim((string) $request->query('q', ''));
        $limit = max(5, min(50, (int) $request->query('limit', 20)));

        $rows = DB::table('salesm')
            ->select(['slno', 'billno', 'tdate', 'custname', 'netamt'])
            ->when($q !== '', function ($qb) use ($q): void {
                $qb->where(function ($qq) use ($q): void {
                    $qq->where('billno', 'like', $q . '%')
                       ->orWhere('custname', 'like', '%' . $q . '%');
                });
            })
            ->orderByDesc('tdate')
            ->orderByDesc('slno')
            ->limit($limit)
            ->get();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    // ── API: Item Details ─────────────────────────────────────────────────────

    public function itemDetails(Request $request)
    {
        if (!$this->auth($request)) return $this->unauth();

        $code = trim($request->query('code', ''));
        $item = DB::table('items')->where('code', $code)->first();

        if (!$item) return response()->json(['success' => false, 'error' => 'Item not found']);

        return response()->json(['success' => true, 'data' => $item]);
    }

    // ── API: Customer Balance ─────────────────────────────────────────────────

    public function customerBalance(Request $request)
    {
        if (!$this->auth($request)) return $this->unauth();

        $code    = trim($request->query('code', ''));
        $balance = DB::table('daybook')->where('accode', $code)->sum('amount');

        return response()->json(['success' => true, 'balance' => (float) $balance]);
    }

    // ── API: Save ─────────────────────────────────────────────────────────────

    public function save(Request $request)
    {
        if (!$this->auth($request)) return $this->unauth();

        $data = $request->json()->all();

        if (empty($data['billno']))  return response()->json(['success' => false, 'error' => 'Bill No is required']);
        if (empty($data['tdate']))   return response()->json(['success' => false, 'error' => 'Date is required']);
        if (empty($data['details'])) return response()->json(['success' => false, 'error' => 'No items entered']);

        $dbamt = array_sum(array_column($data['details'], 'amount'));
        if ($dbamt <= 0) return response()->json(['success' => false, 'error' => 'Bill total is zero']);

        DB::beginTransaction();
        try {
            $isEdit   = !empty($data['slno']) && (int) $data['slno'] > 0;
            $tdate    = $data['tdate'];
            $billno   = $data['billno'];
            $custcode = $data['custcode'] ?? '';
            $custname = $data['custname'] ?? '';
            $smcode   = $data['smcode'] ?? '';
            $billamt  = (float) ($data['billamt'] ?? 0);
            $discount = (float) ($data['discount'] ?? 0);
            $pamt     = (float) ($data['pamt'] ?? 0);
            $staxamt  = (float) ($data['staxamt'] ?? 0);
            $astamt   = (float) ($data['astamt'] ?? 0);
            $staxperc = (float) ($data['staxperc'] ?? 0);
            $netamt   = $billamt + $staxamt + $astamt;
            $control  = (int) ($data['control'] ?? 1);
            $sbillno  = $data['sbillno'] ?? '';
            $billtype = $data['billtype'] ?? '';
            $cst      = $data['cst'] ?? 'N';
            $fr       = $data['fr'] ?? 'N';
            $ob       = (float) ($data['ob'] ?? 0);
            $grate    = (float) ($data['grate'] ?? 0);

            $sgst = $cgst = $igst = 0;
            if ($cst === 'Y') {
                $igst = $staxamt;
            } else {
                $sgst = $cgst = round($staxamt / 2, 2);
            }

            if ($isEdit) {
                $slno       = (int) $data['slno'];
                $oldMaster  = DB::table('salesrm')->where('slno', $slno)->first();
                $oldControl = $oldMaster ? (int) $oldMaster->control : 1;

                if ($this->hasTable('salesrd')) {
                    foreach (DB::table('salesrd')->where('slno', $slno)->get() as $od) {
                        $this->adjustStock($od->code, -$od->qty, -(float)$od->weight, -(float)$od->stonewgt, $oldControl);
                    }
                    DB::table('salesrd')->where('slno', $slno)->delete();
                }

                DB::table('daybook')->where('slno', $slno)->delete();
                DB::table('daybookpart')->where('slno', $slno)->delete();
                if ($this->hasTable('spdmddet')) DB::table('spdmddet')->where('slno', $slno)->delete();

                DB::table('salesrm')->where('slno', $slno)->update([
                    'billno' => $billno, 'custcode' => $custcode, 'custname' => $custname,
                    'billamt' => $billamt, 'pamt' => $pamt, 'discount' => $discount,
                    'tdate' => $tdate, 'grate' => $grate, 'control' => $control,
                    'smcode' => $smcode, 'ob' => $ob, 'staxamt' => $staxamt,
                    'staxperc' => $staxperc, 'sbillno' => $sbillno, 'astamt' => $astamt,
                    'sgst' => $sgst, 'cgst' => $cgst, 'igst' => $igst, 'cst' => $cst,
                    'billtype' => $billtype, 'netamt' => $netamt, 'fr' => $fr,
                ]);
            } else {
                $slno     = $this->nextSerialNo();

                // Determine counter key: BillTypewiseBillNo uses SRET<srprefix>, else SRETURNB/E
                $srBillTypeWise = false;
                $iniPathSave = storage_path('app/software-settings.ini');
                if (file_exists($iniPathSave)) {
                    $inSec = false;
                    foreach (preg_split('/\r\n|\r|\n/', file_get_contents($iniPathSave)) as $ln) {
                        $ln = trim($ln);
                        if ($ln === '[Software]') { $inSec = true; continue; }
                        if ($inSec && str_starts_with($ln, '[')) break;
                        if ($inSec && stripos($ln, 'BillTypewiseBillNo=') === 0) {
                            $srBillTypeWise = strtoupper(trim(explode('=', $ln, 2)[1] ?? '')) === 'Y';
                            break;
                        }
                    }
                }
                $codeKey = 'SRETURN' . ($control == 1 ? 'B' : 'E');
                if ($srBillTypeWise && $billtype !== '' && $this->hasTable('salestype')) {
                    $srSt     = DB::table('salestype')->where('code', $billtype)->first();
                    $srPrefix = trim((string)($srSt->srprefix ?? ''));
                if ($srPrefix !== '') $codeKey = 'SRET' . $srPrefix;
                }

                if (DB::table('generali')->where('code', $codeKey)->exists()) {
                    DB::table('generali')->where('code', $codeKey)->increment('cvalue');
                } else {
                    DB::table('generali')->insert(['code' => $codeKey, 'cvalue' => 1]);
                }

                DB::table('salesrm')->insert([
                    'slno' => $slno, 'billno' => $billno, 'custcode' => $custcode,
                    'custname' => $custname, 'billamt' => $billamt, 'pamt' => $pamt,
                    'discount' => $discount, 'tdate' => $tdate, 'grate' => $grate,
                    'control' => $control, 'smcode' => $smcode, 'ob' => $ob,
                    'staxamt' => $staxamt, 'staxperc' => $staxperc, 'sbillno' => $sbillno,
                    'astamt' => $astamt, 'sgst' => $sgst, 'cgst' => $cgst, 'igst' => $igst,
                    'cst' => $cst, 'billtype' => $billtype, 'netamt' => $netamt,
                    'status' => 1, 'sr' => 'R', 'fr' => $fr,
                ]);
            }

            // Insert details
            foreach ($data['details'] as $i => $d) {
                $code = trim($d['code'] ?? '');
                if (!$code) continue;

                $qty        = (int)   ($d['qty']        ?? 0);
                $weight     = (float) ($d['weight']     ?? 0);
                $stonewgt   = (float) ($d['stonewgt']   ?? 0);
                $stoneprice = (float) ($d['stoneprice'] ?? 0);
                $mcharge    = (float) ($d['mcharge']    ?? 0);
                $wastage    = (float) ($d['wastage']    ?? 0);
                $rate       = (float) ($d['rate']       ?? 0);
                $amount     = (float) ($d['amount']     ?? 0);
                $cost       = (float) ($d['cost']       ?? 0);
                $stktype    =         $d['stktype']     ?? '';
                $iqtype     =         $d['iqtype']      ?? '';
                $stktouch   = (float) ($d['stktouch']   ?? 100);
                $bcode      = (int)   ($d['bcode']      ?? 0);
                $dmdwgt     = (float) ($d['dmdwgt']     ?? 0);
                $vaperc     = (float) ($d['vaperc']     ?? 0);
                $frItem     = (int)   ($d['fr']         ?? 0);
                $note       =         $d['note']        ?? '';
                $itemname   =         $d['itemname']    ?? '';

                $origName = DB::table('items')->where('code', $code)->value('name');
                $saveName = (trim($itemname) !== trim((string) $origName)) ? $itemname : '';

                DB::table('salesrd')->insert([
                    'slno' => $slno, 'sno' => $i + 1, 'code' => $code,
                    'qty' => $qty, 'weight' => $weight, 'stonewgt' => $stonewgt,
                    'stoneprice' => $stoneprice, 'mcharge' => $mcharge, 'wastage' => $wastage,
                    'rate' => $rate, 'amount' => $amount, 'cost' => $cost,
                    'name' => $saveName, 'stktype' => $stktype, 'iqtype' => $iqtype,
                    'stktouch' => $stktouch, 'bcode' => $bcode, 'dmdwgt' => $dmdwgt,
                    'vaperc' => $vaperc, 'fr' => $frItem, 'note' => $note,
                ]);

                $this->adjustStock($code, $qty, $weight, $stonewgt, $control);
            }

            // Daybook
            $particular = substr('By Sales Return(' . $billno . ')' . ($custname ? ' From ' . $custname : ''), 0, 40);
            DB::table('daybookpart')->insert([
                'slno' => $slno, 'particular' => $particular, 'vchno' => '',
                'ic' => 'admin', 'uid' => 'admin', 'ttime' => now(), 'rate' => $grate,
            ]);

            if ($pamt > 0) {
                DB::table('daybook')->insert(['slno' => $slno, 'accode' => 'CASH',
                    'amount' => $pamt, 'control' => $control, 'tdate' => $tdate, 'opaccode' => 'ESR']);
            }

            if ($custcode) {
                $dacamt = $netamt - $discount;
                DB::table('daybook')->insert(['slno' => $slno, 'accode' => $custcode,
                    'amount' => $dacamt, 'control' => $control, 'tdate' => $tdate, 'opaccode' => 'ESR']);
                if ($pamt > 0) {
                    DB::table('daybook')->insert(['slno' => $slno, 'accode' => $custcode,
                        'amount' => -$pamt, 'control' => $control, 'tdate' => $tdate, 'opaccode' => 'ESR']);
                }
            }

            if ($discount > 0) {
                DB::table('daybook')->insert(['slno' => $slno, 'accode' => 'DISC',
                    'amount' => $discount, 'control' => $control, 'tdate' => $tdate, 'opaccode' => 'ESR']);
            }

            if ($staxamt > 0) {
                if ($sgst > 0) {
                    DB::table('daybook')->insert(['slno' => $slno, 'accode' => 'SGST', 'amount' => -$sgst, 'control' => $control, 'tdate' => $tdate, 'opaccode' => 'ESR']);
                    DB::table('daybook')->insert(['slno' => $slno, 'accode' => 'CGST', 'amount' => -$cgst, 'control' => $control, 'tdate' => $tdate, 'opaccode' => 'ESR']);
                } elseif ($igst > 0) {
                    DB::table('daybook')->insert(['slno' => $slno, 'accode' => 'IGST', 'amount' => -$igst, 'control' => $control, 'tdate' => $tdate, 'opaccode' => 'ESR']);
                }
            }

            if ($billamt > 0) {
                DB::table('daybook')->insert(['slno' => $slno, 'accode' => 'ESR',
                    'amount' => -$billamt, 'control' => $control, 'tdate' => $tdate, 'opaccode' => 'ESR']);
            }

            DB::commit();
            $this->logDelpart($request, 'Sales Return(' . $billno . ') Saved', ['utype' => $isEdit ? 'E' : 'A', 'ttype' => 'T', 'slno' => $slno, 'tdate' => $tdate, 'control' => $control]);

            return response()->json([
                'success' => true, 'slno' => $slno, 'billno' => $billno,
                'message' => 'Sales Return saved successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ── API: Delete ───────────────────────────────────────────────────────────

    public function delete(Request $request)
    {
        if (!$this->auth($request)) return $this->unauth();

        $slno = (int) $request->json('slno', 0);
        if (!$slno || !$this->hasTable('salesrm')) {
            return response()->json(['success' => false, 'error' => 'Invalid request']);
        }

        DB::beginTransaction();
        try {
            $master     = DB::table('salesrm')->where('slno', $slno)->first();
            $oldControl = $master ? (int) $master->control : 1;

            if ($this->hasTable('salesrd')) {
                foreach (DB::table('salesrd')->where('slno', $slno)->get() as $d) {
                    $this->adjustStock($d->code, -$d->qty, -(float)$d->weight, -(float)$d->stonewgt, $oldControl);
                }
                DB::table('salesrd')->where('slno', $slno)->delete();
            }

            DB::table('daybook')->where('slno', $slno)->delete();
            DB::table('daybookpart')->where('slno', $slno)->delete();
            if ($this->hasTable('spdmddet')) DB::table('spdmddet')->where('slno', $slno)->delete();
            DB::table('salesrm')->where('slno', $slno)->delete();

            DB::commit();
            $this->logDelpart($request, 'Sales Return(' . trim((string) ($master->billno ?? $slno)) . ') Deleted', ['utype' => 'D', 'ttype' => 'T', 'slno' => $slno, 'tdate' => (string) ($master->tdate ?? date('Y-m-d')), 'control' => $oldControl]);

            return response()->json(['success' => true, 'message' => 'Sales Return deleted successfully']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ── Stock adjustment helper ───────────────────────────────────────────────

    private function adjustStock(string $code, int|float $qty, float $weight, float $stonewgt, int $control): void
    {
        if ($qty == 0 && $weight == 0) return;

        if ($control == 1) {
            DB::table('items')->where('code', $code)->increment('qty', $qty);
            DB::table('items')->where('code', $code)->increment('weight', $weight);
            if (Schema::hasColumn('items', 'stonewgt')) {
                DB::table('items')->where('code', $code)->increment('stonewgt', $stonewgt);
            }
        }
        if (Schema::hasColumn('items', 'qtyb'))    DB::table('items')->where('code', $code)->increment('qtyb', $qty);
        if (Schema::hasColumn('items', 'weightb'))  DB::table('items')->where('code', $code)->increment('weightb', $weight);
        if (Schema::hasColumn('items', 'stonewgtb')) DB::table('items')->where('code', $code)->increment('stonewgtb', $stonewgt);
    }
}
