<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VACheckReportController extends Controller
{
    private array $colCache = [];

    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('reports.va-check-report', [
            'title'  => 'VA Check Report',
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

        $date1  = (string) $request->query('date1', date('Y-m-d'));
        $date2  = (string) $request->query('date2', date('Y-m-d'));
        $rlevel = (int) $request->query('rlevel', (int) $request->session()->get('gilevel', 1));
        $btype  = trim((string) $request->query('btype', ''));
        $btypeon = (int) $request->query('btypeon', 0);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date1) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date2)) {
            return response()->json(['ok' => false, 'message' => 'Invalid date'], 422);
        }

        if (!$this->hasTable('salesm')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => []]);
        }

        $q = DB::table('salesm as m')
            ->select(
                'm.slno', 'm.tdate', 'm.billno', 'm.custname', 'm.billamt',
                'm.discount', 'm.eamt', 'm.sretamt', 'm.staxamt', 'm.round'
            );

        $q->addSelect(DB::raw($this->hasCol('salesm', 'discperc') ? 'm.discperc' : '0 as discperc'));
        $q->addSelect(DB::raw($this->hasCol('salesm', 'advance') ? 'm.advance' : '0 as advance'));
        $q->addSelect(DB::raw($this->hasCol('salesm', 'billtype') ? 'm.billtype' : "'' as billtype"));

        // Subqueries for gold items only
        if ($this->hasTable('salesd') && $this->hasTable('items')) {
            $q->selectRaw("(SELECT COALESCE(SUM(sd.mcharge + sd.wastage * sd.rate), 0) FROM salesd sd JOIN items it ON it.code = sd.code WHERE sd.slno = m.slno AND it.itype = 'G') as totalva");
            $q->selectRaw("(SELECT COALESCE(SUM(sd.stoneprice), 0) FROM salesd sd JOIN items it ON it.code = sd.code WHERE sd.slno = m.slno AND it.itype = 'G') as totalstamt");
            $q->selectRaw("(SELECT COALESCE(SUM(sd.weight - sd.stonewgt), 0) FROM salesd sd JOIN items it ON it.code = sd.code WHERE sd.slno = m.slno AND it.itype = 'G') as totalwgt");
        } else {
            $q->selectRaw('0 as totalva, 0 as totalstamt, 0 as totalwgt');
        }

        $q->whereBetween('m.tdate', [$date1, $date2]);
        if ($this->hasCol('salesm', 'control')) $q->where('m.control', '<=', $rlevel);
        if ($this->hasCol('salesm', 'opbill'))  $q->where('m.opbill', '<>', 1);

        if ($btypeon && $btype !== '' && $this->hasCol('salesm', 'billtype')) {
            $q->where('m.billtype', $btype);
        }

        $q->orderBy('m.tdate')->orderBy('m.slno');

        $rows = $q->get()->map(function ($r) {
            $row = (array) $r;
            $billamt  = (float) ($row['billamt'] ?? 0);
            $eamt     = (float) ($row['eamt'] ?? 0);
            $sretamt  = (float) ($row['sretamt'] ?? 0);
            $discount = (float) ($row['discount'] ?? 0);
            $staxamt  = (float) ($row['staxamt'] ?? 0);
            $round    = (float) ($row['round'] ?? 0);
            $advance  = (float) ($row['advance'] ?? 0);
            $totalva  = (float) ($row['totalva'] ?? 0);
            $totalwgt = (float) ($row['totalwgt'] ?? 0);

            $row['netamt'] = ($billamt - $eamt - $sretamt) - $discount + $staxamt + $round - $advance;
            $row['ctotva'] = $totalva;

            // VA per gram
            $row['vapergm'] = ($totalwgt > 0) ? round($totalva / $totalwgt, 2) : 0;

            // VA% = ctotva * 100 / (billamt - ctotva)
            $denom = $billamt - $totalva;
            $row['vap'] = ($denom > 0) ? round($totalva * 100 / $denom, 1) : 0;

            // D% = discount * 100 / (billamt - ctotva)
            $row['discperc2'] = ($denom > 0) ? round($discount * 100 / $denom, 1) : 0;

            // VA After Disc
            $row['vaafterdisc'] = $totalva - $discount;

            // VA After Disc per gram
            $row['vaafterdiscpergm'] = ($totalwgt > 0) ? round(($totalva - $discount) / $totalwgt, 2) : 0;

            return $row;
        })->values()->all();

        return response()->json([
            'ok'     => true,
            'rows'   => $rows,
            'totals' => $this->buildTotals($rows),
        ]);
    }

    private function buildTotals(array $rows): array
    {
        $t = ['billamt' => 0, 'eamt' => 0, 'sretamt' => 0, 'discount' => 0, 'netamt' => 0,
              'totalwgt' => 0, 'ctotva' => 0, 'vaafterdisc' => 0, 'count' => count($rows)];

        foreach ($rows as $row) {
            $t['billamt']     += (float) ($row['billamt'] ?? 0);
            $t['eamt']        += (float) ($row['eamt'] ?? 0);
            $t['sretamt']     += (float) ($row['sretamt'] ?? 0);
            $t['discount']    += (float) ($row['discount'] ?? 0);
            $t['netamt']      += (float) ($row['netamt'] ?? 0);
            $t['totalwgt']    += (float) ($row['totalwgt'] ?? 0);
            $t['ctotva']      += (float) ($row['ctotva'] ?? 0);
            $t['vaafterdisc'] += (float) ($row['vaafterdisc'] ?? 0);
        }

        $t['vapergm'] = ($t['totalwgt'] > 0) ? round($t['ctotva'] / $t['totalwgt'], 2) : 0;
        $t['vaafterdiscpergm'] = ($t['totalwgt'] > 0) ? round($t['vaafterdisc'] / $t['totalwgt'], 2) : 0;

        return $t;
    }

    private function hasCol(string $table, string $col): bool
    {
        $key = "$table.$col";
        if (!array_key_exists($key, $this->colCache)) {
            $this->colCache[$key] = Schema::hasColumn($table, $col);
        }
        return $this->colCache[$key];
    }
}
