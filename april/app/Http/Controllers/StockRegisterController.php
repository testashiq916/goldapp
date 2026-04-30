<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StockRegisterController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('reports.stock-register', [
            'title'  => 'Stock Register',
            'date1'  => date('Y-m-d'),
            'date2'  => date('Y-m-d'),
            'rlevel' => (int) $request->session()->get('gilevel', 1) ?: 1,
        ]);
    }

    public function summaryIndex(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('reports.stock-register-summary', [
            'title' => (string) $request->query('title', 'Stock Register Summary'),
            'moduleId' => (string) $request->query('module', 'item-reports-stock-register-summary'),
            'date1' => (string) $request->query('date1', date('Y-m-d')),
            'date2' => (string) $request->query('date2', date('Y-m-d')),
            'rlevel' => (int) $request->session()->get('gilevel', 1) ?: 1,
        ]);
    }

    public function lookups(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $groups = [];
        if ($this->hasTable('itemgrp')) {
            $groups = DB::table('itemgrp')->select('code', 'name', 'itype')->orderBy('code')->get()
                ->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name), 'itype' => trim($r->itype ?? '')])->values()->all();
        }

        $subgroups = [];
        if ($this->hasTable('itemsubgrp')) {
            $subgroups = DB::table('itemsubgrp')->select('code', 'name')->orderBy('code')->get()
                ->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name)])->values()->all();
        }

        $stktypes = [];
        if ($this->hasTable('stktype')) {
            $stktypes = DB::table('stktype')->select('code', 'name')->orderBy('code')->get()
                ->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name)])->values()->all();
        }

        return response()->json([
            'ok'        => true,
            'groups'    => $groups,
            'subgroups' => $subgroups,
            'stktypes'  => $stktypes,
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
        $itype    = trim((string) $request->query('itype', ''));
        $grpcode  = trim((string) $request->query('grpcode', ''));
        $subgrp   = trim((string) $request->query('subgrp', ''));
        $stktype  = trim((string) $request->query('stktype', ''));
        $reptype  = trim((string) $request->query('reptype', 'detailed'));
        $zerostock = (int) $request->query('zerostock', 0);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date1) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date2)) {
            return response()->json(['ok' => false, 'message' => 'Invalid date'], 422);
        }

        if (!$this->hasTable('items')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => $this->emptyTotals()]);
        }

        $hasItemsstk = $this->hasTable('itemsstk');
        $hasSalesd   = $this->hasTable('salesd') && $this->hasTable('salesm');
        $hasPurchd   = $this->hasTable('purchased') && $this->hasTable('purchasem');
        $hasSalesrd  = $this->hasTable('salesrd') && $this->hasTable('salesrm');
        $hasPurchrd  = $this->hasTable('purchaserd') && $this->hasTable('purchaserm');

        // Base: items with opening stock
        $q = DB::table('items as i')
            ->select(
                'i.code', 'i.name', 'i.itype', 'i.grpcode', 'i.subgrpcode',
                'i.opqty', 'i.opweight', 'i.opstonewgt',
                'i.qty as curqty', 'i.weight as curweight', 'i.stonewgt as curstonewgt'
            );

        // Filters
        if ($itype !== '') $q->where('i.itype', $itype);
        if ($grpcode !== '') $q->where('i.grpcode', $grpcode);
        if ($subgrp !== '') $q->where('i.subgrpcode', $subgrp);

        $q->orderBy('i.name');

        $items = $q->get();

        // Get period movements: sales and purchases within date range
        $salesByItem = [];
        $purchByItem = [];
        $sretByItem  = [];
        $pretByItem  = [];

        if ($hasSalesd) {
            $sq = DB::table('salesd as d')
                ->join('salesm as m', 'm.slno', '=', 'd.slno')
                ->whereBetween('m.tdate', [$date1, $date2])
                ->where('m.control', '<=', $rlevel)
                ->select('d.code')
                ->selectRaw('SUM(d.qty) as qty, SUM(d.weight) as weight');
            if ($stktype !== '') {
                if (Schema::hasColumn('salesd', 'stktype')) {
                    $sq->where('d.stktype', $stktype);
                }
            }
            $sq->groupBy('d.code');
            foreach ($sq->get() as $r) {
                $salesByItem[trim($r->code)] = ['qty' => (int) $r->qty, 'weight' => (float) $r->weight];
            }
        }

        if ($hasPurchd) {
            $pq = DB::table('purchased as d')
                ->join('purchasem as m', 'm.slno', '=', 'd.slno')
                ->whereBetween('m.tdate', [$date1, $date2])
                ->where('m.control', '<=', $rlevel)
                ->select('d.code')
                ->selectRaw('SUM(d.qty) as qty, SUM(d.weight) as weight');
            if ($stktype !== '') {
                if (Schema::hasColumn('purchased', 'stktype')) {
                    $pq->where('d.stktype', $stktype);
                }
            }
            $pq->groupBy('d.code');
            foreach ($pq->get() as $r) {
                $purchByItem[trim($r->code)] = ['qty' => (int) $r->qty, 'weight' => (float) $r->weight];
            }
        }

        if ($hasSalesrd) {
            $srq = DB::table('salesrd as d')
                ->join('salesrm as m', 'm.slno', '=', 'd.slno')
                ->whereBetween('m.tdate', [$date1, $date2])
                ->where('m.control', '<=', $rlevel)
                ->select('d.code')
                ->selectRaw('SUM(d.qty) as qty, SUM(d.weight) as weight');
            $srq->groupBy('d.code');
            foreach ($srq->get() as $r) {
                $sretByItem[trim($r->code)] = ['qty' => (int) $r->qty, 'weight' => (float) $r->weight];
            }
        }

        if ($hasPurchrd) {
            $prq = DB::table('purchaserd as d')
                ->join('purchaserm as m', 'm.slno', '=', 'd.slno')
                ->whereBetween('m.tdate', [$date1, $date2])
                ->where('m.control', '<=', $rlevel)
                ->select('d.code')
                ->selectRaw('SUM(d.qty) as qty, SUM(d.weight) as weight');
            $prq->groupBy('d.code');
            foreach ($prq->get() as $r) {
                $pretByItem[trim($r->code)] = ['qty' => (int) $r->qty, 'weight' => (float) $r->weight];
            }
        }

        // Build rows
        $rows = [];
        foreach ($items as $item) {
            $code = trim($item->code);
            $opQty = (int) ($item->opqty ?? 0);
            $opWgt = (float) ($item->opweight ?? 0);

            $saleQty = $salesByItem[$code]['qty'] ?? 0;
            $saleWgt = $salesByItem[$code]['weight'] ?? 0;
            $purchQty = $purchByItem[$code]['qty'] ?? 0;
            $purchWgt = $purchByItem[$code]['weight'] ?? 0;
            $sretQty = $sretByItem[$code]['qty'] ?? 0;
            $sretWgt = $sretByItem[$code]['weight'] ?? 0;
            $pretQty = $pretByItem[$code]['qty'] ?? 0;
            $pretWgt = $pretByItem[$code]['weight'] ?? 0;

            // Closing = Opening + Purchase - Sale + SalesReturn - PurchaseReturn
            $clQty = $opQty + $purchQty - $saleQty + $sretQty - $pretQty;
            $clWgt = round($opWgt + $purchWgt - $saleWgt + $sretWgt - $pretWgt, 3);

            // Skip zero stock if not requested
            if (!$zerostock && $clQty == 0 && abs($clWgt) < 0.001 && $opQty == 0 && abs($opWgt) < 0.001) {
                continue;
            }

            $rows[] = [
                'code'      => $code,
                'name'      => trim($item->name ?? ''),
                'itype'     => strtoupper(trim($item->itype ?? '')),
                'grpcode'   => trim($item->grpcode ?? ''),
                'subgrpcode' => trim($item->subgrpcode ?? ''),
                'opqty'     => $opQty,
                'opweight'  => round($opWgt, 3),
                'purchqty'  => $purchQty,
                'purchwgt'  => round($purchWgt, 3),
                'saleqty'   => $saleQty,
                'salewgt'   => round($saleWgt, 3),
                'sretqty'   => $sretQty,
                'sretwgt'   => round($sretWgt, 3),
                'pretqty'   => $pretQty,
                'pretwgt'   => round($pretWgt, 3),
                'clqty'     => $clQty,
                'clwgt'     => $clWgt,
            ];
        }

        return response()->json([
            'ok'     => true,
            'rows'   => $rows,
            'totals' => $this->buildTotals($rows),
        ]);
    }

    public function summaryData(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $date1 = (string) $request->query('date1', date('Y-m-d'));
        $date2 = (string) $request->query('date2', date('Y-m-d'));
        $rlevel = (int) $request->query('rlevel', (int) $request->session()->get('gilevel', 1));
        $grpcode = trim((string) $request->query('grpcode', ''));
        $includeCp = (int) $request->query('include_cp', 0) === 1;
        $withNet = (int) $request->query('with_net', 0) === 1;

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date1) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date2)) {
            return response()->json(['ok' => false, 'message' => 'Invalid date'], 422);
        }

        $opening = $this->summaryOpeningStock($date1, max(1, $rlevel), $grpcode, $includeCp);
        $rows = $this->summaryRangeRows($date1, $date2, max(1, $rlevel), $grpcode, $includeCp, $withNet, $opening);

        return response()->json([
            'ok' => true,
            'rows' => $rows,
            'totals' => $this->summaryTotals($rows, $opening, $withNet),
        ]);
    }

    private function buildTotals(array $rows): array
    {
        $t = ['count' => count($rows),
              'opqty' => 0, 'opweight' => 0,
              'purchqty' => 0, 'purchwgt' => 0,
              'saleqty' => 0, 'salewgt' => 0,
              'sretqty' => 0, 'sretwgt' => 0,
              'pretqty' => 0, 'pretwgt' => 0,
              'clqty' => 0, 'clwgt' => 0,
              'goldwgt' => 0, 'silverwgt' => 0, 'otherwgt' => 0];

        foreach ($rows as $r) {
            $t['opqty']    += (int) ($r['opqty'] ?? 0);
            $t['opweight'] += (float) ($r['opweight'] ?? 0);
            $t['purchqty'] += (int) ($r['purchqty'] ?? 0);
            $t['purchwgt'] += (float) ($r['purchwgt'] ?? 0);
            $t['saleqty']  += (int) ($r['saleqty'] ?? 0);
            $t['salewgt']  += (float) ($r['salewgt'] ?? 0);
            $t['sretqty']  += (int) ($r['sretqty'] ?? 0);
            $t['sretwgt']  += (float) ($r['sretwgt'] ?? 0);
            $t['pretqty']  += (int) ($r['pretqty'] ?? 0);
            $t['pretwgt']  += (float) ($r['pretwgt'] ?? 0);
            $t['clqty']    += (int) ($r['clqty'] ?? 0);
            $t['clwgt']    += (float) ($r['clwgt'] ?? 0);

            $itype = $r['itype'] ?? '';
            if ($itype === 'G') $t['goldwgt'] += (float) ($r['clwgt'] ?? 0);
            elseif ($itype === 'S') $t['silverwgt'] += (float) ($r['clwgt'] ?? 0);
            else $t['otherwgt'] += (float) ($r['clwgt'] ?? 0);
        }

        return $t;
    }

    private function emptyTotals(): array
    {
        return ['count' => 0, 'opqty' => 0, 'opweight' => 0,
                'purchqty' => 0, 'purchwgt' => 0, 'saleqty' => 0, 'salewgt' => 0,
                'sretqty' => 0, 'sretwgt' => 0, 'pretqty' => 0, 'pretwgt' => 0,
                'clqty' => 0, 'clwgt' => 0,
                'goldwgt' => 0, 'silverwgt' => 0, 'otherwgt' => 0];
    }

    private function summaryOpeningStock(string $date1, int $rlevel, string $grpcode, bool $includeCp): array
    {
        $cols = array_map('strtolower', $this->columnList('items'));
        $weightColRaw  = $rlevel === 1 ? 'opweight'    : 'opweightb';
        $stoneColRaw   = $rlevel === 1 ? 'opstonewgt'  : 'opstonewgtb';
        $hasWeight     = in_array($weightColRaw, $cols);
        $hasStone      = in_array($stoneColRaw, $cols);
        $weightCol     = $hasWeight ? $weightColRaw : null;
        $stoneCol      = $hasStone  ? $stoneColRaw  : null;

        $itemBase = DB::table('items')
            ->where(function ($q) use ($grpcode, $includeCp) {
                $q->where(function ($inner) use ($grpcode) {
                    $inner->where('itype', 'G');
                    if ($grpcode !== '') {
                        $inner->where('grpcode', $grpcode);
                    }
                });

                if ($includeCp) {
                    $q->orWhere('code', 'CP');
                }
            });

        $gross = $weightCol
            ? (float) ((clone $itemBase)->selectRaw('COALESCE(SUM(' . $weightCol . '),0) as gross')->value('gross') ?? 0)
            : 0.0;
        $stoneExpr = ($weightCol && $stoneCol)
            ? $weightCol . ' - COALESCE(' . $stoneCol . ',0)'
            : ($weightCol ?? '0');
        $net = $weightCol
            ? (float) ((clone $itemBase)->selectRaw('COALESCE(SUM(' . $stoneExpr . '),0) as net')->value('net') ?? 0)
            : 0.0;

        $gross += $this->movementBeforeDate('purchased', 'purchasem', $date1, $rlevel, $grpcode, $includeCp, false, false);
        $gross -= $this->movementBeforeDate('purchaserd', 'purchaserm', $date1, $rlevel, $grpcode, $includeCp, false, false);
        $gross += $this->movementBeforeDate('salesrd', 'salesrm', $date1, $rlevel, $grpcode, $includeCp, false, false);
        $gross -= $this->movementBeforeDate('salesd', 'salesm', $date1, $rlevel, $grpcode, $includeCp, false, true);
        $gross += $this->movementBeforeDate('smithd', 'smithm', $date1, $rlevel, $grpcode, $includeCp, true, false, 'R');
        $gross -= $this->movementBeforeDate('smithd', 'smithm', $date1, $rlevel, $grpcode, $includeCp, true, false, 'G');
        $gross += $this->movementBeforeDate('repaird', 'repairm', $date1, $rlevel, $grpcode, $includeCp, true, false, 'R');
        $gross -= $this->movementBeforeDate('repaird', 'repairm', $date1, $rlevel, $grpcode, $includeCp, true, false, 'G');
        $gross -= $this->refineryIssuedBeforeDate($date1, $rlevel, $grpcode, $includeCp);
        $gross += $this->refineryReceivedBeforeDate($date1, $rlevel, $grpcode, $includeCp);
        $gross += $this->orderAdvanceBeforeDate($date1, $rlevel, $grpcode, $includeCp, false);
        $gross -= $this->itemAdjustmentBeforeDate($date1, $rlevel, $grpcode, $includeCp, 'from');
        $gross += $this->itemAdjustmentBeforeDate($date1, $rlevel, $grpcode, $includeCp, 'to');

        $net += $this->movementBeforeDate('purchased', 'purchasem', $date1, $rlevel, $grpcode, $includeCp, false, false, null, true);
        $net -= $this->movementBeforeDate('purchaserd', 'purchaserm', $date1, $rlevel, $grpcode, $includeCp, false, false, null, true);
        $net += $this->movementBeforeDate('salesrd', 'salesrm', $date1, $rlevel, $grpcode, $includeCp, false, false, null, true);
        $net -= $this->movementBeforeDate('salesd', 'salesm', $date1, $rlevel, $grpcode, $includeCp, false, true, null, true);
        $net += $this->movementBeforeDate('smithd', 'smithm', $date1, $rlevel, $grpcode, $includeCp, true, false, 'R', true);
        $net -= $this->movementBeforeDate('smithd', 'smithm', $date1, $rlevel, $grpcode, $includeCp, true, false, 'G', true);
        $net += $this->movementBeforeDate('repaird', 'repairm', $date1, $rlevel, $grpcode, $includeCp, true, false, 'R', true);
        $net -= $this->movementBeforeDate('repaird', 'repairm', $date1, $rlevel, $grpcode, $includeCp, true, false, 'G', true);
        $net -= $this->refineryIssuedBeforeDate($date1, $rlevel, $grpcode, $includeCp, true);
        $net += $this->refineryReceivedBeforeDate($date1, $rlevel, $grpcode, $includeCp, true);
        $net += $this->orderAdvanceBeforeDate($date1, $rlevel, $grpcode, $includeCp, true);
        $net -= $this->itemAdjustmentBeforeDate($date1, $rlevel, $grpcode, $includeCp, 'from', true);
        $net += $this->itemAdjustmentBeforeDate($date1, $rlevel, $grpcode, $includeCp, 'to', true);

        return [
            'gross' => round($gross, 3),
            'net' => round($net, 3),
        ];
    }

    private function summaryRangeRows(string $date1, string $date2, int $rlevel, string $grpcode, bool $includeCp, bool $withNet, array $opening): array
    {
        $rows = [];

        $rows = array_merge($rows, $this->summaryPurchaseRows($date1, $date2, $rlevel, $grpcode, $includeCp));
        $rows = array_merge($rows, $this->summarySalesReturnRows($date1, $date2, $rlevel, $grpcode, $includeCp));
        $rows = array_merge($rows, $this->summarySmithRows($date1, $date2, $rlevel, $grpcode, $includeCp));
        $rows = array_merge($rows, $this->summarySalesRows($date1, $date2, $rlevel, $grpcode, $includeCp));
        $rows = array_merge($rows, $this->summaryPurchaseReturnRows($date1, $date2, $rlevel, $grpcode, $includeCp));
        $rows = array_merge($rows, $this->summaryStockLessRows($date1, $date2, $rlevel, $grpcode, $includeCp));
        $rows = array_merge($rows, $this->summaryStockAddRows($date1, $date2, $rlevel, $grpcode, $includeCp));

        usort($rows, function ($a, $b) {
            return [$a['tdate'], $a['sort_order'], $a['partyname'], $a['docno']] <=> [$b['tdate'], $b['sort_order'], $b['partyname'], $b['docno']];
        });

        $runningGross = (float) ($opening['gross'] ?? 0);
        $runningNet = (float) ($opening['net'] ?? 0);

        foreach ($rows as &$row) {
            $row['topstkwgt'] = round($withNet ? $runningNet : $runningGross, 3);
            $runningGross += (float) ($row['delta_gross'] ?? 0);
            $runningNet += (float) ($row['delta_net'] ?? 0);
            $row['clstock'] = round($withNet ? $runningNet : $runningGross, 3);

            $row['rcvdwgt'] = round((float) ($row['rcvdwgt'] ?? 0), 3);
            $row['issuedwgt'] = round((float) ($row['issuedwgt'] ?? 0), 3);
            $row['rcvdcpwgt'] = round((float) ($row['rcvdcpwgt'] ?? 0), 3);
            $row['issuedcpwgt'] = round((float) ($row['issuedcpwgt'] ?? 0), 3);
        }
        unset($row);

        return $rows;
    }

    private function summaryTotals(array $rows, array $opening, bool $withNet): array
    {
        $totals = [
            'count' => count($rows),
            'opening' => round((float) ($withNet ? ($opening['net'] ?? 0) : ($opening['gross'] ?? 0)), 3),
            'received' => 0.0,
            'receivedcp' => 0.0,
            'issued' => 0.0,
            'issuedcp' => 0.0,
            'closing' => round((float) ($withNet ? ($opening['net'] ?? 0) : ($opening['gross'] ?? 0)), 3),
        ];

        foreach ($rows as $row) {
            $totals['received'] += (float) ($row['rcvdwgt'] ?? 0);
            $totals['receivedcp'] += (float) ($row['rcvdcpwgt'] ?? 0);
            $totals['issued'] += (float) ($row['issuedwgt'] ?? 0);
            $totals['issuedcp'] += (float) ($row['issuedcpwgt'] ?? 0);
            $totals['closing'] = (float) ($row['clstock'] ?? $totals['closing']);
        }

        $totals['received'] = round($totals['received'], 3);
        $totals['receivedcp'] = round($totals['receivedcp'], 3);
        $totals['issued'] = round($totals['issued'], 3);
        $totals['issuedcp'] = round($totals['issuedcp'], 3);
        $totals['closing'] = round($totals['closing'], 3);

        return $totals;
    }

    private function summaryPurchaseRows(string $date1, string $date2, int $rlevel, string $grpcode, bool $includeCp): array
    {
        if (!$this->hasTable('purchasem') || !$this->hasTable('purchased')) {
            return [];
        }

        $query = DB::table('purchasem as m')
            ->join('purchased as d', 'd.slno', '=', 'm.slno')
            ->join('items as i', 'i.code', '=', 'd.code')
            ->whereBetween('m.tdate', [$date1, $date2])
            ->where('m.control', '<=', $rlevel);

        $this->applySummaryItemFilter($query, 'i', $grpcode, $includeCp);

        $rows = $query
            ->groupBy('m.tdate', 'm.slno', 'm.name', 'm.billno')
            ->orderBy('m.tdate')
            ->orderBy('m.slno')
            ->get([
                'm.tdate',
                'm.billno',
                'm.name as partyname',
                DB::raw("COALESCE(SUM(CASE WHEN i.code <> 'CP' THEN d.weight ELSE 0 END),0) as goldwgt"),
                DB::raw("COALESCE(SUM(CASE WHEN i.code <> 'CP' THEN d.weight - COALESCE(d.stwgt,0) ELSE 0 END),0) as goldnet"),
                DB::raw("COALESCE(SUM(CASE WHEN i.code = 'CP' THEN d.weight ELSE 0 END),0) as cpwgt"),
            ]);

        return $rows->map(fn($r) => $this->makeSummaryRow(
            (string) $r->tdate,
            'Purchase',
            trim((string) ($r->partyname ?? '')),
            trim((string) ($r->billno ?? '')),
            (float) ($r->goldwgt ?? 0),
            0.0,
            (float) ($r->cpwgt ?? 0),
            0.0,
            (float) ($r->goldwgt ?? 0) + (float) ($r->cpwgt ?? 0),
            (float) ($r->goldnet ?? 0) + (float) ($r->cpwgt ?? 0),
            10
        ))->values()->all();
    }

    private function summarySalesReturnRows(string $date1, string $date2, int $rlevel, string $grpcode, bool $includeCp): array
    {
        if (!$this->hasTable('salesrm') || !$this->hasTable('salesrd')) {
            return [];
        }

        $query = DB::table('salesrm as m')
            ->join('salesrd as d', 'd.slno', '=', 'm.slno')
            ->join('items as i', 'i.code', '=', 'd.code')
            ->whereBetween('m.tdate', [$date1, $date2])
            ->where('m.control', '<=', $rlevel);

        $this->applySummaryItemFilter($query, 'i', $grpcode, $includeCp);

        $rows = $query
            ->groupBy('m.tdate', 'm.slno', 'm.custname', 'm.billno')
            ->orderBy('m.tdate')
            ->orderBy('m.slno')
            ->get([
                'm.tdate',
                'm.billno',
                'm.custname as partyname',
                DB::raw("COALESCE(SUM(CASE WHEN i.code <> 'CP' THEN d.weight ELSE 0 END),0) as goldwgt"),
                DB::raw("COALESCE(SUM(CASE WHEN i.code <> 'CP' THEN d.weight - COALESCE(d.stonewgt,0) ELSE 0 END),0) as goldnet"),
                DB::raw("COALESCE(SUM(CASE WHEN i.code = 'CP' THEN d.weight ELSE 0 END),0) as cpwgt"),
            ]);

        return $rows->map(fn($r) => $this->makeSummaryRow(
            (string) $r->tdate,
            'SReturn',
            trim((string) ($r->partyname ?? '')),
            trim((string) ($r->billno ?? '')),
            (float) ($r->goldwgt ?? 0),
            0.0,
            (float) ($r->cpwgt ?? 0),
            0.0,
            (float) ($r->goldwgt ?? 0) + (float) ($r->cpwgt ?? 0),
            (float) ($r->goldnet ?? 0) + (float) ($r->cpwgt ?? 0),
            20
        ))->values()->all();
    }

    private function summarySmithRows(string $date1, string $date2, int $rlevel, string $grpcode, bool $includeCp): array
    {
        if (!$this->hasTable('smithm') || !$this->hasTable('smithd')) {
            return [];
        }

        $query = DB::table('smithm as m')
            ->join('smithd as d', 'd.slno', '=', 'm.slno')
            ->join('items as i', 'i.code', '=', 'd.code')
            ->leftJoin('clients as c', 'c.code', '=', 'm.smithcode')
            ->whereBetween('m.tdate', [$date1, $date2])
            ->where('m.control', '<=', $rlevel);

        $this->applySummaryItemFilter($query, 'i', $grpcode, $includeCp);

        $rows = $query
            ->groupBy('m.tdate', 'm.smithcode', 'c.name', 'c.ctype')
            ->orderBy('m.tdate')
            ->orderBy('m.smithcode')
            ->get([
                'm.tdate',
                'm.smithcode',
                'c.name as partyname',
                'c.ctype',
                DB::raw("COALESCE(SUM(CASE WHEN d.givrec = 'R' AND i.code <> 'CP' THEN d.weight ELSE 0 END),0) as rcvd_gold"),
                DB::raw("COALESCE(SUM(CASE WHEN d.givrec = 'R' AND i.code <> 'CP' THEN d.weight - COALESCE(d.stonewgt,0) ELSE 0 END),0) as rcvd_gold_net"),
                DB::raw("COALESCE(SUM(CASE WHEN d.givrec = 'G' AND i.code <> 'CP' THEN d.weight ELSE 0 END),0) as issued_gold"),
                DB::raw("COALESCE(SUM(CASE WHEN d.givrec = 'G' AND i.code <> 'CP' THEN d.weight - COALESCE(d.stonewgt,0) ELSE 0 END),0) as issued_gold_net"),
                DB::raw("COALESCE(SUM(CASE WHEN d.givrec = 'R' AND i.code = 'CP' THEN d.weight ELSE 0 END),0) as rcvd_cp"),
                DB::raw("COALESCE(SUM(CASE WHEN d.givrec = 'G' AND i.code = 'CP' THEN d.weight ELSE 0 END),0) as issued_cp"),
            ]);

        return $rows->map(function ($r) {
            $label = strtoupper(trim((string) ($r->ctype ?? ''))) === 'J' ? 'Jewellery' : 'GoldSmith';
            return $this->makeSummaryRow(
                (string) $r->tdate,
                $label,
                trim((string) ($r->partyname ?? $r->smithcode ?? '')),
                trim((string) ($r->smithcode ?? '')),
                (float) ($r->rcvd_gold ?? 0),
                (float) ($r->issued_gold ?? 0),
                (float) ($r->rcvd_cp ?? 0),
                (float) ($r->issued_cp ?? 0),
                (float) ($r->rcvd_gold ?? 0) + (float) ($r->rcvd_cp ?? 0) - (float) ($r->issued_gold ?? 0) - (float) ($r->issued_cp ?? 0),
                (float) ($r->rcvd_gold_net ?? 0) + (float) ($r->rcvd_cp ?? 0) - (float) ($r->issued_gold_net ?? 0) - (float) ($r->issued_cp ?? 0),
                30
            );
        })->values()->all();
    }

    private function summarySalesRows(string $date1, string $date2, int $rlevel, string $grpcode, bool $includeCp): array
    {
        if (!$this->hasTable('salesm') || !$this->hasTable('salesd')) {
            return [];
        }

        $query = DB::table('salesm as m')
            ->join('salesd as d', 'd.slno', '=', 'm.slno')
            ->join('items as i', 'i.code', '=', 'd.code')
            ->whereBetween('m.tdate', [$date1, $date2])
            ->where('m.control', '<=', $rlevel)
            ->where(function ($q) {
                $q->whereNull('d.jcode')->orWhereRaw("TRIM(COALESCE(d.jcode, '')) = ''");
            });

        $this->applySummaryItemFilter($query, 'i', $grpcode, $includeCp);

        $rows = $query
            ->groupBy('m.tdate', 'm.slno', 'm.billno')
            ->orderBy('m.tdate')
            ->orderBy('m.slno')
            ->get([
                'm.tdate',
                'm.billno',
                DB::raw("COALESCE(SUM(CASE WHEN i.code <> 'CP' THEN d.weight ELSE 0 END),0) as goldwgt"),
                DB::raw("COALESCE(SUM(CASE WHEN i.code <> 'CP' THEN d.weight - COALESCE(d.stonewgt,0) ELSE 0 END),0) as goldnet"),
                DB::raw("COALESCE(SUM(CASE WHEN i.code = 'CP' THEN d.weight ELSE 0 END),0) as cpwgt"),
            ]);

        return $rows->map(fn($r) => $this->makeSummaryRow(
            (string) $r->tdate,
            'Sales',
            '',
            trim((string) ($r->billno ?? '')),
            0.0,
            (float) ($r->goldwgt ?? 0),
            0.0,
            (float) ($r->cpwgt ?? 0),
            -((float) ($r->goldwgt ?? 0) + (float) ($r->cpwgt ?? 0)),
            -((float) ($r->goldnet ?? 0) + (float) ($r->cpwgt ?? 0)),
            40
        ))->values()->all();
    }

    private function summaryPurchaseReturnRows(string $date1, string $date2, int $rlevel, string $grpcode, bool $includeCp): array
    {
        if (!$this->hasTable('purchaserm') || !$this->hasTable('purchaserd')) {
            return [];
        }

        $query = DB::table('purchaserm as m')
            ->join('purchaserd as d', 'd.slno', '=', 'm.slno')
            ->join('items as i', 'i.code', '=', 'd.code')
            ->whereBetween('m.tdate', [$date1, $date2])
            ->where('m.control', '<=', $rlevel);

        $this->applySummaryItemFilter($query, 'i', $grpcode, $includeCp);

        $rows = $query
            ->groupBy('m.tdate', 'm.slno', 'm.billno')
            ->orderBy('m.tdate')
            ->orderBy('m.slno')
            ->get([
                'm.tdate',
                'm.billno',
                DB::raw("COALESCE(SUM(CASE WHEN i.code <> 'CP' THEN d.weight ELSE 0 END),0) as goldwgt"),
                DB::raw("COALESCE(SUM(CASE WHEN i.code <> 'CP' THEN d.weight - COALESCE(d.stwgt,0) ELSE 0 END),0) as goldnet"),
                DB::raw("COALESCE(SUM(CASE WHEN i.code = 'CP' THEN d.weight ELSE 0 END),0) as cpwgt"),
            ]);

        return $rows->map(fn($r) => $this->makeSummaryRow(
            (string) $r->tdate,
            'PReturn',
            '',
            trim((string) ($r->billno ?? '')),
            0.0,
            (float) ($r->goldwgt ?? 0),
            0.0,
            (float) ($r->cpwgt ?? 0),
            -((float) ($r->goldwgt ?? 0) + (float) ($r->cpwgt ?? 0)),
            -((float) ($r->goldnet ?? 0) + (float) ($r->cpwgt ?? 0)),
            50
        ))->values()->all();
    }

    private function summaryStockLessRows(string $date1, string $date2, int $rlevel, string $grpcode, bool $includeCp): array
    {
        if (!$this->hasTable('itemadj')) {
            return [];
        }

        $query = DB::table('itemadj as a')
            ->join('items as i', 'i.code', '=', 'a.fromcode')
            ->whereBetween('a.tdate', [$date1, $date2])
            ->where('a.control', '<=', $rlevel)
            ->where('a.tocode', 'AL');

        $this->applySummaryItemFilter($query, 'i', $grpcode, $includeCp);

        $rows = $query
            ->groupBy('a.tdate')
            ->orderBy('a.tdate')
            ->get([
                'a.tdate',
                DB::raw("COALESCE(SUM(CASE WHEN i.code <> 'CP' THEN a.fromwgt ELSE 0 END),0) as goldwgt"),
                DB::raw("COALESCE(SUM(CASE WHEN i.code <> 'CP' THEN a.fromwgt - COALESCE(a.fromstwgt,0) ELSE 0 END),0) as goldnet"),
                DB::raw("COALESCE(SUM(CASE WHEN i.code = 'CP' THEN a.fromwgt ELSE 0 END),0) as cpwgt"),
            ]);

        return $rows->map(fn($r) => $this->makeSummaryRow(
            (string) $r->tdate,
            'Stock Less',
            '',
            '',
            0.0,
            (float) ($r->goldwgt ?? 0),
            0.0,
            (float) ($r->cpwgt ?? 0),
            -((float) ($r->goldwgt ?? 0) + (float) ($r->cpwgt ?? 0)),
            -((float) ($r->goldnet ?? 0) + (float) ($r->cpwgt ?? 0)),
            60
        ))->values()->all();
    }

    private function summaryStockAddRows(string $date1, string $date2, int $rlevel, string $grpcode, bool $includeCp): array
    {
        if (!$this->hasTable('itemadj')) {
            return [];
        }

        $query = DB::table('itemadj as a')
            ->join('items as i', 'i.code', '=', 'a.tocode')
            ->whereBetween('a.tdate', [$date1, $date2])
            ->where('a.control', '<=', $rlevel)
            ->where('a.fromcode', 'AL');

        $this->applySummaryItemFilter($query, 'i', $grpcode, $includeCp);

        $rows = $query
            ->groupBy('a.tdate')
            ->orderBy('a.tdate')
            ->get([
                'a.tdate',
                DB::raw("COALESCE(SUM(CASE WHEN i.code <> 'CP' THEN a.towgt ELSE 0 END),0) as goldwgt"),
                DB::raw("COALESCE(SUM(CASE WHEN i.code <> 'CP' THEN a.towgt - COALESCE(a.tostwgt,0) ELSE 0 END),0) as goldnet"),
                DB::raw("COALESCE(SUM(CASE WHEN i.code = 'CP' THEN a.towgt ELSE 0 END),0) as cpwgt"),
            ]);

        return $rows->map(fn($r) => $this->makeSummaryRow(
            (string) $r->tdate,
            'Stock Add',
            '',
            '',
            (float) ($r->goldwgt ?? 0),
            0.0,
            (float) ($r->cpwgt ?? 0),
            0.0,
            (float) ($r->goldwgt ?? 0) + (float) ($r->cpwgt ?? 0),
            (float) ($r->goldnet ?? 0) + (float) ($r->cpwgt ?? 0),
            70
        ))->values()->all();
    }

    private function makeSummaryRow(string $tdate, string $ttype, string $partyname, string $docno, float $rcvdwgt, float $issuedwgt, float $rcvdcpwgt, float $issuedcpwgt, float $deltaGross, float $deltaNet, int $sortOrder): array
    {
        return [
            'tdate' => $tdate,
            'ttype' => $ttype,
            'partyname' => $partyname,
            'docno' => $docno,
            'rcvdwgt' => $rcvdwgt,
            'issuedwgt' => $issuedwgt,
            'rcvdcpwgt' => $rcvdcpwgt,
            'issuedcpwgt' => $issuedcpwgt,
            'delta_gross' => $deltaGross,
            'delta_net' => $deltaNet,
            'sort_order' => $sortOrder,
        ];
    }

    private function applySummaryItemFilter($query, string $itemsAlias, string $grpcode, bool $includeCp): void
    {
        $query->where(function ($q) use ($itemsAlias, $grpcode, $includeCp) {
            $q->where(function ($inner) use ($itemsAlias, $grpcode) {
                $inner->where($itemsAlias . '.itype', 'G');
                if ($grpcode !== '') {
                    $inner->where($itemsAlias . '.grpcode', $grpcode);
                }
            });

            if ($includeCp) {
                $q->orWhere($itemsAlias . '.code', 'CP');
            }
        });
    }

    private function movementBeforeDate(string $detailTable, string $masterTable, string $date1, int $rlevel, string $grpcode, bool $includeCp, bool $usesStonewgtColumn = false, bool $excludeJCode = false, ?string $givrec = null, bool $net = false): float
    {
        if (!$this->hasTable($detailTable) || !$this->hasTable($masterTable)) {
            return 0.0;
        }

        $query = DB::table($detailTable . ' as d')
            ->join($masterTable . ' as m', 'm.slno', '=', 'd.slno')
            ->join('items as i', 'i.code', '=', 'd.code')
            ->where('m.control', '<=', $rlevel)
            ->where('m.tdate', '<', $date1);

        if ($excludeJCode && Schema::hasColumn($detailTable, 'jcode')) {
            $query->where(function ($q) {
                $q->whereNull('d.jcode')->orWhereRaw("TRIM(COALESCE(d.jcode, '')) = ''");
            });
        }

        if ($givrec !== null && Schema::hasColumn($detailTable, 'givrec')) {
            $query->where('d.givrec', $givrec);
        }

        $this->applySummaryItemFilter($query, 'i', $grpcode, $includeCp);

        $stoneColumn = $this->summaryStoneColumn($detailTable, $usesStonewgtColumn);
        $expr = $net ? 'COALESCE(SUM(d.weight - COALESCE(d.' . $stoneColumn . ',0)),0) as total' : 'COALESCE(SUM(d.weight),0) as total';

        return (float) ($query->selectRaw($expr)->value('total') ?? 0);
    }

    private function summaryStoneColumn(string $detailTable, bool $preferStonewgt = false): string
    {
        if ($preferStonewgt && Schema::hasColumn($detailTable, 'stonewgt')) {
            return 'stonewgt';
        }

        if (Schema::hasColumn($detailTable, 'stwgt')) {
            return 'stwgt';
        }

        if (Schema::hasColumn($detailTable, 'stonewgt')) {
            return 'stonewgt';
        }

        return 'stonewgt';
    }

    private function refineryIssuedBeforeDate(string $date1, int $rlevel, string $grpcode, bool $includeCp, bool $net = false): float
    {
        if (!$this->hasTable('refineryd') || !$this->hasTable('refinerym')) {
            return 0.0;
        }

        $query = DB::table('refineryd as d')
            ->join('refinerym as m', 'm.slno', '=', 'd.slno')
            ->join('items as i', 'i.code', '=', 'd.code')
            ->where('m.control', '<=', $rlevel)
            ->where('m.tdate', '<', $date1);

        $this->applySummaryItemFilter($query, 'i', $grpcode, $includeCp);

        $expr = $net
            ? 'COALESCE(SUM(d.issuedwgt - COALESCE(d.issuedstwgt,0)),0) as total'
            : 'COALESCE(SUM(d.issuedwgt),0) as total';

        return (float) ($query->selectRaw($expr)->value('total') ?? 0);
    }

    private function refineryReceivedBeforeDate(string $date1, int $rlevel, string $grpcode, bool $includeCp, bool $net = false): float
    {
        if (!$this->hasTable('refineryd') || !$this->hasTable('refinerym')) {
            return 0.0;
        }

        $query = DB::table('refineryd as d')
            ->join('refinerym as m', 'm.slno', '=', 'd.slno')
            ->join('items as i', 'i.code', '=', 'd.code')
            ->where('m.control', '<=', $rlevel)
            ->where('m.tdate', '<', $date1);

        $this->applySummaryItemFilter($query, 'i', $grpcode, $includeCp);

        if ($net) {
            $expr = "COALESCE(SUM(CASE WHEN d.ao IS NULL THEN d.rcvdwgt - COALESCE(d.bottlestk,0) - COALESCE(d.testpcs,0) ELSE d.rcvdwgt END),0) + COALESCE(SUM(COALESCE(d.testpcs,0)),0) as total";
        } else {
            $expr = "COALESCE(SUM(CASE WHEN d.ao IS NULL THEN d.rcvdwgt - COALESCE(d.bottlestk,0) - COALESCE(d.testpcs,0) ELSE d.rcvdwgt END),0) + COALESCE(SUM(COALESCE(d.testpcs,0)),0) as total";
        }

        return (float) ($query->selectRaw($expr)->value('total') ?? 0);
    }

    private function orderAdvanceBeforeDate(string $date1, int $rlevel, string $grpcode, bool $includeCp, bool $net = false): float
    {
        if (!$this->hasTable('orderdga')) {
            return 0.0;
        }

        $query = DB::table('orderdga as d')
            ->join('items as i', 'i.code', '=', 'd.code')
            ->where('d.control', '<=', $rlevel)
            ->where('d.tdate', '<', $date1);

        $this->applySummaryItemFilter($query, 'i', $grpcode, $includeCp);

        $expr = $net
            ? 'COALESCE(SUM(d.weight - COALESCE(d.stonewgt,0)),0) as total'
            : 'COALESCE(SUM(d.weight),0) as total';

        return (float) ($query->selectRaw($expr)->value('total') ?? 0);
    }

    private function itemAdjustmentBeforeDate(string $date1, int $rlevel, string $grpcode, bool $includeCp, string $side, bool $net = false): float
    {
        if (!$this->hasTable('itemadj')) {
            return 0.0;
        }

        $codeCol = $side === 'from' ? 'fromcode' : 'tocode';
        $wgtCol = $side === 'from' ? 'fromwgt' : 'towgt';
        $stCol = $side === 'from' ? 'fromstwgt' : 'tostwgt';

        $query = DB::table('itemadj as a')
            ->join('items as i', 'i.code', '=', 'a.' . $codeCol)
            ->where('a.control', '<=', $rlevel)
            ->where('a.tdate', '<', $date1);

        $this->applySummaryItemFilter($query, 'i', $grpcode, $includeCp);

        $expr = $net
            ? 'COALESCE(SUM(a.' . $wgtCol . ' - COALESCE(a.' . $stCol . ',0)),0) as total'
            : 'COALESCE(SUM(a.' . $wgtCol . '),0) as total';

        return (float) ($query->selectRaw($expr)->value('total') ?? 0);
    }
}
