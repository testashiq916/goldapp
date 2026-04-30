<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SalesRegisterController extends Controller
{
    private array $colCache = [];

    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $rlevel = (int) $request->session()->get('gilevel', 1);
        if ($rlevel <= 0) $rlevel = 1;

        return view('reports.sales-register', [
            'title'  => 'Sales Register',
            'date1'  => date('Y-m-d'),
            'date2'  => date('Y-m-d'),
            'rlevel' => $rlevel,
        ]);
    }

    /* ── Lookups for dropdowns ─────────────────────────────────────── */
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

        $stktypes = [];
        if ($this->hasTable('stktype')) {
            $stktypes = DB::table('stktype')->select('code', 'name')->orderBy('code')->get()
                ->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name)])->values()->all();
        }

        $groups = [];
        if ($this->hasTable('itemgrp')) {
            $groups = DB::table('itemgrp')->select('code', 'name')->orderBy('code')->get()
                ->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name)])->values()->all();
        }

        $subgroups = [];
        if ($this->hasTable('itemsubgrp')) {
            $subgroups = DB::table('itemsubgrp')->select('code', 'name')->orderBy('code')->get()
                ->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name)])->values()->all();
        }

        return response()->json([
            'ok'        => true,
            'salesmen'  => $salesmen,
            'counters'  => $counters,
            'incharges' => $incharges,
            'stktypes'  => $stktypes,
            'groups'    => $groups,
            'subgroups' => $subgroups,
        ]);
    }

    /* ── Data endpoint ─────────────────────────────────────────────── */
    public function data(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $date1  = (string) $request->query('date1', date('Y-m-d'));
        $date2  = (string) $request->query('date2', date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date1) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date2)) {
            return response()->json(['ok' => false, 'message' => 'Invalid date'], 422);
        }

        $rlevel  = (int) $request->query('rlevel', (int) $request->session()->get('gilevel', 1));
        $reptype = strtolower(trim((string) $request->query('reptype', 'billwise')));

        $filters = [
            'smcode'   => trim((string) $request->query('smcode', '')),
            'counter'  => trim((string) $request->query('counter', '')),
            'ic'       => trim((string) $request->query('ic', '')),
            'custcode' => trim((string) $request->query('custcode', '')),
            'itemcode' => trim((string) $request->query('itemcode', '')),
            'stktype'  => trim((string) $request->query('stktype', '')),
            'type1'    => trim((string) $request->query('type1', '')),
            'type2'    => trim((string) $request->query('type2', '')),
            'type3'    => trim((string) $request->query('type3', '')),
            'grpcode'  => trim((string) $request->query('grpcode', '')),
            'subgrp'   => trim((string) $request->query('subgrp', '')),
            'stkcounter' => (int) $request->query('stkcounter', 0),
        ];

        if (!$this->hasTable('salesm') || !$this->hasTable('salesd') || !$this->hasTable('items')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => []]);
        }

        if ($reptype === 'itemsummary') {
            $rows = $this->itemSummaryRows($date1, $date2, $rlevel, $filters);
        } else {
            $rows = $this->detailRows($date1, $date2, $rlevel, $filters);
        }

        return response()->json([
            'ok'     => true,
            'rows'   => $rows,
            'totals' => $this->buildTotals($rows),
        ]);
    }

    /* ── Detail rows (Billwise / Itemwise / SManwise) ──────────────── */
    private function detailRows(string $date1, string $date2, int $rlevel, array $filters): array
    {
        $q = DB::table('salesd as d')
            ->join('salesm as m', 'd.slno', '=', 'm.slno')
            ->join('items as i', 'd.code', '=', 'i.code');

        if ($this->hasTable('sman') && $this->hasCol('sman', 'code')) {
            $q->leftJoin('sman as s', 'm.smcode', '=', 's.code');
        }

        $q = $q
            ->selectRaw('m.slno, m.tdate, m.billno, m.custname')
            ->selectRaw($this->colExpr('salesm', 'billamt', 'm', 'billamt', '0'))
            ->selectRaw($this->colExpr('salesm', 'eamt', 'm', 'eamt', '0'))
            ->selectRaw($this->colExpr('salesm', 'sretamt', 'm', 'sretamt', '0'))
            ->selectRaw($this->colExpr('salesm', 'discount', 'm', 'discount', '0'))
            ->selectRaw($this->colExpr('salesm', 'ramt', 'm', 'ramt', '0'))
            ->selectRaw($this->colExpr('salesm', 'staxamt', 'm', 'staxamt', '0'))
            ->selectRaw($this->colExpr('salesm', 'astamt', 'm', 'astamt', '0'))
            ->selectRaw($this->colExpr('salesm', 'netamt', 'm', 'netamt', '0'))
            ->selectRaw($this->colExpr('salesm', 'smcode', 'm', 'smcode', "''"))
            ->selectRaw($this->colExpr('sman', 'name', 's', 'smname', "''"))
            ->selectRaw($this->colExpr('salesm', 'counter', 'm', 'counter', "''"))
            ->selectRaw($this->colExpr('salesm', 'ic', 'm', 'ic', "''"))
            ->selectRaw($this->colExpr('salesm', 'custcode', 'm', 'custcode', "''"))
            ->selectRaw($this->colExpr('salesm', 'status', 'm', 'status', '0'))
            ->selectRaw($this->colExpr('salesm', 'orderno', 'm', 'orderno', "''"))
            ->selectRaw('d.sno, i.name as item_name, d.code as item_code')
            ->selectRaw('d.qty, d.weight, d.rate, d.amount')
            ->selectRaw($this->colExpr('salesd', 'stonewgt', 'd', 'stonewgt', '0'))
            ->selectRaw($this->colExpr('salesd', 'mcharge', 'd', 'mcharge', '0'))
            ->selectRaw($this->colExpr('salesd', 'wastage', 'd', 'wastage', '0'))
            ->selectRaw($this->colExpr('salesd', 'stoneprice', 'd', 'stoneprice', '0'))
            ->selectRaw($this->colExpr('salesd', 'stktype', 'd', 'stktype', "''"))
            ->selectRaw($this->colExpr('salesd', 'stkcounter', 'd', 'stkcounter', "''"))
            ->selectRaw($this->colExpr('salesd', 'model', 'd', 'model', "''"))
            ->selectRaw($this->colExpr('items', 'itype', 'i', 'itype', "''"))
            ->selectRaw($this->colExpr('items', 'dmdplt', 'i', 'dmdplt', "''"))
            ->selectRaw($this->colExpr('items', 'ornament', 'i', 'ornament', "''"))
            ->selectRaw($this->colExpr('items', 'grpcode', 'i', 'grpcode', "''"))
            ->selectRaw($this->colExpr('items', 'subgrp', 'i', 'subgrp', "''"));

        $q->whereBetween('m.tdate', [$date1, $date2]);
        $this->applyFilters($q, $rlevel, $filters);

        $rows = $q->orderBy('m.tdate')->orderBy('m.slno')->orderBy('d.sno')
            ->get()->map(function ($r) {
                $row = (array) $r;
                $row['smcode'] = trim((string) ($row['smcode'] ?? ''));
                $row['smname'] = trim((string) ($row['smname'] ?? ''));
                if ($row['smname'] === '') {
                    $row['smname'] = $row['smcode'];
                }
                $row['netwgt'] = (float) ($row['weight'] ?? 0) - (float) ($row['stonewgt'] ?? 0);
                $va = (float) ($row['mcharge'] ?? 0);
                $row['va'] = $va;
                $row['balance'] = (float) ($row['netamt'] ?? 0) - (float) ($row['ramt'] ?? 0);
                return $row;
            })->values()->all();

        return $rows;
    }

    /* ── Item Summary rows ────────────────────────────────────────── */
    private function itemSummaryRows(string $date1, string $date2, int $rlevel, array $filters): array
    {
        $q = DB::table('salesd as d')
            ->join('salesm as m', 'd.slno', '=', 'm.slno')
            ->join('items as i', 'd.code', '=', 'i.code')
            ->selectRaw('i.name as item_name, d.code as item_code')
            ->selectRaw($this->colExpr('items', 'itype', 'i', 'itype', "''"))
            ->selectRaw('SUM(d.qty) as qty')
            ->selectRaw('SUM(d.weight) as weight')
            ->selectRaw('SUM(' . ($this->hasCol('salesd', 'stonewgt') ? 'd.stonewgt' : '0') . ') as stonewgt')
            ->selectRaw('SUM(d.amount) as amount')
            ->selectRaw('SUM(' . ($this->hasCol('salesd', 'mcharge') ? 'd.mcharge' : '0') . ') as mcharge')
            ->selectRaw('SUM(' . ($this->hasCol('salesd', 'stoneprice') ? 'd.stoneprice' : '0') . ') as stoneprice');

        $q->whereBetween('m.tdate', [$date1, $date2]);
        $this->applyFilters($q, $rlevel, $filters);

        $q->groupBy('d.code', 'i.name');
        if ($this->hasCol('items', 'itype')) $q->groupBy('i.itype');

        $rows = $q->orderBy('i.name')
            ->get()->map(function ($r) {
                $row = (array) $r;
                $row['netwgt'] = (float) ($row['weight'] ?? 0) - (float) ($row['stonewgt'] ?? 0);
                return $row;
            })->values()->all();

        return $rows;
    }

    /* ── Apply filters ─────────────────────────────────────────────── */
    private function applyFilters($q, int $rlevel, array $filters): void
    {
        if ($this->hasCol('salesm', 'control'))  $q->where('m.control', '<=', $rlevel);
        if ($this->hasCol('salesm', 'opbill')) {
            $q->where(function ($sub) {
                $sub->whereNull('m.opbill')
                    ->orWhere('m.opbill', '<>', 1);
            });
        }
        if ($this->hasCol('salesm', 'sr'))       $q->where('m.sr', 'S');

        if ($filters['smcode'] !== '' && $this->hasCol('salesm', 'smcode'))
            $q->where('m.smcode', $filters['smcode']);

        if ($filters['counter'] !== '' && $this->hasCol('salesm', 'counter')) {
            if ($filters['stkcounter'] && $this->hasCol('salesd', 'stkcounter')) {
                $q->where('d.stkcounter', $filters['counter']);
            } else {
                $q->where('m.counter', $filters['counter']);
            }
        }

        if ($filters['ic'] !== '' && $this->hasCol('salesm', 'ic'))
            $q->where('m.ic', $filters['ic']);

        if ($filters['custcode'] !== '' && $this->hasCol('salesm', 'custcode'))
            $q->where('m.custcode', $filters['custcode']);

        if ($filters['itemcode'] !== '')
            $q->where('d.code', $filters['itemcode']);

        if ($filters['stktype'] !== '' && $this->hasCol('salesd', 'stktype'))
            $q->where('d.stktype', $filters['stktype']);

        // Type1: G=Gold, S=Silver, O=Others, P=Platinum, D=Diamond
        if ($filters['type1'] !== '' && $this->hasCol('items', 'itype'))
            $q->where('i.itype', $filters['type1']);

        // Type2: D=Diamond, P=Platinum, G=Gold, S=Silver, O=Others, C=Color Stone, W=Watch
        if ($filters['type2'] !== '' && $this->hasCol('items', 'dmdplt'))
            $q->where('i.dmdplt', $filters['type2']);

        // Type3: O=Ornaments Only, N=Not Ornaments
        if ($filters['type3'] !== '' && $this->hasCol('items', 'ornament'))
            $q->where('i.ornament', $filters['type3'] === 'O' ? 'Y' : 'N');

        if ($filters['grpcode'] !== '' && $this->hasCol('items', 'grpcode'))
            $q->where('i.grpcode', $filters['grpcode']);

        if ($filters['subgrp'] !== '' && $this->hasCol('items', 'subgrp'))
            $q->where('i.subgrp', $filters['subgrp']);
    }

    /* ── Totals ────────────────────────────────────────────────────── */
    private function buildTotals(array $rows): array
    {
        $keys = ['qty','weight','stonewgt','netwgt','amount','mcharge','stoneprice','va','billamt','eamt','sretamt','discount','ramt','staxamt','netamt','balance'];
        $totals = [];
        foreach ($keys as $k) $totals[$k] = 0.0;
        foreach ($rows as $row) {
            foreach ($keys as $k) $totals[$k] += (float) ($row[$k] ?? 0);
        }
        return $totals;
    }

    /* ── Column helpers ────────────────────────────────────────────── */
    private function hasCol(string $table, string $col): bool
    {
        $key = "$table.$col";
        if (!array_key_exists($key, $this->colCache)) {
            $this->colCache[$key] = $this->hasTable($table) && Schema::hasColumn($table, $col);
        }
        return $this->colCache[$key];
    }

    private function colExpr(string $table, string $col, string $alias, string $as, string $fallback): string
    {
        return $this->hasCol($table, $col) ? "$alias.$col as $as" : "$fallback as $as";
    }
}
