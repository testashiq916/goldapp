<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Account Summary / Ledger (PB: w_acsummary / d_acsumm)
 *
 * Per-account ledger showing daybook transactions in a date range,
 * with debit/credit split, running balance, opening and closing balances.
 * Also shows weight balance for smith/jeweller accounts (actype2 = J/G).
 */
class AcSummaryController extends Controller
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

        return view('reports.ac-summary', [
            'title'  => 'Account Summary',
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
            return response()->json(['ok' => false, 'message' => 'Please enter an Account Code'], 422);
        }

        // ── 1. Get account info from accountm ──
        $acName  = '';
        $acType2 = '';
        $opBal   = 0;

        if ($this->hasTable('accountm')) {
            $acCols = array_map('strtolower', $this->columnList('accountm'));
            $opCol  = ($rlevel >= 2 && in_array('opbalb', $acCols)) ? 'opbalb' : 'opbal';

            $acct = DB::table('accountm')
                ->whereRaw('TRIM(accode) = ?', [$code])
                ->select('name', DB::raw("COALESCE({$opCol}, 0) as opbal"))
                ->when(in_array('actype2', $acCols), function ($q) {
                    $q->addSelect('actype2');
                })
                ->first();

            if (!$acct) {
                return response()->json(['ok' => false, 'message' => 'Account code not found'], 404);
            }

            $acName  = trim($acct->name ?? '');
            $opBal   = (float) $acct->opbal;
            $acType2 = trim($acct->actype2 ?? '');
        }

        // ── 2. Calculate opening balance = opbal + sum(daybook before date1) ──
        $priorAmt = 0;
        if ($this->hasTable('daybook')) {
            $priorAmt = (float) DB::table('daybook')
                ->whereRaw('TRIM(accode) = ?', [$code])
                ->where('tdate', '<', $date1)
                ->where('control', '<=', $rlevel)
                ->sum('amount');
        }
        $openingBal = round($opBal + $priorAmt, 2);

        // ── 3. Get transactions in date range ──
        $rows = [];
        if ($this->hasTable('daybook') && $this->hasTable('daybookpart')) {
            $dbpCols = array_map('strtolower', $this->columnList('daybookpart'));
            $hasStaff = in_array('staff', $dbpCols);

            $query = DB::table('daybook as d')
                ->join('daybookpart as dp', 'dp.slno', '=', 'd.slno')
                ->whereRaw('TRIM(d.accode) = ?', [$code])
                ->whereBetween('d.tdate', [$date1, $date2])
                ->where('d.control', '<=', $rlevel)
                ->select([
                    'd.tdate',
                    DB::raw('COALESCE(d.amount, 0) as amount'),
                    DB::raw("TRIM(COALESCE(dp.vchno, '')) as vchno"),
                    DB::raw("TRIM(COALESCE(dp.particular, '')) as particular"),
                    'd.slno',
                ]);

            if ($hasStaff) {
                $query->addSelect(DB::raw("TRIM(COALESCE(dp.staff, '')) as staff"));
            } else {
                $query->addSelect(DB::raw("'' as staff"));
            }

            $query->orderBy('d.tdate')->orderBy('d.slno');

            $results = $query->get();

            $runBal   = $openingBal;
            $totDebit = 0;
            $totCredit = 0;

            foreach ($results as $r) {
                $amt = round((float) $r->amount, 2);
                $runBal += $amt;

                $debit  = $amt < 0 ? round(abs($amt), 2) : 0;
                $credit = $amt > 0 ? round($amt, 2) : 0;
                $totDebit  += $debit;
                $totCredit += $credit;

                $desc = trim($r->particular);
                if ($r->staff !== '') {
                    $desc .= '->Staff->' . trim($r->staff);
                }

                $rows[] = [
                    'tdate'   => $r->tdate,
                    'desc'    => $desc,
                    'vchno'   => trim($r->vchno),
                    'debit'   => $debit,
                    'credit'  => $credit,
                    'balance' => round(abs($runBal), 2),
                    'balside' => $runBal < 0 ? 'Dr' : ($runBal > 0 ? 'Cr' : ''),
                    'slno'    => (int) $r->slno,
                ];
            }
        } else {
            $totDebit  = 0;
            $totCredit = 0;
        }

        // ── 4. Closing balance ──
        $closingBal = round($openingBal - $totDebit + $totCredit, 2);

        // ── 5. Weight balance for J/G type accounts ──
        $wgtNote = '';
        if (in_array($acType2, ['J', 'G']) && $this->hasTable('smithm') && $this->hasTable('smithd')) {
            $wgtBal = $this->smithWgtBal($code, $date2, $rlevel);
            if ($wgtBal < 0) {
                $wgtNote = 'Weight Bal : ' . number_format(abs($wgtBal), 3) . ' TR';
            } elseif ($wgtBal > 0) {
                $wgtNote = 'Weight Bal : ' . number_format(abs($wgtBal), 3) . ' TG';
            }
        }

        return response()->json([
            'ok'         => true,
            'code'       => $code,
            'name'       => $acName,
            'actype'     => $acType2,
            'openingBal' => $openingBal,
            'opSide'     => $openingBal < 0 ? 'Dr' : ($openingBal > 0 ? 'Cr' : ''),
            'closingBal' => $closingBal,
            'clSide'     => $closingBal < 0 ? 'Dr' : ($closingBal > 0 ? 'Cr' : ''),
            'totDebit'   => round($totDebit, 2),
            'totCredit'  => round($totCredit, 2),
            'wgtNote'    => $wgtNote,
            'rows'       => $rows,
        ]);
    }

    /**
     * Calculate smith weight balance as of a date.
     * Balance = sum(netwgt where givrec='R') - sum(netwgt where givrec='G')
     * Plus opening weight from clientsgs.
     */
    private function smithWgtBal(string $code, string $date, int $rlevel): float
    {
        $opwgt = 0;
        if ($this->hasTable('clientsgs')) {
            $gs = DB::table('clientsgs')
                ->whereRaw('TRIM(code) = ?', [$code])
                ->select(DB::raw('COALESCE(opweight, 0) as opweight'))
                ->first();
            if ($gs) {
                $opwgt = (float) $gs->opweight;
            }
        }

        $rcvd = (float) DB::table('smithd as sd')
            ->join('smithm as sm', 'sm.slno', '=', 'sd.slno')
            ->whereRaw('TRIM(sm.smithcode) = ?', [$code])
            ->where('sm.tdate', '<=', $date)
            ->where('sm.control', '<=', $rlevel)
            ->where('sd.givrec', 'R')
            ->sum('sd.netwgt');

        $issued = (float) DB::table('smithd as sd')
            ->join('smithm as sm', 'sm.slno', '=', 'sd.slno')
            ->whereRaw('TRIM(sm.smithcode) = ?', [$code])
            ->where('sm.tdate', '<=', $date)
            ->where('sm.control', '<=', $rlevel)
            ->where('sd.givrec', 'G')
            ->sum('sd.netwgt');

        return round($opwgt + $rcvd - $issued, 3);
    }
}
