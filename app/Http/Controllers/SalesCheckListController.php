<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SalesCheckListController extends Controller
{
    private array $colCache = [];

    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $rlevel = (int) $request->session()->get('gilevel', 1);
        if ($rlevel <= 0) $rlevel = 1;

        $salesmen = [];
        if ($this->hasTable('sman')) {
            $salesmen = DB::table('sman')->select('code', 'name')->orderBy('code')->get()
                ->map(fn($r) => ['code' => trim($r->code), 'name' => trim($r->name)])->values()->all();
        }

        return view('reports.sales-check-list', [
            'title'    => 'Sales Check List',
            'date1'    => date('Y-m-d'),
            'date2'    => date('Y-m-d'),
            'rlevel'   => $rlevel,
            'salesmen' => $salesmen,
        ]);
    }

    /* ── Data endpoint ─────────────────────────────────────────────── */
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
        $custcode = trim((string) $request->query('custcode', ''));
        $billno   = trim((string) $request->query('billno', ''));

        if (!$this->hasTable('salesm')) {
            return response()->json(['ok' => true, 'bills' => []]);
        }

        /* ── Master bills ──────────────────────────────────────────── */
        $q = DB::table('salesm as m')
            ->selectRaw('m.slno, m.tdate, m.billno, m.custname')
            ->selectRaw($this->colExpr('salesm', 'custcode', 'm', 'custcode', "''"))
            ->selectRaw($this->colExpr('salesm', 'addr', 'm', 'addr', "''"))
            ->selectRaw($this->colExpr('salesm', 'pan', 'm', 'pan', "''"))
            ->selectRaw($this->colExpr('salesm', 'note', 'm', 'note', "''"))
            ->selectRaw($this->colExpr('salesm', 'grate', 'm', 'grate', '0'))
            ->selectRaw($this->colExpr('salesm', 'billamt', 'm', 'billamt', '0'))
            ->selectRaw($this->colExpr('salesm', 'eamt', 'm', 'eamt', '0'))
            ->selectRaw($this->colExpr('salesm', 'sretamt', 'm', 'sretamt', '0'))
            ->selectRaw($this->colExpr('salesm', 'staxamt', 'm', 'staxamt', '0'))
            ->selectRaw($this->colExpr('salesm', 'astamt', 'm', 'astamt', '0'))
            ->selectRaw($this->colExpr('salesm', 'discount', 'm', 'discount', '0'))
            ->selectRaw($this->colExpr('salesm', 'advance', 'm', 'advance', '0'))
            ->selectRaw($this->colExpr('salesm', 'round', 'm', 'round_amt', '0'))
            ->selectRaw($this->colExpr('salesm', 'ramt', 'm', 'ramt', '0'))
            ->selectRaw($this->colExpr('salesm', 'smcode', 'm', 'smcode', "''"))
            ->selectRaw($this->colExpr('salesm', 'duedate', 'm', 'duedate', "''"))
            ->selectRaw($this->colExpr('salesm', 'ttime', 'm', 'ttime', "''"));

        // SM name subquery
        if ($this->hasTable('sman') && $this->hasCol('salesm', 'smcode')) {
            $q->selectRaw("(SELECT s.name FROM sman s WHERE s.code = m.smcode LIMIT 1) as smname");
        } else {
            $q->selectRaw("'' as smname");
        }

        $q->whereBetween('m.tdate', [$date1, $date2]);
        if ($this->hasCol('salesm', 'control')) $q->where('m.control', '<=', $rlevel);
        if ($this->hasCol('salesm', 'sr'))      $q->where('m.sr', 'S');
        if ($this->hasCol('salesm', 'opbill'))  $q->where('m.opbill', '<>', 1);

        if ($smcode !== '' && $this->hasCol('salesm', 'smcode'))
            $q->where('m.smcode', $smcode);
        if ($custcode !== '' && $this->hasCol('salesm', 'custcode'))
            $q->whereRaw('TRIM(m.custcode) = ?', [$custcode]);
        if ($billno !== '' && $this->hasCol('salesm', 'billno'))
            $q->whereRaw('TRIM(m.billno) = ?', [$billno]);

        $bills = $q->orderBy('m.tdate')->orderBy('m.slno')->get()->map(function ($r) {
            $row = (array) $r;
            // PB formula: tnetamt1 = billamt + staxamt - eamt - sretamt + round
            $tnetamt1 = (float)($row['billamt']??0) + (float)($row['staxamt']??0) - (float)($row['eamt']??0) - (float)($row['sretamt']??0) + (float)($row['round_amt']??0);
            // tnetamt2 = tnetamt1 - discount - advance + staxamt + astamt
            $tnetamt2 = $tnetamt1 - (float)($row['discount']??0) - (float)($row['advance']??0) + (float)($row['staxamt']??0) + (float)($row['astamt']??0);
            $row['nettotal'] = $tnetamt1;
            $row['netamt']   = $tnetamt2;
            $row['balance']  = $tnetamt2 - (float)($row['ramt']??0);
            return $row;
        })->values()->all();

        /* ── Detail items per bill ─────────────────────────────────── */
        if ($this->hasTable('salesd') && $this->hasTable('items') && count($bills) > 0) {
            $slnos = array_column($bills, 'slno');
            $items = DB::table('salesd as d')
                ->join('items as i', 'd.code', '=', 'i.code')
                ->select('d.slno', 'd.sno', 'i.name as item_name', 'd.code as item_code', 'd.qty', 'd.weight', 'd.rate', 'd.amount')
                ->when($this->hasCol('salesd', 'stonewgt'), fn($q) => $q->addSelect('d.stonewgt'), fn($q) => $q->selectRaw('0 as stonewgt'))
                ->when($this->hasCol('salesd', 'mcharge'), fn($q) => $q->addSelect('d.mcharge'), fn($q) => $q->selectRaw('0 as mcharge'))
                ->when($this->hasCol('salesd', 'stoneprice'), fn($q) => $q->addSelect('d.stoneprice'), fn($q) => $q->selectRaw('0 as stoneprice'))
                ->whereIn('d.slno', $slnos)
                ->orderBy('d.slno')->orderBy('d.sno')
                ->get()->groupBy('slno');

            foreach ($bills as &$bill) {
                $bill['items'] = isset($items[$bill['slno']])
                    ? $items[$bill['slno']]->map(fn($r) => (array) $r)->values()->all()
                    : [];
            }
            unset($bill);
        } else {
            foreach ($bills as &$bill) {
                $bill['items'] = [];
            }
            unset($bill);
        }

        return response()->json(['ok' => true, 'bills' => $bills]);
    }

    /* ── Column helpers ────────────────────────────────────────────── */
    private function hasCol(string $table, string $col): bool
    {
        $key = "$table.$col";
        if (!array_key_exists($key, $this->colCache)) {
            $this->colCache[$key] = $this->hasTable($table) && Schema::hasColumn($table, $col);
        }
        return $this->colCache[$key];
    }

    private function colExpr(string $table, string $col, string $alias, string $as, string $fallback): string
    {
        return $this->hasCol($table, $col) ? "$alias.$col as $as" : "$fallback as $as";
    }
}
