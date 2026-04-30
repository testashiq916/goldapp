<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StockVerificationController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $counters = $this->hasTable('counter')
            ? DB::table('counter')->select('code', 'name')->orderBy('code')->get()->toArray()
            : [];

        $groups = $this->hasTable('itemgrp')
            ? DB::table('itemgrp')->select('code', 'name')->orderBy('code')->get()->toArray()
            : [];

        $subgroups = $this->hasTable('itemsubgrp')
            ? DB::table('itemsubgrp')->select('code', 'name')->orderBy('code')->get()->toArray()
            : [];

        return view('stock-verification.index', [
            'title'     => 'Barcode Stock Verification',
            'counters'  => $counters,
            'groups'    => $groups,
            'subgroups' => $subgroups,
        ]);
    }

    // Lookup a barcode for scanning
    public function lookupBarcode(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $bcode = (int) $request->query('bcode', 0);
        if ($bcode <= 0) return response()->json(['ok' => false, 'message' => 'Invalid barcode']);

        if (!$this->hasTable('barcode')) return response()->json(['ok' => false, 'message' => 'Barcode table missing']);

        $bcCols = array_map('strtolower', $this->columnList('barcode'));

        $row = DB::table('barcode')->where('bcode', $bcode)->first();
        if (!$row) return response()->json(['ok' => false, 'message' => "Barcode {$bcode} not found", 'notfound' => true]);

        // Check if already taken (scanned)
        $taken = in_array('taken', $bcCols) ? (int) ($row->taken ?? 0) : 0;
        if ($taken === 1) {
            return response()->json(['ok' => false, 'message' => trim($row->icode ?? '') . " - Already scanned (taken)", 'alreadytaken' => true]);
        }

        // Check stock status
        $stk = strtoupper(trim($row->stk ?? 'N'));
        $outofstock = ($stk === 'N');

        // Get item name
        $itemname = '';
        if ($this->hasTable('items')) {
            $itemname = trim((string) (DB::table('items')->where('code', trim($row->icode ?? ''))->value('name') ?? ''));
        }

        // Get diamond weight from barcodedmd
        $dmdwgt = (float) ($row->dmdwgt ?? 0);
        if ($this->hasTable('barcodedmd')) {
            $dmd = DB::table('barcodedmd')->where('bcode', $bcode)->value('dmdwgt');
            if ($dmd !== null) $dmdwgt = (float) $dmd;
        }

        // Get diamond cost from barcode_dmddet
        $dmdcost = 0;
        if ($this->hasTable('barcode_dmddet')) {
            $dmdcost = (float) DB::table('barcode_dmddet')->where('bcode', $bcode)->sum('pamt');
        }

        // Mark as taken
        if (in_array('taken', $bcCols)) {
            DB::table('barcode')->where('bcode', $bcode)->update(['taken' => 1]);
        }

        // Counter info
        $counter = trim($row->counter ?? '');

        return response()->json([
            'ok'         => true,
            'bcode'      => (int) $row->bcode,
            'icode'      => trim($row->icode ?? ''),
            'itemname'   => $itemname,
            'qty'        => (int) ($row->qty ?? 0),
            'weight'     => (float) ($row->weight ?? 0),
            'stweight'   => (float) ($row->stweight ?? 0),
            'netwgt'     => round((float) ($row->weight ?? 0) - (float) ($row->stweight ?? 0), 3),
            'dmdwgt'     => $dmdwgt,
            'tdate'      => $row->tdate ?? '',
            'stk'        => $stk,
            'outofstock' => $outofstock,
            'rate'       => (float) ($row->rate ?? 0),
            'touch'      => in_array('stktouch', $bcCols) ? (float) ($row->stktouch ?? 0) : 0,
            'cost'       => (float) ($row->cost ?? 0),
            'dmdcost'    => $dmdcost,
            'transtouch' => in_array('transtouch', $bcCols) ? (float) ($row->transtouch ?? 0) : 0,
            'counter'    => $counter,
        ]);
    }

    // Delete item from list and reset taken=0
    public function deleteItem(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $bcode = (int) $request->input('bcode', 0);
        if ($bcode <= 0) return response()->json(['ok' => false, 'message' => 'Invalid barcode']);

        if (!$this->hasTable('barcode')) return response()->json(['ok' => false, 'message' => 'Barcode table missing']);

        $bcCols = array_map('strtolower', $this->columnList('barcode'));
        if (in_array('taken', $bcCols)) {
            DB::table('barcode')->where('bcode', $bcode)->update(['taken' => 0]);
        }

        return response()->json(['ok' => true]);
    }

    // Fresh mark: reset taken=0 for specific item code
    public function freshMark(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $icode = trim((string) $request->input('icode', ''));
        if ($icode === '') return response()->json(['ok' => false, 'message' => 'No item code specified']);

        if (!$this->hasTable('barcode')) return response()->json(['ok' => false, 'message' => 'Barcode table missing']);

        $bcCols = array_map('strtolower', $this->columnList('barcode'));
        if (in_array('taken', $bcCols)) {
            $updated = DB::table('barcode')->where('icode', $icode)->update(['taken' => 0]);
            return response()->json(['ok' => true, 'message' => "Reset {$updated} items for {$icode}"]);
        }

        return response()->json(['ok' => true, 'message' => 'No taken column']);
    }

    // Finished: compare taken vs stk for all matching barcodes
    public function finished(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $counter  = trim((string) $request->input('counter', ''));
        $group    = trim((string) $request->input('group', ''));
        $subgroup = trim((string) $request->input('subgroup', ''));
        $icode    = trim((string) $request->input('icode', ''));
        $bcBased  = (bool) $request->input('bcBased', false);

        if (!$this->hasTable('barcode')) return response()->json(['ok' => false, 'message' => 'Barcode table missing']);

        $bcCols = array_map('strtolower', $this->columnList('barcode'));

        $query = DB::table('barcode')
            ->leftJoin('items', 'barcode.icode', '=', 'items.code')
            ->select('barcode.bcode', 'barcode.icode', 'barcode.stk',
                     'barcode.weight', 'barcode.stweight', 'barcode.qty',
                     'items.name as itemname');

        if (in_array('taken', $bcCols)) {
            $query->addSelect('barcode.taken');
        }

        // Apply filters
        if ($counter !== '') {
            $query->where('barcode.counter', $counter);
        }
        if ($group !== '') {
            $query->where('items.grpcode', $group);
        }
        if ($subgroup !== '') {
            if ($bcBased && in_array('subgrp', $bcCols)) {
                $query->where('barcode.subgrp', $subgroup);
            } else {
                $query->where('items.subgrpcode', $subgroup);
            }
        }
        if ($icode !== '') {
            $query->where('barcode.icode', $icode);
        }

        // Only items that are in stock (stk=Y) or taken
        $rows = $query->get();

        $nonStockScanned = [];  // taken=1 but stk=N
        $stockNotScanned = []; // taken=0 but stk=Y

        foreach ($rows as $r) {
            $taken = (int) ($r->taken ?? 0);
            $stk   = strtoupper(trim($r->stk ?? 'N'));

            if ($taken === 1 && $stk === 'N') {
                $nonStockScanned[] = [
                    'bcode'    => (int) $r->bcode,
                    'icode'    => trim($r->icode ?? ''),
                    'itemname' => trim($r->itemname ?? ''),
                    'qty'      => (int) ($r->qty ?? 0),
                    'weight'   => (float) ($r->weight ?? 0),
                    'stweight' => (float) ($r->stweight ?? 0),
                    'type'     => 'Non Stock But Scanned',
                ];
            } elseif ($taken === 0 && $stk === 'Y') {
                $stockNotScanned[] = [
                    'bcode'    => (int) $r->bcode,
                    'icode'    => trim($r->icode ?? ''),
                    'itemname' => trim($r->itemname ?? ''),
                    'qty'      => (int) ($r->qty ?? 0),
                    'weight'   => (float) ($r->weight ?? 0),
                    'stweight' => (float) ($r->stweight ?? 0),
                    'type'     => 'Stock Exist But Not Scanned',
                ];
            }
        }

        return response()->json([
            'ok' => true,
            'nonStockScanned' => $nonStockScanned,
            'stockNotScanned' => $stockNotScanned,
        ]);
    }

    // Update this list as correct: stk=Y where taken=1, stk=N where taken=0
    public function updateAsCorrect(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $counter  = trim((string) $request->input('counter', ''));
        $group    = trim((string) $request->input('group', ''));
        $subgroup = trim((string) $request->input('subgroup', ''));
        $icode    = trim((string) $request->input('icode', ''));
        $bcBased  = (bool) $request->input('bcBased', false);

        if (!$this->hasTable('barcode')) return response()->json(['ok' => false, 'message' => 'Barcode table missing']);

        $bcCols = array_map('strtolower', $this->columnList('barcode'));
        if (!in_array('taken', $bcCols)) {
            return response()->json(['ok' => false, 'message' => 'Taken column not found']);
        }

        // Build base query for filtering
        $baseWhere = function ($query) use ($counter, $group, $subgroup, $icode, $bcBased, $bcCols) {
            if ($counter !== '') {
                $query->where('barcode.counter', $counter);
            }
            if ($group !== '' && $this->hasTable('items')) {
                $query->whereIn('barcode.icode', function ($q) use ($group) {
                    $q->select('code')->from('items')->where('grpcode', $group);
                });
            }
            if ($subgroup !== '') {
                if ($bcBased && in_array('subgrp', $bcCols)) {
                    $query->where('barcode.subgrp', $subgroup);
                } elseif ($this->hasTable('items')) {
                    $query->whereIn('barcode.icode', function ($q) use ($subgroup) {
                        $q->select('code')->from('items')->where('subgrpcode', $subgroup);
                    });
                }
            }
            if ($icode !== '') {
                $query->where('barcode.icode', $icode);
            }
        };

        // Set stk=Y where taken=1
        $setY = DB::table('barcode')->where('taken', 1);
        $baseWhere($setY);
        $countY = $setY->update(['stk' => 'Y']);

        // Set stk=N where taken=0
        $setN = DB::table('barcode')->where('taken', 0);
        $baseWhere($setN);
        $countN = $setN->update(['stk' => 'N']);

        return response()->json([
            'ok' => true,
            'message' => "Updated: {$countY} items set as Stock, {$countN} items set as Non-Stock",
        ]);
    }

    // Get summary grouped by item code
    public function summary(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $bcodes = (array) $request->input('bcodes', []);

        if (empty($bcodes)) return response()->json(['ok' => true, 'rows' => []]);

        if (!$this->hasTable('barcode')) return response()->json(['ok' => true, 'rows' => []]);

        $rows = DB::table('barcode')
            ->leftJoin('items', 'barcode.icode', '=', 'items.code')
            ->whereIn('barcode.bcode', $bcodes)
            ->select(
                'barcode.icode',
                'items.name as itemname',
                'items.stkinnos',
                DB::raw('SUM(barcode.qty) as tqty'),
                DB::raw('SUM(barcode.weight) as tweight'),
                DB::raw('SUM(barcode.stweight) as tstweight'),
                DB::raw('SUM(barcode.rate) as trate')
            )
            ->groupBy('barcode.icode', 'items.name', 'items.stkinnos')
            ->orderBy('barcode.icode')
            ->get()
            ->map(function ($r) {
                $stkinnos = strtoupper(trim($r->stkinnos ?? 'N'));
                $tqty     = (int) ($r->tqty ?? 0);
                $tweight  = (float) ($r->tweight ?? 0);
                $tstweight = (float) ($r->tstweight ?? 0);
                $trate    = (float) ($r->trate ?? 0);
                // Amount: if stkinnos=Y then qty*rate, else (weight-stweight)*rate
                $amount = $stkinnos === 'Y'
                    ? $tqty * $trate
                    : ($tweight - $tstweight) * $trate;

                return [
                    'icode'    => trim($r->icode ?? ''),
                    'itemname' => trim($r->itemname ?? ''),
                    'qty'      => $tqty,
                    'weight'   => $tweight,
                    'stweight' => $tstweight,
                    'amount'   => round($amount, 2),
                ];
            })->values()->all();

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    // Import barcodes from file (text file with one barcode per line)
    public function importFile(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $bcodes = (array) $request->input('bcodes', []);
        if (empty($bcodes)) return response()->json(['ok' => false, 'message' => 'No barcodes provided']);

        if (!$this->hasTable('barcode')) return response()->json(['ok' => false, 'message' => 'Barcode table missing']);

        $bcCols = array_map('strtolower', $this->columnList('barcode'));
        $results = ['found' => 0, 'notfound' => 0, 'alreadytaken' => 0, 'outofstock' => 0, 'items' => [], 'errors' => []];

        foreach ($bcodes as $bc) {
            $bcode = (int) $bc;
            if ($bcode <= 0) continue;

            $row = DB::table('barcode')->where('bcode', $bcode)->first();
            if (!$row) {
                $results['notfound']++;
                $results['errors'][] = "Barcode {$bcode} not found";
                continue;
            }

            $taken = in_array('taken', $bcCols) ? (int) ($row->taken ?? 0) : 0;
            if ($taken === 1) {
                $results['alreadytaken']++;
                $results['errors'][] = trim($row->icode ?? '') . " ({$bcode}) - Already taken";
                continue;
            }

            $stk = strtoupper(trim($row->stk ?? 'N'));
            $outofstock = ($stk === 'N');
            if ($outofstock) {
                $results['outofstock']++;
            }

            // Get item name
            $itemname = '';
            if ($this->hasTable('items')) {
                $itemname = trim((string) (DB::table('items')->where('code', trim($row->icode ?? ''))->value('name') ?? ''));
            }

            // Get diamond weight
            $dmdwgt = (float) ($row->dmdwgt ?? 0);
            if ($this->hasTable('barcodedmd')) {
                $dmd = DB::table('barcodedmd')->where('bcode', $bcode)->value('dmdwgt');
                if ($dmd !== null) $dmdwgt = (float) $dmd;
            }

            // Get diamond cost
            $dmdcost = 0;
            if ($this->hasTable('barcode_dmddet')) {
                $dmdcost = (float) DB::table('barcode_dmddet')->where('bcode', $bcode)->sum('pamt');
            }

            // Mark as taken
            if (in_array('taken', $bcCols)) {
                DB::table('barcode')->where('bcode', $bcode)->update(['taken' => 1]);
            }

            $results['found']++;
            $results['items'][] = [
                'bcode'      => (int) $row->bcode,
                'icode'      => trim($row->icode ?? ''),
                'itemname'   => $itemname,
                'qty'        => (int) ($row->qty ?? 0),
                'weight'     => (float) ($row->weight ?? 0),
                'stweight'   => (float) ($row->stweight ?? 0),
                'netwgt'     => round((float) ($row->weight ?? 0) - (float) ($row->stweight ?? 0), 3),
                'dmdwgt'     => $dmdwgt,
                'stk'        => $stk,
                'outofstock' => $outofstock,
                'rate'       => (float) ($row->rate ?? 0),
                'touch'      => in_array('stktouch', $bcCols) ? (float) ($row->stktouch ?? 0) : 0,
                'cost'       => (float) ($row->cost ?? 0),
                'dmdcost'    => $dmdcost,
                'counter'    => trim($row->counter ?? ''),
            ];
        }

        return response()->json(['ok' => true, ...$results]);
    }
}
