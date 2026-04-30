<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class JewlleryBookController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('reports.jewllery-book', [
            'title'  => 'Jewllery Book',
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

        $counters = [];
        if ($this->hasTable('counter')) {
            $counters = DB::table('counter')->select('code', 'name')->orderBy('code')->get()
                ->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name)])->values()->all();
        }

        $incharges = [];
        if ($this->hasTable('incharge')) {
            $incharges = DB::table('incharge')->select('code', 'name')->orderBy('code')->get()
                ->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name)])->values()->all();
        }

        $salesmen = [];
        if ($this->hasTable('sman')) {
            $salesmen = DB::table('sman')->select('code', 'name')->orderBy('code')->get()
                ->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name)])->values()->all();
        }

        $billtypes = [];
        if ($this->hasTable('salestype')) {
            $billtypes = DB::table('salestype')->select('code', 'name')->orderBy('code')->get()
                ->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name)])->values()->all();
        }

        return response()->json([
            'ok' => true,
            'counters'  => $counters,
            'incharges' => $incharges,
            'salesmen'  => $salesmen,
            'billtypes' => $billtypes,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $date1    = (string) $request->query('date1', date('Y-m-d'));
        $date2    = (string) $request->query('date2', date('Y-m-d'));
        $rlevel   = (int) $request->query('rlevel', (int) $request->session()->get('gilevel', 1));
        $counter  = trim((string) $request->query('counter', ''));
        $ic       = trim((string) $request->query('ic', ''));
        $smcode   = trim((string) $request->query('smcode', ''));
        $billtype = trim((string) $request->query('billtype', ''));
        $mode     = trim((string) $request->query('mode', 'book'));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date1) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date2)) {
            return response()->json(['ok' => false, 'message' => 'Invalid date'], 422);
        }

        if (!$this->hasTable('salesm') || !$this->hasTable('salesd') || !$this->hasTable('items')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => $this->emptyTotals()]);
        }

        if ($mode === 'register') {
            return $this->registerData($date1, $date2, $rlevel, $counter, $ic, $smcode, $billtype);
        }

        return $this->bookData($date1, $date2, $rlevel, $counter, $ic, $smcode, $billtype);
    }

    /* ─── Book mode: bill-level summary with customer/GST info ─── */
    private function bookData(string $date1, string $date2, int $rlevel,
                              string $counter, string $ic, string $smcode, string $billtype): JsonResponse
    {
        $q = DB::table('salesm as m')
            ->whereBetween('m.tdate', [$date1, $date2])
            ->where('m.control', '<=', $rlevel)
            ->select(
                'm.slno', 'm.billno', 'm.tdate', 'm.custcode', 'm.custname',
                'm.billamt', 'm.discount', 'm.eamt', 'm.ramt', 'm.netamt',
                'm.staxamt'
            );

        // GST columns
        foreach (['sgst', 'cgst', 'igst', 'tin', 'addr', 'statecode', 'pan'] as $col) {
            if (Schema::hasColumn('salesm', $col)) {
                $q->addSelect("m.$col");
            } else {
                $q->selectRaw("'' as $col");
            }
        }

        if (Schema::hasColumn('salesm', 'round')) {
            $q->addSelect('m.round');
        } else {
            $q->selectRaw('0 as `round`');
        }

        // Gross weight & stone weight subqueries
        $q->selectRaw("(SELECT SUM(sd.weight) FROM salesd sd WHERE sd.slno = m.slno) as grosswgt");
        $q->selectRaw("(SELECT SUM(sd.stonewgt) FROM salesd sd WHERE sd.slno = m.slno) as stonewgt");
        $q->selectRaw("(SELECT SUM(sd.mcharge) FROM salesd sd WHERE sd.slno = m.slno) as va");
        $q->selectRaw("(SELECT SUM(sd.amount) FROM salesd sd WHERE sd.slno = m.slno) as amount");

        // Filters
        if ($counter !== '' && Schema::hasColumn('salesm', 'counter')) $q->where('m.counter', $counter);
        if ($ic !== '' && Schema::hasColumn('salesm', 'ic')) $q->where('m.ic', $ic);
        if ($smcode !== '') $q->where('m.smcode', $smcode);
        if ($billtype !== '' && Schema::hasColumn('salesm', 'billtype')) $q->where('m.billtype', $billtype);

        $q->orderBy('m.tdate')->orderBy('m.billno');

        $rows = $q->get()->map(function ($r) {
            $grosswgt = (float) ($r->grosswgt ?? 0);
            $stonewgt = (float) ($r->stonewgt ?? 0);
            return [
                'billno'   => trim($r->billno ?? ''),
                'tdate'    => $r->tdate,
                'custcode' => trim($r->custcode ?? ''),
                'custname' => trim($r->custname ?? ''),
                'addr'     => trim($r->addr ?? ''),
                'tin'      => trim($r->tin ?? ''),
                'pan'      => trim($r->pan ?? ''),
                'statecode' => trim($r->statecode ?? ''),
                'grosswgt' => round($grosswgt, 3),
                'netwgt'   => round($grosswgt - $stonewgt, 3),
                'stonewgt' => round($stonewgt, 3),
                'va'       => round((float) ($r->va ?? 0), 2),
                'amount'   => round((float) ($r->amount ?? 0), 2),
                'billamt'  => round((float) ($r->billamt ?? 0), 2),
                'discount' => round((float) ($r->discount ?? 0), 2),
                'eamt'     => round((float) ($r->eamt ?? 0), 2),
                'ramt'     => round((float) ($r->ramt ?? 0), 2),
                'sgst'     => round((float) ($r->sgst ?? 0), 2),
                'cgst'     => round((float) ($r->cgst ?? 0), 2),
                'igst'     => round((float) ($r->igst ?? 0), 2),
                'staxamt'  => round((float) ($r->staxamt ?? 0), 2),
                'round_amt' => round((float) ($r->round ?? 0), 2),
                'netamt'   => round((float) ($r->netamt ?? 0), 2),
            ];
        })->values()->all();

        return response()->json([
            'ok'     => true,
            'rows'   => $rows,
            'totals' => $this->buildBookTotals($rows),
            'mode'   => 'book',
        ]);
    }

    /* ─── Register mode: item-level detail ─── */
    private function registerData(string $date1, string $date2, int $rlevel,
                                  string $counter, string $ic, string $smcode, string $billtype): JsonResponse
    {
        $q = DB::table('salesd as d')
            ->join('salesm as m', 'm.slno', '=', 'd.slno')
            ->join('items as i', 'i.code', '=', 'd.code')
            ->whereBetween('m.tdate', [$date1, $date2])
            ->where('m.control', '<=', $rlevel)
            ->select(
                'm.billno', 'm.tdate', 'm.custcode', 'm.custname',
                'd.code as itemcode', 'i.name as itemname',
                'd.rate', 'd.qty', 'd.weight', 'd.stonewgt', 'd.stoneprice',
                'd.lesswgt', 'd.mcharge', 'd.amount'
            );

        if (Schema::hasColumn('items', 'saccode')) {
            $q->addSelect('i.saccode as hsn');
        } else {
            $q->selectRaw("'' as hsn");
        }

        if (Schema::hasColumn('salesd', 'stktype')) {
            $q->addSelect('d.stktype');
        } else {
            $q->selectRaw("'' as stktype");
        }

        // Filters
        if ($counter !== '' && Schema::hasColumn('salesm', 'counter')) $q->where('m.counter', $counter);
        if ($ic !== '' && Schema::hasColumn('salesm', 'ic')) $q->where('m.ic', $ic);
        if ($smcode !== '') $q->where('m.smcode', $smcode);
        if ($billtype !== '' && Schema::hasColumn('salesm', 'billtype')) $q->where('m.billtype', $billtype);

        $q->orderBy('m.tdate')->orderBy('m.billno')->orderBy('d.sno');

        $rows = $q->get()->map(function ($r) {
            return [
                'billno'   => trim($r->billno ?? ''),
                'tdate'    => $r->tdate,
                'custname' => trim($r->custname ?? ''),
                'itemcode' => trim($r->itemcode ?? ''),
                'itemname' => trim($r->itemname ?? ''),
                'hsn'      => trim($r->hsn ?? ''),
                'rate'     => round((float) ($r->rate ?? 0), 2),
                'qty'      => (int) ($r->qty ?? 0),
                'weight'   => round((float) ($r->weight ?? 0), 3),
                'stonewgt' => round((float) ($r->stonewgt ?? 0), 3),
                'stoneprice' => round((float) ($r->stoneprice ?? 0), 2),
                'netwgt'   => round((float) ($r->weight ?? 0) - (float) ($r->stonewgt ?? 0) - (float) ($r->lesswgt ?? 0), 3),
                'va'       => round((float) ($r->mcharge ?? 0), 2),
                'amount'   => round((float) ($r->amount ?? 0), 2),
            ];
        })->values()->all();

        return response()->json([
            'ok'     => true,
            'rows'   => $rows,
            'totals' => $this->buildRegTotals($rows),
            'mode'   => 'register',
        ]);
    }

    private function buildBookTotals(array $rows): array
    {
        $t = $this->emptyTotals();
        $t['count'] = count($rows);
        foreach ($rows as $r) {
            $t['grosswgt'] += (float) ($r['grosswgt'] ?? 0);
            $t['netwgt']   += (float) ($r['netwgt'] ?? 0);
            $t['va']       += (float) ($r['va'] ?? 0);
            $t['amount']   += (float) ($r['amount'] ?? 0);
            $t['billamt']  += (float) ($r['billamt'] ?? 0);
            $t['discount'] += (float) ($r['discount'] ?? 0);
            $t['eamt']     += (float) ($r['eamt'] ?? 0);
            $t['ramt']     += (float) ($r['ramt'] ?? 0);
            $t['sgst']     += (float) ($r['sgst'] ?? 0);
            $t['cgst']     += (float) ($r['cgst'] ?? 0);
            $t['igst']     += (float) ($r['igst'] ?? 0);
            $t['staxamt']  += (float) ($r['staxamt'] ?? 0);
            $t['netamt']   += (float) ($r['netamt'] ?? 0);
        }
        return $t;
    }

    private function buildRegTotals(array $rows): array
    {
        $t = ['count' => count($rows), 'qty' => 0, 'weight' => 0, 'netwgt' => 0,
              'stonewgt' => 0, 'stoneprice' => 0, 'va' => 0, 'amount' => 0];
        foreach ($rows as $r) {
            $t['qty']        += (int) ($r['qty'] ?? 0);
            $t['weight']     += (float) ($r['weight'] ?? 0);
            $t['netwgt']     += (float) ($r['netwgt'] ?? 0);
            $t['stonewgt']   += (float) ($r['stonewgt'] ?? 0);
            $t['stoneprice'] += (float) ($r['stoneprice'] ?? 0);
            $t['va']         += (float) ($r['va'] ?? 0);
            $t['amount']     += (float) ($r['amount'] ?? 0);
        }
        return $t;
    }

    private function emptyTotals(): array
    {
        return ['count' => 0, 'grosswgt' => 0, 'netwgt' => 0, 'va' => 0, 'amount' => 0,
                'billamt' => 0, 'discount' => 0, 'eamt' => 0, 'ramt' => 0,
                'sgst' => 0, 'cgst' => 0, 'igst' => 0, 'staxamt' => 0, 'netamt' => 0];
    }
}
