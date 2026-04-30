<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BarcodeEntryComparisonController extends Controller
{
    private int $gilevel = 1;

    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $this->gilevel = (int) ($request->session()->get('user_level', 1));

        $dateFrom = $request->query('date1', date('Y-m-d'));
        $dateTo   = $request->query('date2', date('Y-m-d'));
        $showData = $request->has('show');

        $rows   = [];
        $totals = ['totnos' => 0, 'totwgt' => 0.0, 'bctqty' => 0, 'bctwgt' => 0.0, 'qtydiff' => 0, 'wgtdiff' => 0.0];

        if ($showData && $this->hasTable('barcodedoc')) {
            $rows = $this->loadData($dateFrom, $dateTo);
            foreach ($rows as $r) {
                $totals['totnos']  += (int) $r['totnos'];
                $totals['totwgt']  += (float) $r['totwgt'];
                $totals['bctqty']  += (int) $r['bctqty'];
                $totals['bctwgt']  += (float) $r['bctwgt'];
                $totals['qtydiff'] += (int) $r['qtydiff'];
                $totals['wgtdiff'] += (float) $r['wgtdiff'];
            }
        }

        return view('barcode-list.entry-comparison', compact(
            'dateFrom', 'dateTo', 'showData', 'rows', 'totals'
        ));
    }

    private function loadData(string $dateFrom, string $dateTo): array
    {
        $docs = DB::table('barcodedoc')
            ->whereBetween('tdate', [$dateFrom, $dateTo])
            ->orderBy('tdate')
            ->orderBy('docno')
            ->get(['docno', 'tdate', 'smith', 'totwgt', 'totnos'])
            ->map(fn ($r) => (array) $r)
            ->toArray();

        // Bulk aggregate barcode sums by docno
        $docnos = array_column($docs, 'docno');
        $bcSums = [];
        if (!empty($docnos) && $this->hasTable('barcode')) {
            $bcRows = DB::table('barcode')
                ->whereIn('docno', $docnos)
                ->groupBy('docno')
                ->selectRaw('docno, COALESCE(SUM(qty),0) as q, COALESCE(SUM(weight),0) as w')
                ->get();
            foreach ($bcRows as $b) {
                $bcSums[$b->docno] = ['q' => (int) $b->q, 'w' => (float) $b->w];
            }
        }

        foreach ($docs as &$row) {
            $bc = $bcSums[$row['docno']] ?? ['q' => 0, 'w' => 0];
            $row['bctqty']  = $bc['q'];
            $row['bctwgt']  = $bc['w'];
            $row['qtydiff'] = (int) $row['totnos'] - $bc['q'];
            $row['wgtdiff'] = round((float) $row['totwgt'] - $bc['w'], 3);
        }
        unset($row);

        return $docs;
    }
}
