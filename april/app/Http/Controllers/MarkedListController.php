<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MarkedListController extends Controller
{
    private array $colCache = [];

    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('reports.marked-list', [
            'title'  => 'Marked List',
            'date1'  => date('Y-m-d'),
            'date2'  => date('Y-m-d'),
            'rlevel' => (int) $request->session()->get('gilevel', 1) ?: 1,
        ]);
    }

    public function lookups(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $salesmen = [];
        if ($this->hasTable('sman')) {
            $salesmen = DB::table('sman')->select('code', 'name')->orderBy('code')->get()
                ->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name)])->values()->all();
        }

        return response()->json(['ok' => true, 'salesmen' => $salesmen]);
    }

    public function data(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) {
            return response()->json(['ok' => false], 401);
        }

        $date1 = (string) $request->query('date1', date('Y-m-d'));
        $date2 = (string) $request->query('date2', date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date1) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date2)) {
            return response()->json(['ok' => false, 'message' => 'Invalid date'], 422);
        }

        $rlevel   = (int) $request->query('rlevel', (int) $request->session()->get('gilevel', 1));
        $smcode   = trim((string) $request->query('smcode', ''));
        $itemcode = trim((string) $request->query('itemcode', ''));
        $type     = trim((string) $request->query('type', ''));       // G/S/O or orn_Y/orn_N
        $marked   = trim((string) $request->query('marked', ''));     // all/Y/N
        $rmnowise = (int) $request->query('rmnowise', 0);

        if (!$this->hasTable('salesm') || !$this->hasTable('salesd')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => []]);
        }

        $q = DB::table('salesd as d')
            ->join('salesm as m', 'm.slno', '=', 'd.slno')
            ->leftJoin('items as i', 'i.code', '=', 'd.code')
            ->select(
                'm.slno', 'm.tdate', 'm.billno', 'm.billamt',
                'd.qty', 'd.weight', 'd.amount', 'd.stonewgt', 'd.mark', 'd.rmno',
                'd.name as dname', 'd.bcode'
            );

        $q->addSelect(DB::raw($this->hasCol('items', 'name') ? 'i.name as itemname' : "'' as itemname"));
        $q->addSelect(DB::raw($this->hasCol('items', 'itype') ? 'i.itype' : "'' as itype"));
        $q->addSelect(DB::raw($this->hasCol('items', 'ornament') ? 'i.ornament' : "'' as ornament"));
        $q->addSelect(DB::raw($this->hasCol('items', 'code') ? 'i.code as itemcode' : "'' as itemcode"));

        // SmithMC rate from barcode table
        if ($this->hasTable('barcode') && $this->hasCol('barcode', 'smithmcrate')) {
            $q->selectRaw('(SELECT bc.smithmcrate FROM barcode bc WHERE bc.bcode = d.bcode LIMIT 1) as smithmc');
        } else {
            $q->selectRaw('0 as smithmc');
        }

        $q->whereBetween('m.tdate', [$date1, $date2]);
        if ($this->hasCol('salesm', 'control')) $q->where('m.control', '<=', $rlevel);

        // Filters
        if ($smcode !== '' && $this->hasCol('salesm', 'smcode'))
            $q->where('m.smcode', $smcode);

        if ($itemcode !== '')
            $q->where('i.code', $itemcode);

        // Type filter
        if ($type === 'G' || $type === 'S' || $type === 'O') {
            if ($this->hasCol('items', 'itype')) $q->where('i.itype', $type);
        } elseif ($type === 'orn_Y') {
            if ($this->hasCol('items', 'ornament')) $q->where('i.ornament', 'Y');
        } elseif ($type === 'orn_N') {
            if ($this->hasCol('items', 'ornament')) $q->where('i.ornament', 'N');
        }

        // Marked filter
        if ($marked === 'Y') {
            $q->where('d.mark', 'Y');
        } elseif ($marked === 'N') {
            $q->where('d.mark', '<>', 'Y');
        }

        // Remake No wise - only show items with rmno
        if ($rmnowise) {
            $q->whereRaw("TRIM(d.rmno) <> ''");
        }

        $q->orderBy('m.tdate')->orderBy('m.slno');

        $rows = $q->get()->map(function ($r) {
            $row = (array) $r;
            $weight   = (float) ($row['weight'] ?? 0);
            $stonewgt = (float) ($row['stonewgt'] ?? 0);
            $smithmc  = (float) ($row['smithmc'] ?? 0);

            // Display name: salesd.name if set, else items.name
            $dname = trim((string) ($row['dname'] ?? ''));
            $row['displayname'] = $dname !== '' ? $dname : trim((string) ($row['itemname'] ?? ''));

            // grosswgt = weight - stonewgt
            $row['grosswgt'] = $weight - $stonewgt;

            // tsmithmc = (weight - stonewgt) * smithmc
            $row['tsmithmc'] = ($weight - $stonewgt) * $smithmc;

            return $row;
        })->values()->all();

        // Build totals split by itype
        $totals = [
            'gold_weight' => 0, 'gold_grosswgt' => 0, 'gold_amount' => 0,
            'silver_weight' => 0, 'silver_grosswgt' => 0, 'silver_amount' => 0,
            'total_stonewgt' => 0, 'total_tsmithmc' => 0,
            'count' => count($rows),
        ];
        $seenSlnos = [];
        $totalBillamt = 0;
        foreach ($rows as $row) {
            $itype = strtoupper(trim((string) ($row['itype'] ?? '')));
            $wgt = (float) ($row['weight'] ?? 0);
            $gwgt = (float) ($row['grosswgt'] ?? 0);
            $amt = (float) ($row['amount'] ?? 0);

            if ($itype === 'G') {
                $totals['gold_weight']   += $wgt;
                $totals['gold_grosswgt'] += $gwgt;
                $totals['gold_amount']   += $amt;
            } elseif ($itype === 'S') {
                $totals['silver_weight']   += $wgt;
                $totals['silver_grosswgt'] += $gwgt;
                $totals['silver_amount']   += $amt;
            }
            $totals['total_stonewgt'] += (float) ($row['stonewgt'] ?? 0);
            $totals['total_tsmithmc'] += (float) ($row['tsmithmc'] ?? 0);

            $slno = $row['slno'] ?? 0;
            if (!isset($seenSlnos[$slno])) {
                $seenSlnos[$slno] = true;
                $totalBillamt += (float) ($row['billamt'] ?? 0);
            }
        }
        $totals['total_billamt'] = $totalBillamt;

        return response()->json(['ok' => true, 'rows' => $rows, 'totals' => $totals]);
    }

    private function hasCol(string $table, string $col): bool
    {
        $key = "$table.$col";
        if (!array_key_exists($key, $this->colCache)) {
            $this->colCache[$key] = Schema::hasColumn($table, $col);
        }
        return $this->colCache[$key];
    }
}
