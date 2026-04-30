<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DaySummaryController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('reports.day-summary', [
            'title'  => 'Day Summary',
            'date1'  => date('Y-m-d'),
            'rlevel' => (int) $request->session()->get('gilevel', 1) ?: 1,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $date1  = (string) $request->query('date1', date('Y-m-d'));
        $rlevel = (int) $request->query('rlevel', (int) $request->session()->get('gilevel', 1));
        $part   = (int) $request->query('part', 1);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date1)) {
            return response()->json(['ok' => false, 'message' => 'Invalid date'], 422);
        }

        return match ($part) {
            1 => $this->part1Sales($date1, $rlevel),
            2 => $this->part2Purchase($date1, $rlevel),
            3 => $this->part3Summary($date1, $rlevel),
            4 => $this->part4Summary($date1, $rlevel),
            default => response()->json(['ok' => false, 'message' => 'Invalid part']),
        };
    }

    /* ─── Part 1: Sales Crosstab (qty/weight by item) ─── */
    private function part1Sales(string $date, int $rlevel): JsonResponse
    {
        if (!$this->hasTable('salesd') || !$this->hasTable('salesm') || !$this->hasTable('items')) {
            return response()->json(['ok' => true, 'rows' => [], 'columns' => [], 'totals' => []]);
        }

        $rows = DB::table('salesd as d')
            ->join('salesm as m', 'm.slno', '=', 'd.slno')
            ->join('items as i', 'i.code', '=', 'd.code')
            ->where('m.tdate', $date)
            ->where('m.control', '<=', $rlevel)
            ->select('i.name as itemname', 'i.itype')
            ->selectRaw('SUM(d.qty) as qty')
            ->selectRaw('SUM(d.weight) as weight')
            ->groupBy('i.name', 'i.itype')
            ->orderBy('i.name')
            ->get();

        // Build pivot: each item becomes a column pair (qty, weight)
        $itemNames = $rows->pluck('itemname')->unique()->values()->all();

        // Also build a flat table for display
        $tableRows = [];
        $totQty = 0;
        $totWeight = 0;
        $totGoldWgt = 0;
        $totSilverWgt = 0;
        $totOtherWgt = 0;

        foreach ($rows as $r) {
            $qty = (int) $r->qty;
            $wgt = (float) $r->weight;
            $tableRows[] = [
                'itemname' => trim($r->itemname),
                'itype'    => strtoupper(trim($r->itype ?? '')),
                'qty'      => $qty,
                'weight'   => round($wgt, 3),
            ];
            $totQty += $qty;
            $totWeight += $wgt;
            $itype = strtoupper(trim($r->itype ?? ''));
            if ($itype === 'G') $totGoldWgt += $wgt;
            elseif ($itype === 'S') $totSilverWgt += $wgt;
            else $totOtherWgt += $wgt;
        }

        return response()->json([
            'ok'      => true,
            'rows'    => $tableRows,
            'totals'  => [
                'count'     => count($tableRows),
                'qty'       => $totQty,
                'weight'    => round($totWeight, 3),
                'goldwgt'   => round($totGoldWgt, 3),
                'silverwgt' => round($totSilverWgt, 3),
                'otherwgt'  => round($totOtherWgt, 3),
            ],
        ]);
    }

    /* ─── Part 2: Purchase Crosstab (qty/weight by item) ─── */
    private function part2Purchase(string $date, int $rlevel): JsonResponse
    {
        if (!$this->hasTable('purchased') || !$this->hasTable('purchasem') || !$this->hasTable('items')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => []]);
        }

        $rows = DB::table('purchased as d')
            ->join('purchasem as m', 'm.slno', '=', 'd.slno')
            ->join('items as i', 'i.code', '=', 'd.code')
            ->where('m.tdate', $date)
            ->where('m.control', '<=', $rlevel)
            ->select('i.name as itemname', 'i.itype')
            ->selectRaw('SUM(d.qty) as qty')
            ->selectRaw('SUM(d.weight) as weight')
            ->groupBy('i.name', 'i.itype')
            ->orderBy('i.name')
            ->get();

        $tableRows = [];
        $totQty = 0;
        $totWeight = 0;
        $totGoldWgt = 0;
        $totSilverWgt = 0;
        $totOtherWgt = 0;

        foreach ($rows as $r) {
            $qty = (int) $r->qty;
            $wgt = (float) $r->weight;
            $tableRows[] = [
                'itemname' => trim($r->itemname),
                'itype'    => strtoupper(trim($r->itype ?? '')),
                'qty'      => $qty,
                'weight'   => round($wgt, 3),
            ];
            $totQty += $qty;
            $totWeight += $wgt;
            $itype = strtoupper(trim($r->itype ?? ''));
            if ($itype === 'G') $totGoldWgt += $wgt;
            elseif ($itype === 'S') $totSilverWgt += $wgt;
            else $totOtherWgt += $wgt;
        }

        return response()->json([
            'ok'      => true,
            'rows'    => $tableRows,
            'totals'  => [
                'count'     => count($tableRows),
                'qty'       => $totQty,
                'weight'    => round($totWeight, 3),
                'goldwgt'   => round($totGoldWgt, 3),
                'silverwgt' => round($totSilverWgt, 3),
                'otherwgt'  => round($totOtherWgt, 3),
            ],
        ]);
    }

    /* ─── Part 3: Financial Summary (Sales, Purchase, Receipts, etc.) ─── */
    private function part3Summary(string $date, int $rlevel): JsonResponse
    {
        $panels = [];

        // ── Sale Amount ──
        if ($this->hasTable('salesm')) {
            $sales = DB::table('salesm')
                ->where('tdate', $date)
                ->where('control', '<=', $rlevel)
                ->selectRaw('COUNT(*) as cnt')
                ->selectRaw('SUM(billamt) as billamt')
                ->selectRaw('SUM(discount) as discount')
                ->selectRaw('SUM(eamt) as eamt')
                ->selectRaw('SUM(ramt) as ramt')
                ->first();

            $panels[] = [
                'title' => 'Sale Amount',
                'icon'  => 'fa-receipt',
                'color' => '#3b82f6',
                'items' => [
                    ['label' => 'Bills', 'value' => (int) ($sales->cnt ?? 0), 'fmt' => 0],
                    ['label' => 'Bill Amount', 'value' => (float) ($sales->billamt ?? 0), 'fmt' => 2],
                    ['label' => 'Received Amt', 'value' => (float) ($sales->ramt ?? 0), 'fmt' => 2],
                ],
            ];

            // ── Sale Discount ──
            $panels[] = [
                'title' => 'Sale Discount',
                'icon'  => 'fa-percent',
                'color' => '#f59e0b',
                'items' => [
                    ['label' => 'Total Discount', 'value' => (float) ($sales->discount ?? 0), 'fmt' => 2],
                ],
            ];

            // ── Old Gold (Exchange) ──
            $panels[] = [
                'title' => 'Old Gold (Exchange)',
                'icon'  => 'fa-exchange-alt',
                'color' => '#8b5cf6',
                'items' => [
                    ['label' => 'Exchange Amount', 'value' => (float) ($sales->eamt ?? 0), 'fmt' => 2],
                ],
            ];
        }

        // ── Purchase Amount ──
        if ($this->hasTable('purchasem')) {
            $purch = DB::table('purchasem')
                ->where('tdate', $date)
                ->where('control', '<=', $rlevel)
                ->selectRaw('COUNT(*) as cnt')
                ->selectRaw('SUM(billamt) as billamt')
                ->selectRaw('SUM(pamt) as pamt')
                ->selectRaw('SUM(eamt) as eamt')
                ->first();

            $panels[] = [
                'title' => 'Purchase Amount',
                'icon'  => 'fa-shopping-cart',
                'color' => '#ef4444',
                'items' => [
                    ['label' => 'Bills', 'value' => (int) ($purch->cnt ?? 0), 'fmt' => 0],
                    ['label' => 'Bill Amount', 'value' => (float) ($purch->billamt ?? 0), 'fmt' => 2],
                    ['label' => 'Paid Amount', 'value' => (float) ($purch->pamt ?? 0), 'fmt' => 2],
                    ['label' => 'Exchange Amt', 'value' => (float) ($purch->eamt ?? 0), 'fmt' => 2],
                ],
            ];
        }

        // ── Credit A/c (Sales Return) ──
        if ($this->hasTable('salesrm')) {
            $sret = DB::table('salesrm')
                ->where('tdate', $date)
                ->where('control', '<=', $rlevel)
                ->selectRaw('COUNT(*) as cnt')
                ->selectRaw('SUM(billamt) as billamt')
                ->selectRaw('SUM(pamt) as pamt')
                ->selectRaw('SUM(eamt) as eamt')
                ->first();

            $panels[] = [
                'title' => 'Credit A/c (Sales Return)',
                'icon'  => 'fa-undo',
                'color' => '#6366f1',
                'items' => [
                    ['label' => 'Bills', 'value' => (int) ($sret->cnt ?? 0), 'fmt' => 0],
                    ['label' => 'Bill Amount', 'value' => (float) ($sret->billamt ?? 0), 'fmt' => 2],
                    ['label' => 'Paid Amount', 'value' => (float) ($sret->pamt ?? 0), 'fmt' => 2],
                    ['label' => 'Exchange Amt', 'value' => (float) ($sret->eamt ?? 0), 'fmt' => 2],
                ],
            ];
        }

        // ── Order Advance ──
        if ($this->hasTable('orderm')) {
            $ord = DB::table('orderm')
                ->where('tdate', $date)
                ->where('control', '<=', $rlevel)
                ->selectRaw('COUNT(*) as cnt')
                ->selectRaw('SUM(advance) as advance')
                ->selectRaw('SUM(billamt) as billamt')
                ->selectRaw('SUM(eamt) as eamt')
                ->first();

            $panels[] = [
                'title' => 'Order / Advance',
                'icon'  => 'fa-file-invoice-dollar',
                'color' => '#0ea5e9',
                'items' => [
                    ['label' => 'Orders', 'value' => (int) ($ord->cnt ?? 0), 'fmt' => 0],
                    ['label' => 'Order Amount', 'value' => (float) ($ord->billamt ?? 0), 'fmt' => 2],
                    ['label' => 'Advance', 'value' => (float) ($ord->advance ?? 0), 'fmt' => 2],
                    ['label' => 'Exchange Amt', 'value' => (float) ($ord->eamt ?? 0), 'fmt' => 2],
                ],
            ];
        }

        // ── Cash Receipt (Daybook) ──
        if ($this->hasTable('daybook')) {
            $db = DB::table('daybook')
                ->where('tdate', $date)
                ->where('control', '<=', $rlevel)
                ->selectRaw('COUNT(*) as cnt')
                ->selectRaw('SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as receipts')
                ->selectRaw('SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as payments')
                ->first();

            $panels[] = [
                'title' => 'Cash Receipt / Payment',
                'icon'  => 'fa-money-bill-wave',
                'color' => '#10b981',
                'items' => [
                    ['label' => 'Entries', 'value' => (int) ($db->cnt ?? 0), 'fmt' => 0],
                    ['label' => 'Receipts', 'value' => (float) ($db->receipts ?? 0), 'fmt' => 2],
                    ['label' => 'Payments', 'value' => (float) ($db->payments ?? 0), 'fmt' => 2],
                ],
            ];
        }

        // ── Silver Old Weight (from sales exchange - silver items) ──
        if ($this->hasTable('salesd') && $this->hasTable('salesm') && $this->hasTable('items')) {
            $silv = DB::table('salesd as d')
                ->join('salesm as m', 'm.slno', '=', 'd.slno')
                ->join('items as i', 'i.code', '=', 'd.code')
                ->where('m.tdate', $date)
                ->where('m.control', '<=', $rlevel)
                ->where('i.itype', 'S')
                ->selectRaw('SUM(d.weight) as weight')
                ->selectRaw('SUM(d.qty) as qty')
                ->first();

            $panels[] = [
                'title' => 'Silver Sales Weight',
                'icon'  => 'fa-coins',
                'color' => '#94a3b8',
                'items' => [
                    ['label' => 'Qty', 'value' => (int) ($silv->qty ?? 0), 'fmt' => 0],
                    ['label' => 'Weight', 'value' => (float) ($silv->weight ?? 0), 'fmt' => 3],
                ],
            ];
        }

        return response()->json(['ok' => true, 'panels' => $panels]);
    }

    /* ─── Part 4: Operations Summary (Smith, Order Exchange, Expenses) ─── */
    private function part4Summary(string $date, int $rlevel): JsonResponse
    {
        $panels = [];

        // ── Smith Receipt (givrec = 'R') ──
        if ($this->hasTable('smithm') && $this->hasTable('smithd')) {
            $rcpt = DB::table('smithd as d')
                ->join('smithm as m', 'm.slno', '=', 'd.slno')
                ->join('items as i', 'i.code', '=', 'd.code')
                ->where('m.tdate', $date)
                ->where('m.control', '<=', $rlevel)
                ->where('d.givrec', 'R')
                ->selectRaw('COUNT(*) as cnt')
                ->selectRaw('SUM(d.qty) as qty')
                ->selectRaw('SUM(d.weight) as weight')
                ->selectRaw('SUM(d.mcharge) as mcharge')
                ->selectRaw("SUM(CASE WHEN i.itype='G' THEN d.weight ELSE 0 END) as goldwgt")
                ->selectRaw("SUM(CASE WHEN i.itype='S' THEN d.weight ELSE 0 END) as silverwgt")
                ->first();

            $panels[] = [
                'title' => 'Smith Receipt',
                'icon'  => 'fa-hammer',
                'color' => '#d97706',
                'items' => [
                    ['label' => 'Items', 'value' => (int) ($rcpt->cnt ?? 0), 'fmt' => 0],
                    ['label' => 'Qty', 'value' => (int) ($rcpt->qty ?? 0), 'fmt' => 0],
                    ['label' => 'Gold Wgt', 'value' => (float) ($rcpt->goldwgt ?? 0), 'fmt' => 3],
                    ['label' => 'Silver Wgt', 'value' => (float) ($rcpt->silverwgt ?? 0), 'fmt' => 3],
                    ['label' => 'MC', 'value' => (float) ($rcpt->mcharge ?? 0), 'fmt' => 2],
                ],
            ];

            // ── Smith Issued (givrec = 'G') ──
            $issu = DB::table('smithd as d')
                ->join('smithm as m', 'm.slno', '=', 'd.slno')
                ->join('items as i', 'i.code', '=', 'd.code')
                ->where('m.tdate', $date)
                ->where('m.control', '<=', $rlevel)
                ->where('d.givrec', 'G')
                ->selectRaw('COUNT(*) as cnt')
                ->selectRaw('SUM(d.qty) as qty')
                ->selectRaw('SUM(d.weight) as weight')
                ->selectRaw("SUM(CASE WHEN i.itype='G' THEN d.weight ELSE 0 END) as goldwgt")
                ->selectRaw("SUM(CASE WHEN i.itype='S' THEN d.weight ELSE 0 END) as silverwgt")
                ->first();

            $panels[] = [
                'title' => 'Smith Issued',
                'icon'  => 'fa-hand-holding',
                'color' => '#b45309',
                'items' => [
                    ['label' => 'Items', 'value' => (int) ($issu->cnt ?? 0), 'fmt' => 0],
                    ['label' => 'Qty', 'value' => (int) ($issu->qty ?? 0), 'fmt' => 0],
                    ['label' => 'Gold Wgt', 'value' => (float) ($issu->goldwgt ?? 0), 'fmt' => 3],
                    ['label' => 'Silver Wgt', 'value' => (float) ($issu->silverwgt ?? 0), 'fmt' => 3],
                ],
            ];
        }

        // ── Order Advance (orders placed on this date) ──
        if ($this->hasTable('orderm')) {
            $ord = DB::table('orderm')
                ->where('tdate', $date)
                ->where('control', '<=', $rlevel)
                ->selectRaw('COUNT(*) as cnt')
                ->selectRaw('SUM(advance) as advance')
                ->selectRaw('SUM(eamt) as eamt')
                ->first();

            $panels[] = [
                'title' => 'Order Advance',
                'icon'  => 'fa-money-check',
                'color' => '#0284c7',
                'items' => [
                    ['label' => 'Orders', 'value' => (int) ($ord->cnt ?? 0), 'fmt' => 0],
                    ['label' => 'Advance', 'value' => (float) ($ord->advance ?? 0), 'fmt' => 2],
                    ['label' => 'Exchange', 'value' => (float) ($ord->eamt ?? 0), 'fmt' => 2],
                ],
            ];
        }

        // ── Order Exchange (from orderd) ──
        if ($this->hasTable('orderd') && $this->hasTable('orderm')) {
            $oex = DB::table('orderd as d')
                ->join('orderm as m', 'm.slno', '=', 'd.slno')
                ->where('m.tdate', $date)
                ->where('m.control', '<=', $rlevel)
                ->selectRaw('SUM(d.qty) as qty')
                ->selectRaw('SUM(d.weight) as weight')
                ->selectRaw('SUM(d.amount) as amount')
                ->first();

            $panels[] = [
                'title' => 'Order Items',
                'icon'  => 'fa-box-open',
                'color' => '#7c3aed',
                'items' => [
                    ['label' => 'Qty', 'value' => (int) ($oex->qty ?? 0), 'fmt' => 0],
                    ['label' => 'Weight', 'value' => (float) ($oex->weight ?? 0), 'fmt' => 3],
                    ['label' => 'Amount', 'value' => (float) ($oex->amount ?? 0), 'fmt' => 2],
                ],
            ];
        }

        // ── Purchase Return ──
        if ($this->hasTable('purchaserm')) {
            $pret = DB::table('purchaserm')
                ->where('tdate', $date)
                ->where('control', '<=', $rlevel)
                ->selectRaw('COUNT(*) as cnt')
                ->selectRaw('SUM(billamt) as billamt')
                ->first();

            $panels[] = [
                'title' => 'Purchase Return',
                'icon'  => 'fa-rotate-left',
                'color' => '#dc2626',
                'items' => [
                    ['label' => 'Bills', 'value' => (int) ($pret->cnt ?? 0), 'fmt' => 0],
                    ['label' => 'Amount', 'value' => (float) ($pret->billamt ?? 0), 'fmt' => 2],
                ],
            ];
        }

        // ── Cash Summary ──
        // Compile cash in / cash out from all transactions
        $cashIn = 0;
        $cashOut = 0;

        // Sales received
        if ($this->hasTable('salesm')) {
            $s = DB::table('salesm')->where('tdate', $date)->where('control', '<=', $rlevel)
                ->selectRaw('SUM(ramt) as ramt')->first();
            $cashIn += (float) ($s->ramt ?? 0);
        }

        // Purchase paid
        if ($this->hasTable('purchasem')) {
            $p = DB::table('purchasem')->where('tdate', $date)->where('control', '<=', $rlevel)
                ->selectRaw('SUM(pamt) as pamt')->first();
            $cashOut += (float) ($p->pamt ?? 0);
        }

        // Sales return paid
        if ($this->hasTable('salesrm')) {
            $sr = DB::table('salesrm')->where('tdate', $date)->where('control', '<=', $rlevel)
                ->selectRaw('SUM(pamt) as pamt')->first();
            $cashOut += (float) ($sr->pamt ?? 0);
        }

        // Order advance
        if ($this->hasTable('orderm')) {
            $oa = DB::table('orderm')->where('tdate', $date)->where('control', '<=', $rlevel)
                ->selectRaw('SUM(advance) as advance')->first();
            $cashIn += (float) ($oa->advance ?? 0);
        }

        // Daybook
        if ($this->hasTable('daybook')) {
            $db = DB::table('daybook')->where('tdate', $date)->where('control', '<=', $rlevel)
                ->selectRaw('SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as rcpt')
                ->selectRaw('SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as pmnt')
                ->first();
            $cashIn += (float) ($db->rcpt ?? 0);
            $cashOut += (float) ($db->pmnt ?? 0);
        }

        // Smith MC
        if ($this->hasTable('smithm')) {
            $sm = DB::table('smithm')->where('tdate', $date)->where('control', '<=', $rlevel)
                ->selectRaw('SUM(pamt) as pamt')->first();
            $cashOut += (float) ($sm->pamt ?? 0);
        }

        $panels[] = [
            'title' => 'Cash Summary',
            'icon'  => 'fa-wallet',
            'color' => '#059669',
            'items' => [
                ['label' => 'Cash In', 'value' => round($cashIn, 2), 'fmt' => 2],
                ['label' => 'Cash Out', 'value' => round($cashOut, 2), 'fmt' => 2],
                ['label' => 'Net Cash', 'value' => round($cashIn - $cashOut, 2), 'fmt' => 2],
            ],
        ];

        return response()->json(['ok' => true, 'panels' => $panels]);
    }
}
