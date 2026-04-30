<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NetSalesReportController extends Controller
{
    private array $colCache = [];

    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $rlevel = (int) $request->session()->get('gilevel', 1);
        if ($rlevel <= 0) $rlevel = 1;

        return view('reports.net-sales', [
            'title'  => 'Net Sales Report',
            'date1'  => date('Y-m-d'),
            'date2'  => date('Y-m-d'),
            'rlevel' => $rlevel,
        ]);
    }

    /* ── Lookups ──────────────────────────────────────────────────── */
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

        $agents = [];
        if ($this->hasTable('accountm') && $this->hasCol('accountm', 'actype1')) {
            $agents = DB::table('accountm')->select('accode as code', 'name')
                ->where('actype1', 'AG')
                ->orderBy('accode')->get()
                ->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name)])->values()->all();
        }

        $coaccounts = [];
        if ($this->hasTable('accountm')) {
            $coaccounts = DB::table('accountm')->select('accode as code', 'name')
                ->orderBy('accode')->get()
                ->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name)])->values()->all();
        }

        return response()->json([
            'ok'         => true,
            'salesmen'   => $salesmen,
            'counters'   => $counters,
            'incharges'  => $incharges,
            'agents'     => $agents,
            'coaccounts' => $coaccounts,
        ]);
    }

    /* ── Data endpoint ────────────────────────────────────────────── */
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

        $rlevel  = (int) $request->query('rlevel', (int) $request->session()->get('gilevel', 1));
        $reptype = strtolower(trim((string) $request->query('reptype', 'netsales')));

        $filters = [
            'smcode'     => trim((string) $request->query('smcode', '')),
            'counter'    => trim((string) $request->query('counter', '')),
            'counteron'  => (int) $request->query('counteron', 0),
            'ic'         => trim((string) $request->query('ic', '')),
            'icon'       => (int) $request->query('icon', 0),
            'cocode'     => trim((string) $request->query('cocode', '')),
            'agcode'     => trim((string) $request->query('agcode', '')),
            'agcodeon'   => (int) $request->query('agcodeon', 0),
        ];

        if (!$this->hasTable('salesm')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => []]);
        }

        if ($reptype === 'cosummary') {
            $rows = $this->coSummaryRows($date1, $date2, $rlevel, $filters);
        } elseif ($reptype === 'agentsummary') {
            $rows = $this->agentSummaryRows($date1, $date2, $rlevel, $filters);
        } else {
            $rows = $this->netSalesRows($date1, $date2, $rlevel, $filters);
        }

        return response()->json([
            'ok'     => true,
            'rows'   => $rows,
            'totals' => $this->buildTotals($rows),
        ]);
    }

    /* ── Net Sales detail rows ────────────────────────────────────── */
    private function netSalesRows(string $date1, string $date2, int $rlevel, array $filters): array
    {
        $q = DB::table('salesm as m')
            ->selectRaw('m.slno, m.tdate, m.billno, m.custname')
            ->selectRaw($this->colExpr('salesm', 'smcode', 'm', 'smcode', "''"))
            ->selectRaw($this->colExpr('salesm', 'counter', 'm', 'counter', "''"))
            ->selectRaw($this->colExpr('salesm', 'ic', 'm', 'ic', "''"))
            ->selectRaw($this->colExpr('salesm', 'billamt', 'm', 'billamt', '0'))
            ->selectRaw($this->colExpr('salesm', 'eamt', 'm', 'eamt', '0'))
            ->selectRaw($this->colExpr('salesm', 'discount', 'm', 'discount', '0'))
            ->selectRaw($this->colExpr('salesm', 'netamt', 'm', 'netamt', '0'))
            ->selectRaw($this->colExpr('salesm', 'sretamt', 'm', 'sretamt', '0'))
            ->selectRaw($this->colExpr('salesm', 'cocode', 'm', 'cocode', "''"))
            ->selectRaw($this->colExpr('salesm', 'agcode', 'm', 'agcode', "''"))
            ->selectRaw($this->colExpr('salesm', 'tva', 'm', 'tva', '0'));

        // Subquery: total sales weight from salesd
        if ($this->hasTable('salesd')) {
            $q->selectRaw('(SELECT COALESCE(SUM(sd.weight),0) FROM salesd sd WHERE sd.slno = m.slno) as tsaleswgt');
            $q->selectRaw('(SELECT COALESCE(SUM(' . ($this->hasCol('salesd', 'stonewgt') ? 'sd.stonewgt' : '0') . '),0) FROM salesd sd WHERE sd.slno = m.slno) as tsalesstwgt');
            $q->selectRaw('(SELECT COALESCE(SUM(' . ($this->hasCol('salesd', 'mcharge') ? 'sd.mcharge' : '0') . '),0) FROM salesd sd WHERE sd.slno = m.slno) as tva');
            $q->selectRaw('(SELECT COALESCE(SUM(' . ($this->hasCol('salesd', 'stoneprice') ? 'sd.stoneprice' : '0') . '),0) FROM salesd sd WHERE sd.slno = m.slno) as tstamt');
        } else {
            $q->selectRaw('0 as tsaleswgt, 0 as tsalesstwgt, 0 as tva, 0 as tstamt');
        }

        // Subquery: total return weight from salesrd
        if ($this->hasTable('salesrd')) {
            $q->selectRaw('(SELECT COALESCE(SUM(sr.weight),0) FROM salesrd sr WHERE sr.slno = m.' . ($this->hasCol('salesm', 'retnslno') ? 'retnslno' : 'slno') . ') as tsretwgt');
        } else {
            $q->selectRaw('0 as tsretwgt');
        }

        $q->whereBetween('m.tdate', [$date1, $date2]);
        $this->applyFilters($q, $rlevel, $filters);

        $q->orderBy('m.tdate')->orderBy('m.slno');

        $rows = $q->get()->map(function ($r) {
            $row = (array) $r;
            $tsaleswgt   = (float) ($row['tsaleswgt'] ?? 0);
            $tsalesstwgt = (float) ($row['tsalesstwgt'] ?? 0);
            $tsretwgt    = (float) ($row['tsretwgt'] ?? 0);
            $tva         = (float) ($row['tva'] ?? 0);
            $tstamt      = (float) ($row['tstamt'] ?? 0);
            $billamt     = (float) ($row['billamt'] ?? 0);

            $row['netsalewgt'] = $tsaleswgt - $tsalesstwgt;
            $row['netswgt']    = $row['netsalewgt'] - $tsretwgt;
            $row['netsamt']    = (float) ($row['netamt'] ?? 0);
            // MC% = 100 * tva / (billamt - tva - tstamt), guard div-by-zero
            $denom = $billamt - $tva - $tstamt;
            $row['vap'] = ($denom != 0) ? round(100 * $tva / $denom, 2) : 0;

            return $row;
        })->values()->all();

        return $rows;
    }

    /* ── C/o Summary rows ─────────────────────────────────────────── */
    private function coSummaryRows(string $date1, string $date2, int $rlevel, array $filters): array
    {
        if (!$this->hasCol('salesm', 'cocode')) {
            return [];
        }

        $q = DB::table('salesm as m')
            ->selectRaw('m.cocode')
            ->selectRaw('COUNT(*) as billcount')
            ->selectRaw('SUM(' . ($this->hasCol('salesm', 'billamt') ? 'm.billamt' : '0') . ') as billamt')
            ->selectRaw('SUM(' . ($this->hasCol('salesm', 'netamt') ? 'm.netamt' : '0') . ') as netamt')
            ->selectRaw('SUM(' . ($this->hasCol('salesm', 'discount') ? 'm.discount' : '0') . ') as discount')
            ->selectRaw('SUM(' . ($this->hasCol('salesm', 'sretamt') ? 'm.sretamt' : '0') . ') as sretamt');

        $q->whereBetween('m.tdate', [$date1, $date2]);
        $this->applyFilters($q, $rlevel, $filters);
        $q->groupBy('m.cocode')->orderBy('m.cocode');

        $accounts = [];
        if ($this->hasTable('accountm')) {
            $accounts = DB::table('accountm')->pluck('name', 'accode')
                ->mapWithKeys(fn($v, $k) => [trim($k) => trim($v)])->all();
        }

        $rows = $q->get()->map(function ($r) use ($accounts) {
            $row = (array) $r;
            $code = trim($row['cocode'] ?? '');
            $row['coname'] = $accounts[$code] ?? $code;
            return $row;
        })->values()->all();

        return $rows;
    }

    /* ── Agent Summary rows ───────────────────────────────────────── */
    private function agentSummaryRows(string $date1, string $date2, int $rlevel, array $filters): array
    {
        if (!$this->hasCol('salesm', 'agcode')) {
            return [];
        }

        $q = DB::table('salesm as m')
            ->selectRaw('m.agcode')
            ->selectRaw('COUNT(*) as billcount')
            ->selectRaw('SUM(' . ($this->hasCol('salesm', 'billamt') ? 'm.billamt' : '0') . ') as billamt')
            ->selectRaw('SUM(' . ($this->hasCol('salesm', 'netamt') ? 'm.netamt' : '0') . ') as netamt')
            ->selectRaw('SUM(' . ($this->hasCol('salesm', 'discount') ? 'm.discount' : '0') . ') as discount')
            ->selectRaw('SUM(' . ($this->hasCol('salesm', 'sretamt') ? 'm.sretamt' : '0') . ') as sretamt');

        $q->whereBetween('m.tdate', [$date1, $date2]);
        $this->applyFilters($q, $rlevel, $filters);
        $q->groupBy('m.agcode')->orderBy('m.agcode');

        $accounts = [];
        if ($this->hasTable('accountm')) {
            $accounts = DB::table('accountm')->pluck('name', 'accode')
                ->mapWithKeys(fn($v, $k) => [trim($k) => trim($v)])->all();
        }

        $rows = $q->get()->map(function ($r) use ($accounts) {
            $row = (array) $r;
            $code = trim($row['agcode'] ?? '');
            $row['agname'] = $accounts[$code] ?? $code;
            return $row;
        })->values()->all();

        return $rows;
    }

    /* ── Apply filters ────────────────────────────────────────────── */
    private function applyFilters($q, int $rlevel, array $filters): void
    {
        if ($this->hasCol('salesm', 'control'))  $q->where('m.control', '<=', $rlevel);
        if ($this->hasCol('salesm', 'opbill'))   $q->where('m.opbill', '<>', 1);
        if ($this->hasCol('salesm', 'sr'))       $q->where('m.sr', 'S');

        if ($filters['smcode'] !== '' && $this->hasCol('salesm', 'smcode'))
            $q->where('m.smcode', $filters['smcode']);

        if ($filters['counter'] !== '' && $filters['counteron'] && $this->hasCol('salesm', 'counter'))
            $q->where('m.counter', $filters['counter']);

        if ($filters['ic'] !== '' && $filters['icon'] && $this->hasCol('salesm', 'ic'))
            $q->where('m.ic', $filters['ic']);

        if ($filters['cocode'] !== '' && $this->hasCol('salesm', 'cocode'))
            $q->where('m.cocode', $filters['cocode']);

        if ($filters['agcode'] !== '' && $filters['agcodeon'] && $this->hasCol('salesm', 'agcode'))
            $q->where('m.agcode', $filters['agcode']);
    }

    /* ── Totals ───────────────────────────────────────────────────── */
    private function buildTotals(array $rows): array
    {
        $keys = ['billamt','netamt','discount','sretamt','eamt','tsaleswgt','tsalesstwgt','tsretwgt','netsalewgt','netswgt','netsamt','tva','tstamt','billcount'];
        $totals = [];
        foreach ($keys as $k) $totals[$k] = 0.0;
        foreach ($rows as $row) {
            foreach ($keys as $k) $totals[$k] += (float) ($row[$k] ?? 0);
        }
        // Average MC%
        $denom = $totals['billamt'] - $totals['tva'] - $totals['tstamt'];
        $totals['vap'] = ($denom != 0) ? round(100 * $totals['tva'] / $denom, 2) : 0;
        return $totals;
    }

    /* ── Column helpers ───────────────────────────────────────────── */
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
