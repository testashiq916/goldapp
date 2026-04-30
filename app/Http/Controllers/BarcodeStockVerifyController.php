<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BarcodeStockVerifyController extends Controller
{
    private int $gilevel = 1;

    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $this->gilevel = (int) ($request->session()->get('user_level', 1));

        $date       = $request->query('date', date('Y-m-d'));
        $counter    = trim($request->query('counter', ''));
        $counterFilter = $request->has('counter_filter');
        $showData   = $request->has('show');

        $rows   = [];
        $totals = [
            'counteriqty' => 0, 'counteriwgt' => 0.0,
            'countersqty' => 0, 'counterswgt' => 0.0,
            'balqty' => 0, 'balwgt' => 0.0,
            'verifyqty' => 0, 'verifywgt' => 0.0,
            'diffqty' => 0, 'diffwgt' => 0.0,
        ];

        $counters = $this->hasTable('counter')
            ? DB::table('counter')->orderBy('name')->get(['code', 'name'])->toArray() : [];

        if ($showData && $this->hasTable('barcode') && $this->hasTable('items')) {
            $rows = $this->loadData($date, $counterFilter ? $counter : null);
            foreach ($rows as $r) {
                $totals['counteriqty'] += (int) $r['counteriqty'];
                $totals['counteriwgt'] += (float) $r['counteriwgt'];
                $totals['countersqty'] += (int) $r['countersqty'];
                $totals['counterswgt'] += (float) $r['counterswgt'];
                $totals['balqty']      += (int) $r['balqty'];
                $totals['balwgt']      += (float) $r['balwgt'];
                $totals['verifyqty']   += (int) $r['verifyqty'];
                $totals['verifywgt']   += (float) $r['verifywgt'];
                $totals['diffqty']     += (int) $r['diffqty'];
                $totals['diffwgt']     += (float) $r['diffwgt'];
            }
        }

        return view('barcode-list.stock-verify', compact(
            'date', 'counter', 'counterFilter', 'showData',
            'rows', 'totals', 'counters'
        ));
    }

    private function loadData(string $date, ?string $counter): array
    {
        $gl = $this->gilevel;

        // Get all items that have barcodes with counter assigned
        $items = DB::table('items')
            ->select(['items.code', 'items.name', 'items.itype', 'items.grpcode'])
            ->whereExists(function ($q) use ($counter) {
                $q->select(DB::raw(1))
                    ->from('barcode')
                    ->whereColumn('barcode.icode', 'items.code')
                    ->where('barcode.counter', '<>', '');
                if ($counter !== null && $counter !== '') {
                    $q->where('barcode.counter', $counter);
                }
            })
            ->orderBy('items.code')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->toArray();

        $hasSalesd = $this->hasTable('salesd') && $this->hasTable('salesm');

        foreach ($items as &$item) {
            $code = $item['code'];

            $counterWhere = function ($q) use ($counter) {
                $q->where('barcode.counter', '<>', '');
                if ($counter !== null && $counter !== '') {
                    $q->where('barcode.counter', $counter);
                }
            };

            // Issue to counter: total qty/weight in barcode for this item+counter
            $issue = DB::table('barcode')
                ->where('icode', $code)
                ->where('counter', '<>', '')
                ->when($counter !== null && $counter !== '', fn ($q) => $q->where('counter', $counter))
                ->selectRaw('COALESCE(SUM(qty),0) as qty, COALESCE(SUM(weight),0) as wgt')
                ->first();
            $item['counteriqty'] = (int) $issue->qty;
            $item['counteriwgt'] = (float) $issue->wgt;

            // Sales: qty/weight sold today from this counter
            $item['countersqty'] = 0;
            $item['counterswgt'] = 0;
            if ($hasSalesd) {
                $sales = DB::table('salesd')
                    ->join('salesm', 'salesd.slno', '=', 'salesm.slno')
                    ->join('barcode', 'salesd.bcode', '=', 'barcode.bcode')
                    ->where('salesm.tdate', $date)
                    ->where('salesm.control', '<=', $gl)
                    ->where('barcode.icode', $code)
                    ->where('barcode.counter', '<>', '')
                    ->when($counter !== null && $counter !== '', fn ($q) => $q->where('barcode.counter', $counter))
                    ->selectRaw('COALESCE(SUM(salesd.qty),0) as qty, COALESCE(SUM(salesd.weight),0) as wgt')
                    ->first();
                $item['countersqty'] = (int) $sales->qty;
                $item['counterswgt'] = (float) $sales->wgt;
            }

            // Balance = issued - sold
            $item['balqty'] = $item['counteriqty'] - $item['countersqty'];
            $item['balwgt'] = $item['counteriwgt'] - $item['counterswgt'];

            // Verified: barcode.taken = 1
            $verify = DB::table('barcode')
                ->where('icode', $code)
                ->where('counter', '<>', '')
                ->where('taken', 1)
                ->when($counter !== null && $counter !== '', fn ($q) => $q->where('counter', $counter))
                ->selectRaw('COALESCE(SUM(qty),0) as qty, COALESCE(SUM(weight),0) as wgt')
                ->first();
            $item['verifyqty'] = (int) $verify->qty;
            $item['verifywgt'] = (float) $verify->wgt;

            // Difference = balance - verified
            $item['diffqty'] = $item['balqty'] - $item['verifyqty'];
            $item['diffwgt'] = $item['balwgt'] - $item['verifywgt'];
        }
        unset($item);

        // Filter out items with all zeros
        $items = array_filter($items, fn ($r) =>
            $r['counteriqty'] || $r['counteriwgt'] || $r['verifyqty'] || $r['verifywgt']
        );

        return array_values($items);
    }
}
