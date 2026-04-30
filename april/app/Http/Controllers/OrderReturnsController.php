<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderReturnsController extends Controller
{
    private int $gilevel = 1;

    private function buildReceiptRows(array $order, array $advafterRows = []): array
    {
        $rows = [];

        $pushAmountRow = function (string $type, float $amount, ?string $date = null, ?string $docNo = null, ?float $rate = null) use (&$rows): void {
            if (abs($amount) < 0.00001) {
                return;
            }

            $rows[] = [
                'tdate' => $date,
                'docno' => $docNo,
                'ttype' => $type,
                'rate' => $rate,
                'wgt' => 0,
                'amount' => $amount,
            ];
        };

        $pushWeightRow = function (string $type, float $weight, ?string $date = null, ?string $docNo = null, ?float $rate = null) use (&$rows): void {
            if (abs($weight) < 0.00001) {
                return;
            }

            $rows[] = [
                'tdate' => $date,
                'docno' => $docNo,
                'ttype' => $type,
                'rate' => $rate,
                'wgt' => $weight,
                'amount' => 0,
            ];
        };

        $orderDate = $order['ord_tdate'] ?? null;
        $orderNo = $order['ordno'] ?? null;
        $goldRate = isset($order['grate']) ? (float) $order['grate'] : null;

        $pushAmountRow('CASH ADV', (float) ($order['ord_advance'] ?? 0), $orderDate, $orderNo, $goldRate);
        $pushAmountRow('EX ADV', (float) ($order['ord_eamt'] ?? 0), $orderDate, $orderNo, $goldRate);
        $pushWeightRow('GOLD ADV', (float) ($order['gadvance'] ?? 0), $orderDate, $orderNo, $goldRate);

        foreach ($advafterRows as $row) {
            $rows[] = $row;
        }

        usort($rows, function (array $a, array $b): int {
            return strcmp((string) ($a['tdate'] ?? ''), (string) ($b['tdate'] ?? ''));
        });

        return $rows;
    }

    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $this->gilevel = (int) ($request->session()->get('user_level', 1));

        $dateFrom = $request->query('date1', date('Y-m-d'));
        $dateTo   = $request->query('date2', date('Y-m-d'));
        $showData = $request->has('show');

        $orders = [];

        if ($showData && $this->hasTable('orderm') && $this->hasTable('salesm')) {
            $orders = $this->loadOrders($dateFrom, $dateTo);
        }

        return view('order-report.returns', compact('dateFrom', 'dateTo', 'showData', 'orders'));
    }

    private function loadOrders(string $dateFrom, string $dateTo): array
    {
        $ordermCols = array_map('strtolower', $this->columnList('orderm'));

        $query = DB::table('orderm')
            ->join('salesm', 'orderm.salebill', '=', 'salesm.billno')
            ->leftJoin('sman', 'orderm.smcode', '=', 'sman.code')
            ->select([
                'orderm.ordno', 'orderm.tdate as ord_tdate', 'orderm.duedate',
                'orderm.custname', 'orderm.billamt as ord_billamt',
                'orderm.eamt as ord_eamt', 'orderm.advance as ord_advance',
                'orderm.smcode', 'orderm.gadvance', 'orderm.slno as ord_slno',
                'salesm.slno as sale_slno', 'salesm.billno as sale_billno',
                'salesm.tdate as sale_tdate', 'salesm.ttime as sale_ttime',
                'salesm.custname as sale_custname',
                'salesm.billamt as sale_billamt', 'salesm.eamt as sale_eamt',
                'salesm.staxamt', 'salesm.discount', 'salesm.ramt',
                'salesm.grate', 'salesm.sretamt as sale_sretamt',
                'salesm.round as sale_round', 'salesm.advance as sale_advance',
                'salesm.astamt',
                'sman.name as smname',
            ])
            ->where('orderm.control', '<=', $this->gilevel)
            ->whereBetween('salesm.tdate', [$dateFrom, $dateTo])
            ->orderBy('orderm.tdate');

        $rows = $query->get()->map(fn ($r) => (array) $r)->toArray();

        $hasSalesd   = $this->hasTable('salesd');
        $hasAdvafter = $this->hasTable('advafter');
        $hasOrderdga = $this->hasTable('orderdga');

        foreach ($rows as &$order) {
            // Balance = billamt + staxamt + astamt - eamt - sretamt - advance - discount + round - ramt
            $order['balance'] = (float) $order['sale_billamt']
                + (float) $order['staxamt']
                + (float) $order['astamt']
                - (float) $order['sale_eamt']
                - (float) $order['sale_sretamt']
                - (float) $order['sale_advance']
                - (float) $order['discount']
                + (float) $order['sale_round']
                - (float) $order['ramt'];

            // Sales items
            $order['saleitems'] = [];
            if ($hasSalesd) {
                $order['saleitems'] = DB::table('salesd')
                    ->leftJoin('items', 'salesd.code', '=', 'items.code')
                    ->select([
                        'salesd.sno', 'salesd.code', 'salesd.qty', 'salesd.weight',
                        'salesd.rate', 'salesd.stonewgt', 'salesd.stoneprice',
                        'salesd.mcharge', 'salesd.wastage', 'salesd.amount',
                        'items.name as itemname',
                    ])
                    ->where('salesd.slno', $order['sale_slno'])
                    ->orderBy('salesd.sno')
                    ->get()->map(fn ($r) => (array) $r)->toArray();
            }

            // All receipts (order-level)
            $order['receipts'] = [];
            if ($hasAdvafter) {
                $order['receipts'] = DB::table('advafter')
                    ->where('slno', $order['ord_slno'])
                    ->where('control', '<=', $this->gilevel)
                    ->orderBy('tdate')
                    ->get()->map(fn ($r) => (array) $r)->toArray();
            }
            $order['receipts'] = $this->buildReceiptRows($order, $order['receipts']);

            // Gold advance items
            $order['goldadv'] = [];
            if ($hasOrderdga) {
                $order['goldadv'] = DB::table('orderdga')
                    ->leftJoin('items', 'orderdga.code', '=', 'items.code')
                    ->select(['orderdga.sno','orderdga.code','orderdga.qty',
                              'orderdga.weight','orderdga.cost','items.name as itemname'])
                    ->where('orderdga.slno', $order['ord_slno'])
                    ->orderBy('orderdga.sno')
                    ->get()->map(fn ($r) => (array) $r)->toArray();
            }
        }
        unset($order);

        return $rows;
    }
}
