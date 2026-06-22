<?php

namespace App\Http\Controllers;

use App\Support\DatabaseBackupManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TaxPurchaseBookController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('reports.tax-purchase-book', [
            'title'  => 'Purchase Book',
            'date1'  => date('Y-m-d'),
            'date2'  => date('Y-m-d'),
            'rlevel' => (int) $request->session()->get('gilevel', 1) ?: 1,
        ]);
    }

    public function lookups(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $billtypes = [];
        if ($this->hasTable('salestype')) {
            $billtypes = DB::table('salestype')->select('code', 'name')->orderBy('code')->get()
                ->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name)])->values()->all();
        }

        return response()->json(['ok' => true, 'billtypes' => $billtypes]);
    }

    public function data(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $date1    = (string) $request->query('date1', date('Y-m-d'));
        $date2    = (string) $request->query('date2', date('Y-m-d'));
        $rlevel   = (int) $request->query('rlevel', (int) $request->session()->get('gilevel', 1));
        $billtype = trim((string) $request->query('billtype', ''));
        $mode     = trim((string) $request->query('mode', 'daywise'));
        $registeredPurchase = filter_var($request->query('registered_purchase', false), FILTER_VALIDATE_BOOLEAN);
        $unregisteredPurchase = filter_var(
            $request->query('unregistered_purchase', $request->query('unregistered_b2b', false)),
            FILTER_VALIDATE_BOOLEAN
        );
        if ($registeredPurchase && $unregisteredPurchase) {
            $unregisteredPurchase = false;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date1) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date2)) {
            return response()->json(['ok' => false, 'message' => 'Invalid date'], 422);
        }

        if (!$this->hasTable('purchasem') || !$this->hasTable('purchased')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => $this->emptyTotals()]);
        }

        $hasBilltype = Schema::hasColumn('purchasem', 'billtype');

        if ($mode === 'billwise') {
            return $this->billwiseData($date1, $date2, $rlevel, $billtype, $hasBilltype, $registeredPurchase, $unregisteredPurchase);
        }

        return $this->daywiseData($date1, $date2, $rlevel, $billtype, $hasBilltype);
    }

    private function daywiseData(string $date1, string $date2, int $rlevel, string $billtype, bool $hasBilltype): JsonResponse
    {
        // Day-wise aggregated: one row per date (like d_taxrep_sales_daywise)
        $q = DB::table('purchasem as m')
            ->whereBetween('m.tdate', [$date1, $date2])
            ->where('m.control', '<=', $rlevel)
            ->select('m.tdate')
            ->selectRaw('MIN(m.docno) as docno1')
            ->selectRaw('MAX(m.docno) as docno2')
            ->selectRaw('SUM(m.billamt) as billamt')
            ->selectRaw('SUM(m.pamt) as pamt')
            ->selectRaw('SUM(m.eamt) as eamt')
            ->selectRaw('SUM(m.discount) as discount');

        if (Schema::hasColumn('purchasem', 'netamt')) {
            $q->selectRaw('SUM(m.netamt) as netamt');
        } else {
            $q->selectRaw('SUM(m.billamt) as netamt');
        }

        if (Schema::hasColumn('purchasem', 'hmc')) {
            $q->selectRaw('SUM(m.hmc) as hmc');
        } else {
            $q->selectRaw('0 as hmc');
        }

        if (Schema::hasColumn('purchasem', 'round')) {
            $q->selectRaw('SUM(m.round) as round_amt');
        } else {
            $q->selectRaw('0 as round_amt');
        }

        // GST columns
        foreach (['sgst', 'cgst', 'igst'] as $col) {
            if (Schema::hasColumn('purchasem', $col)) {
                $q->selectRaw("SUM(m.$col) as $col");
            } else {
                $q->selectRaw("0 as $col");
            }
        }

        // Weight from purchased
        $q->selectRaw("(SELECT SUM(pd.weight) FROM purchased pd JOIN purchasem pm ON pm.slno = pd.slno WHERE pm.tdate = m.tdate AND pm.control <= ? " . ($billtype !== '' && $hasBilltype ? "AND pm.billtype = ?" : "") . ") as grosswgt", $billtype !== '' && $hasBilltype ? [$rlevel, $billtype] : [$rlevel]);

        $q->selectRaw("(SELECT SUM(pd.stwgt) FROM purchased pd JOIN purchasem pm ON pm.slno = pd.slno WHERE pm.tdate = m.tdate AND pm.control <= ? " . ($billtype !== '' && $hasBilltype ? "AND pm.billtype = ?" : "") . ") as stonewgt", $billtype !== '' && $hasBilltype ? [$rlevel, $billtype] : [$rlevel]);

        if ($billtype !== '' && $hasBilltype) {
            $q->where('m.billtype', $billtype);
        }

        $q->groupBy('m.tdate')
          ->orderBy('m.tdate');

        $rows = $q->get()->map(function ($r) {
            $grosswgt = (float) ($r->grosswgt ?? 0);
            $stonewgt = (float) ($r->stonewgt ?? 0);
            return [
                'tdate'    => $r->tdate,
                'docnos'   => trim($r->docno1 ?? '') === trim($r->docno2 ?? '')
                    ? trim($r->docno1 ?? '')
                    : trim($r->docno1 ?? '') . ' - ' . trim($r->docno2 ?? ''),
                'grosswgt' => round($grosswgt, 3),
                'netwgt'   => round($grosswgt - $stonewgt, 3),
                'billamt'  => round((float) ($r->billamt ?? 0), 2),
                'pamt'     => round((float) ($r->pamt ?? 0), 2),
                'eamt'     => round((float) ($r->eamt ?? 0), 2),
                'discount' => round((float) ($r->discount ?? 0), 2),
                'hmc'      => round((float) ($r->hmc ?? 0), 2),
                'sgst'     => round((float) ($r->sgst ?? 0), 2),
                'cgst'     => round((float) ($r->cgst ?? 0), 2),
                'igst'     => round((float) ($r->igst ?? 0), 2),
                'round_amt' => round((float) ($r->round_amt ?? 0), 2),
                'netamt'   => round((float) ($r->netamt ?? 0), 2),
            ];
        })->values()->all();

        return response()->json([
            'ok'     => true,
            'rows'   => $rows,
            'totals' => $this->buildTotals($rows),
            'mode'   => 'daywise',
        ]);
    }

    private function billwiseData(
        string $date1,
        string $date2,
        int $rlevel,
        string $billtype,
        bool $hasBilltype,
        bool $registeredPurchase = false,
        bool $unregisteredPurchase = false
    ): JsonResponse
    {
        $q = DB::table('purchasem as m')
            ->whereBetween('m.tdate', [$date1, $date2])
            ->where('m.control', '<=', $rlevel)
            ->select('m.slno', 'm.tdate', 'm.docno', 'm.billno', 'm.suppcode', 'm.name as suppname',
                     'm.billamt', 'm.pamt', 'm.eamt', 'm.discount');

        $supplierGstSql = null;
        if ($this->hasTable('clients')) {
            $q->leftJoin('clients as c', function ($join) {
                $join->on(DB::raw('UPPER(TRIM(c.code))'), '=', DB::raw('UPPER(TRIM(m.suppcode))'));
            });

            $gstCols = [];
            foreach (['tin', 'tinno'] as $col) {
                if (Schema::hasColumn('clients', $col)) {
                    $gstCols[] = "NULLIF(TRIM(c.$col),'')";
                }
            }
            $supplierGstSql = $gstCols ? 'COALESCE(' . implode(',', $gstCols) . ",'')" : null;
            $q->selectRaw($supplierGstSql ? "$supplierGstSql as gstno" : "'' as gstno");
            $q->selectRaw("COALESCE(NULLIF(TRIM(m.addr),''), NULLIF(TRIM(CONCAT_WS(', ', c.addr1, c.addr2, c.addr3, c.city)),''), '') as suppaddr");
        } else {
            $q->selectRaw("'' as gstno");
            $q->selectRaw(Schema::hasColumn('purchasem', 'addr') ? "TRIM(COALESCE(m.addr,'')) as suppaddr" : "'' as suppaddr");
        }

        if ($hasBilltype) {
            $q->addSelect('m.billtype');
        } else {
            $q->selectRaw("'' as billtype");
        }

        if (Schema::hasColumn('purchasem', 'netamt')) {
            $q->addSelect('m.netamt');
        } else {
            $q->selectRaw('m.billamt as netamt');
        }

        foreach (['hmc', 'round', 'sgst', 'cgst', 'igst'] as $col) {
            if (Schema::hasColumn('purchasem', $col)) {
                $q->addSelect("m.$col");
            } else {
                $q->selectRaw("0 as $col");
            }
        }

        // Weight subquery
        $q->selectRaw("(SELECT SUM(pd.weight) FROM purchased pd WHERE pd.slno = m.slno) as grosswgt");
        $q->selectRaw("(SELECT SUM(pd.stwgt) FROM purchased pd WHERE pd.slno = m.slno) as stonewgt");

        if ($billtype !== '' && $hasBilltype) {
            $q->where('m.billtype', $billtype);
        }
        $this->applyRegistrationFilter($q, $supplierGstSql, $registeredPurchase, $unregisteredPurchase);

        $q->orderBy('m.tdate');
        if (Schema::hasColumn('purchasem', 'billno')) {
            $q->orderByRaw("
                CASE
                    WHEN m.billno REGEXP '^[A-Za-z0-9_-]+/[0-9]+$'
                    THEN CAST(SUBSTRING_INDEX(m.billno, '/', -1) AS UNSIGNED)
                    ELSE 999999999
                END
            ")->orderBy('m.billno');
        }
        $q->orderBy('m.docno');

        $rows = $q->get()->map(function ($r) {
            $grosswgt = (float) ($r->grosswgt ?? 0);
            $stonewgt = (float) ($r->stonewgt ?? 0);
            $rowBillType = strtoupper(trim((string) ($r->billtype ?? '')));
            return [
                'slno'     => (int) ($r->slno ?? 0),
                'tdate'    => $r->tdate,
                'docno'    => trim($r->docno ?? ''),
                'billno'   => trim($r->billno ?? '') !== '' ? trim($r->billno ?? '') : trim($r->docno ?? ''),
                'suppcode' => trim($r->suppcode ?? ''),
                'suppname' => trim($r->suppname ?? ''),
                'suppaddr' => trim($r->suppaddr ?? ''),
                'gstno'    => trim($r->gstno ?? ''),
                'billtype' => $rowBillType,
                'grosswgt' => round($grosswgt, 3),
                'netwgt'   => round($grosswgt - $stonewgt, 3),
                'billamt'  => round((float) ($r->billamt ?? 0), 2),
                'pamt'     => round((float) ($r->pamt ?? 0), 2),
                'eamt'     => round((float) ($r->eamt ?? 0), 2),
                'discount' => round((float) ($r->discount ?? 0), 2),
                'hmc'      => round((float) ($r->hmc ?? 0), 2),
                'sgst'     => round((float) ($r->sgst ?? 0), 2),
                'cgst'     => round((float) ($r->cgst ?? 0), 2),
                'igst'     => round((float) ($r->igst ?? 0), 2),
                'round_amt' => round((float) ($r->round ?? 0), 2),
                'netamt'   => round((float) ($r->netamt ?? 0), 2),
            ];
        })->values()->all();

        return response()->json([
            'ok'     => true,
            'rows'   => $rows,
            'totals' => $this->buildTotals($rows),
            'mode'   => 'billwise',
        ]);
    }

    private function buildTotals(array $rows): array
    {
        $t = $this->emptyTotals();
        $t['count'] = count($rows);
        foreach ($rows as $r) {
            $t['grosswgt'] += (float) ($r['grosswgt'] ?? 0);
            $t['netwgt']   += (float) ($r['netwgt'] ?? 0);
            $t['billamt']  += (float) ($r['billamt'] ?? 0);
            $t['pamt']     += (float) ($r['pamt'] ?? 0);
            $t['eamt']     += (float) ($r['eamt'] ?? 0);
            $t['discount'] += (float) ($r['discount'] ?? 0);
            $t['hmc']      += (float) ($r['hmc'] ?? 0);
            $t['sgst']     += (float) ($r['sgst'] ?? 0);
            $t['cgst']     += (float) ($r['cgst'] ?? 0);
            $t['igst']     += (float) ($r['igst'] ?? 0);
            $t['round_amt'] += (float) ($r['round_amt'] ?? 0);
            $t['netamt']   += (float) ($r['netamt'] ?? 0);
        }
        return $t;
    }

    public function billCorrectPreview(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        return response()->json([
            'ok' => true,
            'rows' => $this->billCorrectRows($request),
        ]);
    }

    public function billCorrectApply(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $rows = $this->billCorrectRows($request);
        if ($rows === []) {
            return response()->json(['ok' => false, 'message' => 'No bills found for correction.'], 422);
        }

        $backup = app(DatabaseBackupManager::class)->runManualBackup('rearanged');
        if (!($backup['ok'] ?? false)) {
            return response()->json([
                'ok' => false,
                'message' => 'Backup failed. Bill numbers were not changed. ' . (string) ($backup['message'] ?? ''),
                'backup' => $backup,
            ], 500);
        }

        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                DB::table('purchasem')
                    ->where('slno', $row['slno'])
                    ->update([
                        'billno' => $row['new_no'],
                    ]);
            }
        });

        return response()->json([
            'ok' => true,
            'message' => 'Bill numbers corrected.',
            'updated' => count($rows),
            'backup' => $backup,
            'rows' => $rows,
        ]);
    }

    private function billCorrectRows(Request $request): array
    {
        $date1 = (string) $request->input('date1', date('Y-m-d'));
        $date2 = (string) $request->input('date2', date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date1) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date2)) {
            return [];
        }

        $rlevel = (int) $request->input('rlevel', (int) $request->session()->get('gilevel', 1));
        $start = max(1, (int) $request->input('start_no', 1));
        $digits = min(8, max(1, (int) $request->input('digits', 3)));
        $purchaseType = strtolower(trim((string) $request->input('purchase_type', 'all')));
        $registeredPurchase = $purchaseType === 'registered';
        $unregisteredPurchase = $purchaseType === 'unregistered';
        $selectedSeries = $this->parseItemCodes((string) $request->input('series', 'OG,OS'));

        $groups = [
            [
                'key' => 'og',
                'label' => 'OG / Old Diamond',
                'prefix' => $this->cleanSeriesPrefix((string) $request->input('og_prefix', 'OG')),
                'codes' => $this->parseItemCodes((string) $request->input('og_codes', $request->input('og_types', 'OG,OD,ODM,GO'))),
            ],
            [
                'key' => 'os',
                'label' => 'Old Silver',
                'prefix' => $this->cleanSeriesPrefix((string) $request->input('os_prefix', 'OS')),
                'codes' => $this->parseItemCodes((string) $request->input('os_codes', $request->input('os_types', 'OS'))),
            ],
        ];

        $rows = [];
        foreach ($groups as $group) {
            if ($selectedSeries !== [] && !in_array($group['prefix'], $selectedSeries, true) && !in_array(strtoupper($group['key']), $selectedSeries, true)) {
                continue;
            }
            if ($group['prefix'] === '' || $group['codes'] === []) {
                continue;
            }

            $q = DB::table('purchasem as m')
                ->join('purchased as d', 'd.slno', '=', 'm.slno')
                ->whereBetween('m.tdate', [$date1, $date2])
                ->where('m.control', '<=', $rlevel)
                ->whereIn(DB::raw("UPPER(TRIM(COALESCE(d.code,'')))"), $group['codes'])
                ->select('m.slno', 'm.tdate', 'm.docno', 'm.billno', 'm.billtype', 'm.name')
                ->selectRaw("GROUP_CONCAT(DISTINCT UPPER(TRIM(COALESCE(d.code,''))) ORDER BY d.code SEPARATOR ', ') as item_codes")
                ->groupBy('m.slno', 'm.tdate', 'm.docno', 'm.billno', 'm.billtype', 'm.name');

            $supplierGstSql = null;
            if ($this->hasTable('clients')) {
                $q->leftJoin('clients as c', function ($join) {
                    $join->on(DB::raw('UPPER(TRIM(c.code))'), '=', DB::raw('UPPER(TRIM(m.suppcode))'));
                });

                $gstCols = [];
                foreach (['tin', 'tinno'] as $col) {
                    if (Schema::hasColumn('clients', $col)) {
                        $gstCols[] = "NULLIF(TRIM(c.$col),'')";
                    }
                }
                $supplierGstSql = $gstCols ? 'COALESCE(' . implode(',', $gstCols) . ",'')" : null;
            }
            $this->applyRegistrationFilter($q, $supplierGstSql, $registeredPurchase, $unregisteredPurchase);

            $records = $q->orderBy('m.tdate')->orderBy('m.slno')->get();
            $nextNo = $start;
            foreach ($records as $record) {
                $newNo = $group['prefix'] . '/' . str_pad((string) $nextNo, $digits, '0', STR_PAD_LEFT);
                $rows[] = [
                    'slno' => (int) $record->slno,
                    'group' => $group['label'],
                    'tdate' => (string) $record->tdate,
                    'old_docno' => trim((string) ($record->docno ?? '')),
                    'old_billno' => trim((string) ($record->billno ?? '')) !== '' ? trim((string) ($record->billno ?? '')) : trim((string) ($record->docno ?? '')),
                    'new_no' => $newNo,
                    'item_codes' => trim((string) ($record->item_codes ?? '')),
                    'supplier' => trim((string) ($record->name ?? '')),
                ];
                $nextNo++;
            }
        }

        return $rows;
    }

    private function parseItemCodes(string $value): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn ($code) => strtoupper(trim($code)),
            preg_split('/[,;\s]+/', $value) ?: []
        ))));
    }

    private function cleanSeriesPrefix(string $value): string
    {
        return strtoupper(trim(preg_replace('/[^A-Za-z0-9_-]+/', '', $value) ?? ''));
    }

    private function emptyTotals(): array
    {
        return ['count' => 0, 'grosswgt' => 0, 'netwgt' => 0, 'billamt' => 0,
                'pamt' => 0, 'eamt' => 0, 'discount' => 0, 'hmc' => 0,
                'sgst' => 0, 'cgst' => 0, 'igst' => 0, 'round_amt' => 0, 'netamt' => 0];
    }

    private function applyRegistrationFilter($q, ?string $supplierGstSql, bool $registeredPurchase, bool $unregisteredPurchase): void
    {
        if (!$registeredPurchase && !$unregisteredPurchase) {
            return;
        }

        if ($supplierGstSql === null) {
            if ($registeredPurchase) {
                $q->whereRaw('1 = 0');
            }
            return;
        }
        if ($registeredPurchase) {
            $q->whereRaw("$supplierGstSql <> ''");
            return;
        }

        $q->whereRaw("$supplierGstSql = ''");
    }
}
