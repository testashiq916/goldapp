<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurchaseReturnRegisterController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('reports.purchase-return-register', [
            'title'  => 'Purchase Return Register',
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

        $date1    = (string) $request->query('date1', date('Y-m-d'));
        $date2    = (string) $request->query('date2', date('Y-m-d'));
        $rlevel   = (int) $request->query('rlevel', (int) $request->session()->get('gilevel', 1));
        $suppcode = trim((string) $request->query('suppcode', ''));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date1) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date2)) {
            return response()->json(['ok' => false, 'message' => 'Invalid date'], 422);
        }

        if (!$this->hasTable('purchaserd') || !$this->hasTable('purchaserm') || !$this->hasTable('items')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => $this->emptyTotals()]);
        }

        $q = DB::table('purchaserd as d')
            ->join('purchaserm as m', 'm.slno', '=', 'd.slno')
            ->join('items as i', 'i.code', '=', 'd.code')
            ->select(
                'm.slno', 'm.tdate', 'm.docno', 'm.suppcode', 'm.name as suppname',
                'm.billamt', 'm.smcode',
                'd.code as itemcode', 'i.name as itemname', 'i.itype',
                'd.rate', 'd.qty', 'd.weight', 'd.lesswgt',
                'd.stwgt', 'd.stprice', 'd.amount', 'd.stktype'
            );
        $q->selectRaw($this->partyGstExpr('purchaserm', 'm', 'suppcode', 'gstno', ['tin', 'tinno']));
        if (Schema::hasColumn('purchaserm', 'billno')) {
            $q->addSelect('m.billno');
        } else {
            $q->selectRaw("'' as billno");
        }

        // Optional columns
        if (Schema::hasColumn('purchaserd', 'name')) {
            $q->addSelect('d.name as dname');
        } else {
            $q->selectRaw("'' as dname");
        }

        $q->whereBetween('m.tdate', [$date1, $date2]);
        $q->where('m.control', '<=', $rlevel);

        if ($suppcode !== '') {
            $q->where('m.suppcode', $suppcode);
        }
        $this->applyTransferShadowDocFilter($q, 'm.docno');

        $q->orderBy('i.name')->orderBy('m.tdate')->orderBy('m.docno');

        $rows = $q->get()->map(function ($r) {
            $row = (array) $r;
            $billNo = trim((string) ($row['billno'] ?? ''));
            $row['billno'] = $billNo !== '' ? $billNo : trim((string) ($row['docno'] ?? ''));
            $row['qty']     = (int) ($row['qty'] ?? 0);
            $row['rate']    = (float) ($row['rate'] ?? 0);
            $row['weight']  = (float) ($row['weight'] ?? 0);
            $row['lesswgt'] = (float) ($row['lesswgt'] ?? 0);
            $row['stwgt']   = (float) ($row['stwgt'] ?? 0);
            $row['stprice'] = (float) ($row['stprice'] ?? 0);
            $row['amount']  = (float) ($row['amount'] ?? 0);

            // Net weight = weight - lesswgt - stwgt
            $row['netwgt'] = round($row['weight'] - $row['lesswgt'] - $row['stwgt'], 3);

            // Display name: prefer detail name over item name
            $dname = trim($row['dname'] ?? '');
            $row['displayname'] = ($dname !== '') ? $dname : ($row['itemname'] ?? '');

            return $row;
        })->values()->all();

        return response()->json([
            'ok'     => true,
            'rows'   => $rows,
            'totals' => $this->buildTotals($rows),
        ]);
    }

    private function buildTotals(array $rows): array
    {
        $t = ['count' => count($rows), 'qty' => 0, 'weight' => 0, 'lesswgt' => 0,
              'stwgt' => 0, 'stprice' => 0, 'netwgt' => 0, 'amount' => 0,
              'goldwgt' => 0, 'silverwgt' => 0, 'otherwgt' => 0];

        foreach ($rows as $row) {
            $t['qty']     += (int) ($row['qty'] ?? 0);
            $t['weight']  += (float) ($row['weight'] ?? 0);
            $t['lesswgt'] += (float) ($row['lesswgt'] ?? 0);
            $t['stwgt']   += (float) ($row['stwgt'] ?? 0);
            $t['stprice'] += (float) ($row['stprice'] ?? 0);
            $t['netwgt']  += (float) ($row['netwgt'] ?? 0);
            $t['amount']  += (float) ($row['amount'] ?? 0);

            $itype = strtoupper(trim($row['itype'] ?? ''));
            if ($itype === 'G') $t['goldwgt'] += (float) ($row['weight'] ?? 0);
            elseif ($itype === 'S') $t['silverwgt'] += (float) ($row['weight'] ?? 0);
            else $t['otherwgt'] += (float) ($row['weight'] ?? 0);
        }

        return $t;
    }

    private function emptyTotals(): array
    {
        return ['count' => 0, 'qty' => 0, 'weight' => 0, 'lesswgt' => 0,
                'stwgt' => 0, 'stprice' => 0, 'netwgt' => 0, 'amount' => 0,
                'goldwgt' => 0, 'silverwgt' => 0, 'otherwgt' => 0];
    }

    private function partyGstExpr(string $table, string $alias, string $codeColumn, string $as, array $sourceColumns = []): string
    {
        $sources = [];
        foreach ($sourceColumns as $column) {
            if (Schema::hasColumn($table, $column)) {
                $sources[] = "NULLIF(TRIM(COALESCE({$alias}.{$column}, \"\")), \"\")";
            }
        }
        if ($this->hasTable('clients') && Schema::hasColumn($table, $codeColumn) && Schema::hasColumn('clients', 'code')) {
            foreach (['tin', 'tinno'] as $column) {
                if (Schema::hasColumn('clients', $column)) {
                    $sources[] = "(select NULLIF(TRIM(COALESCE(c.{$column}, \"\")), \"\") from clients c where TRIM(COALESCE(c.code, \"\")) = TRIM(COALESCE({$alias}.{$codeColumn}, \"\")) limit 1)";
                }
            }
        }

        return ($sources ? 'COALESCE(' . implode(', ', $sources) . ', "")' : '""') . " as {$as}";
    }
}
