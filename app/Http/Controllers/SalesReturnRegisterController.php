<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SalesReturnRegisterController extends Controller
{
    private array $colCache = [];

    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $rlevel = (int) $request->session()->get('gilevel', 1);
        if ($rlevel <= 0) $rlevel = 1;

        $salesmen = [];
        if ($this->hasTable('sman')) {
            $salesmen = DB::table('sman')->select('code', 'name')->orderBy('code')->get()
                ->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name)])->values()->all();
        }

        return view('reports.sales-return-register', [
            'title'    => 'Sales Return Register',
            'date1'    => date('Y-m-d'),
            'date2'    => date('Y-m-d'),
            'rlevel'   => $rlevel,
            'salesmen' => $salesmen,
        ]);
    }

    /* ── Data endpoint ─────────────────────────────────────────────── */
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

        $rlevel   = (int) $request->query('rlevel', (int) $request->session()->get('gilevel', 1));
        $itemwise = (int) $request->query('itemwise', 0);

        $filters = [
            'smcode'   => trim((string) $request->query('smcode', '')),
            'itemcode' => trim((string) $request->query('itemcode', '')),
            'stktype'  => trim((string) $request->query('stktype', '')),
            'type1'    => trim((string) $request->query('type1', '')),
            'type2'    => trim((string) $request->query('type2', '')),
            'type3'    => trim((string) $request->query('type3', '')),
        ];

        if (!$this->hasTable('salesrm') || !$this->hasTable('salesrd') || !$this->hasTable('items')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => []]);
        }

        if ($itemwise) {
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

    /* ── Detail rows (item-level per bill) ─────────────────────────── */
    private function detailRows(string $date1, string $date2, int $rlevel, array $filters): array
    {
        $q = DB::table('salesrd as d')
            ->join('salesrm as m', 'd.slno', '=', 'm.slno')
            ->join('items as i', 'd.code', '=', 'i.code')
            ->selectRaw('m.slno, m.tdate, m.billno, m.custname')
            ->selectRaw($this->colExpr('salesrm', 'billamt', 'm', 'billamt', '0'))
            ->selectRaw($this->colExpr('salesrm', 'staxamt', 'm', 'staxamt', '0'))
            ->selectRaw($this->colExpr('salesrm', 'discount', 'm', 'discount', '0'))
            ->selectRaw($this->colExpr('salesrm', 'pamt', 'm', 'pamt', '0'))
            ->selectRaw($this->colExpr('salesrm', 'smcode', 'm', 'smcode', "''"))
            ->selectRaw($this->colExpr('salesrm', 'ic', 'm', 'ic', "''"))
            ->selectRaw('i.name as item_name, d.code as item_code')
            ->selectRaw('d.qty, d.weight, d.rate, d.amount')
            ->selectRaw($this->colExpr('salesrd', 'stonewgt', 'd', 'stonewgt', '0'))
            ->selectRaw($this->colExpr('salesrd', 'stoneprice', 'd', 'stoneprice', '0'))
            ->selectRaw($this->colExpr('salesrd', 'mcharge', 'd', 'mcharge', '0'))
            ->selectRaw($this->colExpr('salesrd', 'wastage', 'd', 'wastage', '0'))
            ->selectRaw($this->colExpr('salesrd', 'stktype', 'd', 'stktype', "''"))
            ->selectRaw($this->colExpr('salesrd', 'name', 'd', 'dname', "''"))
            ->selectRaw($this->colExpr('items', 'itype', 'i', 'itype', "''"))
            ->selectRaw($this->colExpr('items', 'dmdplt', 'i', 'dmdplt', "''"))
            ->selectRaw($this->colExpr('items', 'ornament', 'i', 'ornament', "''"));

        $q->whereBetween('m.tdate', [$date1, $date2]);
        $this->applyFilters($q, $rlevel, $filters);

        return $q->orderBy('m.tdate')->orderBy('m.slno')->orderBy('d.sno')
            ->get()->map(function ($r) {
                $row = (array) $r;
                $row['grosswgt'] = (float)($row['weight'] ?? 0) - (float)($row['stonewgt'] ?? 0);
                // Use salesrd.name if available, else items.name
                $dname = trim($row['dname'] ?? '');
                if ($dname !== '') $row['item_name'] = $dname;
                unset($row['dname']);
                return $row;
            })->values()->all();
    }

    /* ── Itemwise summary ──────────────────────────────────────────── */
    private function itemSummaryRows(string $date1, string $date2, int $rlevel, array $filters): array
    {
        $q = DB::table('salesrd as d')
            ->join('salesrm as m', 'd.slno', '=', 'm.slno')
            ->join('items as i', 'd.code', '=', 'i.code')
            ->selectRaw('i.name as item_name, d.code as item_code')
            ->selectRaw($this->colExpr('items', 'itype', 'i', 'itype', "''"))
            ->selectRaw('SUM(d.qty) as qty')
            ->selectRaw('SUM(d.weight) as weight')
            ->selectRaw('SUM(' . ($this->hasCol('salesrd', 'stonewgt') ? 'd.stonewgt' : '0') . ') as stonewgt')
            ->selectRaw('SUM(d.amount) as amount')
            ->selectRaw('SUM(' . ($this->hasCol('salesrd', 'mcharge') ? 'd.mcharge' : '0') . ') as mcharge')
            ->selectRaw('SUM(' . ($this->hasCol('salesrd', 'stoneprice') ? 'd.stoneprice' : '0') . ') as stoneprice')
            ->selectRaw('SUM(' . ($this->hasCol('salesrd', 'wastage') ? 'd.wastage' : '0') . ') as wastage');

        $q->whereBetween('m.tdate', [$date1, $date2]);
        $this->applyFilters($q, $rlevel, $filters);

        $q->groupBy('d.code', 'i.name');
        if ($this->hasCol('items', 'itype')) $q->groupBy('i.itype');

        return $q->orderBy('i.name')
            ->get()->map(function ($r) {
                $row = (array) $r;
                $row['grosswgt'] = (float)($row['weight'] ?? 0) - (float)($row['stonewgt'] ?? 0);
                return $row;
            })->values()->all();
    }

    /* ── Filters ───────────────────────────────────────────────────── */
    private function applyFilters($q, int $rlevel, array $filters): void
    {
        $this->applyTransferShadowDocFilter($q, 'm.billno');

        if ($this->hasCol('salesrm', 'control')) $q->where('m.control', '<=', $rlevel);

        if ($filters['smcode'] !== '' && $this->hasCol('salesrm', 'smcode'))
            $q->where('m.smcode', $filters['smcode']);

        if ($filters['itemcode'] !== '')
            $q->where('d.code', $filters['itemcode']);

        if ($filters['stktype'] !== '' && $this->hasCol('salesrd', 'stktype'))
            $q->where('d.stktype', $filters['stktype']);

        if ($filters['type1'] !== '' && $this->hasCol('items', 'itype'))
            $q->where('i.itype', $filters['type1']);

        if ($filters['type2'] !== '' && $this->hasCol('items', 'dmdplt'))
            $q->where('i.dmdplt', $filters['type2']);

        if ($filters['type3'] !== '' && $this->hasCol('items', 'ornament'))
            $q->where('i.ornament', $filters['type3'] === 'O' ? 'Y' : 'N');
    }

    /* ── Totals ────────────────────────────────────────────────────── */
    private function buildTotals(array $rows): array
    {
        $keys = ['qty','weight','stonewgt','grosswgt','amount','mcharge','stoneprice','wastage','billamt','staxamt','discount','pamt'];
        $totals = [];
        foreach ($keys as $k) $totals[$k] = 0.0;
        foreach ($rows as $row) {
            foreach ($keys as $k) $totals[$k] += (float)($row[$k] ?? 0);
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
