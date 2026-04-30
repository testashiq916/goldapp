<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class SupplierReportsController extends Controller
{
    private function auth(Request $request): bool
    {
        return $request->session()->has('user_code');
    }

    private function gilevel(Request $request): int
    {
        return max(1, (int) $request->session()->get('gilevel', 1));
    }

    private function startDate(Request $request): string
    {
        $d = (string) $request->session()->get('gdtstartdate', '');
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : date('Y') . '-04-01';
    }

    private function nd(string $v): string
    {
        $v = trim($v);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return $v;
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $v, $m)) return "$m[3]-$m[2]-$m[1]";
        return date('Y-m-d');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 1. DUEDATE REPORT — suppliers with outstanding payable balances
    // ══════════════════════════════════════════════════════════════════════════

    public function duedateIndex(Request $request): View|RedirectResponse
    {
        if (!$this->auth($request)) return redirect('/login');
        return view('suppliers.duedate-report', [
            'title'  => 'Supplier Duedate Report',
            'date1'  => date('Y-m-d'),
            'date2'  => date('Y-m-d', strtotime('+30 days')),
            'rlevel' => $this->gilevel($request),
        ]);
    }

    public function duedateData(Request $request): JsonResponse
    {
        if (!$this->auth($request)) return response()->json(['ok' => false], 401);
        $date1  = $this->nd((string) $request->query('date1', date('Y-m-d')));
        $date2  = $this->nd((string) $request->query('date2', date('Y-m-d', strtotime('+30 days'))));
        $rl     = $this->gilevel($request);
        $name   = trim((string) $request->query('name', ''));
        $filter = trim((string) $request->query('filter', 'overdue_first'));

        $opColumn = $rl === 1 ? 'opbal' : 'opbalb';

        if (!$this->hasTable('accountm') || !$this->hasTable('clients')) {
            return response()->json(['ok' => true, 'rows' => []]);
        }

        $hasDaybook  = $this->hasTable('daybook');
        $hasPurchase = $this->hasTable('purchasem');

        $baseRows = DB::table('accountm as a')
            ->join('clients as c', 'a.accode', '=', 'c.code')
            ->selectRaw("
                TRIM(a.accode) as accode,
                TRIM(COALESCE(c.name,'')) as cname,
                TRIM(COALESCE(c.mobile,'')) as mobile,
                TRIM(COALESCE(c.telephone,'')) as telephone,
                COALESCE(a.$opColumn,0) as opbal,
                c.duedate as client_duedate
            ")
            ->where('a.actype2', 'S')
            ->where('a.control', '<=', $rl)
            ->when($name !== '', function ($q) use ($name) {
                $n = '%' . strtoupper($name) . '%';
                $q->where(function ($sub) use ($n) {
                    $sub->whereRaw('UPPER(COALESCE(c.name,"")) LIKE ?', [$n])
                        ->orWhereRaw('UPPER(TRIM(a.accode)) LIKE ?', [$n]);
                });
            })
            ->get();

        if ($baseRows->isEmpty()) return response()->json(['ok' => true, 'rows' => []]);

        $codes = $baseRows->pluck('accode')->map(fn($x) => trim((string)$x))->filter()->values()->all();

        $dbStats = [];
        if ($hasDaybook && count($codes)) {
            $dbStats = DB::table('daybook')
                ->selectRaw('TRIM(accode) as code, COALESCE(SUM(amount),0) as tamt')
                ->whereIn('accode', $codes)
                ->where('tdate', '<=', $date2)
                ->where('control', '<=', $rl)
                ->groupBy('accode')->get()
                ->keyBy(fn($r) => trim($r->code))->all();
        }

        $purchaseDue = [];
        if ($hasPurchase && count($codes)) {
            $purchaseDue = DB::table('purchasem')
                ->selectRaw('TRIM(suppcode) as code, MAX(duedate) as due')
                ->whereIn('suppcode', $codes)
                ->groupBy('suppcode')->get()
                ->keyBy(fn($r) => trim($r->code))->all();
        }

        $rows = [];
        foreach ($baseRows as $row) {
            $code   = trim((string) $row->accode);
            $tamt   = (float) ($dbStats[$code]->tamt ?? 0);
            $netbal = round((float) $row->opbal + $tamt, 2);

            if (abs($netbal) < 0.01) continue;

            $pdue    = $purchaseDue[$code]->due ?? null;
            $cldue   = $row->client_duedate;
            $duedate = max(array_filter([$pdue, $cldue])) ?: null;
            $overdue = $duedate && $duedate < date('Y-m-d');

            $rows[] = [
                'accode'    => $code,
                'cname'     => (string) $row->cname,
                'mobile'    => (string) $row->mobile,
                'telephone' => (string) $row->telephone,
                'netbal'    => $netbal,
                'bal_abs'   => abs($netbal),
                'status'    => $netbal > 0 ? 'TG' : 'TR',  // TG=payable for supplier
                'duedate'   => $duedate ?? '',
                'overdue'   => $overdue,
            ];
        }

        if ($request->boolean('range_filter', false)) {
            $rows = array_values(array_filter($rows, fn($r) => $r['duedate'] >= $date1 && $r['duedate'] <= $date2));
        }

        usort($rows, function ($a, $b) use ($filter) {
            if ($filter === 'by_name')    return strcmp($a['cname'], $b['cname']);
            if ($filter === 'by_balance') return $b['bal_abs'] <=> $a['bal_abs'];
            if ($a['overdue'] !== $b['overdue']) return $b['overdue'] - $a['overdue'];
            return strcmp($a['duedate'], $b['duedate']) ?: strcmp($a['cname'], $b['cname']);
        });

        $totals = ['tr' => 0.0, 'tg' => 0.0, 'count' => count($rows)];
        foreach ($rows as $r) {
            if ($r['status'] === 'TR') $totals['tr'] += $r['bal_abs'];
            else $totals['tg'] += $r['bal_abs'];
        }

        return response()->json(['ok' => true, 'rows' => $rows, 'totals' => $totals]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // 2. SUPPLIER LIST
    // ══════════════════════════════════════════════════════════════════════════

    public function supplierListIndex(Request $request): View|RedirectResponse
    {
        if (!$this->auth($request)) return redirect('/login');
        return view('suppliers.list', [
            'title'  => 'Supplier List',
            'rlevel' => $this->gilevel($request),
        ]);
    }

    public function supplierListData(Request $request): JsonResponse
    {
        if (!$this->auth($request)) return response()->json(['ok' => false], 401);
        $rl    = $this->gilevel($request);
        $name  = trim((string) $request->query('name', ''));
        $grp   = trim((string) $request->query('grp', ''));
        $route = trim((string) $request->query('route', ''));

        if (!$this->hasTable('clients') || !$this->hasTable('accountm')) {
            return response()->json(['ok' => true, 'rows' => []]);
        }

        $opCol      = $rl === 1 ? 'opbal' : 'opbalb';
        $hasDaybook = $this->hasTable('daybook');

        $q = DB::table('clients as c')
            ->join('accountm as a', 'c.code', '=', 'a.accode')
            ->selectRaw("
                TRIM(c.code) as code,
                TRIM(COALESCE(c.name,'')) as name,
                TRIM(COALESCE(c.addr1,'')) as addr1,
                TRIM(COALESCE(c.addr2,'')) as addr2,
                TRIM(COALESCE(c.city,'')) as city,
                TRIM(COALESCE(c.mobile,'')) as mobile,
                TRIM(COALESCE(c.telephone,'')) as telephone,
                TRIM(COALESCE(c.tinno,'')) as tinno,
                TRIM(COALESCE(c.grp,'')) as grp,
                TRIM(COALESCE(c.route,'')) as route,
                COALESCE(c.duedate,'') as duedate,
                COALESCE(a.$opCol,0) as opbal
            ")
            ->where('a.actype2', 'S')
            ->where('a.control', '<=', $rl);

        if ($name !== '') {
            $n = '%' . strtoupper($name) . '%';
            $q->where(function ($sub) use ($n) {
                $sub->whereRaw('UPPER(COALESCE(c.name,"")) LIKE ?', [$n])
                    ->orWhereRaw('UPPER(TRIM(c.code)) LIKE ?', [$n])
                    ->orWhereRaw('UPPER(COALESCE(c.mobile,"")) LIKE ?', [$n]);
            });
        }
        if ($grp !== '')   $q->where('c.grp', $grp);
        if ($route !== '') $q->where('c.route', $route);

        $baseRows = $q->orderBy('c.name')->get();
        if ($baseRows->isEmpty()) return response()->json(['ok' => true, 'rows' => [], 'groups' => [], 'routes' => []]);

        $codes = $baseRows->pluck('code')->map(fn($x) => trim((string)$x))->filter()->values()->all();

        $dbStats = [];
        if ($hasDaybook && count($codes)) {
            $dbStats = DB::table('daybook')
                ->selectRaw('TRIM(accode) as code, COALESCE(SUM(amount),0) as tamt')
                ->whereIn('accode', $codes)
                ->where('control', '<=', $rl)
                ->groupBy('accode')->get()
                ->keyBy(fn($r) => trim($r->code))->all();
        }

        $rows = $baseRows->map(function ($row) use ($dbStats) {
            $code   = trim((string) $row->code);
            $tamt   = (float) ($dbStats[$code]->tamt ?? 0);
            $netbal = round((float) $row->opbal + $tamt, 2);
            return [
                'code'      => $code,
                'name'      => (string) $row->name,
                'addr1'     => (string) $row->addr1,
                'addr2'     => (string) $row->addr2,
                'city'      => (string) $row->city,
                'mobile'    => (string) $row->mobile,
                'telephone' => (string) $row->telephone,
                'tinno'     => (string) $row->tinno,
                'grp'       => (string) $row->grp,
                'route'     => (string) $row->route,
                'duedate'   => (string) $row->duedate,
                'netbal'    => $netbal,
                'status'    => $netbal > 0 ? 'TG' : ($netbal < 0 ? 'TR' : ''),
            ];
        })->values()->all();

        $groups = $this->hasTable('clientsgrp')
            ? DB::table('clientsgrp')->selectRaw('TRIM(code) as code, TRIM(name) as name')->orderBy('name')->get()->toArray()
            : [];
        $routes = $this->hasTable('route')
            ? DB::table('route')->selectRaw('TRIM(code) as code, TRIM(name) as name')->orderBy('name')->get()->toArray()
            : [];

        return response()->json(['ok' => true, 'rows' => $rows, 'count' => count($rows), 'groups' => $groups, 'routes' => $routes]);
    }
}
