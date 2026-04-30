<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RemakeReportController extends Controller
{
    /* ────────────────────────────────────────────────────────────────
     |  PAGE METHODS
     * ──────────────────────────────────────────────────────────────── */

    public function remakeReports(Request $request)
    {
        if (!$request->session()->has('user_code')) return redirect('/login');
        return view('remake-reports.remake-reports', [
            'title'    => 'Remake Reports',
            'moduleId' => (string) $request->query('module', 'remake-reports-remake-reports'),
            'today'    => date('Y-m-d'),
        ]);
    }

    public function remakePending(Request $request)
    {
        if (!$request->session()->has('user_code')) return redirect('/login');
        return view('remake-reports.remake-pending', [
            'title'    => 'Remake Pending',
            'moduleId' => (string) $request->query('module', 'remake-reports-remake-pending'),
            'today'    => date('Y-m-d'),
        ]);
    }

    public function remakeReturns(Request $request)
    {
        if (!$request->session()->has('user_code')) return redirect('/login');
        return view('remake-reports.remake-returns', [
            'title'    => 'Remake Returns',
            'moduleId' => (string) $request->query('module', 'remake-reports-remake-returns'),
            'today'    => date('Y-m-d'),
        ]);
    }

    /* ────────────────────────────────────────────────────────────────
     |  API: Remake Reports — 4 sub-types matching d_remakereport_1..4
     |
     |  type=1  Remake Entry Details       (repairm givrec='R')
     |  type=2  Goldsmith Issue Details    (smithm/smithd givrec='G')
     |  type=3  Goldsmith Rcpt Details     (smithm/smithd givrec='R')
     |  type=4  Remake Return to Party     (repairm givrec='G')
     * ──────────────────────────────────────────────────────────────── */

    public function remakeReportsData(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $date1  = $this->date($request, 'date1');
        $date2  = $this->date($request, 'date2');
        $type   = (int) $request->query('type', 1);
        $rlevel = (int) $request->session()->get('gilevel', 1);
        if ($rlevel <= 0) $rlevel = 1;

        return match ($type) {
            2       => $this->smithData($date1, $date2, 'G', $rlevel),
            3       => $this->smithData($date1, $date2, 'R', $rlevel),
            4       => $this->repairData($date1, $date2, 'G', $rlevel),
            default => $this->repairData($date1, $date2, 'R', $rlevel),
        };
    }

    /* ────────────────────────────────────────────────────────────────
     |  API: Remake Pending (receipts with no matching return)
     * ──────────────────────────────────────────────────────────────── */

    public function remakePendingData(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        if (!$this->hasTable('repairm')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => []]);
        }

        $custcode = trim((string) $request->query('custcode', ''));
        $today    = date('Y-m-d');

        $masters = DB::table('repairm as m')
            ->where('m.givrec', 'R')
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('repairm as r')
                    ->where('r.givrec', 'G')
                    ->whereRaw('UPPER(TRIM(r.rbillno)) = UPPER(TRIM(m.billno))');
            })
            ->when($custcode !== '', fn($q) => $q->where('m.custcode', $custcode))
            ->select('m.slno', 'm.billno', 'm.tdate', 'm.duedate',
                     'm.custcode', 'm.custname', 'm.sman')
            ->orderBy('m.tdate')->orderBy('m.slno')
            ->get()->keyBy('slno');

        $rows = [];

        if ($masters->isNotEmpty() && $this->hasTable('repaird')) {
            $slnos   = $masters->keys()->all();
            $details = DB::table('repaird as d')
                ->whereIn('d.slno', $slnos)
                ->select('d.slno', 'd.sno', 'd.code', 'd.name',
                         'd.qty', 'd.weight', 'd.stonewgt', 'd.netwgt', 'd.complaint')
                ->orderBy('d.slno')->orderBy('d.sno')
                ->get()->groupBy('slno');

            foreach ($masters as $slno => $m) {
                $items = $details->get($slno, collect())->map(fn($d) => [
                    'sno'       => (int)($d->sno ?? 0),
                    'code'      => trim($d->code ?? ''),
                    'name'      => trim($d->name ?? ''),
                    'qty'       => (float)($d->qty ?? 0),
                    'weight'    => (float)($d->weight ?? 0),
                    'stonewgt'  => (float)($d->stonewgt ?? 0),
                    'netwgt'    => (float)($d->netwgt ?? 0),
                    'complaint' => trim($d->complaint ?? ''),
                ])->values()->all();

                $duedate = $m->duedate ?? '';
                $overdue = ($duedate !== '' && $duedate < $today);

                $rows[] = [
                    'slno'      => (int)$slno,
                    'billno'    => trim($m->billno ?? ''),
                    'tdate'     => $m->tdate ?? '',
                    'duedate'   => $duedate,
                    'overdue'   => $overdue,
                    'custcode'  => trim($m->custcode ?? ''),
                    'custname'  => trim($m->custname ?? ''),
                    'sman'      => trim($m->sman ?? ''),
                    'items'     => $items,
                    'tot_qty'   => array_sum(array_column($items, 'qty')),
                    'tot_wgt'   => array_sum(array_column($items, 'weight')),
                    'tot_net'   => array_sum(array_column($items, 'netwgt')),
                    'item_count'=> count($items),
                ];
            }
        }

        $totals = [
            'bills'   => count($rows),
            'overdue' => count(array_filter($rows, fn($r) => $r['overdue'])),
            'tot_wgt' => array_sum(array_column($rows, 'tot_wgt')),
            'tot_net' => array_sum(array_column($rows, 'tot_net')),
        ];

        return response()->json(['ok' => true, 'rows' => $rows, 'totals' => $totals]);
    }

    /* ────────────────────────────────────────────────────────────────
     |  API: Remake Returns (givrec='G') in date range
     * ──────────────────────────────────────────────────────────────── */

    public function remakeReturnsData(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        if (!$this->hasTable('repairm')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => []]);
        }

        $date1    = $this->date($request, 'date1');
        $date2    = $this->date($request, 'date2');
        $custcode = trim((string) $request->query('custcode', ''));

        $q = DB::table('repairm as m')
            ->where('m.givrec', 'G')
            ->whereBetween('m.tdate', [$date1, $date2])
            ->when($custcode !== '', fn($q) => $q->where('m.custcode', $custcode))
            ->select('m.slno', 'm.billno', 'm.tdate', 'm.custcode', 'm.custname',
                     'm.rbillno', 'm.sman');

        foreach (['amount', 'discount', 'rcvd', 'taxamt'] as $col) {
            if (Schema::hasColumn('repairm', $col)) $q->addSelect("m.{$col}");
        }

        $masters = $q->orderBy('m.tdate')->orderBy('m.slno')->get()->keyBy('slno');
        $rows    = [];

        if ($masters->isNotEmpty() && $this->hasTable('repaird')) {
            $slnos   = $masters->keys()->all();
            $details = DB::table('repaird as d')
                ->whereIn('d.slno', $slnos)
                ->select('d.slno', 'd.sno', 'd.code', 'd.name',
                         'd.qty', 'd.weight', 'd.stonewgt', 'd.netwgt')
                ->orderBy('d.slno')->orderBy('d.sno')
                ->get()->groupBy('slno');

            foreach ($masters as $slno => $m) {
                $items    = $details->get($slno, collect())->map(fn($d) => [
                    'sno'      => (int)($d->sno ?? 0),
                    'code'     => trim($d->code ?? ''),
                    'name'     => trim($d->name ?? ''),
                    'qty'      => (float)($d->qty ?? 0),
                    'weight'   => (float)($d->weight ?? 0),
                    'stonewgt' => (float)($d->stonewgt ?? 0),
                    'netwgt'   => (float)($d->netwgt ?? 0),
                ])->values()->all();

                $amount   = (float)($m->amount   ?? 0);
                $discount = (float)($m->discount  ?? 0);
                $taxamt   = (float)($m->taxamt    ?? 0);
                $rcvd     = (float)($m->rcvd      ?? 0);
                $netamt   = $amount + $taxamt - $discount;

                $rows[] = [
                    'slno'      => (int)$slno,
                    'billno'    => trim($m->billno ?? ''),
                    'tdate'     => $m->tdate ?? '',
                    'custcode'  => trim($m->custcode ?? ''),
                    'custname'  => trim($m->custname ?? ''),
                    'rbillno'   => trim($m->rbillno ?? ''),
                    'sman'      => trim($m->sman ?? ''),
                    'amount'    => $amount,
                    'discount'  => $discount,
                    'taxamt'    => $taxamt,
                    'netamt'    => $netamt,
                    'rcvd'      => $rcvd,
                    'balance'   => $netamt - $rcvd,
                    'items'     => $items,
                    'tot_wgt'   => array_sum(array_column($items, 'weight')),
                    'tot_net'   => array_sum(array_column($items, 'netwgt')),
                    'item_count'=> count($items),
                ];
            }
        }

        $totals = [
            'bills'    => count($rows),
            'amount'   => array_sum(array_column($rows, 'amount')),
            'discount' => array_sum(array_column($rows, 'discount')),
            'taxamt'   => array_sum(array_column($rows, 'taxamt')),
            'netamt'   => array_sum(array_column($rows, 'netamt')),
            'rcvd'     => array_sum(array_column($rows, 'rcvd')),
            'balance'  => array_sum(array_column($rows, 'balance')),
            'tot_wgt'  => array_sum(array_column($rows, 'tot_wgt')),
        ];

        return response()->json(['ok' => true, 'rows' => $rows, 'totals' => $totals]);
    }

    /* ────────────────────────────────────────────────────────────────
     |  PRIVATE: repairm data (type 1 = givrec='R', type 4 = givrec='G')
     * ──────────────────────────────────────────────────────────────── */

    private function repairData(string $date1, string $date2, string $givrec, int $rlevel): JsonResponse
    {
        if (!$this->hasTable('repairm')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => [], 'rtype' => $givrec === 'R' ? 1 : 4]);
        }

        $masters = DB::table('repairm as m')
            ->where('m.givrec', $givrec)
            ->whereBetween('m.tdate', [$date1, $date2])
            ->select('m.slno', 'm.billno', 'm.tdate', 'm.duedate',
                     'm.custcode', 'm.custname', 'm.sman', 'm.addr')
            ->orderBy('m.tdate')->orderBy('m.slno')
            ->get()->keyBy('slno');

        $rows = [];

        if ($masters->isNotEmpty() && $this->hasTable('repaird')) {
            $cols    = array_map('strtolower', $this->columnList('repaird'));
            $slnos   = $masters->keys()->all();
            $details = DB::table('repaird as d')
                ->whereIn('d.slno', $slnos)
                ->select(array_filter([
                    'd.slno', 'd.sno', 'd.code', 'd.name', 'd.qty',
                    'd.weight', 'd.stonewgt',
                    in_array('netwgt', $cols)    ? 'd.netwgt'    : DB::raw('(d.weight - d.stonewgt) as netwgt'),
                    in_array('complaint', $cols) ? 'd.complaint' : DB::raw("'' as complaint"),
                    in_array('purity', $cols)    ? 'd.purity'    : DB::raw("'' as purity"),
                    in_array('mcharge', $cols)   ? 'd.mcharge'   : DB::raw('0 as mcharge'),
                    in_array('wastage', $cols)   ? 'd.wastage'   : DB::raw('0 as wastage'),
                    in_array('amount', $cols)    ? 'd.amount'    : DB::raw('0 as amount'),
                ]))
                ->orderBy('d.slno')->orderBy('d.sno')
                ->get()->groupBy('slno');

            foreach ($masters as $slno => $m) {
                $items = $details->get($slno, collect())->map(fn($d) => [
                    'sno'       => (int)($d->sno ?? 0),
                    'code'      => trim($d->code ?? ''),
                    'name'      => trim($d->name ?? ''),
                    'qty'       => (float)($d->qty ?? 0),
                    'weight'    => (float)($d->weight ?? 0),
                    'stonewgt'  => (float)($d->stonewgt ?? 0),
                    'netwgt'    => (float)($d->netwgt ?? 0),
                    'complaint' => trim($d->complaint ?? ''),
                    'purity'    => trim($d->purity ?? ''),
                    'mcharge'   => (float)($d->mcharge ?? 0),
                    'wastage'   => (float)($d->wastage ?? 0),
                    'amount'    => (float)($d->amount ?? 0),
                ])->values()->all();

                // For type 4 (return), check the original receipt billno
                $returned = false;
                if ($givrec === 'R') {
                    $returned = DB::table('repairm')
                        ->where('givrec', 'G')
                        ->whereRaw('UPPER(TRIM(rbillno)) = ?', [strtoupper(trim($m->billno ?? ''))])
                        ->exists();
                }

                $mcols = array_map('strtolower', $this->columnList('repairm'));
                $rbillno = in_array('rbillno', $mcols)
                    ? (string)(DB::table('repairm')->where('slno', $slno)->value('rbillno') ?? '')
                    : '';

                $rows[] = [
                    'slno'      => (int)$slno,
                    'billno'    => trim($m->billno ?? ''),
                    'tdate'     => $m->tdate ?? '',
                    'duedate'   => $m->duedate ?? '',
                    'custcode'  => trim($m->custcode ?? ''),
                    'custname'  => trim($m->custname ?? ''),
                    'sman'      => trim($m->sman ?? ''),
                    'returned'  => $returned,
                    'rbillno'   => trim($rbillno),
                    'items'     => $items,
                    'tot_qty'   => array_sum(array_column($items, 'qty')),
                    'tot_wgt'   => array_sum(array_column($items, 'weight')),
                    'tot_net'   => array_sum(array_column($items, 'netwgt')),
                    'tot_amt'   => array_sum(array_column($items, 'amount')),
                    'item_count'=> count($items),
                ];
            }
        }

        $totals = [
            'bills'   => count($rows),
            'tot_wgt' => array_sum(array_column($rows, 'tot_wgt')),
            'tot_net' => array_sum(array_column($rows, 'tot_net')),
            'tot_qty' => array_sum(array_column($rows, 'tot_qty')),
            'tot_amt' => array_sum(array_column($rows, 'tot_amt')),
        ];

        return response()->json(['ok' => true, 'rows' => $rows, 'totals' => $totals, 'rtype' => $givrec === 'R' ? 1 : 4]);
    }

    /* ────────────────────────────────────────────────────────────────
     |  PRIVATE: smithm/smithd data (type 2 = givrec='G', type 3 = givrec='R')
     * ──────────────────────────────────────────────────────────────── */

    private function smithData(string $date1, string $date2, string $givrec, int $rlevel): JsonResponse
    {
        if (!$this->hasTable('smithm') || !$this->hasTable('smithd')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => [], 'rtype' => $givrec === 'G' ? 2 : 3]);
        }

        $smithCols = array_map('strtolower', $this->columnList('smithm'));
        $detCols   = array_map('strtolower', $this->columnList('smithd'));

        $q = DB::table('smithm as m')
            ->join('smithd as d', 'd.slno', '=', 'm.slno')
            ->where('d.givrec', $givrec)
            ->whereBetween('m.tdate', [$date1, $date2])
            ->select([
                'm.slno as smithm_slno', 'm.tdate', 'm.docno',
                'm.smithcode',
                in_array('control', $smithCols) ? 'm.control' : DB::raw('1 as control'),
            ])
            ->addSelect([
                'd.sno', 'd.code', 'd.name',
                'd.weight', 'd.stonewgt',
                in_array('netwgt', $detCols)   ? 'd.netwgt'   : DB::raw('(d.weight - d.stonewgt) as netwgt'),
                in_array('touch', $detCols)    ? 'd.touch'    : DB::raw('0 as touch'),
                in_array('purity', $detCols)   ? 'd.purity'   : DB::raw("'' as purity"),
                in_array('mcharge', $detCols)  ? 'd.mcharge'  : DB::raw('0 as mcharge'),
                in_array('wastage', $detCols)  ? 'd.wastage'  : DB::raw('0 as wastage'),
                in_array('qty', $detCols)      ? 'd.qty'      : DB::raw('0 as qty'),
            ]);

        // Join clients for smith name
        if ($this->hasTable('clients') && in_array('smithcode', $smithCols)) {
            $q->leftJoin('clients as c', function ($j) {
                $j->whereRaw('TRIM(c.code) = TRIM(m.smithcode)');
            })->addSelect(DB::raw('COALESCE(TRIM(c.name), TRIM(m.smithcode)) as smithname'));
        } else {
            $q->addSelect(DB::raw('TRIM(m.smithcode) as smithname'));
        }

        if (in_array('control', $smithCols)) {
            $q->where('m.control', '<=', $rlevel);
        }

        $rows = $q->orderBy('m.tdate')->orderBy('m.slno')->orderBy('d.sno')
            ->get()->map(fn($r) => [
                'slno'      => (int)($r->smithm_slno ?? 0),
                'docno'     => trim($r->docno ?? ''),
                'tdate'     => $r->tdate ?? '',
                'smithcode' => trim($r->smithcode ?? ''),
                'smithname' => trim($r->smithname ?? ''),
                'sno'       => (int)($r->sno ?? 0),
                'code'      => trim($r->code ?? ''),
                'name'      => trim($r->name ?? ''),
                'qty'       => (float)($r->qty ?? 0),
                'weight'    => (float)($r->weight ?? 0),
                'stonewgt'  => (float)($r->stonewgt ?? 0),
                'netwgt'    => (float)($r->netwgt ?? 0),
                'touch'     => (float)($r->touch ?? 0),
                'mcharge'   => (float)($r->mcharge ?? 0),
                'wastage'   => (float)($r->wastage ?? 0),
            ])->values()->all();

        $totals = [
            'rows'    => count($rows),
            'weight'  => array_sum(array_column($rows, 'weight')),
            'netwgt'  => array_sum(array_column($rows, 'netwgt')),
            'mcharge' => array_sum(array_column($rows, 'mcharge')),
        ];

        return response()->json(['ok' => true, 'rows' => $rows, 'totals' => $totals, 'rtype' => $givrec === 'G' ? 2 : 3]);
    }

    /* ────────────────────────────────────────────────────────────────
     |  Helper
     * ──────────────────────────────────────────────────────────────── */

    private function date(Request $request, string $key): string
    {
        $v = (string) $request->query($key, date('Y-m-d'));
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : date('Y-m-d');
    }
}
