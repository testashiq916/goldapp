<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DiamondStoneStockController extends Controller
{
    private int $gilevel = 1;

    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $this->gilevel = (int) ($request->session()->get('user_level', 1));

        $type      = $request->query('type', 'All');
        $report    = $request->query('report', 'Summary');
        $stItem    = trim($request->query('st_item', ''));
        $itemCode  = strtoupper(trim($request->query('item', '')));
        $grpCode   = trim($request->query('grp', ''));
        $grpFilter = $request->has('grp_filter');
        $stkOnly   = $request->has('stk_only');
        $withPres  = $request->has('with_pres');
        $dateFrom  = $request->query('date1', '');
        $dateTo    = $request->query('date2', '');
        $showData  = $request->has('show');

        $rows   = [];
        $totals = ['count' => 0, 'qty' => 0, 'weight' => 0.0, 'stone' => 0.0, 'netwgt' => 0.0,
                   'pcs' => 0, 'carats' => 0.0, 'amount' => 0.0, 'nos' => 0, 'dmdwgt' => 0.0, 'goldwgt' => 0.0];

        $groupCodes = $this->hasTable('items') ? DB::table('items')->whereNotNull('grpcode')
            ->where('grpcode','<>','')->distinct()->orderBy('grpcode')->pluck('grpcode')->toArray() : [];

        if ($showData && $this->hasTable('barcode')) {
            if ($report === 'Summary') {
                $rows = $this->loadSummary($type, $stItem, $grpCode, $grpFilter);
            } elseif ($report === 'Stone Info') {
                $rows = $this->loadStoneInfo($type, $stItem, $itemCode, $stkOnly, $dateFrom, $dateTo);
            } else {
                $rows = $this->loadDetailed($type, $stItem, $itemCode, $grpCode, $grpFilter, $withPres, $dateFrom, $dateTo);
            }
            $totals['count'] = count($rows);
            foreach ($rows as $r) {
                $totals['qty']    += (int) ($r['qty'] ?? 0);
                $totals['weight'] += (float) ($r['weight'] ?? $r['gwgt'] ?? 0);
                $totals['stone']  += (float) ($r['stweight'] ?? $r['stwgt'] ?? 0);
                $totals['nos']    += (int) ($r['nos'] ?? 0);
                $totals['dmdwgt'] += (float) ($r['dmdwgt'] ?? 0);
                $totals['goldwgt'] += (float) ($r['goldwgt'] ?? 0);
                $totals['pcs']    += (int) ($r['pcs'] ?? 0);
                $totals['carats'] += (float) ($r['carats'] ?? 0);
                $totals['amount'] += (float) ($r['amount'] ?? 0);
            }
            $totals['netwgt'] = $totals['weight'] - $totals['stone'];
        }

        return view('barcode-list.diamond-stock', compact(
            'type', 'report', 'stItem', 'itemCode', 'grpCode', 'grpFilter',
            'stkOnly', 'withPres', 'dateFrom', 'dateTo',
            'showData', 'rows', 'totals', 'groupCodes'
        ));
    }

    private function dmdFilter(string $type): string
    {
        if ($type === 'Diamond')     return "AND items.dmdplt = 'D'";
        if ($type === 'Platinum')    return "AND items.dmdplt = 'P'";
        if ($type === 'Color Stone') return "AND items.dmdplt = 'C'";
        return "AND (items.dmdplt = 'D' OR items.dmdplt = 'P')";
    }

    private function loadSummary(string $type, string $stItem, string $grpCode, bool $grpFilter): array
    {
        $gl = $this->gilevel;
        $df = $this->dmdFilter($type);
        $stParam = $stItem !== '' ? $stItem : null;
        $grpWhere = ($grpFilter && $grpCode !== '') ? "AND items.grpcode = " . DB::getPdo()->quote($grpCode) : '';

        $sql = "SELECT items.name, items.code,
            COALESCE((SELECT COUNT(bc.bcode) FROM barcode bc WHERE bc.icode=items.code AND bc.stk='Y'),0) as nos,
            COALESCE((SELECT SUM(bc.weight) FROM barcode bc WHERE bc.icode=items.code AND bc.stk='Y'),0) as gwgt,
            COALESCE((SELECT SUM(bc.stweight) FROM barcode bc WHERE bc.icode=items.code AND bc.stk='Y'),0) as stwgt,
            COALESCE((SELECT SUM(bd.carats) FROM barcode bc JOIN barcode_dmddet bd ON bd.bcode=bc.bcode
                WHERE bc.icode=items.code AND bc.stk='Y'
                " . ($stParam ? "AND bd.stcode = " . DB::getPdo()->quote($stParam) : '') . "),0) as dmdwgt
        FROM items
        WHERE items.disabled <> 1 AND items.showinstkrep = 'Y' {$df} {$grpWhere}
        ORDER BY items.name";

        $rows = DB::select($sql);
        $result = [];
        foreach ($rows as $r) {
            $r = (array) $r;
            $r['goldwgt'] = (float) $r['gwgt'] - (float) $r['stwgt'];
            if ((int) $r['nos'] > 0 || (float) $r['gwgt'] > 0) $result[] = $r;
        }
        return $result;
    }

    private function loadDetailed(string $type, string $stItem, string $itemCode,
        string $grpCode, bool $grpFilter, bool $withPres, string $dateFrom, string $dateTo): array
    {
        $gl = $this->gilevel;
        $q = DB::table('barcode')
            ->join('items', 'barcode.icode', '=', 'items.code')
            ->select(['barcode.bcode','barcode.icode','barcode.qty','barcode.weight',
                      'barcode.stweight','barcode.stk','barcode.tdate',
                      'items.name as itemname','items.dmdplt'])
            ->where('barcode.control', '<=', $gl)
            ->where('items.disabled', '<>', 1);

        if ($type === 'Diamond')     $q->where('items.dmdplt', 'D');
        elseif ($type === 'Platinum') $q->where('items.dmdplt', 'P');
        elseif ($type === 'Color Stone') $q->where('items.dmdplt', 'C');
        else $q->whereIn('items.dmdplt', ['D', 'P']);

        if ($itemCode !== '') $q->where('barcode.icode', $itemCode);
        if ($grpFilter && $grpCode !== '') $q->where('items.grpcode', $grpCode);
        if ($dateFrom !== '') $q->where('barcode.tdate', '>=', $dateFrom);
        if ($dateTo !== '') $q->where('barcode.tdate', '<=', $dateTo);

        $rows = $q->orderBy('barcode.bcode')->get()->map(fn($r)=>(array)$r)->toArray();

        // Get dmdwgt per barcode
        $bcodes = array_column($rows, 'bcode');
        $dmdSums = [];
        if (!empty($bcodes)) {
            $chunks = array_chunk($bcodes, 500);
            foreach ($chunks as $chunk) {
                $dq = DB::table('barcode_dmddet')->whereIn('bcode', $chunk);
                if ($stItem !== '') $dq->where('stcode', $stItem);
                $dRows = $dq->groupBy('bcode')->selectRaw('bcode, SUM(carats) as ct')->get();
                foreach ($dRows as $d) $dmdSums[$d->bcode] = (float) $d->ct;
            }
        }

        foreach ($rows as &$r) {
            $r['dmdwgt'] = $dmdSums[$r['bcode']] ?? 0;
        }
        unset($r);

        if ($withPres) $rows = array_values(array_filter($rows, fn($r) => $r['dmdwgt'] > 0));

        return $rows;
    }

    private function loadStoneInfo(string $type, string $stItem, string $itemCode,
        bool $stkOnly, string $dateFrom, string $dateTo): array
    {
        $gl = $this->gilevel;
        $q = DB::table('barcode')
            ->join('items', 'barcode.icode', '=', 'items.code')
            ->join('barcode_dmddet', 'barcode_dmddet.bcode', '=', 'barcode.bcode')
            ->select([
                'barcode.bcode','barcode.icode','barcode.qty','barcode.weight',
                'barcode.stweight','barcode.stk','barcode.tdate',
                'items.name as itemname',
                'barcode_dmddet.stcode','barcode_dmddet.sttype','barcode_dmddet.stcut',
                'barcode_dmddet.stsize','barcode_dmddet.pcs','barcode_dmddet.carats',
                'barcode_dmddet.rate','barcode_dmddet.amount',
            ])
            ->where('barcode.control', '<=', $gl);

        if ($type === 'Diamond')     $q->where('items.dmdplt', 'D');
        elseif ($type === 'Platinum') $q->where('items.dmdplt', 'P');
        elseif ($type === 'Color Stone') $q->where('items.dmdplt', 'C');
        else $q->whereIn('items.dmdplt', ['D', 'P']);

        if ($stkOnly) $q->where('barcode.stk', 'Y');
        if ($itemCode !== '') $q->where('barcode.icode', $itemCode);
        if ($stItem !== '') $q->where('barcode_dmddet.stcode', $stItem);
        if ($dateFrom !== '') $q->where('barcode.tdate', '>=', $dateFrom);
        if ($dateTo !== '') $q->where('barcode.tdate', '<=', $dateTo);

        return $q->orderBy('barcode.bcode')->get()->map(fn($r)=>(array)$r)->toArray();
    }
}
