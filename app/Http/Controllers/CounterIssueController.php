<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CounterIssueController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $counters = $this->hasTable('counter')
            ? DB::table('counter')->select('code', 'name')->orderBy('code')->get()->toArray()
            : [];

        return view('counter-issue.index', [
            'title'    => 'Items Issue to Counter',
            'counters' => $counters,
        ]);
    }

    // Load items currently assigned to a counter
    public function loadItems(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $counter = trim($request->query('counter', ''));

        if (!$this->hasTable('barcode')) {
            return response()->json(['ok' => true, 'rows' => []]);
        }

        $query = DB::table('barcode')
            ->leftJoin('items', 'barcode.icode', '=', 'items.code')
            ->select(
                'barcode.bcode', 'barcode.icode', 'barcode.qty', 'barcode.weight',
                'barcode.stweight', 'barcode.dmdwgt', 'barcode.tdate', 'barcode.stk',
                'barcode.counter', 'barcode.rate', 'barcode.cost',
                'items.name as itemname'
            );

        // Check optional columns
        $bcCols = array_map('strtolower', $this->columnList('barcode'));
        if (in_array('stktouch', $bcCols)) {
            $query->addSelect('barcode.stktouch');
        }
        if (in_array('transtouch', $bcCols)) {
            $query->addSelect('barcode.transtouch');
        }

        $query->where('barcode.counter', $counter)
              ->orderBy('barcode.bcode');

        $rows = $query->get()->map(function ($r) {
            return [
                'bcode'      => (int) ($r->bcode ?? 0),
                'icode'      => trim($r->icode ?? ''),
                'itemname'   => trim($r->itemname ?? ''),
                'qty'        => (int) ($r->qty ?? 0),
                'weight'     => (float) ($r->weight ?? 0),
                'stweight'   => (float) ($r->stweight ?? 0),
                'netwgt'     => round((float) ($r->weight ?? 0) - (float) ($r->stweight ?? 0), 3),
                'dmdwgt'     => (float) ($r->dmdwgt ?? 0),
                'tdate'      => $r->tdate ?? '',
                'stk'        => trim($r->stk ?? ''),
                'rate'       => (float) ($r->rate ?? 0),
                'touch'      => (float) ($r->stktouch ?? 0),
                'cost'       => (float) ($r->cost ?? 0),
                'transtouch' => (float) ($r->transtouch ?? 0),
            ];
        })->values()->all();

        // Get counter name
        $counterName = '';
        if ($counter !== '' && $this->hasTable('counter')) {
            $counterName = trim((string) (DB::table('counter')->where('code', $counter)->value('name') ?? ''));
        }

        return response()->json(['ok' => true, 'rows' => $rows, 'counterName' => $counterName]);
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
        if (!$row) return response()->json(['ok' => false, 'message' => 'Barcode not found']);

        $stk = strtoupper(trim($row->stk ?? 'N'));
        if ($stk === 'N') {
            return response()->json(['ok' => false, 'message' => trim($row->icode ?? '') . ' - Out of stock', 'outofstock' => true]);
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
            'rate'       => (float) ($row->rate ?? 0),
            'touch'      => in_array('stktouch', $bcCols) ? (float) ($row->stktouch ?? 0) : 0,
            'cost'       => (float) ($row->cost ?? 0),
            'transtouch' => in_array('transtouch', $bcCols) ? (float) ($row->transtouch ?? 0) : 0,
        ]);
    }

    // Update counter for all scanned barcodes
    public function updateCounter(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $counter = trim((string) $request->input('counter', ''));
        $bcodes  = (array) $request->input('bcodes', []);

        if (empty($bcodes)) return response()->json(['ok' => false, 'message' => 'No items to update']);

        if (!$this->hasTable('barcode')) return response()->json(['ok' => false, 'message' => 'Barcode table missing']);

        $updated = 0;
        foreach ($bcodes as $bcode) {
            $bcode = (int) $bcode;
            if ($bcode <= 0) continue;
            DB::table('barcode')->where('bcode', $bcode)->update(['counter' => $counter]);
            $updated++;
        }

        return response()->json(['ok' => true, 'message' => "Updated {$updated} items", 'updated' => $updated]);
    }

    // Refresh / clear counter assignment
    public function refreshCounter(Request $request): JsonResponse
    {
        if (!$request->session()->has('user_code')) return response()->json(['ok' => false], 401);

        $counter = trim((string) $request->input('counter', ''));

        if (!$this->hasTable('barcode')) return response()->json(['ok' => false, 'message' => 'Barcode table missing']);

        $bcCols = array_map('strtolower', $this->columnList('barcode'));

        if ($counter === '') {
            // Clear taken flag for all
            if (in_array('taken', $bcCols)) {
                DB::table('barcode')->update(['taken' => 0]);
            }
        } else {
            // Clear counter + taken for specific counter
            $upd = ['counter' => ''];
            if (in_array('taken', $bcCols)) {
                $upd['taken'] = 0;
            }
            DB::table('barcode')->where('counter', $counter)->update($upd);
        }

        return response()->json(['ok' => true, 'message' => 'Counter refreshed']);
    }
}
