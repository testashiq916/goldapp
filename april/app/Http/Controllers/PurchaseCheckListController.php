<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurchaseCheckListController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        return view('reports.purchase-check-list', [
            'title'  => 'Purchase Check List',
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

        if (!$this->hasTable('purchasem')) {
            return response()->json(['ok' => true, 'rows' => [], 'totals' => $this->emptyTotals()]);
        }

        $hasOpbill = Schema::hasColumn('purchasem', 'opbill');

        $q = DB::table('purchasem as m')
            ->select(
                'm.slno', 'm.tdate', 'm.docno', 'm.suppcode', 'm.name',
                'm.billamt', 'm.pamt', 'm.addamt', 'm.eamt', 'm.discount',
                'm.pr', 'm.duedate', 'm.smcode', 'm.round', 'm.netamt'
            );

        if (Schema::hasColumn('purchasem', 'taxamt')) {
            $q->addSelect('m.taxamt');
        } else {
            $q->selectRaw('0 as taxamt');
        }

        if (Schema::hasColumn('purchasem', 'hmc')) {
            $q->addSelect('m.hmc');
        } else {
            $q->selectRaw('0 as hmc');
        }

        // Gold/Silver weight subqueries
        if ($this->hasTable('purchased') && $this->hasTable('items')) {
            $q->selectRaw("(SELECT COALESCE(SUM(d.weight), 0) FROM purchased d JOIN items i ON i.code = d.code WHERE d.slno = m.slno AND i.itype = 'G') as goldwgt");
            $q->selectRaw("(SELECT COALESCE(SUM(d.weight), 0) FROM purchased d JOIN items i ON i.code = d.code WHERE d.slno = m.slno AND i.itype = 'S') as silverwgt");
        } else {
            $q->selectRaw('0 as goldwgt, 0 as silverwgt');
        }

        $q->whereBetween('m.tdate', [$date1, $date2]);
        $q->where('m.control', '<=', $rlevel);

        if ($hasOpbill) {
            $q->where(function ($qq) {
                $qq->where('m.opbill', '<>', 1)->orWhereNull('m.opbill');
            });
        }

        if ($suppcode !== '') {
            $q->where('m.suppcode', $suppcode);
        }

        $q->orderBy('m.tdate')->orderBy('m.slno');

        $rows = $q->get()->map(function ($r) {
            $row = (array) $r;
            $row['billamt']  = (float) ($row['billamt'] ?? 0);
            $row['pamt']     = (float) ($row['pamt'] ?? 0);
            $row['addamt']   = (float) ($row['addamt'] ?? 0);
            $row['eamt']     = (float) ($row['eamt'] ?? 0);
            $row['discount'] = (float) ($row['discount'] ?? 0);
            $row['taxamt']   = (float) ($row['taxamt'] ?? 0);
            $row['round']    = (float) ($row['round'] ?? 0);
            $row['hmc']      = (float) ($row['hmc'] ?? 0);
            $row['goldwgt']  = (float) ($row['goldwgt'] ?? 0);
            $row['silverwgt'] = (float) ($row['silverwgt'] ?? 0);

            // Net = billamt - discount + taxamt + round - eamt
            $row['calcnet'] = $row['billamt'] - $row['discount'] + $row['taxamt'] + $row['round'] + $row['hmc'] - $row['eamt'];

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
        $t = ['count' => count($rows), 'billamt' => 0, 'eamt' => 0, 'discount' => 0,
              'taxamt' => 0, 'hmc' => 0, 'round' => 0, 'calcnet' => 0,
              'goldwgt' => 0, 'silverwgt' => 0, 'pamt' => 0, 'addamt' => 0];

        foreach ($rows as $row) {
            foreach (['billamt','eamt','discount','taxamt','hmc','round','calcnet','goldwgt','silverwgt','pamt','addamt'] as $k) {
                $t[$k] += (float) ($row[$k] ?? 0);
            }
        }

        return $t;
    }

    private function emptyTotals(): array
    {
        return ['count' => 0, 'billamt' => 0, 'eamt' => 0, 'discount' => 0,
                'taxamt' => 0, 'hmc' => 0, 'round' => 0, 'calcnet' => 0,
                'goldwgt' => 0, 'silverwgt' => 0, 'pamt' => 0, 'addamt' => 0];
    }
}
