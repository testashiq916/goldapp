<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OutstandingTaxReportController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('reports.outstanding-tax', [
            'title'  => 'Outstanding Tax Report',
            'date1'  => date('Y-m-d'),
            'date2'  => date('Y-m-d'),
            'rlevel' => (int) $request->session()->get('gilevel', 1) ?: 1,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $date1  = (string) $request->query('date1', date('Y-m-d'));
        $date2  = (string) $request->query('date2', date('Y-m-d'));
        $rlevel = (int) $request->query('rlevel', (int) $request->session()->get('gilevel', 1));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date1) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date2)) {
            return response()->json(['ok' => false, 'message' => 'Invalid date'], 422);
        }

        $rows = [];
        $totInput = 0;
        $totOutput = 0;

        // ── Sales Tax (Output Tax: tax collected on sales) ──
        if ($this->hasTable('salesm')) {
            $s = DB::table('salesm')
                ->whereBetween('tdate', [$date1, $date2])
                ->where('control', '<=', $rlevel)
                ->selectRaw('SUM(COALESCE(sgst,0)) as sgst')
                ->selectRaw('SUM(COALESCE(cgst,0)) as cgst')
                ->selectRaw('SUM(COALESCE(igst,0)) as igst')
                ->selectRaw('SUM(COALESCE(staxamt,0)) as staxamt')
                ->first();

            $sgst = (float) ($s->sgst ?? 0);
            $cgst = (float) ($s->cgst ?? 0);
            $igst = (float) ($s->igst ?? 0);
            $total = $sgst + $cgst + $igst;
            if ($total == 0) $total = (float) ($s->staxamt ?? 0);

            if ($sgst > 0) {
                $rows[] = ['description' => 'Sales - SGST', 'inputtax' => 0, 'outputtax' => round($sgst, 2)];
                $totOutput += $sgst;
            }
            if ($cgst > 0) {
                $rows[] = ['description' => 'Sales - CGST', 'inputtax' => 0, 'outputtax' => round($cgst, 2)];
                $totOutput += $cgst;
            }
            if ($igst > 0) {
                $rows[] = ['description' => 'Sales - IGST', 'inputtax' => 0, 'outputtax' => round($igst, 2)];
                $totOutput += $igst;
            }
            if ($sgst == 0 && $cgst == 0 && $igst == 0 && $total > 0) {
                $rows[] = ['description' => 'Sales Tax', 'inputtax' => 0, 'outputtax' => round($total, 2)];
                $totOutput += $total;
            }

            // TCS on sales (output)
            if (Schema::hasColumn('salesm', 'tcsamt')) {
                $tcs = DB::table('salesm')
                    ->whereBetween('tdate', [$date1, $date2])
                    ->where('control', '<=', $rlevel)
                    ->selectRaw('SUM(COALESCE(tcsamt,0)) as tcsamt')->first();
                $tcsAmt = (float) ($tcs->tcsamt ?? 0);
                if ($tcsAmt > 0) {
                    $rows[] = ['description' => 'Sales - TCS', 'inputtax' => 0, 'outputtax' => round($tcsAmt, 2)];
                    $totOutput += $tcsAmt;
                }
            }
        }

        // ── Purchase Tax (Input Tax: tax paid on purchases) ──
        if ($this->hasTable('purchasem')) {
            $p = DB::table('purchasem')
                ->whereBetween('tdate', [$date1, $date2])
                ->where('control', '<=', $rlevel)
                ->selectRaw('SUM(COALESCE(sgst,0)) as sgst')
                ->selectRaw('SUM(COALESCE(cgst,0)) as cgst')
                ->selectRaw('SUM(COALESCE(igst,0)) as igst')
                ->first();

            $sgst = (float) ($p->sgst ?? 0);
            $cgst = (float) ($p->cgst ?? 0);
            $igst = (float) ($p->igst ?? 0);

            if ($sgst > 0) {
                $rows[] = ['description' => 'Purchase - SGST', 'inputtax' => round($sgst, 2), 'outputtax' => 0];
                $totInput += $sgst;
            }
            if ($cgst > 0) {
                $rows[] = ['description' => 'Purchase - CGST', 'inputtax' => round($cgst, 2), 'outputtax' => 0];
                $totInput += $cgst;
            }
            if ($igst > 0) {
                $rows[] = ['description' => 'Purchase - IGST', 'inputtax' => round($igst, 2), 'outputtax' => 0];
                $totInput += $igst;
            }

            // TCS on purchase (input)
            if (Schema::hasColumn('purchasem', 'tcsamt')) {
                $tcs = DB::table('purchasem')
                    ->whereBetween('tdate', [$date1, $date2])
                    ->where('control', '<=', $rlevel)
                    ->selectRaw('SUM(COALESCE(tcsamt,0)) as tcsamt')->first();
                $tcsAmt = (float) ($tcs->tcsamt ?? 0);
                if ($tcsAmt > 0) {
                    $rows[] = ['description' => 'Purchase - TCS', 'inputtax' => round($tcsAmt, 2), 'outputtax' => 0];
                    $totInput += $tcsAmt;
                }
            }
        }

        // ── Sales Return Tax (Input Tax credit: tax refunded on returns) ──
        if ($this->hasTable('salesrm')) {
            $sr = DB::table('salesrm')
                ->whereBetween('tdate', [$date1, $date2])
                ->where('control', '<=', $rlevel)
                ->selectRaw('SUM(COALESCE(sgst,0)) as sgst')
                ->selectRaw('SUM(COALESCE(cgst,0)) as cgst')
                ->selectRaw('SUM(COALESCE(igst,0)) as igst')
                ->selectRaw('SUM(COALESCE(staxamt,0)) as staxamt')
                ->first();

            $sgst = (float) ($sr->sgst ?? 0);
            $cgst = (float) ($sr->cgst ?? 0);
            $igst = (float) ($sr->igst ?? 0);
            $total = $sgst + $cgst + $igst;
            if ($total == 0) $total = (float) ($sr->staxamt ?? 0);

            if ($total > 0) {
                $rows[] = ['description' => 'Sales Return Tax (Credit)', 'inputtax' => round($total, 2), 'outputtax' => 0];
                $totInput += $total;
            }
        }

        // ── Purchase Return Tax (Output Tax: tax reversed on returns) ──
        if ($this->hasTable('purchaserm')) {
            $pr = DB::table('purchaserm')
                ->whereBetween('tdate', [$date1, $date2])
                ->where('control', '<=', $rlevel)
                ->selectRaw('SUM(COALESCE(sgst,0)) as sgst')
                ->selectRaw('SUM(COALESCE(cgst,0)) as cgst')
                ->selectRaw('SUM(COALESCE(igst,0)) as igst')
                ->first();

            $sgst = (float) ($pr->sgst ?? 0);
            $cgst = (float) ($pr->cgst ?? 0);
            $igst = (float) ($pr->igst ?? 0);
            $total = $sgst + $cgst + $igst;

            if ($total > 0) {
                $rows[] = ['description' => 'Purchase Return Tax (Reversal)', 'inputtax' => 0, 'outputtax' => round($total, 2)];
                $totOutput += $total;
            }
        }

        // ── Smith Tax (Input Tax) ──
        if ($this->hasTable('smithm')) {
            $sm = DB::table('smithm')
                ->whereBetween('tdate', [$date1, $date2])
                ->where('control', '<=', $rlevel)
                ->selectRaw('SUM(COALESCE(taxamt,0)) as taxamt')
                ->selectRaw('SUM(COALESCE(tdsamt,0)) as tdsamt')
                ->first();

            $taxAmt = (float) ($sm->taxamt ?? 0);
            $tdsAmt = (float) ($sm->tdsamt ?? 0);

            if ($taxAmt > 0) {
                $rows[] = ['description' => 'Goldsmith Tax', 'inputtax' => round($taxAmt, 2), 'outputtax' => 0];
                $totInput += $taxAmt;
            }
            if ($tdsAmt > 0) {
                $rows[] = ['description' => 'Goldsmith TDS', 'inputtax' => 0, 'outputtax' => round($tdsAmt, 2)];
                $totOutput += $tdsAmt;
            }
        }

        // Net outstanding
        $netInput = ($totInput - $totOutput) > 0 ? round(abs($totInput - $totOutput), 2) : 0;
        $netOutput = ($totInput - $totOutput) > 0 ? 0 : round(abs($totInput - $totOutput), 2);

        return response()->json([
            'ok'     => true,
            'rows'   => $rows,
            'totals' => [
                'inputtax'  => round($totInput, 2),
                'outputtax' => round($totOutput, 2),
                'netinput'  => $netInput,
                'netoutput' => $netOutput,
            ],
        ]);
    }
}
