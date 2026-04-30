<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Party/Smith/Jewl Fix/Unfix Report (PB: w_smithjewl_fixunfix_rep / d_smith_fixunfix_rep)
 *
 * Shows smithm records where doctype='U' (unfixing transactions).
 * Per transaction: issued wgt (givrec='G'), received wgt (givrec='R'),
 * adjusted wgt (smithd linked via refno=docno), pending wgt.
 */
class SmithFixUnfixReportController extends Controller
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

        return view('reports.smith-fixunfix-report', [
            'title'  => 'Party/Smith/Jewl Fix/Unfix Report',
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
        $rlevel = (int) $request->query('rlevel', (int) ($request->session()->get('gilevel', 1)));
        $smith  = trim((string) $request->query('smith', ''));
        $rtype  = trim((string) $request->query('rtype', '')); // All, Receivable, Payable

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date1) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date2)) {
            return response()->json(['ok' => false, 'message' => 'Invalid date'], 422);
        }

        if (!$this->hasTable('clients') || !$this->hasTable('smithm') || !$this->hasTable('smithd')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => $this->emptyTotals()]);
        }

        // PB: clients JOIN smithm WHERE doctype='U', ORDER BY name, tdate, docno
        $query = DB::table('clients as c')
            ->join('smithm as sm', 'sm.smithcode', '=', 'c.code')
            ->where('sm.doctype', 'U')
            ->select([
                DB::raw('TRIM(c.code) as code'),
                DB::raw('TRIM(c.name) as name'),
                DB::raw('TRIM(sm.docno) as docno'),
                'sm.tdate',
                'sm.duedate',
                'sm.slno',
            ]);

        // Issued wgt per transaction (givrec='G')
        $query->selectRaw(
            "COALESCE((SELECT SUM(sd.netwgt) FROM smithd sd WHERE sd.slno = sm.slno AND sd.givrec = 'G'), 0) as issuewgt"
        );
        // Received wgt per transaction (givrec='R')
        $query->selectRaw(
            "COALESCE((SELECT SUM(sd.netwgt) FROM smithd sd WHERE sd.slno = sm.slno AND sd.givrec = 'R'), 0) as rcvdwgt"
        );
        // Adjusted wgt (smithd linked via refno=docno)
        $query->selectRaw(
            "COALESCE((SELECT SUM(sd2.netwgt) FROM smithd sd2 JOIN smithm sm2 ON sd2.slno = sm2.slno WHERE sm2.refno = sm.docno AND sm2.control <= ?), 0) as adjwgt",
            [$rlevel]
        );

        if ($smith !== '') {
            $query->where('c.code', $smith);
        }

        $results = $query->orderBy('c.name')->orderBy('sm.tdate')->orderBy('sm.docno')->get();
        $rows = [];

        foreach ($results as $r) {
            $iw  = round((float) $r->issuewgt, 3);
            $rw  = round((float) $r->rcvdwgt, 3);
            $aw  = round((float) $r->adjwgt, 3);
            // PB: pendwgt = abs(issuewgt - rcvdwgt) - adjwgt
            $pw  = round(abs($iw - $rw) - $aw, 3);

            // Filter by type
            if ($rtype === 'Receivable' && $iw <= 0) continue;
            if ($rtype === 'Payable' && $rw <= 0) continue;

            $rows[] = [
                'code'    => trim($r->code),
                'name'    => trim($r->name),
                'docno'   => trim($r->docno),
                'tdate'   => $r->tdate,
                'duedate' => $r->duedate,
                'rcvdwgt' => $rw,
                'issuewgt'=> $iw,
                'adjwgt'  => $aw,
                'pendwgt' => $pw,
            ];
        }

        return response()->json([
            'ok'     => true,
            'rows'   => $rows,
            'totals' => $this->buildTotals($rows),
        ]);
    }

    private function buildTotals(array $rows): array
    {
        $t = $this->emptyTotals();
        $t['count'] = count($rows);
        foreach ($rows as $r) {
            $t['rcvdwgt'] += (float) ($r['rcvdwgt'] ?? 0);
            $t['issuewgt']+= (float) ($r['issuewgt'] ?? 0);
            $t['adjwgt']  += (float) ($r['adjwgt'] ?? 0);
            $t['pendwgt'] += (float) ($r['pendwgt'] ?? 0);
        }
        return $t;
    }

    private function emptyTotals(): array
    {
        return ['count' => 0, 'rcvdwgt' => 0, 'issuewgt' => 0, 'adjwgt' => 0, 'pendwgt' => 0];
    }
}
