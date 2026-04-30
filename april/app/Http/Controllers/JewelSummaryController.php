<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jewellery Sales/Purchase Item Report (PB: w_jewelsummary / d_jewlsummary)
 *
 * Per-jeweller report showing sales items, sales returns, and purchase items
 * in a date range. Also shows closing balance from daybook.
 * Nested PB reports: d_jewlsummary_sale, d_jewlsummary_salercvd,
 *                    d_jewlsummary_salereturn, d_jewlsummary_purch
 */
class JewelSummaryController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $startDate = (string) $request->session()->get('gdtstartdate', '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            $startDate = date('Y') . '-04-01';
        }

        return view('reports.jewel-summary', [
            'title'  => 'Sales/Purchase Item Report',
            'date1'  => $startDate,
            'date2'  => date('Y-m-d'),
            'rlevel' => (int) ($request->session()->get('gilevel', 1) ?: 1),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $date1  = (string) $request->query('date1', date('Y-m-d'));
        $date2  = (string) $request->query('date2', date('Y-m-d'));
        $code   = trim((string) $request->query('code', ''));
        $rlevel = (int) $request->query('rlevel', (int) ($request->session()->get('gilevel', 1)));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date1) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date2)) {
            return response()->json(['ok' => false, 'message' => 'Invalid date'], 422);
        }
        if ($code === '') {
            return response()->json(['ok' => false, 'message' => 'Please enter a Jewellery code'], 422);
        }

        // Get jeweller name and closing balance (PB cb_show logic)
        $jewlName = '';
        $clBalance = 0;

        if ($this->hasTable('accountm')) {
            $opCol = $rlevel >= 2 ? 'opbalb' : 'opbal';
            $cols = array_map('strtolower', $this->columnList('accountm'));
            if (in_array($opCol, $cols)) {
                $acct = DB::table('accountm')
                    ->whereRaw('TRIM(accode) = ?', [$code])
                    ->select('name', DB::raw("COALESCE({$opCol}, 0) as opbal"))
                    ->first();
            } else {
                $acct = DB::table('accountm')
                    ->whereRaw('TRIM(accode) = ?', [$code])
                    ->select('name', DB::raw('COALESCE(opbal, 0) as opbal'))
                    ->first();
            }

            if ($acct) {
                $jewlName = trim($acct->name ?? '');
                $opBal = (float) $acct->opbal;

                // Closing balance = opbal + sum(daybook.amount) up to date2
                $tranAmt = 0;
                if ($this->hasTable('daybook')) {
                    $tranAmt = (float) DB::table('daybook')
                        ->whereRaw('TRIM(accode) = ?', [$code])
                        ->where('tdate', '<=', $date2)
                        ->where('control', '<=', $rlevel)
                        ->sum('amount');
                }
                $clBalance = round($opBal + $tranAmt, 2);
            }
        }

        // If no accountm, try clients table
        if ($jewlName === '' && $this->hasTable('clients')) {
            $cl = DB::table('clients')->whereRaw('TRIM(code) = ?', [$code])->select('name')->first();
            if ($cl) $jewlName = trim($cl->name ?? '');
        }

        $sections = [];

        // ── Section 1: Sales items (items sold to this jeweller) ──
        if ($this->hasTable('salesm') && $this->hasTable('salesd')) {
            $sales = DB::table('salesd as sd')
                ->join('salesm as sm', 'sm.slno', '=', 'sd.slno')
                ->whereRaw('TRIM(sm.custcode) = ?', [$code])
                ->where('sm.tdate', '>=', $date1)
                ->where('sm.tdate', '<=', $date2)
                ->where('sm.control', '<=', $rlevel)
                ->select([
                    'sm.billno', 'sm.tdate',
                    DB::raw('TRIM(sd.code) as itemcode'),
                    DB::raw('TRIM(sd.name) as itemname'),
                    'sd.qty', 'sd.weight', 'sd.rate',
                    'sd.lessperc', 'sd.lesswgt', 'sd.mcharge',
                    'sd.stoneprice', 'sd.amount',
                ])
                ->orderBy('sm.tdate')
                ->orderBy('sm.billno')
                ->get();

            if ($sales->count() > 0) {
                $saleRows = [];
                $totQty = 0; $totWgt = 0; $totAmt = 0;
                foreach ($sales as $s) {
                    $qty = (int) ($s->qty ?? 0);
                    $wgt = round((float) ($s->weight ?? 0), 3);
                    $amt = round((float) ($s->amount ?? 0), 2);
                    $totQty += $qty; $totWgt += $wgt; $totAmt += $amt;
                    $saleRows[] = [
                        'billno'   => trim($s->billno ?? ''),
                        'tdate'    => $s->tdate,
                        'itemcode' => trim($s->itemcode ?? ''),
                        'itemname' => trim($s->itemname ?? ''),
                        'qty'      => $qty,
                        'weight'   => $wgt,
                        'rate'     => round((float) ($s->rate ?? 0), 2),
                        'lessperc' => round((float) ($s->lessperc ?? 0), 2),
                        'lesswgt'  => round((float) ($s->lesswgt ?? 0), 3),
                        'mcharge'  => round((float) ($s->mcharge ?? 0), 2),
                        'stprice'  => round((float) ($s->stoneprice ?? 0), 2),
                        'amount'   => $amt,
                    ];
                }
                $sections[] = [
                    'title'  => 'Sales (Issued)',
                    'rows'   => $saleRows,
                    'totals' => ['qty' => $totQty, 'weight' => round($totWgt, 3), 'amount' => round($totAmt, 2)],
                ];
            }
        }

        // ── Section 2: Sales Return items (items returned by jeweller) ──
        if ($this->hasTable('salesrm') && $this->hasTable('salesrd')) {
            $returns = DB::table('salesrd as sd')
                ->join('salesrm as sm', 'sm.slno', '=', 'sd.slno')
                ->whereRaw('TRIM(sm.custcode) = ?', [$code])
                ->where('sm.tdate', '>=', $date1)
                ->where('sm.tdate', '<=', $date2)
                ->where('sm.control', '<=', $rlevel)
                ->select([
                    'sm.billno', 'sm.tdate',
                    DB::raw('TRIM(sd.code) as itemcode'),
                    DB::raw('TRIM(sd.name) as itemname'),
                    'sd.qty', 'sd.weight', 'sd.rate',
                    'sd.lessperc', 'sd.lesswgt', 'sd.mcharge',
                    'sd.stoneprice', 'sd.amount',
                ])
                ->orderBy('sm.tdate')
                ->orderBy('sm.billno')
                ->get();

            if ($returns->count() > 0) {
                $retRows = [];
                $totQty = 0; $totWgt = 0; $totAmt = 0;
                foreach ($returns as $s) {
                    $qty = (int) ($s->qty ?? 0);
                    $wgt = round((float) ($s->weight ?? 0), 3);
                    $amt = round((float) ($s->amount ?? 0), 2);
                    $totQty += $qty; $totWgt += $wgt; $totAmt += $amt;
                    $retRows[] = [
                        'billno'   => trim($s->billno ?? ''),
                        'tdate'    => $s->tdate,
                        'itemcode' => trim($s->itemcode ?? ''),
                        'itemname' => trim($s->itemname ?? ''),
                        'qty'      => $qty,
                        'weight'   => $wgt,
                        'rate'     => round((float) ($s->rate ?? 0), 2),
                        'lessperc' => round((float) ($s->lessperc ?? 0), 2),
                        'lesswgt'  => round((float) ($s->lesswgt ?? 0), 3),
                        'mcharge'  => round((float) ($s->mcharge ?? 0), 2),
                        'stprice'  => round((float) ($s->stoneprice ?? 0), 2),
                        'amount'   => $amt,
                    ];
                }
                $sections[] = [
                    'title'  => 'Sales Returns (Received Back)',
                    'rows'   => $retRows,
                    'totals' => ['qty' => $totQty, 'weight' => round($totWgt, 3), 'amount' => round($totAmt, 2)],
                ];
            }
        }

        // ── Section 3: Purchase items (items purchased from this jeweller) ──
        if ($this->hasTable('purchasem') && $this->hasTable('purchased')) {
            $purchases = DB::table('purchased as pd')
                ->join('purchasem as pm', 'pm.slno', '=', 'pd.slno')
                ->whereRaw('TRIM(pm.suppcode) = ?', [$code])
                ->where('pm.tdate', '>=', $date1)
                ->where('pm.tdate', '<=', $date2)
                ->where('pm.control', '<=', $rlevel)
                ->select([
                    'pm.billno', 'pm.tdate',
                    DB::raw('TRIM(pd.code) as itemcode'),
                    DB::raw('TRIM(pd.name) as itemname'),
                    'pd.qty', 'pd.weight', 'pd.rate',
                    'pd.lessperc', 'pd.lesswgt', 'pd.mcharge',
                    'pd.stprice', 'pd.amount',
                ])
                ->orderBy('pm.tdate')
                ->orderBy('pm.billno')
                ->get();

            if ($purchases->count() > 0) {
                $purRows = [];
                $totQty = 0; $totWgt = 0; $totAmt = 0;
                foreach ($purchases as $s) {
                    $qty = (int) ($s->qty ?? 0);
                    $wgt = round((float) ($s->weight ?? 0), 3);
                    $amt = round((float) ($s->amount ?? 0), 2);
                    $totQty += $qty; $totWgt += $wgt; $totAmt += $amt;
                    $purRows[] = [
                        'billno'   => trim($s->billno ?? ''),
                        'tdate'    => $s->tdate,
                        'itemcode' => trim($s->itemcode ?? ''),
                        'itemname' => trim($s->itemname ?? ''),
                        'qty'      => $qty,
                        'weight'   => $wgt,
                        'rate'     => round((float) ($s->rate ?? 0), 2),
                        'lessperc' => round((float) ($s->lessperc ?? 0), 2),
                        'lesswgt'  => round((float) ($s->lesswgt ?? 0), 3),
                        'mcharge'  => round((float) ($s->mcharge ?? 0), 2),
                        'stprice'  => round((float) ($s->stprice ?? 0), 2),
                        'amount'   => $amt,
                    ];
                }
                $sections[] = [
                    'title'  => 'Purchases (Received)',
                    'rows'   => $purRows,
                    'totals' => ['qty' => $totQty, 'weight' => round($totWgt, 3), 'amount' => round($totAmt, 2)],
                ];
            }
        }

        // ── Section 4: Purchase Return items ──
        if ($this->hasTable('purchaserm') && $this->hasTable('purchaserd')) {
            $pretns = DB::table('purchaserd as pd')
                ->join('purchaserm as pm', 'pm.slno', '=', 'pd.slno')
                ->whereRaw('TRIM(pm.suppcode) = ?', [$code])
                ->where('pm.tdate', '>=', $date1)
                ->where('pm.tdate', '<=', $date2)
                ->where('pm.control', '<=', $rlevel)
                ->select([
                    'pm.billno', 'pm.tdate',
                    DB::raw('TRIM(pd.code) as itemcode'),
                    DB::raw('TRIM(pd.name) as itemname'),
                    'pd.qty', 'pd.weight', 'pd.rate',
                    'pd.lessperc', 'pd.lesswgt', 'pd.mcharge',
                    'pd.stprice', 'pd.amount',
                ])
                ->orderBy('pm.tdate')
                ->orderBy('pm.billno')
                ->get();

            if ($pretns->count() > 0) {
                $prRows = [];
                $totQty = 0; $totWgt = 0; $totAmt = 0;
                foreach ($pretns as $s) {
                    $qty = (int) ($s->qty ?? 0);
                    $wgt = round((float) ($s->weight ?? 0), 3);
                    $amt = round((float) ($s->amount ?? 0), 2);
                    $totQty += $qty; $totWgt += $wgt; $totAmt += $amt;
                    $prRows[] = [
                        'billno'   => trim($s->billno ?? ''),
                        'tdate'    => $s->tdate,
                        'itemcode' => trim($s->itemcode ?? ''),
                        'itemname' => trim($s->itemname ?? ''),
                        'qty'      => $qty,
                        'weight'   => $wgt,
                        'rate'     => round((float) ($s->rate ?? 0), 2),
                        'lessperc' => round((float) ($s->lessperc ?? 0), 2),
                        'lesswgt'  => round((float) ($s->lesswgt ?? 0), 3),
                        'mcharge'  => round((float) ($s->mcharge ?? 0), 2),
                        'stprice'  => round((float) ($s->stprice ?? 0), 2),
                        'amount'   => $amt,
                    ];
                }
                $sections[] = [
                    'title'  => 'Purchase Returns (Issued Back)',
                    'rows'   => $prRows,
                    'totals' => ['qty' => $totQty, 'weight' => round($totWgt, 3), 'amount' => round($totAmt, 2)],
                ];
            }
        }

        return response()->json([
            'ok'        => true,
            'code'      => $code,
            'name'      => $jewlName,
            'clbalance' => $clBalance,
            'balstatus' => $clBalance < 0 ? 'To Receive' : ($clBalance > 0 ? 'To Give' : ''),
            'sections'  => $sections,
        ]);
    }
}
