<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MCProfitReportController extends Controller
{
    private array $colCache = [];

    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('reports.mc-profit', [
            'title'  => 'MC Profit Report',
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

        $salesmen = [];
        if ($this->hasTable('sman')) {
            $salesmen = DB::table('sman')->select('code', 'name')->orderBy('code')->get()
                ->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name)])->values()->all();
        }

        $incharges = [];
        if ($this->hasTable('incharge')) {
            $incharges = DB::table('incharge')->select('code', 'name')->orderBy('code')->get()
                ->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name)])->values()->all();
        }

        return response()->json(['ok' => true, 'salesmen' => $salesmen, 'incharges' => $incharges]);
    }

    public function data(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $date1 = (string) $request->query('date1', date('Y-m-d'));
        $date2 = (string) $request->query('date2', date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date1) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date2)) {
            return response()->json(['ok' => false, 'message' => 'Invalid date'], 422);
        }

        $rlevel    = (int) $request->query('rlevel', (int) $request->session()->get('gilevel', 1));
        $smcode    = trim((string) $request->query('smcode', ''));
        $itemcode  = trim((string) $request->query('itemcode', ''));
        $stktype   = trim((string) $request->query('stktype', ''));
        $type      = trim((string) $request->query('type', ''));
        $ic        = trim((string) $request->query('ic', ''));
        $icon      = (int) $request->query('icon', 0);
        $custcode  = trim((string) $request->query('custcode', ''));
        $mctype    = trim((string) $request->query('mctype', ''));
        $zerodisc  = (int) $request->query('zerodisc', 0);
        $cashonly  = (int) $request->query('cashonly', 0);

        if (!$this->hasTable('salesm') || !$this->hasTable('salesd')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => []]);
        }

        // Sales rows (igrp=1)
        $salesRows = $this->fetchSalesRows($date1, $date2, $rlevel);

        // Sales return rows (igrp=2) - negative amounts
        $returnRows = $this->fetchReturnRows($date1, $date2, $rlevel);

        $allRows = array_merge($salesRows, $returnRows);

        // Apply client-side filters
        $allRows = $this->applyFilters($allRows, [
            'smcode' => $smcode, 'itemcode' => $itemcode, 'stktype' => $stktype,
            'type' => $type, 'ic' => $ic, 'icon' => $icon, 'custcode' => $custcode,
            'mctype' => $mctype, 'zerodisc' => $zerodisc, 'cashonly' => $cashonly,
        ]);

        // Sort by date, slno
        usort($allRows, function ($a, $b) {
            $c = strcmp($a['tdate'] ?? '', $b['tdate'] ?? '');
            return $c !== 0 ? $c : (($a['slno'] ?? 0) - ($b['slno'] ?? 0));
        });

        $rows = array_values($allRows);

        return response()->json([
            'ok'     => true,
            'rows'   => $rows,
            'totals' => $this->buildTotals($rows),
        ]);
    }

    private function fetchSalesRows(string $date1, string $date2, int $rlevel): array
    {
        $q = DB::table('salesd as d')
            ->join('salesm as m', 'm.slno', '=', 'd.slno')
            ->leftJoin('items as i', 'i.code', '=', 'd.code')
            ->select(
                'm.slno', 'm.tdate', 'm.billno', 'm.billamt', 'm.custname',
                'd.qty', 'd.weight', 'd.stonewgt', 'd.mcharge', 'd.wastage',
                'd.rate', 'd.amount', 'd.bcode'
            );

        $q->addSelect(DB::raw($this->hasCol('salesm', 'smcode') ? 'm.smcode' : "'' as smcode"));
        $q->addSelect(DB::raw($this->hasCol('salesm', 'discount') ? 'm.discount' : '0 as discount'));
        $q->addSelect(DB::raw($this->hasCol('salesm', 'eamt') ? 'm.eamt' : '0 as eamt'));
        $q->addSelect(DB::raw($this->hasCol('salesm', 'sretamt') ? 'm.sretamt' : '0 as sretamt'));
        $q->addSelect(DB::raw($this->hasCol('salesm', 'ic') ? 'm.ic' : "'' as ic"));
        $q->addSelect(DB::raw($this->hasCol('salesm', 'custcode') ? 'm.custcode' : "'' as custcode"));
        $q->addSelect(DB::raw($this->hasCol('salesm', 'status') ? 'm.status' : '0 as status'));
        $q->addSelect(DB::raw($this->hasCol('salesd', 'stktype') ? 'd.stktype' : "'' as stktype"));
        $q->addSelect(DB::raw($this->hasCol('salesd', 'name') ? 'd.name as dname' : "'' as dname"));
        $q->addSelect(DB::raw($this->hasCol('items', 'name') ? 'i.name as itemname' : "'' as itemname"));
        $q->addSelect(DB::raw($this->hasCol('items', 'itype') ? 'i.itype' : "'' as itype"));
        $q->addSelect(DB::raw($this->hasCol('items', 'ornament') ? 'i.ornament' : "'' as ornament"));
        $q->addSelect(DB::raw($this->hasCol('items', 'smithmc') ? 'i.smithmc' : '0 as smithmc'));
        $q->addSelect(DB::raw($this->hasCol('items', 'code') ? 'i.code as itemcode' : "'' as itemcode"));

        $q->whereBetween('m.tdate', [$date1, $date2]);
        if ($this->hasCol('salesm', 'control')) $q->where('m.control', '<=', $rlevel);

        $q->orderBy('m.tdate')->orderBy('m.slno');

        return $q->get()->map(function ($r) {
            $row = $this->computeRow((array) $r, 1);
            return $row;
        })->values()->all();
    }

    private function fetchReturnRows(string $date1, string $date2, int $rlevel): array
    {
        if (!$this->hasTable('salesrm') || !$this->hasTable('salesrd')) {
            return [];
        }

        $q = DB::table('salesrd as d')
            ->join('salesrm as m', 'm.slno', '=', 'd.slno')
            ->leftJoin('items as i', 'i.code', '=', 'd.code')
            ->select('m.slno', 'm.tdate', 'm.billno', 'm.billamt', 'm.custname');

        $q->addSelect(DB::raw($this->hasCol('salesrd', 'qty') ? 'd.qty' : '0 as qty'));
        $q->addSelect(DB::raw($this->hasCol('salesrd', 'weight') ? 'd.weight' : '0 as weight'));
        $q->addSelect(DB::raw($this->hasCol('salesrd', 'stonewgt') ? 'd.stonewgt' : '0 as stonewgt'));
        $q->addSelect(DB::raw($this->hasCol('salesrd', 'mcharge') ? 'd.mcharge' : '0 as mcharge'));
        $q->addSelect(DB::raw($this->hasCol('salesrd', 'wastage') ? 'd.wastage' : '0 as wastage'));
        $q->addSelect(DB::raw($this->hasCol('salesrd', 'rate') ? 'd.rate' : '0 as rate'));
        $q->addSelect(DB::raw($this->hasCol('salesrd', 'amount') ? 'd.amount' : '0 as amount'));
        $q->selectRaw('0 as bcode');
        $q->addSelect(DB::raw($this->hasCol('salesrm', 'smcode') ? 'm.smcode' : "'' as smcode"));
        $q->addSelect(DB::raw($this->hasCol('salesrm', 'discount') ? 'm.discount' : '0 as discount'));
        $q->selectRaw('0 as eamt, 0 as sretamt');
        $q->addSelect(DB::raw($this->hasCol('salesrm', 'ic') ? 'm.ic' : "'' as ic"));
        $q->addSelect(DB::raw("'' as custcode"));
        $q->selectRaw('0 as status');
        $q->addSelect(DB::raw($this->hasCol('salesrd', 'stktype') ? 'd.stktype' : "'' as stktype"));
        $q->selectRaw("'' as dname");
        $q->addSelect(DB::raw($this->hasCol('items', 'name') ? 'i.name as itemname' : "'' as itemname"));
        $q->addSelect(DB::raw($this->hasCol('items', 'itype') ? 'i.itype' : "'' as itype"));
        $q->addSelect(DB::raw($this->hasCol('items', 'ornament') ? 'i.ornament' : "'' as ornament"));
        $q->addSelect(DB::raw($this->hasCol('items', 'smithmc') ? 'i.smithmc' : '0 as smithmc'));
        $q->addSelect(DB::raw($this->hasCol('items', 'code') ? 'i.code as itemcode' : "'' as itemcode"));

        $q->whereBetween('m.tdate', [$date1, $date2]);
        if ($this->hasCol('salesrm', 'control')) $q->where('m.control', '<=', $rlevel);

        return $q->get()->map(function ($r) {
            $arr = (array) $r;
            // Negate values for returns
            $arr['qty']      = -((int) ($arr['qty'] ?? 0));
            $arr['weight']   = -((float) ($arr['weight'] ?? 0));
            $arr['stonewgt'] = -((float) ($arr['stonewgt'] ?? 0));
            $arr['mcharge']  = -((float) ($arr['mcharge'] ?? 0));
            $arr['wastage']  = -((float) ($arr['wastage'] ?? 0));
            $arr['amount']   = -((float) ($arr['amount'] ?? 0));
            $arr['billamt']  = -((float) ($arr['billamt'] ?? 0));
            $arr['discount'] = -((float) ($arr['discount'] ?? 0));
            return $this->computeRow($arr, 2);
        })->values()->all();
    }

    private function computeRow(array $row, int $igrp): array
    {
        $weight   = (float) ($row['weight'] ?? 0);
        $stonewgt = (float) ($row['stonewgt'] ?? 0);
        $mcharge  = (float) ($row['mcharge'] ?? 0);
        $wastage  = (float) ($row['wastage'] ?? 0);
        $rate     = (float) ($row['rate'] ?? 0);
        $smithmc  = (float) ($row['smithmc'] ?? 0);

        $netWgt = $weight - $stonewgt;

        // VA = mcharge + wastage * rate
        $row['va'] = $mcharge + $wastage * $rate;

        // VA per gram = va / netWgt (guard div-by-zero)
        $row['vapergm'] = ($netWgt != 0) ? round($row['va'] / $netWgt, 2) : 0;

        // MC Profit = va - netWgt * smithmc
        $row['mcprof'] = $row['va'] - $netWgt * $smithmc;

        // Display name
        $dname = trim((string) ($row['dname'] ?? ''));
        $row['displayname'] = $dname !== '' ? $dname : trim((string) ($row['itemname'] ?? ''));

        $row['igrp'] = $igrp;

        return $row;
    }

    private function applyFilters(array $rows, array $f): array
    {
        return array_values(array_filter($rows, function ($row) use ($f) {
            if ($f['smcode'] !== '' && trim((string) ($row['smcode'] ?? '')) !== $f['smcode']) return false;
            if ($f['itemcode'] !== '' && trim((string) ($row['itemcode'] ?? '')) !== $f['itemcode']) return false;
            if ($f['stktype'] !== '' && trim((string) ($row['stktype'] ?? '')) !== $f['stktype']) return false;

            $itype = strtoupper(trim((string) ($row['itype'] ?? '')));
            $orn   = strtoupper(trim((string) ($row['ornament'] ?? '')));
            if ($f['type'] === 'G' && $itype !== 'G') return false;
            if ($f['type'] === 'S' && $itype !== 'S') return false;
            if ($f['type'] === 'O' && $itype !== 'O') return false;
            if ($f['type'] === 'orn_Y' && $orn !== 'Y') return false;
            if ($f['type'] === 'orn_N' && $orn !== 'N') return false;

            if ($f['icon'] && $f['ic'] !== '' && trim((string) ($row['ic'] ?? '')) !== $f['ic']) return false;
            if ($f['custcode'] !== '' && trim((string) ($row['custcode'] ?? '')) !== $f['custcode']) return false;

            if ($f['zerodisc'] && (float) ($row['discount'] ?? 0) != 0) return false;
            if ($f['cashonly'] && (int) ($row['status'] ?? 0) !== 3) return false;

            // MC Type filter on vapergm
            $vapergm = (float) ($row['vapergm'] ?? 0);
            if ($f['mctype'] === 'lt200' && $vapergm >= 200) return false;
            if ($f['mctype'] === '200-250' && ($vapergm < 200 || $vapergm > 250)) return false;
            if ($f['mctype'] === '250-300' && ($vapergm <= 250 || $vapergm > 300)) return false;
            if ($f['mctype'] === '300-350' && ($vapergm <= 300 || $vapergm > 350)) return false;
            if ($f['mctype'] === 'gt350' && $vapergm <= 350) return false;

            return true;
        }));
    }

    private function buildTotals(array $rows): array
    {
        $t = [
            'gold_weight' => 0, 'gold_amount' => 0, 'silver_weight' => 0, 'silver_amount' => 0,
            'total_stonewgt' => 0, 'total_va' => 0, 'total_mcprof' => 0,
            'total_discount' => 0, 'count' => count($rows),
        ];
        $seenSlnos = [];
        $totalBillamt = 0;

        foreach ($rows as $row) {
            $itype = strtoupper(trim((string) ($row['itype'] ?? '')));
            $wgt = (float) ($row['weight'] ?? 0);
            $amt = (float) ($row['amount'] ?? 0);

            if ($itype === 'G') { $t['gold_weight'] += $wgt; $t['gold_amount'] += $amt; }
            if ($itype === 'S') { $t['silver_weight'] += $wgt; $t['silver_amount'] += $amt; }

            $t['total_stonewgt'] += (float) ($row['stonewgt'] ?? 0);
            $t['total_va'] += (float) ($row['va'] ?? 0);
            $t['total_mcprof'] += (float) ($row['mcprof'] ?? 0);
            $t['total_discount'] += (float) ($row['discount'] ?? 0);

            $key = ($row['igrp'] ?? 1) . '_' . ($row['slno'] ?? 0);
            if (!isset($seenSlnos[$key])) {
                $seenSlnos[$key] = true;
                $totalBillamt += (float) ($row['billamt'] ?? 0);
            }
        }
        $t['total_billamt'] = $totalBillamt;
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
