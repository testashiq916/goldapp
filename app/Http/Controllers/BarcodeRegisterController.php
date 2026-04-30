<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BarcodeRegisterController extends Controller
{
    private array $colCache = [];

    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('reports.barcode-register', [
            'title'  => 'Barcode Register',
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

        $date1 = (string) $request->query('date1', date('Y-m-d'));
        $date2 = (string) $request->query('date2', date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date1) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date2)) {
            return response()->json(['ok' => false, 'message' => 'Invalid date'], 422);
        }

        $rlevel = (int) $request->query('rlevel', (int) $request->session()->get('gilevel', 1));

        if (!$this->hasTable('salesm') || !$this->hasTable('salesd')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => []]);
        }

        $q = DB::table('salesd as d')
            ->join('salesm as m', 'm.slno', '=', 'd.slno')
            ->leftJoin('items as i', 'i.code', '=', 'd.code')
            ->select(
                'm.tdate', 'm.billno', 'm.custname',
                'd.code as itemcode', 'd.bcode', 'd.qty', 'd.weight',
                'd.amount'
            );

        if ($this->hasCol('salesd', 'dmdwgt'))  $q->addSelect('d.dmdwgt');  else $q->selectRaw('0 as dmdwgt');
        if ($this->hasCol('salesd', 'dmdunit')) $q->addSelect('d.dmdunit'); else $q->selectRaw("'' as dmdunit");
        if ($this->hasCol('salesd', 'dmdamt'))  $q->addSelect('d.dmdamt');  else $q->selectRaw('0 as dmdamt');

        $q->addSelect(DB::raw($this->hasCol('items', 'name') ? 'i.name as itemname' : "'' as itemname"));

        $q->whereBetween('m.tdate', [$date1, $date2]);
        if ($this->hasCol('salesm', 'control')) $q->where('m.control', '<=', $rlevel);
        $q->where('d.bcode', '>', 0);

        $q->orderBy('m.tdate')->orderBy('m.slno');

        $rows = $q->get()->map(fn($r) => (array) $r)->values()->all();

        $totals = ['qty' => 0, 'weight' => 0, 'dmdwgt' => 0, 'dmdamt' => 0, 'amount' => 0, 'count' => count($rows)];
        foreach ($rows as $row) {
            $totals['qty']    += (int) ($row['qty'] ?? 0);
            $totals['weight'] += (float) ($row['weight'] ?? 0);
            $totals['dmdwgt'] += (float) ($row['dmdwgt'] ?? 0);
            $totals['dmdamt'] += (float) ($row['dmdamt'] ?? 0);
            $totals['amount'] += (float) ($row['amount'] ?? 0);
        }

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
