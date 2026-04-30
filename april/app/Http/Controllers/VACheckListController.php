<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VACheckListController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('reports.va-check-list', [
            'title'  => 'V/A Check List',
            'date1'  => date('Y-m-d'),
            'date2'  => date('Y-m-d'),
            'rlevel' => (int) $request->session()->get('gilevel', 1) ?: 1,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $date1  = (string) $request->query('date1', date('Y-m-d'));
        $date2  = (string) $request->query('date2', date('Y-m-d'));
        $rlevel = (int) $request->query('rlevel', (int) $request->session()->get('gilevel', 1));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date1) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date2)) {
            return response()->json(['ok' => false, 'message' => 'Invalid date'], 422);
        }

        $data = [];

        // ── Sales (Received) ──
        $data['sale'] = $this->salesAggregates($date1, $date2, $rlevel);

        // ── Sales Return (Issued) ──
        $data['salereturn'] = $this->salesReturnAggregates($date1, $date2, $rlevel);

        // ── GoldSmith (Issued) ──
        $data['smith'] = $this->smithAggregates($date1, $date2, $rlevel);

        // ── Computed net values ──
        $saleVaAmt  = (float) ($data['sale']['vaamt'] ?? 0);
        $saleDisc   = (float) ($data['sale']['disc'] ?? 0);
        $saleWgt    = (float) ($data['sale']['weight'] ?? 0);

        $data['sale']['netva']   = $saleVaAmt - $saleDisc;
        $data['sale']['vapergm'] = ($saleWgt > 0) ? round(($saleVaAmt - $saleDisc) / $saleWgt, 2) : 0;

        return response()->json(['ok' => true, 'data' => $data]);
    }

    private function salesAggregates(string $date1, string $date2, int $rlevel): array
    {
        if (!$this->hasTable('salesd') || !$this->hasTable('salesm')) {
            return $this->emptyRow();
        }

        $row = DB::table('salesd as d')
            ->join('salesm as m', 'm.slno', '=', 'd.slno')
            ->selectRaw('COALESCE(SUM(d.weight), 0) as weight')
            ->selectRaw('COALESCE(SUM(d.wastage), 0) as wastage')
            ->selectRaw('COALESCE(SUM(d.mcharge), 0) as mcharge')
            ->selectRaw('COALESCE(SUM(d.mcharge + (d.wastage * d.rate)), 0) as vaamt')
            ->selectRaw('COALESCE(SUM(d.stoneprice), 0) as stoneprice')
            ->selectRaw('COALESCE(SUM(d.stonewgt), 0) as stonewgt')
            ->whereBetween('m.tdate', [$date1, $date2])
            ->where('m.control', '<=', $rlevel)
            ->first();

        $disc = DB::table('salesm')
            ->whereBetween('tdate', [$date1, $date2])
            ->where('control', '<=', $rlevel)
            ->selectRaw('COALESCE(SUM(discount), 0) as disc')
            ->value('disc');

        // tvaperc: sum( (mcharge/weight/rate)*100 ) where rate>0 and weight>0
        $tvaperc = DB::table('salesd as d')
            ->join('salesm as m', 'm.slno', '=', 'd.slno')
            ->whereBetween('m.tdate', [$date1, $date2])
            ->where('m.control', '<=', $rlevel)
            ->where('d.rate', '>', 0)
            ->where('d.weight', '>', 0)
            ->selectRaw('COALESCE(SUM((d.mcharge / d.weight / d.rate) * 100), 0) as tvaperc')
            ->value('tvaperc');

        $r = (array) $row;
        $r['disc']    = (float) $disc;
        $r['tvaperc'] = round((float) $tvaperc, 2);

        return $r;
    }

    private function salesReturnAggregates(string $date1, string $date2, int $rlevel): array
    {
        if (!$this->hasTable('salesrd') || !$this->hasTable('salesrm')) {
            return $this->emptyRow();
        }

        $row = DB::table('salesrd as d')
            ->join('salesrm as m', 'm.slno', '=', 'd.slno')
            ->selectRaw('COALESCE(SUM(d.wastage), 0) as wastage')
            ->selectRaw('COALESCE(SUM(d.mcharge), 0) as mcharge')
            ->selectRaw('COALESCE(SUM(d.mcharge + (d.wastage * d.rate)), 0) as vaamt')
            ->selectRaw('COALESCE(SUM(d.stoneprice), 0) as stoneprice')
            ->selectRaw('COALESCE(SUM(d.stonewgt), 0) as stonewgt')
            ->whereBetween('m.tdate', [$date1, $date2])
            ->where('m.control', '<=', $rlevel)
            ->first();

        return (array) $row;
    }

    private function smithAggregates(string $date1, string $date2, int $rlevel): array
    {
        if (!$this->hasTable('smithd') || !$this->hasTable('smithm')) {
            return $this->emptyRow();
        }

        $row = DB::table('smithd as d')
            ->join('smithm as m', 'm.slno', '=', 'd.slno')
            ->selectRaw('COALESCE(SUM(d.wastage), 0) as wastage')
            ->selectRaw('COALESCE(SUM(d.mcharge), 0) as mcharge')
            ->selectRaw('COALESCE(SUM(d.stoneprice), 0) as stoneprice')
            ->whereBetween('m.tdate', [$date1, $date2])
            ->where('m.control', '<=', $rlevel)
            ->first();

        // Smith VA only for gold items
        $smithVaAmt = DB::table('smithd as d')
            ->join('smithm as m', 'm.slno', '=', 'd.slno')
            ->join('items as i', 'i.code', '=', 'd.code')
            ->whereBetween('m.tdate', [$date1, $date2])
            ->where('m.control', '<=', $rlevel)
            ->where('i.itype', 'G')
            ->selectRaw('COALESCE(SUM(d.mcharge + (d.wastage * m.rate)), 0) as vaamt')
            ->value('vaamt');

        $r = (array) $row;
        $r['vaamt'] = (float) $smithVaAmt;

        return $r;
    }

    private function emptyRow(): array
    {
        return ['weight' => 0, 'wastage' => 0, 'mcharge' => 0, 'vaamt' => 0,
                'stoneprice' => 0, 'stonewgt' => 0, 'disc' => 0, 'tvaperc' => 0];
    }
}
