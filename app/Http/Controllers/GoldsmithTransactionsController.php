<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GoldsmithTransactionsController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $ctype = strtoupper(trim((string) $request->query('ctype', '')));
        $title = match ($ctype) {
            'J' => 'Jewellery Ledger',
            'C' => 'Party Deposit Ledger',
            default => 'Goldsmith Ledger',
        };

        return view('reports.goldsmith-transactions', [
            'title'  => $title,
            'date1'  => date('Y-m-d'),
            'date2'  => date('Y-m-d'),
            'rlevel' => (int) $request->session()->get('gilevel', 1) ?: 1,
            'ctype'  => $ctype,
        ]);
    }

    public function lookups(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $ctype = strtoupper(trim((string) $request->query('ctype', '')));

        $smiths = [];
        if ($this->hasTable('clients')) {
            $validTypes = ['G', 'J', 'C', 'S'];
            $q = DB::table('clients')
                ->select('clients.code', 'clients.name', 'clients.ctype')
                ->orderBy('clients.name');
            if (in_array($ctype, $validTypes)) {
                $q->where('clients.ctype', $ctype);
            } else {
                $q->whereIn('clients.ctype', $validTypes);
            }
            // Also filter by clientsgs if table exists (goldsmith-linked clients)
            if ($this->hasTable('clientsgs') && in_array($ctype, ['G', 'J', 'C'])) {
                $q->join('clientsgs', 'clientsgs.code', '=', 'clients.code');
            }
            $smiths = $q->get()
                ->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name), 'ctype' => trim($r->ctype)])
                ->values()->all();
        }

        $items = [];
        if ($this->hasTable('items')) {
            $items = DB::table('items')
                ->select('code', 'name', 'itype')
                ->orderBy('name')
                ->get()
                ->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name), 'itype' => trim($r->itype)])
                ->values()->all();
        }

        return response()->json([
            'ok'     => true,
            'smiths' => $smiths,
            'items'  => $items,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $date1      = (string) $request->query('date1', date('Y-m-d'));
        $date2      = (string) $request->query('date2', date('Y-m-d'));
        $rlevel     = (int) $request->query('rlevel', (int) $request->session()->get('gilevel', 1));
        $ctype      = strtoupper(trim((string) $request->query('ctype', '')));
        $smithcode  = trim((string) $request->query('smithcode', ''));
        $itemcode   = trim((string) $request->query('itemcode', ''));
        $lotno      = trim((string) $request->query('lotno', ''));
        $smithCodes = [];

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date1) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date2)) {
            return response()->json(['ok' => false, 'message' => 'Invalid date'], 422);
        }

        // ── Get smith info and opening balances ──
        $smithName = $smithcode === '' ? 'All' : '';
        $smithCtype = $ctype;
        $opBalAmt = 0;
        $opBalWgt = 0;

        if ($this->hasTable('clients') && $this->hasTable('clientsgs')) {
            $wgtCol = $rlevel === 1 ? 'clientsgs.opweight' : 'clientsgs.opweightb';
            $amtCol = $rlevel === 1 ? 'clients.opbalance' : 'clients.opbalanceb';

            $query = DB::table('clients')
                ->join('clientsgs', 'clientsgs.code', '=', 'clients.code');

            if ($smithcode !== '') {
                $info = (clone $query)
                    ->where('clients.code', $smithcode)
                    ->selectRaw("clients.name, clients.ctype, {$amtCol} as opamt, {$wgtCol} as opwgt")
                    ->first();

                if ($info) {
                    $smithName = trim($info->name ?? '');
                    $smithCtype = trim($info->ctype ?? '');
                    $opBalAmt = (float) ($info->opamt ?? 0);
                    $opBalWgt = (float) ($info->opwgt ?? 0);
                    $smithCodes = [$smithcode];
                }
            } else {
                if (in_array($ctype, ['G', 'J', 'C'], true)) {
                    $query->where('clients.ctype', $ctype);
                } else {
                    $query->whereIn('clients.ctype', ['G', 'J', 'C']);
                }

                $smithCodes = (clone $query)
                    ->pluck('clients.code')
                    ->map(fn ($code) => trim((string) $code))
                    ->filter()
                    ->values()
                    ->all();

                $info = (clone $query)
                    ->selectRaw("SUM({$amtCol}) as opamt, SUM({$wgtCol}) as opwgt")
                    ->first();

                $opBalAmt = (float) ($info->opamt ?? 0);
                $opBalWgt = (float) ($info->opwgt ?? 0);
            }
        }

        if ($smithCodes === [] && $smithcode !== '') {
            $smithCodes = [$smithcode];
        }

        // Daybook amount before date1 → add to opening amt
        $dbAmtBefore = 0;
        if ($this->hasTable('daybook') && $smithCodes !== []) {
            $r = DB::table('daybook')
                ->whereIn('accode', $smithCodes)
                ->where('tdate', '<', $date1)
                ->where('control', '<=', $rlevel)
                ->selectRaw('SUM(amount) as total')
                ->first();
            $dbAmtBefore = (float) ($r->total ?? 0);
        }

        // Daybook amount up to date2 → for closing amount balance
        $dbAmtUpto = 0;
        if ($this->hasTable('daybook') && $smithCodes !== []) {
            $r = DB::table('daybook')
                ->whereIn('accode', $smithCodes)
                ->where('tdate', '<=', $date2)
                ->where('control', '<=', $rlevel)
                ->selectRaw('SUM(amount) as total')
                ->first();
            $dbAmtUpto = (float) ($r->total ?? 0);
        }

        // Issued netwgt before date1 (givrec='G')
        $issBeforeWgt = 0;
        if ($this->hasTable('smithd') && $this->hasTable('smithm') && $smithCodes !== []) {
            $r = DB::table('smithd')
                ->join('smithm', 'smithm.slno', '=', 'smithd.slno')
                ->whereIn('smithm.smithcode', $smithCodes)
                ->where('smithm.tdate', '<', $date1)
                ->where('smithd.givrec', 'G')
                ->where('smithm.control', '<=', $rlevel)
                ->selectRaw('SUM(smithd.netwgt) as total')
                ->first();
            $issBeforeWgt = (float) ($r->total ?? 0);
        }

        // Received netwgt before date1 (givrec='R')
        $rcvBeforeWgt = 0;
        if ($this->hasTable('smithd') && $this->hasTable('smithm') && $smithCodes !== []) {
            $r = DB::table('smithd')
                ->join('smithm', 'smithm.slno', '=', 'smithd.slno')
                ->whereIn('smithm.smithcode', $smithCodes)
                ->where('smithm.tdate', '<', $date1)
                ->where('smithd.givrec', 'R')
                ->where('smithm.control', '<=', $rlevel)
                ->selectRaw('SUM(smithd.netwgt) as total')
                ->first();
            $rcvBeforeWgt = (float) ($r->total ?? 0);
        }

        // Paid amount in period (from smithm.pamt)
        $paidAmt = 0;
        if ($this->hasTable('smithm') && $smithCodes !== []) {
            $r = DB::table('smithm')
                ->whereIn('smithcode', $smithCodes)
                ->whereBetween('tdate', [$date1, $date2])
                ->where('control', '<=', $rlevel)
                ->selectRaw('SUM(pamt) as total')
                ->first();
            $paidAmt = (float) ($r->total ?? 0);
        }

        // Additional paid from daybook+daybookpart with vchno
        $paidAmt2 = 0;
        if ($this->hasTable('daybook') && $this->hasTable('daybookpart') && $smithCodes !== []) {
            $r = DB::table('daybook')
                ->join('daybookpart', 'daybookpart.slno', '=', 'daybook.slno')
                ->whereIn('daybook.accode', $smithCodes)
                ->whereBetween('daybook.tdate', [$date1, $date2])
                ->where('daybook.control', '<=', $rlevel)
                ->whereRaw("TRIM(daybookpart.vchno) <> ''")
                ->selectRaw('SUM(daybook.amount) as total')
                ->first();
            $paidAmt2 = (float) ($r->total ?? 0);
        }

        // Calculate opening balances
        $opAmtCalc = $opBalAmt + $dbAmtBefore;
        $opWgtCalc = round($opBalWgt - $issBeforeWgt + $rcvBeforeWgt, 3);
        $clAmtCalc = round($dbAmtUpto + $opBalAmt, 2); // daybook total + original opening
        $totalPaid = $paidAmt + (-$paidAmt2);

        // ── Fetch detail rows ──
        $q = DB::table('smithd as d')
            ->join('smithm as m', 'm.slno', '=', 'd.slno')
            ->join('items as i', 'i.code', '=', 'd.code')
            ->whereBetween('m.tdate', [$date1, $date2])
            ->where('m.control', '<=', $rlevel)
            ->select(
                'm.slno', 'm.docno', 'm.tdate', 'm.ttime', 'm.pamt',
                'm.acidcharge', 'm.discount', 'm.lotno', 'm.rate as mrate',
                'd.code as itemcode', 'd.name as dname', 'i.name as itemname',
                'd.sno', 'd.qty', 'd.weight', 'd.stonewgt', 'd.wastage', 'd.givrec',
                'd.mcharge', 'd.touch', 'd.touchwgt', 'd.netwgt',
                'd.touchnote', 'd.stktype', 'd.hmc', 'd.stoneprice',
                'i.itype', 'i.ornament'
            );

        if ($smithCodes !== []) {
            $q->whereIn('m.smithcode', $smithCodes);
        } elseif ($smithcode !== '') {
            $q->where('m.smithcode', $smithcode);
        }

        if ($itemcode !== '') {
            $q->where('d.code', $itemcode);
        }
        if ($lotno !== '') {
            $q->where('m.lotno', $lotno);
        }

        $q->orderBy('m.tdate')->orderBy('m.slno')->orderBy('d.sno');

        $results = $q->get();

        // Group by slno (transaction)
        $transactions = [];
        $seenSlno = [];

        // Totals
        $totIssuedWgt = 0; $totRcvdWgt = 0;
        $totIssuedWgt2 = 0; $totRcvdWgt2 = 0; // gross weight
        $totMcharge = 0; $totStPrice = 0; $totHmc = 0;
        $totAcid = 0; $totDisc = 0;

        foreach ($results as $r) {
            $slno = $r->slno;
            $givrec = strtoupper(trim($r->givrec ?? ''));
            $weight = round((float) ($r->weight ?? 0), 3);
            $netwgt = round((float) ($r->netwgt ?? 0), 3);
            $mcharge = round((float) ($r->mcharge ?? 0), 2);
            $stprice = round((float) ($r->stoneprice ?? 0), 2);
            $hmc = round((float) ($r->hmc ?? 0), 2);

            if (!isset($transactions[$slno])) {
                $transactions[$slno] = [
                    'slno'   => $slno,
                    'docno'  => trim($r->docno ?? ''),
                    'tdate'  => $r->tdate,
                    'ttime'  => trim($r->ttime ?? ''),
                    'pamt'   => round((float) ($r->pamt ?? 0), 2),
                    'acid'   => round((float) ($r->acidcharge ?? 0), 2),
                    'disc'   => round((float) ($r->discount ?? 0), 2),
                    'items'  => [],
                ];
            }

            $transactions[$slno]['items'][] = [
                'itemname'   => trim($r->itemname ?? $r->dname ?? ''),
                'givrec'     => $givrec,
                'qty'        => (int) ($r->qty ?? 0),
                'weight'     => $weight,
                'issuedqty'  => $givrec === 'G' ? (int) ($r->qty ?? 0) : 0,
                'issuedwgt'  => $givrec === 'G' ? $weight : 0,
                'rcvdqty'    => $givrec === 'R' ? (int) ($r->qty ?? 0) : 0,
                'rcvdwgt'    => $givrec === 'R' ? $weight : 0,
                'stonewgt'   => round((float) ($r->stonewgt ?? 0), 3),
                'wastage'    => round((float) ($r->wastage ?? 0), 3),
                'touch'      => round((float) ($r->touch ?? 0), 2),
                'touchwgt'   => round((float) ($r->touchwgt ?? 0), 3),
                'netwgt'     => $netwgt,
                'mcharge'    => $mcharge,
                'stoneprice' => $stprice,
                'hmc'        => $hmc,
                'touchnote'  => trim($r->touchnote ?? ''),
            ];

            // Accumulate totals
            if ($givrec === 'G') {
                $totIssuedWgt += $netwgt;
                $totIssuedWgt2 += $weight;
            } else {
                $totRcvdWgt += $netwgt;
                $totRcvdWgt2 += $weight;
            }
            $totMcharge += $mcharge;
            $totStPrice += $stprice;
            $totHmc += $hmc;

            // Master-level (once per slno)
            if (!isset($seenSlno[$slno])) {
                $seenSlno[$slno] = true;
                $totAcid += round((float) ($r->acidcharge ?? 0), 2);
                $totDisc += round((float) ($r->discount ?? 0), 2);
            }
        }

        // ── Weight Summary ──
        $wsOpBal = round($opWgtCalc, 3);
        $wsRcvd  = round($totRcvdWgt, 3);
        $wsTotal = round($wsOpBal + $wsRcvd, 3);
        $wsIssued = round($totIssuedWgt, 3);
        $wsClose = round($wsTotal - $wsIssued, 3);

        // ── Amount Summary ──
        $asOpBal   = round($opAmtCalc, 2);
        $asMaking  = round($totMcharge + $totStPrice, 2);
        $asHmc     = round($totHmc, 2);
        $asAcid    = round($totAcid, 2);
        $asDisc    = round($totDisc, 2);
        $asTotal   = round($asOpBal + $asMaking + $asHmc - $asAcid + $asDisc, 2);
        $asPaid    = round(abs($totalPaid), 2);
        $asClose   = round($clAmtCalc, 2);

        // Suspense items
        $suspense = [];
        if ($this->hasTable('smithsusp') && $smithCodes !== []) {
            $suspense = DB::table('smithsusp')
                ->whereIn('smithcode', $smithCodes)
                ->select('tdate', 'iname', 'qty', 'weight', 'part')
                ->get()
                ->map(fn($r) => [
                    'tdate'  => $r->tdate,
                    'iname'  => trim($r->iname ?? ''),
                    'qty'    => (int) ($r->qty ?? 0),
                    'weight' => round((float) ($r->weight ?? 0), 3),
                    'part'   => trim($r->part ?? ''),
                ])->values()->all();
        }

        return response()->json([
            'ok'         => true,
            'smithcode'  => $smithcode,
            'smithname'  => $smithName,
            'smithctype' => $smithCtype,
            'transactions' => array_values($transactions),
            'totals'     => [
                'issuedwgt'  => round($totIssuedWgt, 3),
                'rcvdwgt'    => round($totRcvdWgt, 3),
                'issuedwgt2' => round($totIssuedWgt2, 3),
                'rcvdwgt2'   => round($totRcvdWgt2, 3),
                'mcharge'    => round($totMcharge, 2),
                'stprice'    => round($totStPrice, 2),
                'hmc'        => round($totHmc, 2),
                'acid'       => round($totAcid, 2),
                'disc'       => round($totDisc, 2),
                'paidamt'    => round($totalPaid, 2),
                'count'      => count($results),
            ],
            'weight_summary' => [
                'opbal'   => $wsOpBal,
                'rcvd'    => $wsRcvd,
                'total'   => $wsTotal,
                'issued'  => $wsIssued,
                'close'   => $wsClose,
            ],
            'amount_summary' => [
                'opbal'   => $asOpBal,
                'making'  => $asMaking,
                'hmc'     => $asHmc,
                'acid'    => $asAcid,
                'disc'    => $asDisc,
                'total'   => $asTotal,
                'paid'    => $asPaid,
                'close'   => $asClose,
            ],
            'suspense' => $suspense,
        ]);
    }
}
