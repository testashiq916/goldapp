<?php

namespace App\Http\Controllers;

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

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date1) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date2)) {
            return response()->json(['ok' => false, 'message' => 'Invalid date'], 422);
        }

        if (!$this->hasTable('purchasem') || !$this->hasTable('purchased')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => $this->emptyTotals()]);
        }

        $hasBilltype = Schema::hasColumn('purchasem', 'billtype');

        if ($mode === 'billwise') {
            return $this->billwiseData($date1, $date2, $rlevel, $billtype, $hasBilltype);
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

    private function billwiseData(string $date1, string $date2, int $rlevel, string $billtype, bool $hasBilltype): JsonResponse
    {
        $q = DB::table('purchasem as m')
            ->whereBetween('m.tdate', [$date1, $date2])
            ->where('m.control', '<=', $rlevel)
            ->select('m.slno', 'm.tdate', 'm.docno', 'm.suppcode', 'm.name as suppname',
                     'm.billamt', 'm.pamt', 'm.eamt', 'm.discount');

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

        $q->orderBy('m.tdate')->orderBy('m.docno');

        $rows = $q->get()->map(function ($r) {
            $grosswgt = (float) ($r->grosswgt ?? 0);
            $stonewgt = (float) ($r->stonewgt ?? 0);
            return [
                'tdate'    => $r->tdate,
                'docno'    => trim($r->docno ?? ''),
                'suppcode' => trim($r->suppcode ?? ''),
                'suppname' => trim($r->suppname ?? ''),
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

    private function emptyTotals(): array
    {
        return ['count' => 0, 'grosswgt' => 0, 'netwgt' => 0, 'billamt' => 0,
                'pamt' => 0, 'eamt' => 0, 'discount' => 0, 'hmc' => 0,
                'sgst' => 0, 'cgst' => 0, 'igst' => 0, 'round_amt' => 0, 'netamt' => 0];
    }
}
