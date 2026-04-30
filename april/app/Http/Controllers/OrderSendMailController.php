<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderSendMailController extends Controller
{
    private int $gilevel = 1;

    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $this->gilevel = (int) ($request->session()->get('user_level', 1));

        $dateFrom    = $request->query('date1', date('Y-m-d'));
        $dateTo      = $request->query('date2', date('Y-m-d'));
        $filter      = $request->query('filter', 'Pending');
        $smith       = trim($request->query('smith', ''));
        $jewl        = trim($request->query('jewl', ''));
        $stage       = $request->query('stage', 'All');
        $stagePref   = $request->query('stage_pref', 'Only');
        $showData    = $request->has('show');

        $rows   = [];
        $totals = ['count' => 0, 'qty' => 0, 'weight' => 0.0, 'stonewgt' => 0.0];

        if ($showData && $this->hasTable('orderm') && $this->hasTable('orderd')) {
            $rows = $this->loadData($dateFrom, $dateTo, $filter, $smith, $jewl, $stage, $stagePref);
            $totals['count'] = count($rows);
            foreach ($rows as $r) {
                $totals['qty']     += (int) $r['qty'];
                $totals['weight']  += (float) $r['weight'];
                $totals['stonewgt'] += (float) ($r['stonewgt'] ?? 0);
            }
        }

        return view('order-report.send-mail', compact(
            'dateFrom', 'dateTo', 'filter', 'smith', 'jewl',
            'stage', 'stagePref', 'showData', 'rows', 'totals'
        ));
    }

    private function loadData(string $dateFrom, string $dateTo, string $filter,
                              string $smith, string $jewl,
                              string $stage, string $stagePref): array
    {
        $orderdCols = array_map('strtolower', $this->columnList('orderd'));
        $ordermCols = array_map('strtolower', $this->columnList('orderm'));

        $select = [
            'orderm.slno', 'orderm.ordno', 'orderm.tdate', 'orderm.duedate',
            'orderm.custname', 'orderm.custcode', 'orderm.status',
            'orderd.code as itemcode', 'orderd.qty', 'orderd.weight',
            'orderd.stonewgt', 'orderd.sno',
        ];

        if (in_array('part', $orderdCols))      $select[] = 'orderd.part';
        if (in_array('smith', $orderdCols))     $select[] = 'orderd.smith';
        if (in_array('stage', $orderdCols))     $select[] = 'orderd.stage';
        if (in_array('jewlcode', $ordermCols))  $select[] = 'orderm.jewlcode';

        $query = DB::table('orderd')
            ->join('orderm', 'orderd.slno', '=', 'orderm.slno')
            ->select($select)
            ->where('orderm.control', '<=', $this->gilevel)
            ->whereBetween('orderm.tdate', [$dateFrom, $dateTo])
            ->orderBy('orderm.tdate')
            ->orderBy('orderm.ordno')
            ->orderBy('orderd.sno');

        // Status filter
        if ($filter === 'Pending') {
            $query->where('orderm.status', 1);
        } elseif ($filter === 'Returned') {
            $query->where('orderm.status', 2);
        }

        // Smith filter
        if ($smith !== '' && in_array('smith', $orderdCols)) {
            $query->where('orderd.smith', $smith);
        }

        // Jewellery filter
        if ($jewl !== '' && in_array('jewlcode', $ordermCols)) {
            $query->where('orderm.jewlcode', $jewl);
        }

        // Stage filter
        if ($stage !== 'All' && in_array('stage', $orderdCols)) {
            $stageMap = ['New Work' => 1, 'Work In Progress' => 2, 'Finished' => 3];
            $stageVal = $stageMap[$stage] ?? null;
            if ($stageVal !== null) {
                if ($stagePref === 'Only') {
                    $query->where('orderd.stage', $stageVal);
                } else {
                    $query->where('orderd.stage', '<>', $stageVal);
                }
            }
        }

        // Get item names
        $rows = $query->get()->map(fn ($r) => (array) $r)->toArray();

        $hasItems = $this->hasTable('items');
        foreach ($rows as &$row) {
            $row['itemname'] = '';
            if ($hasItems) {
                $row['itemname'] = (string) DB::table('items')
                    ->where('code', $row['itemcode'])
                    ->value('name');
            }
        }
        unset($row);

        return $rows;
    }

    public function exportCsv(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        // Re-run the same query
        $this->gilevel = (int) ($request->session()->get('user_level', 1));
        $rows = $this->loadData(
            $request->query('date1', date('Y-m-d')),
            $request->query('date2', date('Y-m-d')),
            $request->query('filter', 'Pending'),
            trim($request->query('smith', '')),
            trim($request->query('jewl', '')),
            $request->query('stage', 'All'),
            $request->query('stage_pref', 'Only')
        );

        $csv = "Date,OrdNo,DueDate,Customer,ItemCode,Item,Qty,Weight,StoneWgt,Model,Smith,Stage,Jewlry\n";
        $prevSlno = null;
        foreach ($rows as $r) {
            $sparse = $r['slno'] !== $prevSlno;
            $stageName = match((int)($r['stage'] ?? 0)) { 1=>'New Work', 2=>'In Progress', 3=>'Finished', default=>'' };
            $csv .= implode(',', [
                $sparse ? $r['tdate'] : '',
                $sparse ? $r['ordno'] : '',
                $sparse ? ($r['duedate'] ?? '') : '',
                $sparse ? '"'.str_replace('"','""',$r['custname']).'"' : '',
                $r['itemcode'],
                '"'.str_replace('"','""',$r['itemname']).'"',
                (int)$r['qty'],
                number_format((float)$r['weight'], 3, '.', ''),
                number_format((float)($r['stonewgt'] ?? 0), 3, '.', ''),
                '"'.str_replace('"','""',$r['part'] ?? '').'"',
                $r['smith'] ?? '',
                $stageName,
                $r['jewlcode'] ?? '',
            ]) . "\n";
            $prevSlno = $r['slno'];
        }

        $filename = 'order_report_' . $request->query('date1') . '_' . $request->query('date2') . '.csv';
        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
