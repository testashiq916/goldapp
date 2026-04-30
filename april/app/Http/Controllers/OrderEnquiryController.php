<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderEnquiryController extends Controller
{
    private int $gilevel = 1;

    private function buildReceiptRows(array $order, array $advafterRows = []): array
    {
        $rows = [];
        $orderDate = (string) ($order['tdate'] ?? '');
        $orderNo = trim((string) ($order['ordno'] ?? ''));
        $orderRate = (float) ($order['rate'] ?? 0);

        $pushRow = function (float $amount, string $type, float $weight = 0.0, ?string $docNo = null, ?string $tdate = null, ?float $rate = null) use (&$rows, $orderDate, $orderNo, $orderRate): void {
            if (abs($amount) <= 0.00001 && abs($weight) <= 0.00001) {
                return;
            }
            $rows[] = [
                'tdate' => $tdate ?: $orderDate,
                'docno' => trim((string) ($docNo ?: $orderNo)),
                'ttype' => $type,
                'rate' => $rate ?? $orderRate,
                'wgt' => $weight,
                'amount' => round($amount, 2),
            ];
        };

        $pushRow((float) ($order['advance'] ?? 0), 'CASH ADV');
        $pushRow((float) ($order['eamt'] ?? 0), 'EX ADV');
        $pushRow((float) ($order['sretamt'] ?? 0), 'SRET ADV');
        $pushRow(0.0, 'GOLD ADV', (float) ($order['gadvance'] ?? 0));

        foreach ($advafterRows as $r) {
            $pushRow(
                (float) ($r['amount'] ?? 0),
                trim((string) ($r['ttype'] ?? 'ADV RCPT')) ?: 'ADV RCPT',
                (float) ($r['wgt'] ?? 0),
                trim((string) ($r['docno'] ?? '')) ?: $orderNo,
                (string) ($r['tdate'] ?? $orderDate),
                isset($r['rate']) ? (float) $r['rate'] : $orderRate
            );
        }

        return $rows;
    }

    public function index(Request $request)
    {
        if (!$request->session()->has('user_code')) {
            return redirect('/login');
        }

        $this->gilevel = (int) ($request->session()->get('user_level', 1));

        $ordno    = strtoupper(trim($request->query('ordno', '')));
        $showData = $request->has('show') && $ordno !== '';

        $orders = [];

        if ($showData && $this->hasTable('orderm')) {
            $orders = $this->loadOrders($ordno);
        }

        return view('order-report.enquiry', compact('ordno', 'showData', 'orders'));
    }

    private function loadOrders(string $ordno): array
    {
        $ordermCols = array_map('strtolower', $this->columnList('orderm'));

        $select = [
            'orderm.slno', 'orderm.ordno', 'orderm.tdate', 'orderm.duedate',
            'orderm.rate', 'orderm.custname', 'orderm.custcode',
            'orderm.billamt', 'orderm.eamt', 'orderm.advance',
            'orderm.status', 'orderm.smcode', 'orderm.gadvance',
            'orderm.sretamt', 'orderm.addr', 'orderm.refund',
            'sman.name as smname',
        ];

        if (in_array('duedate_org', $ordermCols)) $select[] = 'orderm.duedate_org';
        if (in_array('phone', $ordermCols))       $select[] = 'orderm.phone';

        $rows = DB::table('orderm')
            ->leftJoin('sman', 'orderm.smcode', '=', 'sman.code')
            ->select($select)
            ->where('orderm.control', '<=', $this->gilevel)
            ->where('orderm.ordno', $ordno)
            ->orderBy('orderm.tdate')
            ->orderBy('orderm.slno')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->toArray();

        $hasOrderd   = $this->hasTable('orderd');
        $hasAdvafter = $this->hasTable('advafter');
        $hasOrderdga = $this->hasTable('orderdga');

        foreach ($rows as &$order) {
            $slno = $order['slno'];

            // Advance-after totals (sum from advafter table)
            $order['advafter']    = 0;
            $order['advafterwgt'] = 0;
            if ($hasAdvafter) {
                $agg = DB::table('advafter')
                    ->where('slno', $slno)
                    ->selectRaw('COALESCE(SUM(amount),0) as total_amt, COALESCE(SUM(wgt),0) as total_wgt')
                    ->first();
                if ($agg) {
                    $order['advafter']    = (float) $agg->total_amt;
                    $order['advafterwgt'] = (float) $agg->total_wgt;
                }
            }

            // Total advance = advance + eamt + sretamt + advafter
            $order['totadv'] = (float) $order['advance'] + (float) $order['eamt']
                             + (float) $order['sretamt'] + $order['advafter'];

            // Order detail items
            $order['items'] = [];
            if ($hasOrderd) {
                $order['items'] = DB::table('orderd')
                    ->leftJoin('items', 'orderd.code', '=', 'items.code')
                    ->select([
                        'orderd.sno', 'orderd.code', 'orderd.qty', 'orderd.weight',
                        'orderd.wastage', 'orderd.mcharge', 'orderd.stonewgt',
                        'orderd.stoneprice', 'orderd.amount', 'orderd.part',
                        'items.name as itemname',
                    ])
                    ->where('orderd.slno', $slno)
                    ->orderBy('orderd.sno')
                    ->get()->map(fn ($r) => (array) $r)->toArray();
            }

            // Advance receipts
            $order['receipts'] = [];
            if ($hasAdvafter) {
                $order['receipts'] = DB::table('advafter')
                    ->where('slno', $slno)
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
                    ->select([
                        'orderdga.sno', 'orderdga.code', 'orderdga.qty',
                        'orderdga.weight', 'orderdga.cost',
                        'items.name as itemname',
                    ])
                    ->where('orderdga.slno', $slno)
                    ->orderBy('orderdga.sno')
                    ->get()->map(fn ($r) => (array) $r)->toArray();
            }
        }
        unset($order);

        return $rows;
    }
}
