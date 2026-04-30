<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurchaseBookController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('reports.purchase-book', [
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

        $incharges = [];
        if ($this->hasTable('incharge')) {
            $incharges = DB::table('incharge')->select('code', 'name')->orderBy('code')->get()
                ->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name)])->values()->all();
        }

        $counters = [];
        if ($this->hasTable('counter')) {
            $counters = DB::table('counter')->select('code', 'name')->orderBy('code')->get()
                ->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name)])->values()->all();
        }

        $billtypes = [];
        if ($this->hasTable('salestype')) {
            $billtypes = DB::table('salestype')->select('code', 'name')->orderBy('code')->get()
                ->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name)])->values()->all();
        }

        return response()->json(['ok' => true, 'incharges' => $incharges, 'counters' => $counters, 'billtypes' => $billtypes]);
    }

    /**
     * Purchase Book data — bill-level summary from purchasem
     */
    public function data(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $date1    = (string) $request->query('date1', date('Y-m-d'));
        $date2    = (string) $request->query('date2', date('Y-m-d'));
        $rlevel   = (int) $request->query('rlevel', (int) $request->session()->get('gilevel', 1));
        $mode     = trim((string) $request->query('mode', 'book')); // book | register
        $ic       = trim((string) $request->query('ic', ''));
        $icon     = (int) $request->query('icon', 1);
        $counter  = trim((string) $request->query('counter', ''));
        $counteron = (int) $request->query('counteron', 1);
        $billtype = trim((string) $request->query('billtype', ''));
        $pe       = trim((string) $request->query('pe', '')); // P | E | (empty=all)

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date1) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date2)) {
            return response()->json(['ok' => false, 'message' => 'Invalid date'], 422);
        }

        if (!$this->hasTable('purchasem')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => []]);
        }

        if ($mode === 'register') {
            return $this->registerData($date1, $date2, $rlevel, $ic, $icon, $counter, $counteron, $billtype, $pe);
        }

        // ── Book mode: bill-level from purchasem ──
        $q = DB::table('purchasem as m')
            ->select(
                'm.slno', 'm.tdate', 'm.docno', 'm.suppcode', 'm.name',
                'm.billamt', 'm.pamt', 'm.addamt', 'm.eamt', 'm.discount',
                'm.pr', 'm.duedate', 'm.smcode', 'm.round', 'm.netamt',
                'm.ic', 'm.billtype'
            );

        $this->addOptionalCol($q, 'purchasem', 'counter', 'm');
        $this->addOptionalCol($q, 'purchasem', 'taxamt', 'm');
        $this->addOptionalCol($q, 'purchasem', 'hmc', 'm');
        $this->addOptionalCol($q, 'purchasem', 'astamt', 'm');
        $this->addOptionalCol($q, 'purchasem', 'taxperc', 'm');
        $this->addOptionalCol($q, 'purchasem', 'sgst', 'm');
        $this->addOptionalCol($q, 'purchasem', 'cgst', 'm');
        $this->addOptionalCol($q, 'purchasem', 'igst', 'm');

        // Gold/Silver weight subqueries
        if ($this->hasTable('purchased') && $this->hasTable('items')) {
            $q->selectRaw("(SELECT COALESCE(SUM(d.weight), 0) FROM purchased d JOIN items i ON i.code = d.code WHERE d.slno = m.slno AND i.itype = 'G') as goldwgt");
            $q->selectRaw("(SELECT COALESCE(SUM(d.weight), 0) FROM purchased d JOIN items i ON i.code = d.code WHERE d.slno = m.slno AND i.itype = 'S') as silverwgt");
            $q->selectRaw("(SELECT COALESCE(SUM(d.weight), 0) FROM purchased d JOIN items i ON i.code = d.code WHERE d.slno = m.slno AND i.itype = 'O') as otherwgt");
        } else {
            $q->selectRaw('0 as goldwgt, 0 as silverwgt, 0 as otherwgt');
        }

        $q->whereBetween('m.tdate', [$date1, $date2]);
        $q->where('m.control', '<=', $rlevel);

        $this->applyFilters($q, $ic, $icon, $counter, $counteron, $billtype, $pe);

        $q->orderBy('m.tdate')->orderBy('m.slno');

        $rows = $q->get()->map(fn($r) => $this->formatBookRow((array) $r))->values()->all();

        return response()->json([
            'ok'     => true,
            'rows'   => $rows,
            'totals' => $this->buildBookTotals($rows),
        ]);
    }

    /**
     * Register mode: item-level from purchased + purchasem + items
     */
    private function registerData(string $date1, string $date2, int $rlevel,
        string $ic, int $icon, string $counter, int $counteron, string $billtype, string $pe): JsonResponse
    {
        if (!$this->hasTable('purchased') || !$this->hasTable('items')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => []]);
        }

        $q = DB::table('purchased as d')
            ->join('purchasem as m', 'm.slno', '=', 'd.slno')
            ->join('items as i', 'i.code', '=', 'd.code')
            ->select(
                'd.slno as purchased_slno', 'm.slno', 'm.tdate', 'm.docno', 'm.name as suppname',
                'm.suppcode', 'm.billamt', 'm.pamt', 'm.addamt', 'm.eamt', 'm.duedate',
                'm.pr', 'm.smcode', 'm.ic',
                'i.name as itemname', 'i.itype', 'i.code as itemcode',
                'd.code', 'd.qty', 'd.weight', 'd.amount',
                'd.stwgt', 'd.stprice', 'd.lesswgt', 'd.stktype'
            );

        $this->addOptionalCol($q, 'purchased', 'name', 'd', 'dname');
        $this->addOptionalCol($q, 'purchasem', 'counter', 'm');
        $this->addOptionalCol($q, 'purchasem', 'billtype', 'm');

        $q->whereBetween('m.tdate', [$date1, $date2]);
        $q->where('m.control', '<=', $rlevel);

        $this->applyFilters($q, $ic, $icon, $counter, $counteron, $billtype, $pe);

        $q->orderBy('i.name')->orderBy('m.tdate')->orderBy('m.docno');

        $rows = $q->get()->map(function ($r) {
            $row = (array) $r;
            $row['qty']     = (int) ($row['qty'] ?? 0);
            $row['weight']  = (float) ($row['weight'] ?? 0);
            $row['amount']  = (float) ($row['amount'] ?? 0);
            $row['stwgt']   = (float) ($row['stwgt'] ?? 0);
            $row['stprice'] = (float) ($row['stprice'] ?? 0);
            $row['lesswgt'] = (float) ($row['lesswgt'] ?? 0);
            $row['navigate_url'] = $this->purchaseNavigateUrl((string) ($row['docno'] ?? ''), (string) ($row['pr'] ?? ''));
            return $row;
        })->values()->all();

        return response()->json([
            'ok'     => true,
            'rows'   => $rows,
            'totals' => $this->buildRegTotals($rows),
        ]);
    }

    private function applyFilters($q, string $ic, int $icon, string $counter, int $counteron, string $billtype, string $pe): void
    {
        if ($pe === 'P') {
            $q->where('m.pr', 'P');
        } elseif ($pe === 'E') {
            $q->where('m.pr', 'E');
        }

        if ($ic !== '' && $icon) {
            $q->where('m.ic', $ic);
        }

        if ($counter !== '' && $counteron && Schema::hasColumn('purchasem', 'counter')) {
            $q->where('m.counter', $counter);
        }

        if ($billtype !== '' && Schema::hasColumn('purchasem', 'billtype')) {
            $q->where('m.billtype', $billtype);
        }
    }

    private function addOptionalCol($q, string $table, string $col, string $alias, ?string $asName = null): void
    {
        if (Schema::hasColumn($table, $col)) {
            $sel = "$alias.$col";
            if ($asName) $sel .= " as $asName";
            $q->addSelect($sel);
        } else {
            $name = $asName ?? $col;
            $q->selectRaw("'' as $name");
        }
    }

    private function formatBookRow(array $row): array
    {
        foreach (['billamt','pamt','addamt','eamt','discount','taxamt','hmc','round','astamt','goldwgt','silverwgt','otherwgt','sgst','cgst','igst'] as $k) {
            $row[$k] = (float) ($row[$k] ?? 0);
        }
        $row['calcnet'] = $row['billamt'] - $row['discount'] + $row['taxamt'] + $row['round'] + $row['hmc'] - $row['eamt'];
        $row['navigate_url'] = $this->purchaseNavigateUrl((string) ($row['docno'] ?? ''), (string) ($row['pr'] ?? ''));
        return $row;
    }

    private function purchaseNavigateUrl(string $docNo, string $pr): string
    {
        $docNo = trim($docNo);
        $pr = strtoupper(trim($pr));
        if ($docNo === '' || !in_array($pr, ['P', 'E'], true)) {
            return '';
        }

        return url('/purchase-bill/edit?' . http_build_query(['doc_no' => $docNo]));
    }

    private function buildBookTotals(array $rows): array
    {
        $t = ['count' => count($rows), 'billamt' => 0, 'eamt' => 0, 'discount' => 0,
              'taxamt' => 0, 'hmc' => 0, 'round' => 0, 'calcnet' => 0,
              'goldwgt' => 0, 'silverwgt' => 0, 'otherwgt' => 0, 'pamt' => 0, 'addamt' => 0,
              'sgst' => 0, 'cgst' => 0, 'igst' => 0];

        foreach ($rows as $row) {
            foreach (array_keys($t) as $k) {
                if ($k === 'count') continue;
                $t[$k] += (float) ($row[$k] ?? 0);
            }
        }
        return $t;
    }

    private function buildRegTotals(array $rows): array
    {
        $t = ['count' => count($rows), 'qty' => 0, 'weight' => 0, 'amount' => 0,
              'stwgt' => 0, 'stprice' => 0, 'lesswgt' => 0,
              'goldwgt' => 0, 'silverwgt' => 0, 'otherwgt' => 0];

        foreach ($rows as $row) {
            $t['qty']     += (int) ($row['qty'] ?? 0);
            $t['weight']  += (float) ($row['weight'] ?? 0);
            $t['amount']  += (float) ($row['amount'] ?? 0);
            $t['stwgt']   += (float) ($row['stwgt'] ?? 0);
            $t['stprice'] += (float) ($row['stprice'] ?? 0);
            $t['lesswgt'] += (float) ($row['lesswgt'] ?? 0);

            $itype = strtoupper(trim($row['itype'] ?? ''));
            if ($itype === 'G') $t['goldwgt'] += (float) ($row['weight'] ?? 0);
            elseif ($itype === 'S') $t['silverwgt'] += (float) ($row['weight'] ?? 0);
            else $t['otherwgt'] += (float) ($row['weight'] ?? 0);
        }
        return $t;
    }
}
